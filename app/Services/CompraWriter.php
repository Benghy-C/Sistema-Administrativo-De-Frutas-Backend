<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraWriter
{
    public function crear(array $datos, int $usuario): array
    {
        return DB::transaction(function () use ($datos, $usuario) {
            if (DB::getDriverName() === 'pgsql') {
                DB::select(
                    'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    ['compra:'.$datos['s_codigo']]
                );
            }

            $existente = DB::table('compra')
                ->where('codigo_compra', $datos['s_codigo'])
                ->lockForUpdate()
                ->first();

            if ($existente) {
                [$detalle, $lote] = $this->leerDetalle($existente->id);
                $this->consumido($detalle, $lote);
                $this->comprobarReintento($existente, $detalle, $datos);

                return $this->respuesta($existente->id, 1, true);
            }

            $this->validarCatalogo($datos);
            $cabecera = $this->cabecera($datos, $usuario);
            $cabecera['codigo_compra'] = $datos['s_codigo'];
            $cabecera['estado'] = 1;
            $id = DB::table('compra')->insertGetId($cabecera);

            DB::table('compra_fruta')->insert(
                $this->detalle($datos) + ['id_pedido' => $id, 'estado' => 1]
            );

            DB::table('camara_refigeracion')->insert([
                'id_compra' => $id,
                'id_fruta' => $datos['s_id_fru'],
                'fecha_registro' => now('America/Lima'),
                'cantidad_camara' => $datos['s_cantidad'],
                'canta' => $datos['s_cantA'],
                'cantb' => $datos['s_cantB'],
                'cantc' => $datos['s_cantC'],
            ]);

            return $this->respuesta($id, 1);
        }, 3);
    }

    public function actualizar(array $datos, int $usuario): array
    {
        return DB::transaction(function () use ($datos, $usuario) {
            $compra = $this->leerCompra($datos['s_id_compra']);
            [$detalle, $lote] = $this->leerDetalle($compra->id);
            $consumido = $this->consumido($detalle, $lote);

            if ($compra->codigo_compra !== $datos['s_codigo']) {
                $this->rechazar('El código de la compra no puede cambiar.');
            }

            if ((int) $compra->estado !== (int) $datos['s_estado']) {
                $this->rechazar('El estado cambió. Recarga la compra antes de editarla.');
            }

            $cambiaFruta = (int) $detalle->id_fruta !== (int) $datos['s_id_fru'];

            if ($cambiaFruta && array_sum($consumido) > 0) {
                $this->rechazar('No puedes cambiar la fruta de un lote con cajas utilizadas.');
            }

            $saldo = [];

            foreach (['A', 'B', 'C'] as $calidad) {
                $saldo[$calidad] = $datos['s_cant'.$calidad] - $consumido[$calidad];

                if ($saldo[$calidad] < 0) {
                    $this->rechazar(
                        'La calidad '.$calidad.' ya tiene '.$consumido[$calidad].
                        ' cajas utilizadas. No puedes reducirla por debajo de esa cantidad.'
                    );
                }
            }

            $this->validarCatalogo($datos);

            DB::table('compra')->where('id', $compra->id)->update(
                $this->cabecera($datos, $usuario)
            );

            DB::table('compra_fruta')->where('id', $detalle->id)->update(
                $this->detalle($datos)
            );

            DB::table('camara_refigeracion')
                ->where('id_camara', $lote->id_camara)
                ->update([
                    'id_fruta' => $datos['s_id_fru'],
                    'cantidad_camara' => $datos['s_cantidad'],
                    'canta' => $saldo['A'],
                    'cantb' => $saldo['B'],
                    'cantc' => $saldo['C'],
                ]);

            return $this->respuesta($compra->id, (int) $compra->estado);
        }, 3);
    }

    public function cambiarEstado(int $id, int $estado, int $usuario): array
    {
        return DB::transaction(function () use ($id, $estado, $usuario) {
            $compra = $this->leerCompra($id);
            [$detalle, $lote] = $this->leerDetalle($id);
            $consumido = $this->consumido($detalle, $lote);

            if ((int) $compra->estado === $estado) {
                return $this->respuesta($id, $estado);
            }

            if (array_sum($consumido) > 0) {
                $this->rechazar(
                    'No puedes cambiar el estado de una compra con cajas utilizadas.'
                );
            }

            DB::table('compra')->where('id', $id)->update([
                'estado' => $estado,
                'updated_at' => now(),
                'usuid_alt' => $usuario,
            ]);

            DB::table('compra_fruta')->where('id', $detalle->id)->update([
                'estado' => $estado,
            ]);

            return $this->respuesta($id, $estado);
        }, 3);
    }

    private function leerCompra(int $id): object
    {
        $compra = DB::table('compra')->where('id', $id)->lockForUpdate()->first();

        if (!$compra) {
            abort(404, 'Compra no encontrada.');
        }

        if (!in_array((int) $compra->estado, [0, 1], true)) {
            $this->rechazar('La compra tiene un estado inválido. Revisa su registro.');
        }

        return $compra;
    }

    private function leerDetalle(int $id): array
    {
        $detalles = DB::table('compra_fruta')
            ->where('id_pedido', $id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $lotes = DB::table('camara_refigeracion')
            ->where('id_compra', $id)
            ->orderBy('id_camara')
            ->lockForUpdate()
            ->get();

        if ($detalles->count() !== 1 || $lotes->count() !== 1) {
            $this->rechazar('La compra debe tener un detalle y un lote. Revisa su registro.');
        }

        return [$detalles->first(), $lotes->first()];
    }

    private function consumido(object $detalle, object $lote): array
    {
        if ((int) $detalle->id_fruta !== (int) $lote->id_fruta
            || (float) $detalle->cantidad !== (float) $lote->cantidad_camara) {
            $this->rechazar('El detalle y el lote no coinciden. Revisa la compra.');
        }

        $consumido = [];
        $total = 0;

        foreach (['A', 'B', 'C'] as $calidad) {
            $campo = 'cant'.strtolower($calidad);
            $original = filter_var($detalle->$campo, FILTER_VALIDATE_INT);
            $disponible = filter_var($lote->$campo, FILTER_VALIDATE_INT);

            if ($original === false || $disponible === false
                || $disponible < 0 || $original < $disponible) {
                $this->rechazar('El lote tiene cantidades inconsistentes. Revisa la compra.');
            }

            $consumido[$calidad] = $original - $disponible;
            $total += $original;
        }

        if ((float) $total !== (float) $detalle->cantidad) {
            $this->rechazar('Las calidades no coinciden con la cantidad de la compra.');
        }

        return $consumido;
    }

    private function validarCatalogo(array $datos): void
    {
        $proveedor = DB::table('proveedores')
            ->where('id', $datos['s_id_provedor'])
            ->sharedLock()
            ->first();

        $fruta = DB::table('fruta')
            ->where('id', $datos['s_id_fru'])
            ->sharedLock()
            ->first();

        if (!$proveedor || (int) $proveedor->estado !== 1) {
            $this->rechazar('Selecciona un proveedor activo.');
        }

        if (!$fruta || (int) $fruta->estado !== 1
            || !in_array(mb_strtolower(trim($fruta->descripcion)), ['platano', 'plátano'], true)) {
            $this->rechazar('Selecciona una fruta habilitada.');
        }
    }

    private function cabecera(array $datos, int $usuario): array
    {
        return [
            'id_proveedor' => $datos['s_id_provedor'],
            'fecha' => $datos['s_fecha'],
            'observacion' => $datos['s_observacion'] ?? '',
            'precio_total' => $datos['s_total'],
            'cost_adici' => $datos['s_cost_adi'],
            'updated_at' => now(),
            'usuid_alt' => $usuario,
        ];
    }

    private function detalle(array $datos): array
    {
        return [
            'id_fruta' => $datos['s_id_fru'],
            'cantidad' => $datos['s_cantidad'],
            'precio_sub_total' => $datos['s_subtotal'],
            'canta' => $datos['s_cantA'],
            'cantb' => $datos['s_cantB'],
            'cantc' => $datos['s_cantC'],
            'precioa' => $datos['s_precioA'],
            'preciob' => $datos['s_precioB'],
            'precioc' => $datos['s_precioC'],
        ];
    }

    private function comprobarReintento(object $compra, object $detalle, array $datos): void
    {
        $iguales = (int) $compra->estado === 1
            && (int) $compra->id_proveedor === (int) $datos['s_id_provedor']
            && substr($compra->fecha, 0, 10) === $datos['s_fecha']
            && ($compra->observacion ?? '') === ($datos['s_observacion'] ?? '')
            && (float) $compra->precio_total === (float) $datos['s_total']
            && (float) $compra->cost_adici === (float) $datos['s_cost_adi'];

        foreach ($this->detalle($datos) as $campo => $valor) {
            $iguales = $iguales && (float) $detalle->$campo === (float) $valor;
        }

        if (!$iguales) {
            $this->rechazar('El código ya pertenece a otra compra. Revisa el registro existente.');
        }
    }

    private function respuesta(int $id, int $estado, bool $reintento = false): array
    {
        return [[
            'mensa' => 'Compra guardada correctamente.',
            'error' => 0,
            'numid' => $id,
            'estado' => $estado,
            'reintento' => $reintento,
        ]];
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['compra' => $mensaje]);
    }
}
