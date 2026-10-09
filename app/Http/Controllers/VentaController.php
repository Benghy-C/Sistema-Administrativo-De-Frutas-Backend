<?php

namespace App\Http\Controllers;

use App\Services\VentaWriter;
use App\Services\VentaManager;
use Illuminate\Http\Request;

class VentaController extends Controller
{
    public function store(Request $request, VentaWriter $writer)
    {
        $datos = $request->validate($this->reglas());

        return response()->json([
            $writer->crear($datos, (int) $request->user()->id),
        ]);
    }

    public function index(Request $request, VentaManager $manager)
    {
        $filtros = $request->validate([
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:desde',
            'estado' => 'nullable|integer|in:0,1',
            'buscar' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        return response()->json($manager->listar($filtros));
    }

    public function show(int $id, VentaManager $manager)
    {
        return response()->json($manager->detalle($id));
    }

    public function rectificar(Request $request, int $id, VentaManager $manager)
    {
        return $this->cambiar($request, $id, 'rectificar', $manager);
    }

    public function cancelar(Request $request, int $id, VentaManager $manager)
    {
        return $this->cambiar($request, $id, 'cancelar', $manager);
    }

    public function devolver(Request $request, int $id, VentaManager $manager)
    {
        return $this->cambiar($request, $id, 'devolver', $manager);
    }

    private function cambiar(Request $request, int $id, string $tipo, VentaManager $manager)
    {
        $reglas = [
            'operacion' => 'required|uuid',
            'version' => 'required|integer|min:1',
            'motivo' => 'required|string|min:3|max:500',
            'devoluciones' => 'sometimes|array|max:1000',
            'devoluciones.*.id' => 'required|integer|min:1|distinct',
            'devoluciones.*.cantidad' => 'required|integer|min:1|max:999999',
            'devolucion_confirmada' => 'sometimes|accepted',
            'salida_confirmada' => 'sometimes|boolean',
        ];

        if ($tipo === 'devolver') {
            $reglas['devoluciones'] = 'required|array|min:1|max:1000';
        }

        if (!empty($request->input('devoluciones'))) {
            $reglas['devolucion_confirmada'] = 'required|accepted';
        }

        if ($tipo === 'rectificar') {
            foreach ($this->reglas() as $campo => $regla) {
                $reglas['envio.'.$campo] = $regla;
            }
        }

        $datos = $request->validate($reglas, [
            'devoluciones.required' => 'Indica al menos una caja que regresó a cámara.',
            'devoluciones.min' => 'Indica al menos una caja que regresó a cámara.',
            'devoluciones.*.cantidad.min' => 'La cantidad devuelta debe ser mayor que cero.',
            'devoluciones.*.cantidad.integer' => 'La cantidad devuelta debe ser un número entero.',
            'devolucion_confirmada.required' => 'Confirma que las cajas regresaron físicamente a cámara.',
            'devolucion_confirmada.accepted' => 'Confirma que las cajas regresaron físicamente a cámara.',
        ]);

        return response()->json($manager->ejecutar(
            $id, $tipo, $datos, (int) $request->user()->id
        ));
    }

    private function reglas(): array
    {
        return [
            's_codigo' => 'required|string|max:50',
            's_idClie' => 'required|integer|min:1',
            's_cantiVen' => 'required|integer|min:1|max:2999997',
            's_totalVen' => 'required|numeric|min:0|max:999999999999|decimal:0,2',
            's_flete' => 'required|numeric|min:0|max:999999999|decimal:0,2',
            's_estiva' => 'required|numeric|min:0|max:999999999|decimal:0,2',
            's_fecha' => 'required|date_format:Y-m-d|before_or_equal:today',
            's_id_fru' => 'required|integer|min:1',
            's_subtotal' => 'required|numeric|min:0|max:999999999999|decimal:0,2',
            's_cantA' => 'required|integer|min:0|max:999999',
            's_cantB' => 'required|integer|min:0|max:999999',
            's_cantC' => 'required|integer|min:0|max:999999',
            's_precioA' => 'nullable|numeric|min:0|max:999999|decimal:0,2',
            's_precioB' => 'nullable|numeric|min:0|max:999999|decimal:0,2',
            's_precioC' => 'nullable|numeric|min:0|max:999999|decimal:0,2',
        ];
    }

    public function confirmar(Request $request, string $codigo, VentaWriter $writer)
    {
        abort_if(strlen($codigo) > 50, 422);
        $resultado = $writer->confirmar($codigo, (int) $request->user()->id);

        return response()->json([
            'confirmado' => $resultado !== null,
            'resultado' => $resultado ? $resultado[0] : null,
        ]);
    }
}
