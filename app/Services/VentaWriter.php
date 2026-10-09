<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaWriter
{
    public function crear(array $datos, int $usuario): array
    {
        $datos = $this->normalizar($datos);
        $hash = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($datos, $usuario, $hash) {
            if (DB::getDriverName() === 'pgsql') {
                DB::select(
                    'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    ['venta:'.$datos['s_codigo']]
                );
            }

            $confirmacion = DB::table('venta_confirmaciones')
                ->where('codigo', $datos['s_codigo'])
                ->lockForUpdate()
                ->first();

            if ($confirmacion) {
                if ((int) $confirmacion->usuario_id !== $usuario
                    || !hash_equals($confirmacion->solicitud_hash, $hash)) {
                    $this->rechazar('El código ya pertenece a otro envío.');
                }

                return $this->respuesta($confirmacion->venta_id, true);
            }

            if (DB::table('venta')->where('codigo_venta', $datos['s_codigo'])->exists()) {
                $this->rechazar('El código ya está registrado. Consulta ese envío.');
            }

            $this->validarCatalogo($datos);
            $lotes = $this->bloquearLotes($datos['s_id_fru']);

            foreach (['A', 'B', 'C'] as $calidad) {
                $campo = 'cant'.strtolower($calidad);

                if ($lotes->sum($campo) < $datos['s_cant'.$calidad]) {
                    $this->rechazar('Stock insuficiente para la calidad '.$calidad.'.');
                }
            }

            $id = DB::table('venta')->insertGetId([
                'codigo_venta' => $datos['s_codigo'],
                'id_clie' => $datos['s_idClie'],
                'cant_ven' => $datos['s_cantiVen'],
                'total_ven' => $datos['s_totalVen'],
                'cost_flete' => $datos['s_flete'],
                'cost_estiva' => $datos['s_estiva'],
                'estado' => 1,
                'fecha_registro' => $datos['s_fecha'],
            ], 'pk_venta');

            $detalleId = DB::table('venta_detalle')->insertGetId([
                'venta_id' => $id,
                'futra_id' => $datos['s_id_fru'],
                'canta' => $datos['s_cantA'],
                'cantb' => $datos['s_cantB'],
                'cantc' => $datos['s_cantC'],
                'precia' => $datos['s_precioA'],
                'precib' => $datos['s_precioB'],
                'precic' => $datos['s_precioC'],
                'subtotal' => $datos['s_subtotal'],
                'estadodeta' => 1,
                'fecha_registrodet' => now(),
            ], 'pk_ventadeta');

            $pendientes = [];

            foreach (['A', 'B', 'C'] as $calidad) {
                $pendientes[$calidad] = $datos['s_cant'.$calidad];
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
                            'detalle_id' => $detalleId,
                            'camara_id' => $lote->id_camara,
                            'calidad' => $calidad,
                            'cantidad' => $cantidad,
                        ]);
                    }
                }

                DB::table('camara_refigeracion')
                    ->where('id_camara', $lote->id_camara)
                    ->update($saldo);
            }

            DB::table('venta_confirmaciones')->insert([
                'venta_id' => $id,
                'codigo' => $datos['s_codigo'],
                'usuario_id' => $usuario,
                'solicitud_hash' => $hash,
                'solicitud' => json_encode($datos, JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            app(ActivityRecorder::class)->registrar('venta', $id, 'registrar', $usuario,
                null, ['solicitud' => $datos, 'detalle_id' => $detalleId]);

            return $this->respuesta($id, false);
        }, 3);
    }

    public function confirmar(string $codigo, int $usuario): ?array
    {
        $registro = DB::table('venta_confirmaciones')
            ->where('codigo', $codigo)
            ->where('usuario_id', $usuario)
            ->first();

        return $registro ? $this->respuesta($registro->venta_id, true) : null;
    }

    public function bloquearLotes(int $fruta)
    {
        DB::table('compra')
            ->whereIn('id', function ($query) use ($fruta) {
                $query->select('id_compra')
                    ->from('camara_refigeracion')
                    ->where('id_fruta', $fruta);
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $lotes = DB::table('camara_refigeracion as c')
            ->join('compra as co', 'co.id', '=', 'c.id_compra')
            ->where('co.estado', 1)
            ->where('c.id_fruta', $fruta)
            ->orderByRaw('co.fecha IS NULL')
            ->orderBy('co.fecha')
            ->orderBy('c.id_camara')
            ->select('c.*')
            ->lockForUpdate()
            ->get();

        foreach ($lotes as $lote) {
            foreach (['canta', 'cantb', 'cantc'] as $campo) {
                if (!is_numeric($lote->$campo) || (int) $lote->$campo < 0) {
                    $this->rechazar('El lote tiene cantidades inconsistentes. Revisa cámara.');
                }
            }
        }

        return $lotes;
    }

    private function validarCatalogo(array $datos): void
    {
        $cliente = DB::table('clientes')
            ->where('id', $datos['s_idClie'])
            ->sharedLock()
            ->first();

        $fruta = DB::table('fruta')
            ->where('id', $datos['s_id_fru'])
            ->sharedLock()
            ->first();

        if (!$cliente || (int) $cliente->estado !== 1) {
            $this->rechazar('Selecciona un cliente activo.');
        }

        if (!$fruta || (int) $fruta->estado !== 1
            || !in_array(mb_strtolower(trim($fruta->descripcion)), ['platano', 'plátano'], true)) {
            $this->rechazar('Selecciona una fruta habilitada.');
        }
    }

    public function normalizar(array $datos): array
    {
        $resultado = ['s_codigo' => $datos['s_codigo']];

        foreach (['s_idClie', 's_id_fru', 's_cantiVen', 's_cantA', 's_cantB', 's_cantC'] as $campo) {
            $resultado[$campo] = (int) $datos[$campo];
        }

        $subtotal = 0;
        $cantidad = 0;

        foreach (['A', 'B', 'C'] as $calidad) {
            $cajas = $resultado['s_cant'.$calidad];
            $precio = $this->centimos($datos['s_precio'.$calidad] ?? 0);

            if ($cajas > 0 && $precio <= 0) {
                $this->rechazar('Ingresa un precio mayor que cero para la calidad '.$calidad.'.');
            }

            $resultado['s_precio'.$calidad] = number_format(
                $cajas > 0 ? $precio / 100 : 0, 2, '.', ''
            );
            $subtotal += $cajas * $precio;
            $cantidad += $cajas;
        }

        if ($cantidad <= 0 || $cantidad !== $resultado['s_cantiVen']) {
            $this->rechazar('Las calidades deben coincidir con el total de cajas.');
        }

        $flete = $this->centimos($datos['s_flete']);
        $estiba = $this->centimos($datos['s_estiva']);

        if ($this->centimos($datos['s_subtotal'] ?? 0) !== $subtotal
            || $this->centimos($datos['s_totalVen']) !== $subtotal + $flete + $estiba) {
            $this->rechazar('Los importes no coinciden con las cajas y sus precios.');
        }

        foreach (['s_subtotal' => $subtotal, 's_flete' => $flete,
            's_estiva' => $estiba, 's_totalVen' => $subtotal + $flete + $estiba] as $campo => $valor) {
            $resultado[$campo] = number_format($valor / 100, 2, '.', '');
        }

        $resultado['s_fecha'] = $datos['s_fecha'];

        return $resultado;
    }

    private function centimos(mixed $valor): int
    {
        return (int) round((float) $valor * 100);
    }

    private function respuesta(int $id, bool $reintento): array
    {
        $venta = DB::table('venta')->where('pk_venta', $id)->first();
        $version = DB::table('venta_confirmaciones')->where('venta_id', $id)->value('version');

        return [[
            'mensa' => 'Envío guardado.',
            'error' => 0,
            'numid' => $id,
            'numero_envio' => ShipmentNumber::format($id),
            'reintento' => $reintento,
            'estado' => (int) $venta->estado,
            'version' => (int) ($version ?? 1),
        ]];
    }

    private function rechazar(string $mensaje): never
    {
        throw ValidationException::withMessages(['envio' => $mensaje]);
    }
}
