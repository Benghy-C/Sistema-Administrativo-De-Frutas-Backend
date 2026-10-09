<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaManager
{
    public function listar(array $filtros)
    {
        $query = DB::table('venta as v')
            ->leftJoin('clientes as c', 'c.id', '=', 'v.id_clie')
            ->select('v.*', 'c.nombres as cliente');

        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
            if (!empty($filtros[$campo])) {
                $query->whereDate('v.fecha_registro', $operador, $filtros[$campo]);
            }
        }

        if (isset($filtros['estado'])) {
            $query->where('v.estado', $filtros['estado']);
        }

        if (!empty($filtros['buscar'])) {
            $buscar = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $filtros['buscar']);
            $query->where(function ($query) use ($buscar, $filtros) {
                $query->whereRaw('LOWER(v.codigo_venta) LIKE ?', ['%'.mb_strtolower($buscar).'%'])
                    ->orWhereRaw('LOWER(c.nombres) LIKE ?', ['%'.mb_strtolower($buscar).'%']);
                if (preg_match('/^ENV-0*([1-9][0-9]{0,9})$/i', trim($filtros['buscar']), $numero)) {
                    $query->orWhere('v.pk_venta', (int) $numero[1]);
                }
            });
        }

        $resumen = (clone $query)->select([])->selectRaw(
            'COUNT(*) AS envios, '.
            'COALESCE(SUM(CASE WHEN v.estado = 1 THEN v.cant_ven ELSE 0 END), 0) AS cajas, '.
            'COALESCE(SUM(CASE WHEN v.estado = 1 THEN 1 ELSE 0 END), 0) AS registrados, '.
            'COALESCE(SUM(CASE WHEN v.estado = 0 THEN 1 ELSE 0 END), 0) AS cancelados'
        )->first();

        $pagina = $query->orderByDesc('v.pk_venta')
            ->paginate(5);

        $pagina->getCollection()->each(function ($venta) {
            $venta->numero_envio = ShipmentNumber::format((int) $venta->pk_venta);
        });

        return $pagina->toArray() + ['resumen' => $resumen];
    }

    public function detalle(int $id): array
    {
        $venta = DB::table('venta as v')
            ->leftJoin('clientes as c', 'c.id', '=', 'v.id_clie')
            ->where('v.pk_venta', $id)
            ->select('v.*', 'c.nombres as cliente')
            ->first();
        abort_unless($venta, 404, 'Envío no encontrado.');
        $venta->numero_envio = ShipmentNumber::format((int) $venta->pk_venta);

        $detalles = DB::table('venta_detalle as d')
            ->leftJoin('fruta as f', 'f.id', '=', 'd.futra_id')
            ->where('d.venta_id', $id)
            ->select('d.*', 'f.descripcion as fruta')
            ->get();
        $lotes = DB::table('venta_lotes as vl')
            ->join('venta_detalle as d', 'd.pk_ventadeta', '=', 'vl.detalle_id')
            ->join('camara_refigeracion as c', 'c.id_camara', '=', 'vl.camara_id')
            ->join('compra as co', 'co.id', '=', 'c.id_compra')
            ->where('d.venta_id', $id)
            ->select('vl.*', 'co.codigo_compra as lote')
            ->orderBy('vl.id')
            ->get();
        $confirmacion = DB::table('venta_confirmaciones')->where('venta_id', $id)->first();

        return [
            'venta' => $venta,
            'detalles' => $detalles,
            'lotes' => $lotes,
            'version' => (int) ($confirmacion->version ?? 0),
            'trazabilidad' => $confirmacion !== null,
            'historial' => DB::table('registro_actividad')
                ->where('entidad', 'venta')->where('registro_id', $id)
                ->orderByDesc('id')->get(),
        ];
    }

    public function ejecutar(int $id, string $tipo, array $datos, int $usuario): array
    {
        $hash = hash('sha256', json_encode([$id, $tipo, $datos], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($id, $tipo, $datos, $usuario, $hash) {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    ['operacion-venta:'.$datos['operacion']]);
            }
            $anterior = DB::table('venta_operaciones')
                ->where('clave', $datos['operacion'])->lockForUpdate()->first();
            if ($anterior) {
                if ($anterior->solicitud_hash !== $hash
                    || (int) $anterior->usuario_id !== $usuario) {
                    $this->rechazar('La operación ya corresponde a otro cambio.');
                }

                return json_decode($anterior->resultado, true, flags: JSON_THROW_ON_ERROR);
            }
            $venta = DB::table('venta')->where('pk_venta', $id)->lockForUpdate()->first();
            abort_unless($venta, 404, 'Envío no encontrado.');
            $confirmacion = DB::table('venta_confirmaciones')
                ->where('venta_id', $id)->lockForUpdate()->first();
            if (!$confirmacion) {
                $this->rechazar('Este envío antiguo no tiene trazabilidad. No se puede ajustar su inventario.');
            }
            if ((int) $confirmacion->version !== (int) $datos['version']) {
                abort(409, 'El envío cambió. Recarga el detalle antes de continuar.');
            }
            if ($tipo !== 'devolver' && (int) $venta->estado !== 1) {
                $this->rechazar('El envío ya está cancelado.');
            }
            if ($tipo === 'devolver' && (int) $venta->estado !== 0) {
                $this->rechazar('Para un envío activo, registra la devolución al rectificarlo.');
            }
            $detalles = DB::table('venta_detalle')->where('venta_id', $id)
                ->orderBy('pk_ventadeta')->lockForUpdate()->get();
            if ($detalles->count() !== 1) {
                $this->rechazar('El envío debe tener un único detalle.');
            }
            $detalle = $detalles->first();
            $writer = app(VentaWriter::class);
            $lotes = $writer->bloquearLotes((int) $detalle->futra_id);
            $antes = $this->detalle($id);
            unset($antes['historial']);
            $devueltas = $this->devolver($detalle->pk_ventadeta, $datos['devoluciones'] ?? [], $lotes);

            if ($tipo === 'cancelar') {
                DB::table('venta')->where('pk_venta', $id)->update(['estado' => 0]);
                DB::table('venta_detalle')->where('venta_id', $id)->update(['estadodeta' => 0]);
            } elseif ($tipo === 'rectificar') {
                $this->rectificar($venta, $detalle, $datos, $devueltas, $lotes,
                    (int) $confirmacion->version + 1);
            } elseif (array_sum($devueltas) === 0) {
                $this->rechazar('Indica las cajas que regresaron físicamente a cámara.');
            }

            DB::table('venta_confirmaciones')->where('venta_id', $id)->update([
                'version' => (int) $confirmacion->version + 1, 'updated_at' => now(),
            ]);
            $despues = $this->detalle($id);
            unset($despues['historial']);
            DB::table('registro_actividad')->insert([
                'entidad' => 'venta', 'registro_id' => $id, 'usuario_id' => $usuario,
                'accion' => $tipo, 'motivo' => $datos['motivo'],
                'antes' => json_encode($antes, JSON_THROW_ON_ERROR),
                'despues' => json_encode($despues, JSON_THROW_ON_ERROR),
            ]);
            $resultado = ['mensaje' => 'Cambio guardado.', 'version' => $despues['version']];
            DB::table('venta_operaciones')->insert([
                'clave' => $datos['operacion'], 'venta_id' => $id, 'usuario_id' => $usuario,
                'tipo' => $tipo, 'solicitud_hash' => $hash,
                'resultado' => json_encode($resultado, JSON_THROW_ON_ERROR),
            ]);

            return $resultado;
        }, 3);
    }

    private function devolver(int $detalle, array $devoluciones, $lotes): array
    {
        $totales = ['A' => 0, 'B' => 0, 'C' => 0];
        foreach ($devoluciones as $devolucion) {
            $asignacion = DB::table('venta_lotes')->where('id', $devolucion['id'])
                ->where('detalle_id', $detalle)->lockForUpdate()->first();
            if (!$asignacion || $devolucion['cantidad'] > $asignacion->cantidad - $asignacion->devuelta) {
                $this->rechazar('La devolución supera las cajas de ese lote.');
            }
            $lote = $lotes->firstWhere('id_camara', $asignacion->camara_id);
            if (!$lote) {
                $this->rechazar('El lote de origen no está activo. Revisa la compra.');
            }
            $campo = 'cant'.strtolower($asignacion->calidad);
            $lote->$campo += $devolucion['cantidad'];
            DB::table('camara_refigeracion')->where('id_camara', $lote->id_camara)
                ->update([$campo => $lote->$campo]);
            DB::table('venta_lotes')->where('id', $asignacion->id)
                ->increment('devuelta', $devolucion['cantidad']);
            $totales[$asignacion->calidad] += $devolucion['cantidad'];
        }

        return $totales;
    }

    private function rectificar(object $venta, object $detalle, array $datos,
        array $devueltas, $lotes, int $revision): void
    {
        $valores = app(VentaWriter::class)->normalizar($datos['envio']);
        if ($valores['s_codigo'] !== $venta->codigo_venta
            || $valores['s_id_fru'] !== (int) $detalle->futra_id) {
            $this->rechazar('La fruta y el código del envío no pueden cambiar.');
        }
        $cliente = DB::table('clientes')->where('id', $valores['s_idClie'])->sharedLock()->first();
        if (!$cliente || (int) $cliente->estado !== 1) {
            $this->rechazar('Selecciona un cliente activo.');
        }
        $pendientes = [];
        $cambio = [];
        foreach (['A', 'B', 'C'] as $calidad) {
            $campo = 'cant'.strtolower($calidad);
            $nueva = $valores['s_cant'.$calidad];
            $saldo = (int) $detalle->$campo - $devueltas[$calidad];
            if ($nueva < $saldo) {
                $this->rechazar('Confirma la devolución de las cajas que estás reduciendo.');
            }
            $pendientes[$calidad] = $nueva - $saldo;
            if ($lotes->sum($campo) < $pendientes[$calidad]) {
                $this->rechazar('El stock cambió. Quedan '.$lotes->sum($campo).' cajas de calidad '.$calidad.'. Revisa la cantidad del envío.');
            }
            $cambio[$campo] = $nueva;
            $cambio['preci'.strtolower($calidad)] = $valores['s_precio'.$calidad];
        }
        if (array_sum($pendientes) > 0 && empty($datos['salida_confirmada'])) {
            $this->rechazar('Confirma que las cajas adicionales salieron de cámara.');
        }
        foreach ($lotes as $lote) {
            $saldo = [];
            foreach ($pendientes as $calidad => $pendiente) {
                $campo = 'cant'.strtolower($calidad);
                $cantidad = min($pendiente, (int) $lote->$campo);
                $saldo[$campo] = (int) $lote->$campo - $cantidad;
                $pendientes[$calidad] -= $cantidad;
                if ($cantidad > 0) {
                    DB::table('venta_lotes')->insert([
                        'detalle_id' => $detalle->pk_ventadeta, 'camara_id' => $lote->id_camara,
                        'calidad' => $calidad, 'cantidad' => $cantidad, 'revision' => $revision,
                    ]);
                }
            }
            DB::table('camara_refigeracion')->where('id_camara', $lote->id_camara)->update($saldo);
        }
        DB::table('venta_detalle')->where('pk_ventadeta', $detalle->pk_ventadeta)
            ->update($cambio + ['subtotal' => $valores['s_subtotal']]);
        DB::table('venta')->where('pk_venta', $venta->pk_venta)->update([
            'id_clie' => $valores['s_idClie'], 'fecha_registro' => $valores['s_fecha'],
            'cant_ven' => $valores['s_cantiVen'], 'total_ven' => $valores['s_totalVen'],
            'cost_flete' => $valores['s_flete'], 'cost_estiva' => $valores['s_estiva'],
        ]);
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['envio' => $mensaje]);
    }
}
