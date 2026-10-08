<?php

namespace App\Http\Controllers;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

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
            's_id_fru' => 'required',
            's_subtotal' => 'nullable|numeric',
            's_cantA' => 'required',
            's_cantB' => 'required',
            's_cantC' => 'required',
            's_precioA' => 'nullable',
            's_precioB' => 'nullable',
            's_precioC' => 'nullable',
        ]);

        $respuesta = DB::transaction(function () use ($request) {
            $cabecera = DB::select(
                'SELECT * FROM public.spu_venta_ins(?,?,?,?,?,?,?)',
                [
                    $request->s_codigo,
                    $request->s_idClie,
                    $request->s_cantiVen,
                    $request->s_totalVen,
                    $request->s_flete,
                    $request->s_estiva,
                    $request->s_fecha,
                ]
            );

            $venta = $this->validarRespuesta($cabecera);
            $ventaId = filter_var($venta->numid ?? null, FILTER_VALIDATE_INT);

            if ($ventaId === false || $ventaId <= 0) {
                throw new RuntimeException('No se recibió un identificador válido para la venta.');
            }

            $detalle = DB::select(
                'SELECT * FROM public.spu_compraDetalle_ins(?,?,?,?,?,?,?,?,?)',
                [
                    $request->s_id_fru,
                    $ventaId,
                    $request->s_cantA,
                    $request->s_cantB,
                    $request->s_cantC,
                    $request->s_precioA ?? 0,
                    $request->s_precioB ?? 0,
                    $request->s_precioC ?? 0,
                    $request->s_subtotal ?? 0,
                ]
            );

            $this->validarRespuesta($detalle);

            return $detalle;
        });

        return response()->json([$respuesta]);
    }

    private function validarRespuesta(array $respuesta): object
    {
        if (count($respuesta) !== 1 || ! is_object($respuesta[0] ?? null)) {
            throw new RuntimeException('La base de datos devolvió una respuesta inválida.');
        }

        $resultado = $respuesta[0];
        $error = filter_var($resultado->error ?? null, FILTER_VALIDATE_INT);

        if ($error === false) {
            throw new RuntimeException('La base de datos no confirmó el resultado de la venta.');
        }

        if ($error !== 0) {
            throw new HttpResponseException(response()->json([$respuesta]));
        }

        return $resultado;
    }
}
