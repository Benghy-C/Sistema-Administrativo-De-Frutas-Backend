<?php

namespace App\Http\Controllers;

use App\Services\GastoWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CajaChicaController extends Controller
{
    public function store(Request $request, GastoWriter $writer)
    {
        $request->merge(['s_desc' => trim((string) $request->input('s_desc', ''))]);
        $datos = $request->validate([
            's_monto' => 'required|numeric|gt:0|max:999999999|decimal:0,2',
            's_desc' => 'required|string|min:1|max:120',
            's_fecha' => [
                'required',
                'date_format:Y-m-d H:i:s',
                'before_or_equal:'.now('America/Lima')->format('Y-m-d H:i:s'),
            ],
            'categoria_id' => 'required|integer|min:1',
            'operacion' => 'required|uuid',
        ], [
            's_monto.gt' => 'El monto debe ser mayor a cero.',
            's_monto.decimal' => 'El monto admite hasta dos decimales.',
            's_desc.required' => 'Escribe la descripción del gasto.',
            's_fecha.before_or_equal' => 'La fecha y hora del gasto no pueden ser futuras.',
            'categoria_id.required' => 'Selecciona la categoría del gasto.',
        ]);

        $id = $writer->crear($datos, (int) $request->user()->id);

        return response()->json([[[
            'error' => 0,
            'numid' => $id,
            'mensa' => 'Gasto registrado.',
        ]]]);
    }

    public function show(Request $request)
    {
        $datos = $request->validate([
            's_fecha_ini' => 'required|date',
            's_fecha_fin' => 'required|date|after_or_equal:s_fecha_ini',
            'buscar' => 'nullable|string|max:120',
            'categoria_id' => 'nullable|integer|min:0',
        ]);

        $query = $this->consulta()->whereBetween('g.fecha_registro', [
            $datos['s_fecha_ini'], $datos['s_fecha_fin'],
        ]);

        if (!empty($datos['buscar'])) {
            $query->whereRaw('LOWER(g.descripcion) LIKE ?', [
                '%'.mb_strtolower($datos['buscar']).'%',
            ]);
        }

        if (isset($datos['categoria_id'])) {
            if ((int) $datos['categoria_id'] === 0) {
                $query->whereNull('g.categoria_id');
            } else {
                $query->where('g.categoria_id', $datos['categoria_id']);
            }
        }

        return response()->json([
            $query->orderByDesc('g.fecha_registro')->orderByDesc('g.id_caja')->get(),
        ]);
    }

    public function index()
    {
        return response()->json([
            $this->consulta()->orderByDesc('g.fecha_registro')
                ->orderByDesc('g.id_caja')->get(),
        ]);
    }

    private function consulta()
    {
        return DB::table('cjchica as g')
            ->leftJoin('categorias_gasto as c', 'c.id', '=', 'g.categoria_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.id_user_ins')
            ->select(
                'g.id_caja as id',
                'g.descripcion as descri',
                'g.mmonto as monto',
                'g.fecha_registro as fecha_regi',
                'g.categoria_id',
                'c.nombre as categoria',
                'u.name as responsable'
            );
    }
}
