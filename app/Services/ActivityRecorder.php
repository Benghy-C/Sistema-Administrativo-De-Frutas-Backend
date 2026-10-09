<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ActivityRecorder
{
    public function registrar(string $entidad, int $id, string $accion, int $usuario,
        mixed $antes = null, mixed $despues = null, ?string $motivo = null): void
    {
        DB::table('registro_actividad')->insert([
            'entidad' => $entidad,
            'registro_id' => $id,
            'usuario_id' => $usuario,
            'accion' => $accion,
            'motivo' => $motivo,
            'antes' => $antes === null ? null : json_encode($antes, JSON_THROW_ON_ERROR),
            'despues' => $despues === null ? null : json_encode($despues, JSON_THROW_ON_ERROR),
        ]);
    }
}
