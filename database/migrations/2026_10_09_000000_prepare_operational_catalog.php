<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['Transporte', 'Alimentación', 'Mantenimiento', 'Otros'] as $nombre) {
            DB::table('categorias_gasto')->insertOrIgnore([
                'nombre' => $nombre,
                'nombre_normalizado' => mb_strtolower($nombre),
                'activa' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        DB::table('fruta')
            ->whereRaw("LOWER(TRIM(descripcion)) = 'palta'")
            ->update(['estado' => 0]);
    }

    public function down(): void
    {
    }
};
