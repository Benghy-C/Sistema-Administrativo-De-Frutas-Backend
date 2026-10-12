<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderReader
{
    public function forDate(string $date): array
    {
        return DB::select('select * from sp_listar_compra(?,?,?)', [0, $date ?: '0001-01-01', $date ?: '9999-12-31']);
    }
}