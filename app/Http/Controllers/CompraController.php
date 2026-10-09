<?php

namespace App\Http\Controllers;

use App\Services\CompraWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompraController extends Controller
{
    public function store(Request $request, CompraWriter $writer)
    {
        $datos = $this->validarCompra($request);
        $respuesta = $writer->crear($datos, $request->user()->id);

        return response()->json([$respuesta]);
    }

    public function update(Request $request, CompraWriter $writer)
    {
        $datos = $this->validarCompra($request, true);
        $respuesta = $writer->actualizar($datos, $request->user()->id);

        return response()->json([$respuesta]);
    }

    public function editarEstado(Request $request, CompraWriter $writer)
    {
        $datos = $request->validate([
            's_id_compra' => 'required|integer|min:1|max:2147483647',
            's_estado' => 'required|integer|in:0,1',
        ]);

        $respuesta = $writer->cambiarEstado(
            $datos['s_id_compra'],
            $datos['s_estado'],
            $request->user()->id
        );

        return response()->json([$respuesta]);
    }

    private function validarCompra(Request $request, bool $edicion = false): array
    {
        $reglas = [
            's_codigo' => 'required|string|max:15',
            's_id_provedor' => 'required|integer|min:1|max:2147483647',
            's_fecha' => 'required|date_format:Y-m-d',
            's_observacion' => 'nullable|string|max:5000',
            's_total' => 'required|numeric|min:0|max:999999999.99',
            's_cost_adi' => 'required|numeric|min:0|max:999999999.99',
            's_id_fru' => 'required|integer|min:1|max:2147483647',
            's_cantidad' => 'required|integer|min:1|max:2147483647',
            's_cantA' => 'required|integer|min:0|max:2147483647',
            's_cantB' => 'required|integer|min:0|max:2147483647',
            's_cantC' => 'required|integer|min:0|max:2147483647',
            's_precioA' => 'nullable|numeric|min:0|max:999999999.99|decimal:0,2',
            's_precioB' => 'nullable|numeric|min:0|max:999999999.99|decimal:0,2',
            's_precioC' => 'nullable|numeric|min:0|max:999999999.99|decimal:0,2',
        ];

        if ($edicion) {
            $reglas['s_id_compra'] = 'required|integer|min:1|max:2147483647';
            $reglas['s_estado'] = 'required|integer|in:0,1';
        }

        $datos = $request->validate($reglas);
        $cantidad = 0;
        $centimos = 0;

        foreach (['A', 'B', 'C'] as $calidad) {
            $cajas = (int) $datos['s_cant'.$calidad];
            if ($edicion && $cajas > 0 && !isset($datos['s_precio'.$calidad])) {
                throw ValidationException::withMessages([
                    's_precio'.$calidad => 'Registra el precio de la calidad '.$calidad.'.',
                ]);
            }
            $precio = $cajas > 0 ? ($datos['s_precio'.$calidad] ?? 0) : 0;
            $datos['s_precio'.$calidad] = $precio;
            $cantidad += $cajas;
            $centimos += $cajas * (int) round((float) $precio * 100);
        }

        if ($cantidad !== (int) $datos['s_cantidad']) {
            throw ValidationException::withMessages([
                's_cantidad' => 'Las calidades deben sumar la cantidad de cajas.',
            ]);
        }

        $costo = (int) round((float) $datos['s_cost_adi'] * 100);
        $total = (int) round((float) $datos['s_total'] * 100);

        if ($centimos + $costo !== $total) {
            throw ValidationException::withMessages([
                's_total' => 'El total no coincide con las cantidades, precios y costos.',
            ]);
        }

        $datos['s_subtotal'] = $centimos / 100;
        $datos['s_total'] = $total / 100;
        $datos['s_cost_adi'] = $costo / 100;

        return $datos;
    }


    public function listarCompra(Request $request)
    {
        $datos = $request->validate([
            's_estado' => 'required',
            's_fechaDesde' => 'required',
            's_fechaHasta' => 'required',
        ]);

        $respuesta = DB::select(
            'SELECT * FROM public.sp_listar_compra(?,?,?)',
            [
                $datos['s_estado'],
                $datos['s_fechaDesde'],
                $datos['s_fechaHasta'],
            ]
        );

        return response()->json([$respuesta]);
    }

    public function show(Request $request)
    {
        $datos = $request->validate([
            's_id_compra' => 'required',
        ]);

        $respuesta = DB::select(
            'SELECT * FROM public.sp_listar_datos_compra(?)',
            [$datos['s_id_compra']]
        );

        return response()->json([$respuesta]);
    }

    public function detalle(Request $request)
    {
        $datos = $request->validate([
            's_id_compra' => 'required|integer|min:1',
        ]);

        $order = DB::select(
            'SELECT * FROM public.sp_listar_datos_compra(?)',
            [$datos['s_id_compra']]
        );

        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }

        $items = DB::select(
            'SELECT * FROM public.sp_listar_frutas_compra(?)',
            [$datos['s_id_compra']]
        );

        return response()->json(['order' => $order, 'items' => $items]);
    }

    public function onePeido(Request $request)
    {
        $datos = $request->validate([
            's_id_compra' => 'required',
        ]);

        $respuesta = DB::select(
            'SELECT * FROM public.sp_listar_frutas_compra(?)',
            [$datos['s_id_compra']]
        );

        return response()->json([$respuesta]);
    }
}
