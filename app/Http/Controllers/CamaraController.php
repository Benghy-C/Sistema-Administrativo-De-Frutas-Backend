<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CamaraController extends Controller
{
    public function index()
    {

        $respuesta = DB::select(
            <<<'SQL'
                SELECT
                    compra.codigo_compra AS codigo,
                    proveedor.nombre AS provedor,
                    fruta.descripcion AS fruta,
                    camara.cantidad_camara AS cantidad,
                    compra.fecha,
                    compra.estado,
                    compra.id,
                    COALESCE(camara.canta, 0) AS tipoa,
                    COALESCE(camara.cantb, 0) AS tipob,
                    COALESCE(camara.cantc, 0) AS tipoc
                FROM public.camara_refigeracion AS camara
                INNER JOIN public.compra AS compra
                    ON compra.id = camara.id_compra
                INNER JOIN public.fruta AS fruta
                    ON fruta.id = camara.id_fruta
                INNER JOIN public.proveedores AS proveedor
                    ON proveedor.id = compra.id_proveedor
                WHERE compra.estado = 1
                ORDER BY camara.id_camara DESC
            SQL
        );

        return response()->json([$respuesta]);
    }

    public function listarCantidades()
    {

        $respuesta = DB::select('select * from sp_listar_cantidades() ');

        return response()->json([$respuesta]);
    }

    public function listarOneLote(Request $request)
    {

        $request->validate([
            'p_id_lote' => 'required',

        ]);

        $s_id_lote = $request->p_id_lote;

        $respuesta = DB::select('select * from sp_listar_one_camara0(?)', [$s_id_lote]);

        return response()->json([$respuesta]);
    }
}
