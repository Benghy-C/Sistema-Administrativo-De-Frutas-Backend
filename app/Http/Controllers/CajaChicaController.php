<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CajaChicaController extends Controller
{
    //
    public function store(Request $request)
    {
        $request->validate([
            's_monto' => 'required',
            's_desc' => 'required|string',

            's_fecha' => 'required'
        ]);

        $p_monto = $request->s_monto;
        $p_desc = $request->s_desc;
        $p_id_user = $request->user()->id;
        $p_fecha = $request->s_fecha;

        $respuesta = DB::select('SELECT * FROM sp_ins_cjchica(?,?,?,?)', [$p_monto, $p_desc, $p_id_user, $p_fecha]);

        return response()->json([$respuesta]);

        //al del front, te retorno un mensaje y un error si el valor del error es 0 esta bien, caso contrario algo fallo
    }
    public function show(Request $request)
    {
        $request->validate([
            's_fecha_ini' => 'required',
            's_fecha_fin' => 'required'           
        ]);

        $p_fecha_ini = $request->s_fecha_ini;
        $p_fecha_fin = $request->s_fecha_fin;

        $respuesta = DB::select('SELECT * FROM sp_listar_cj(?,?)', [$p_fecha_ini, $p_fecha_fin]);

        return response()->json([$respuesta]);
    }

    public function index()
    {
        $respuesta = DB::table('cjchica')
            ->select('descripcion', 'mmonto', 'fecha_registro')
            ->orderBy('fecha_registro', 'desc')
            ->get();
            
        return response()->json([$respuesta]);
    }
}
