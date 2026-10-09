<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GastoWriter
{
    public function crear(array $datos, int $usuario): int
    {
        $datos = [
            's_monto' => number_format((float) $datos['s_monto'], 2, '.', ''),
            's_desc' => trim($datos['s_desc']),
            's_fecha' => $datos['s_fecha'],
            'categoria_id' => (int) $datos['categoria_id'],
            'operacion' => $datos['operacion'],
        ];
        $hash = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($datos, $usuario, $hash) {
            if (DB::getDriverName() === 'pgsql') {
                DB::select(
                    'SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    ['gasto:'.$datos['operacion']]
                );
            }

            $confirmacion = DB::table('gasto_confirmaciones')
                ->where('operacion', $datos['operacion'])
                ->lockForUpdate()
                ->first();

            if ($confirmacion) {
                if ($confirmacion->solicitud_hash !== $hash
                    || (int) $confirmacion->usuario_id !== $usuario) {
                    throw ValidationException::withMessages([
                        'operacion' => 'La operación ya corresponde a otro gasto.',
                    ]);
                }

                return (int) $confirmacion->gasto_id;
            }

            $categoria = DB::table('categorias_gasto')
                ->where('id', $datos['categoria_id'])
                ->sharedLock()
                ->first();

            if (!$categoria || !$categoria->activa) {
                throw ValidationException::withMessages([
                    'categoria_id' => 'Selecciona una categoría activa.',
                ]);
            }

            $id = DB::table('cjchica')->insertGetId([
                'descripcion' => $datos['s_desc'],
                'mmonto' => $datos['s_monto'],
                'id_user_ins' => $usuario,
                'fecha_registro' => $datos['s_fecha'],
                'categoria_id' => $categoria->id,
            ], 'id_caja');

            DB::table('gasto_confirmaciones')->insert([
                'operacion' => $datos['operacion'],
                'gasto_id' => $id,
                'usuario_id' => $usuario,
                'solicitud_hash' => $hash,
            ]);

            app(ActivityRecorder::class)->registrar(
                'gasto', $id, 'registrar', $usuario, null,
                array_diff_key($datos, ['operacion' => true])
            );

            return $id;
        }, 3);
    }
}
