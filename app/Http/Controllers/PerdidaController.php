<?php

namespace App\Http\Controllers;

use App\Services\PerdidaWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PerdidaController extends Controller
{
    public function index(Request $request)
    {
        $reglas = [
            'buscar' => 'nullable|string|max:100',
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d',
            'camara_id' => 'nullable|integer|min:1',
            'calidad' => 'nullable|in:A,B,C',
            'page' => 'nullable|integer|min:1',
        ];
        if ($request->filled('desde')) {
            $reglas['hasta'] .= '|after_or_equal:desde';
        }
        $datos = $request->validate($reglas, [
            'hasta.after_or_equal' => 'La fecha de fin debe ser igual o posterior al inicio.',
        ]);

        $query = DB::table('perdidas as perdida')
            ->join('camara_refigeracion as camara', 'camara.id_camara', '=', 'perdida.camara_id')
            ->join('compra', 'compra.id', '=', 'camara.id_compra')
            ->join('fruta', 'fruta.id', '=', 'camara.id_fruta')
            ->leftJoin('users as usuario', 'usuario.id', '=', 'perdida.usuario_id');

        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
            if (!empty($datos[$campo])) {
                $query->whereDate('perdida.fecha', $operador, $datos[$campo]);
            }
        }
        foreach (['camara_id', 'calidad'] as $campo) {
            if (!empty($datos[$campo])) {
                $query->where('perdida.'.$campo, $datos[$campo]);
            }
        }
        if (!empty($datos['buscar'])) {
            $buscar = trim($datos['buscar']);
            $query->where(function ($query) use ($buscar) {
                $query->whereRaw('LOWER(compra.codigo_compra) LIKE ?', [
                    '%'.mb_strtolower($buscar).'%',
                ])->orWhereRaw('LOWER(fruta.descripcion) LIKE ?', [
                    '%'.mb_strtolower($buscar).'%',
                ]);

                if (preg_match('/^LOT-0*([1-9][0-9]{0,9})$/i', $buscar, $match)) {
                    $query->orWhere('compra.id', (int) $match[1]);
                }
            });
        }

        $resumen = (clone $query)->selectRaw(
            'COUNT(*) AS registros, '.
            'COALESCE(SUM(perdida.cantidad), 0) AS cajas, '.
            "COALESCE(SUM(CASE WHEN perdida.calidad = 'A' THEN perdida.cantidad ELSE 0 END), 0) AS a, ".
            "COALESCE(SUM(CASE WHEN perdida.calidad = 'B' THEN perdida.cantidad ELSE 0 END), 0) AS b, ".
            "COALESCE(SUM(CASE WHEN perdida.calidad = 'C' THEN perdida.cantidad ELSE 0 END), 0) AS c"
        )->first();

        $pagina = $query->select(
            'perdida.id',
            'perdida.camara_id',
            'perdida.calidad',
            'perdida.cantidad',
            'perdida.fecha',
            'perdida.motivo',
            'compra.id as compra_id',
            'compra.codigo_compra as lote',
            'fruta.descripcion as fruta',
            'usuario.name as responsable'
        )->orderByDesc('perdida.id')->paginate(5);

        return response()->json($pagina->toArray() + ['resumen' => $resumen]);
    }

    public function store(Request $request, PerdidaWriter $writer)
    {
        $request->merge(['motivo' => trim((string) $request->input('motivo', ''))]);
        $datos = $request->validate([
            'camara_id' => 'required|integer|min:1',
            'calidad' => 'required|in:A,B,C',
            'cantidad' => 'required|integer|min:1|max:999999',
            'fecha' => 'required|date_format:Y-m-d|before_or_equal:'.now('America/Lima')->toDateString(),
            'motivo' => 'required|string|min:3|max:500',
            'operacion' => 'required|uuid',
        ], [
            'cantidad.min' => 'Indica al menos una caja perdida.',
            'cantidad.integer' => 'La cantidad debe ser un número entero de cajas.',
            'fecha.before_or_equal' => 'La fecha de la pérdida no puede ser futura.',
            'motivo.required' => 'Escribe el motivo de la pérdida.',
            'motivo.min' => 'Escribe un motivo de al menos tres caracteres.',
        ]);

        return response()->json($writer->crear($datos, (int) $request->user()->id));
    }
}
