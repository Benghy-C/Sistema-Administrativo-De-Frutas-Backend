<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PerdidaWriter
{
    public function crear(array $datos, int $usuario): array
    {
        $datos = [
            'camara_id' => (int) $datos['camara_id'],
            'calidad' => $datos['calidad'],
            'cantidad' => (int) $datos['cantidad'],
            'fecha' => $datos['fecha'],
            'motivo' => trim($datos['motivo']),
            'operacion' => $datos['operacion'],
        ];
        $hash = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($datos, $usuario, $hash) {
            $this->bloquearOperacion($datos['operacion']);
            $existente = DB::table('perdidas')
                ->where('operacion', $datos['operacion'])
                ->lockForUpdate()
                ->first();

            if ($existente) {
                if ($existente->solicitud_hash !== $hash
                    || (int) $existente->usuario_id !== $usuario) {
                    $this->rechazar('La operación ya corresponde a otra pérdida.');
                }

                return $this->respuesta((int) $existente->id);
            }

            [$lote, $detalle] = $this->leerLote($datos['camara_id']);
            $ingreso = substr((string) $lote->fecha_registro, 0, 10);
            if ($ingreso !== '' && $datos['fecha'] < $ingreso) {
                throw ValidationException::withMessages([
                    'fecha' => 'La fecha de la pérdida no puede ser anterior al ingreso del lote.',
                ]);
            }
            $campo = 'cant'.strtolower($datos['calidad']);
            $antes = filter_var($lote->$campo, FILTER_VALIDATE_INT);

            if ($antes === false || $antes < 0) {
                $this->rechazar('El lote tiene un saldo inválido. Revisa sus existencias.');
            }
            if ($datos['cantidad'] > $antes) {
                throw ValidationException::withMessages([
                    'cantidad' => 'Solo quedan '.$antes.' cajas de calidad '.$datos['calidad'].'.',
                ]);
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
            $saldo = $antes - $datos['cantidad'];

            DB::table('camara_refigeracion')
                ->where('id_camara', $lote->id_camara)
                ->update([$campo => $saldo]);

            app(ActivityRecorder::class)->registrar(
                'perdida',
                $id,
                'registrar',
                $usuario,
                ['disponible' => $antes],
                ['disponible' => $saldo] + $datos,
                $datos['motivo']
            );

            return $this->respuesta($id);
        }, 3);
    }

    private function bloquearOperacion(string $operacion): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::select(
                'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                ['perdida:'.$operacion]
            );
        }
    }

    private function leerLote(int $id): array
    {
        $referencia = DB::table('camara_refigeracion')
            ->where('id_camara', $id)
            ->first();
        abort_unless($referencia, 404, 'Lote no encontrado.');

        $compra = DB::table('compra')
            ->where('id', $referencia->id_compra)
            ->lockForUpdate()
            ->first();
        $detalles = DB::table('compra_fruta')
            ->where('id_pedido', $referencia->id_compra)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $lote = DB::table('camara_refigeracion')
            ->where('id_camara', $id)
            ->lockForUpdate()
            ->first();

        if (!$compra || (int) $compra->estado !== 1
            || !$lote || $detalles->count() !== 1) {
            $this->rechazar('La pérdida debe pertenecer a un lote de compra activo.');
        }
        $detalle = $detalles->first();

        if ((int) $detalle->id_fruta !== (int) $lote->id_fruta) {
            $this->rechazar('La fruta del lote no coincide con la compra.');
        }

        return [$lote, $detalle];
    }

    private function respuesta(int $id): array
    {
        return ['id' => $id, 'mensaje' => 'Pérdida registrada.'];
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['perdida' => $mensaje]);
    }
}
