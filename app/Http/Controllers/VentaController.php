<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VentaController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            's_codigo' => 'required',
            's_idClie' => 'required',
            's_cantiVen' => 'required',
            's_totalVen' => 'required|string',
            's_flete' => 'required',
            's_estiva' => 'required',
            's_fecha' => 'required',
            //para insertar en la tabla relacion
            's_id_fru' => 'required',
            // 's_cantidad' => 'required',
            's_subtotal' => 'nullable|numeric',
            //calidades de frutas
            's_cantA' => 'required',
            's_cantB' => 'required',
            's_cantC' => 'required',
            's_precioA' => 'nullable',
            's_precioB' => 'nullable',
            's_precioC' => 'nullable'
        ]);

        $p_codigo = $request->s_codigo;
        $p_idClie = $request->s_idClie;
        $p_cantiVen = $request->s_cantiVen;
        $p_totalVen = $request->s_totalVen;
        $p_flete = $request->s_flete;
        $p_estiva = $request->s_estiva;
        $p_fecha = $request->s_fecha;
        $i_ventaId = 0;

        $i_frutaId = $request->s_id_fru;
        // $p_cantidad = $request->s_cantidad;
        $i_subTotal = $request->s_subtotal ?? 0;

        $i_cantA = $request->s_cantA;
        $i_cantB = $request->s_cantB;
        $i_cantC = $request->s_cantC;

        $i_precA = $request->s_precioA ?? 0;
        $i_precB = $request->s_precioB ?? 0;
        $i_precC = $request->s_precioC ?? 0;

        $respuesta = DB::select('SELECT * FROM public.spu_venta_ins(?,?,? ,?,?,? ,?)', [$p_codigo, $p_idClie, $p_cantiVen, $p_totalVen, $p_flete, $p_estiva , $p_fecha]);

        if ($respuesta[0]->error == 0) {
            $i_ventaId = $respuesta[0]->numid;
            //aca deberia ser un forech, pero como solo insertamos una fruta lo dejo asi :v
            $respuesta0 = DB::select('SELECT * FROM public.spu_compraDetalle_ins(?,?,? ,?,?,? ,?,?,?)', [$i_frutaId, $i_ventaId, $i_cantA, $i_cantB, $i_cantC, $i_precA, $i_precB, $i_precC, $i_subTotal]);
            return response()->json([$respuesta0]);
        }

        return response()->json([$respuesta]);

        //al del front, te retorno un mensaje y un error si el valor del error es 0 esta bien, caso contrario algo fallo
    }
}
