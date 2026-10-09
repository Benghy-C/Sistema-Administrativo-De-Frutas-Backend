<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ContactWriter
{
    public function guardar(string $tabla, array $datos, int $usuario): int
    {
        return DB::transaction(function () use ($tabla, $datos, $usuario) {
            $id = $datos['s_id'] ?? null;
            $antes = $id ? DB::table($tabla)->where('id', $id)->lockForUpdate()->first() : null;
            abort_if($id && !$antes, 404, 'Contacto no encontrado.');
            $nombre = $tabla === 'clientes' ? 'nombres' : 'nombre';
            $valores = [
                $nombre => trim($datos['s_nombre']),
                'cel' => $datos['s_cel'] ?? null,
                'telefono' => $datos['s_telefono'] ?? null,
                'correo' => $datos['s_correo'],
                'descripcion' => $datos['s_descripcion'] ?? '',
            ];
            if ($id) {
                DB::table($tabla)->where('id', $id)->update($valores);
            } else {
                $valores['estado'] = 1;
                if ($tabla === 'proveedores') $valores['fecha_inscripcion'] = now();
                $id = DB::table($tabla)->insertGetId($valores);
            }
            app(ActivityRecorder::class)->registrar($tabla, $id,
                $antes ? 'actualizar' : 'registrar', $usuario, $antes,
                DB::table($tabla)->where('id', $id)->first());

            return (int) $id;
        });
    }

    public function estado(string $tabla, int $id, int $usuario): void
    {
        DB::transaction(function () use ($tabla, $id, $usuario) {
            $antes = DB::table($tabla)->where('id', $id)->lockForUpdate()->first();
            abort_unless($antes, 404, 'Contacto no encontrado.');
            $estado = (int) $antes->estado === 1 ? 0 : 1;
            DB::table($tabla)->where('id', $id)->update(['estado' => $estado]);
            app(ActivityRecorder::class)->registrar($tabla, $id, 'cambiar_estado', $usuario,
                ['estado' => $antes->estado], ['estado' => $estado]);
        });
    }
}
