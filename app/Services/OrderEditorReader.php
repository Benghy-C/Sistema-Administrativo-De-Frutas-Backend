<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class OrderEditorReader
{
    public function read(int $id): array
    {
        $result = DB::selectOne(<<<'SQL'
SELECT jsonb_build_object(
    'order', (SELECT coalesce(jsonb_agg(o), '[]'::jsonb) FROM public.sp_listar_datos_compra(?) o),
    'items', (SELECT coalesce(jsonb_agg(i), '[]'::jsonb) FROM public.sp_listar_frutas_compra(?) i),
    'providers', (SELECT coalesce(jsonb_agg(p), '[]'::jsonb) FROM public.fn_listar_proveedor() p),
    'fruits', (SELECT coalesce(jsonb_agg(f), '[]'::jsonb) FROM public.listar_fruta() f)
) AS data
SQL, [$id, $id]);
        return json_decode($result->data, true, 512, JSON_THROW_ON_ERROR);
    }
}
