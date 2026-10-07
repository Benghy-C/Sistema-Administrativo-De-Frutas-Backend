<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CompraController extends Controller
{
    //
    public function store(Request $request)
    {
        $request->validate([
            's_codigo' => 'required',
            's_id_provedor' => 'required',
            's_fecha' => 'required',
            's_observacion' => 'required|string',
            's_total' => 'required',
            's_cost_adi' => 'required',
            //para insertar en la tabla relacion
            's_id_fru' => 'required',
            's_cantidad' => 'required',
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
        $p_id_prove = $request->s_id_provedor;
        $p_fecha = $request->s_fecha;
        $p_obs = $request->s_observacion;
        $p_total = $request->s_total;
        $p_cost_adi = $request->s_cost_adi;
        $id_venta = 0 ;

        $p_id_fruta = $request->s_id_fru;
        $p_cantidad = $request->s_cantidad;
        $p_precio_uni = $request->s_subtotal ?? 0;

        $p_cantA = $request->s_cantA;
        $p_cantB = $request->s_cantB;
        $p_cantC = $request->s_cantC;
        
        $p_precioA = $request->s_precioA ?? 0;
        $p_precioB = $request->s_precioB ?? 0;
        $p_precioC = $request->s_precioC ?? 0;

        $respuesta = DB::select('SELECT * FROM public.spu_compra_ins(?,?,?,?,?,?)', [$p_cost_adi,$p_codigo, $p_obs, $p_id_prove, $p_total ,$p_fecha]);

        if ($respuesta[0]->error == 0 ) {
            $id_venta = $respuesta[0]->numid;
            //aca deberia ser un forech, pero como solo insertamos una fruta lo dejo asi :v
            $respuesta0 = DB::select('SELECT * FROM public.spu_compra_fruta_ins(?,?,?,?,?,?,?,?,?,?)', [$p_id_fruta, $id_venta, $p_cantidad, $p_precio_uni,$p_cantA,$p_cantB,$p_cantC, $p_precioA, $p_precioB, $p_precioC]);
            return response()->json([$respuesta0]);
        }

        return response()->json([$respuesta]);

        //al del front, te retorno un mensaje y un error si el valor del error es 0 esta bien, caso contrario algo fallo
    }


     public function update(Request $request)
    {
        $request->validate([
            's_id_compra' => 'required',
            's_estado' => 'required',            
            's_codigo' => 'required',
            's_id_provedor' => 'required',
            's_fecha' => 'required',
            's_observacion' => 'required|string',
            's_total' => 'required',
            's_cost_adi' => 'required',
            //para insertar en la tabla relacion
            's_id_fru' => 'required',
            's_cantidad' => 'required',
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
        $p_id_prove = $request->s_id_provedor;
        $p_fecha = $request->s_fecha;
        $p_obs = $request->s_observacion;
        $p_total = $request->s_total;
        $p_cost_adi = $request->s_cost_adi;
        $p_id_compra= $request->s_id_compra;
        $p_estado= $request->s_estado;

        $p_id_fruta = $request->s_id_fru;
        $p_cantidad = $request->s_cantidad;
        $p_precio_uni = $request->s_subtotal ?? 0;

        $p_cantA = $request->s_cantA;
        $p_cantB = $request->s_cantB;
        $p_cantC = $request->s_cantC;

        $p_precioA = $request->s_precioA ?? 0;
        $p_precioB = $request->s_precioB ?? 0;
        $p_precioC = $request->s_precioC ?? 0;


        $respuesta = DB::select('SELECT * FROM public.spu_compra_update(?,?,? ,?,?,? ,?,?)', [ $p_id_compra,$p_estado,$p_cost_adi,$p_codigo, $p_obs, $p_id_prove, $p_total ,$p_fecha]);

        if ($respuesta[0]->error == 0 ) {
            $p_id_pedido = $p_id_compra;
            //aca deberia ser un forech, pero como solo insertamos una fruta lo dejo asi :v
            $respuesta0 = DB::select('SELECT * FROM public.spu_compra_fruta_upd(?,?,? ,?,?,? ,?,?,?,?)', [$p_id_fruta, $p_id_pedido, $p_cantidad, $p_precio_uni,$p_cantA,$p_cantB,$p_cantC, $p_precioA, $p_precioB, $p_precioC]);
            return response()->json([$respuesta0]);
        }

        return response()->json([$respuesta]);

        //al del front, te retorno un mensaje y un error si el valor del error es 0 esta bien, caso contrario algo fallo
    }
























    public function editarEstado(Request $request)
    {
        $request->validate([
            's_id_compra' => 'required',


        ]);

        $p_id_compra = $request->s_id_compra;
        $p_id_user = $request->user()->id;

        $respuesta = DB::select('SELECT * FROM public.cambiarEstadoCompra(?,?)', [ $p_id_compra, $p_id_user ]);
        return response()->json([$respuesta]);

    }

    public function listarCompra(Request $request){
        $request->validate([
            's_estado' => 'required',
            's_fechaDesde' => 'required',
            's_fechaHasta' => 'required'

        ]);

        $p_estado = $request->s_estado;
        $p_fechaDesde = $request->s_fechaDesde;
        $p_fechaHasta = $request->s_fechaHasta;

        $respuesta= DB::select('select * from sp_listar_compra(?,?,?)', [$p_estado, $p_fechaDesde, $p_fechaHasta]);
        return response()->json([$respuesta]);
    }

    public function show(Request $request)
    {
        $request->validate([
            's_id_compra' => 'required'
        ]);

        $p_id_compra = $request->s_id_compra;

        $respuesta = DB::select('SELECT * FROM public.sp_listar_datos_compra(?)', [ $p_id_compra ]);
        return response()->json([$respuesta]);

    }

    public function detalle(Request $request)
    {
        $data = $request->validate(['s_id_compra' => 'required|integer|min:1']);
        $id = $data['s_id_compra'];
        $order = DB::select('SELECT * FROM public.sp_listar_datos_compra(?)', [$id]);
        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        $items = DB::select('SELECT * FROM public.sp_listar_frutas_compra(?)', [$id]);
        return response()->json(['order' => $order, 'items' => $items]);
    }

    public function onePeido(Request $request)
    {
        $request->validate([
            's_id_compra' => 'required'
        ]);

        $p_id_compra = $request->s_id_compra;

        $respuesta = DB::select('SELECT * FROM public.sp_listar_frutas_compra(?)', [ $p_id_compra ]);
        return response()->json([$respuesta]);

    }




}
