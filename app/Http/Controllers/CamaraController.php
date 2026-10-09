<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CamaraController extends Controller
{
    private function lotes()
    {
        return DB::table('camara_refigeracion as camara')
            ->join('compra', 'compra.id', '=', 'camara.id_compra')
            ->join('fruta', 'fruta.id', '=', 'camara.id_fruta')
            ->join('proveedores as proveedor', 'proveedor.id', '=', 'compra.id_proveedor')
            ->where('compra.estado', 1);
    }

    private function datosLote()
    {
        return $this->lotes()->select(
            'compra.codigo_compra as codigo',
            'proveedor.nombre as provedor',
            'fruta.descripcion as fruta',
            'camara.cantidad_camara as cantidad_original',
            'camara.fecha_registro as fecha',
            'compra.fecha as fecha_compra',
            'compra.estado',
            'camara.id_camara as camara_id',
            'compra.id'
        )->selectRaw(
            'COALESCE(camara.canta, 0) + COALESCE(camara.cantb, 0) + '.
            'COALESCE(camara.cantc, 0) AS cantidad, '.
            'COALESCE(camara.canta, 0) AS tipoa, '.
            'COALESCE(camara.cantb, 0) AS tipob, '.
            'COALESCE(camara.cantc, 0) AS tipoc'
        );
    }

    public function index()
    {
        $respuesta = $this->datosLote()
            ->orderBy('camara.id_camara')
            ->get();

        return response()->json([$respuesta]);
    }

    public function listarCantidades()
    {
        $respuesta = $this->lotes()->selectRaw(
            'COUNT(*) AS totallotes, '.
            'COALESCE(SUM(camara.canta), 0) AS cantidada, '.
            'COALESCE(SUM(camara.cantb), 0) AS cantidadb, '.
            'COALESCE(SUM(camara.cantc), 0) AS cantidadc'
        )->get();

        return response()->json([$respuesta]);
    }

    public function listarOneLote(Request $request)
    {
        $datos = $request->validate([
            'p_id_lote' => 'required|integer|min:1',
        ]);

        $respuesta = $this->datosLote()
            ->where('compra.id', $datos['p_id_lote'])
            ->orderBy('camara.id_camara')
            ->first();

        abort_unless($respuesta, 404, 'No se encontró el lote activo.');

        return response()->json([[$respuesta]]);
    }
}
