<?php

namespace App\Http\Controllers;

use App\Services\ActivityRecorder;
use App\Services\PerdidaWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OperationalController extends Controller
{
    public function perdidas(Request $request)
    {
        $datos = $request->validate([
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:desde',
            'camara_id' => 'nullable|integer|min:1',
            'calidad' => 'nullable|in:A,B,C',
            'page' => 'nullable|integer|min:1',
        ]);
        $query = DB::table('perdidas as p')
            ->join('camara_refigeracion as c', 'c.id_camara', '=', 'p.camara_id')
            ->join('compra as co', 'co.id', '=', 'c.id_compra')
            ->leftJoin('users as u', 'u.id', '=', 'p.usuario_id')
            ->select('p.*', 'co.codigo_compra as lote', 'u.name as responsable');
        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
            if (!empty($datos[$campo])) $query->whereDate('p.fecha', $operador, $datos[$campo]);
        }
        foreach (['camara_id', 'calidad'] as $campo) {
            if (!empty($datos[$campo])) $query->where('p.'.$campo, $datos[$campo]);
        }

        return response()->json($query->orderByDesc('p.fecha')->orderByDesc('p.id')->paginate(20));
    }

    public function registrarPerdida(Request $request, PerdidaWriter $writer)
    {
        $datos = $request->validate([
            'camara_id' => 'required|integer|min:1', 'calidad' => 'required|in:A,B,C',
            'cantidad' => 'required|integer|min:1|max:999999',
            'fecha' => 'required|date_format:Y-m-d|before_or_equal:'.now('America/Lima')->toDateString(),
            'motivo' => 'required|string|min:3|max:500', 'operacion' => 'required|uuid',
        ]);

        return response()->json($writer->crear($datos, (int) $request->user()->id));
    }

    public function categorias()
    {
        return response()->json(DB::table('categorias_gasto')->orderBy('nombre')->get(['id', 'nombre', 'activa']));
    }

    public function guardarCategoria(Request $request)
    {
        $datos = $request->validate([
            'id' => 'nullable|integer|min:1', 'nombre' => 'required|string|max:80',
            'activa' => 'required|boolean',
        ]);
        $nombre = trim($datos['nombre']);
        abort_if($nombre === '', 422, 'Escribe el nombre de la categoría.');
        $normalizado = mb_strtolower($nombre);
        $id = DB::transaction(function () use ($datos, $nombre, $normalizado, $request) {
            $id = $datos['id'] ?? null;
            $antes = $id ? DB::table('categorias_gasto')->where('id', $id)->lockForUpdate()->first() : null;
            abort_if($id && !$antes, 404, 'Categoría no encontrada.');
            $query = DB::table('categorias_gasto')->where('nombre_normalizado', $normalizado);
            if ($id) $query->where('id', '!=', $id);
            abort_if($query->exists(), 422, 'Ya existe una categoría con ese nombre.');
            $valores = ['nombre' => $nombre, 'nombre_normalizado' => $normalizado,
                'activa' => $datos['activa'], 'updated_at' => now()];
            if ($id) DB::table('categorias_gasto')->where('id', $id)->update($valores);
            else $id = DB::table('categorias_gasto')->insertGetId($valores + ['created_at' => now()]);
            app(ActivityRecorder::class)->registrar('categoria_gasto', $id,
                $antes ? 'actualizar' : 'registrar', (int) $request->user()->id, $antes, $valores);

            return $id;
        });

        return response()->json(['id' => $id, 'mensaje' => 'Categoría guardada.']);
    }

    public function historial(Request $request)
    {
        $datos = $request->validate([
            'entidad' => 'nullable|string|max:40', 'registro_id' => 'nullable|integer|min:1',
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => 'nullable|date_format:Y-m-d|after_or_equal:desde',
            'page' => 'nullable|integer|min:1',
        ]);
        $query = DB::table('registro_actividad as a')->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->select('a.*', 'u.name as responsable');
        foreach (['entidad', 'registro_id'] as $campo) {
            if (!empty($datos[$campo])) $query->where('a.'.$campo, $datos[$campo]);
        }
        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
            if (!empty($datos[$campo])) $query->whereDate('a.created_at', $operador, $datos[$campo]);
        }

        return response()->json($query->orderByDesc('a.id')->paginate(20));
    }
}
