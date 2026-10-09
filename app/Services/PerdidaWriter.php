<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerdidaWriter
{
    public function crear(array $datos, int $usuario): array
    {
        $hash = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($datos, $usuario, $hash) {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    ['perdida:'.$datos['operacion']]);
            }
            $existente = DB::table('perdidas')->where('operacion', $datos['operacion'])
                ->lockForUpdate()->first();
            if ($existente) {
                if ($existente->solicitud_hash !== $hash || (int) $existente->usuario_id !== $usuario) {
                    $this->rechazar('La operación ya corresponde a otra pérdida.');
                }

                return ['id' => $existente->id, 'mensaje' => 'Pérdida registrada.'];
            }
            $referencia = DB::table('camara_refigeracion')->where('id_camara', $datos['camara_id'])->first();
            abort_unless($referencia, 404, 'Lote no encontrado.');
            $compra = DB::table('compra')->where('id', $referencia->id_compra)->lockForUpdate()->first();
            $detalles = DB::table('compra_fruta')->where('id_pedido', $referencia->id_compra)
                ->orderBy('id')->lockForUpdate()->get();
            $lote = DB::table('camara_refigeracion')->where('id_camara', $datos['camara_id'])
                ->lockForUpdate()->first();
            if (!$compra || (int) $compra->estado !== 1 || $detalles->count() !== 1) {
                $this->rechazar('La pérdida debe pertenecer a un lote de compra activo.');
            }
            $campo = 'cant'.strtolower($datos['calidad']);
            if ((int) $lote->$campo < $datos['cantidad']) {
                $this->rechazar('La pérdida supera las cajas disponibles de esa calidad.');
            }
            $detalle = $detalles->first();
            if ((int) $detalle->id_fruta !== (int) $lote->id_fruta) {
                $this->rechazar('La fruta del lote no coincide con la compra.');
            }
            $precio = 'precio'.strtolower($datos['calidad']);
            $id = DB::table('perdidas')->insertGetId([
                'camara_id' => $lote->id_camara,
                'calidad' => $datos['calidad'],
                'cantidad' => $datos['cantidad'],
                'fecha' => $datos['fecha'],
                'motivo' => $datos['motivo'],
                'usuario_id' => $usuario,
                'costo_unitario' => $detalle->$precio,
                'operacion' => $datos['operacion'],
                'solicitud_hash' => $hash,
            ]);
            $antes = (int) $lote->$campo;
            $saldo = $antes - $datos['cantidad'];
            DB::table('camara_refigeracion')->where('id_camara', $lote->id_camara)->update([$campo => $saldo]);
            app(ActivityRecorder::class)->registrar('perdida', $id, 'registrar', $usuario,
                ['disponible' => $antes], ['disponible' => $saldo] + $datos, $datos['motivo']);

            return ['id' => $id, 'mensaje' => 'Pérdida registrada.'];
        }, 3);
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['perdida' => $mensaje]);
    }
}
