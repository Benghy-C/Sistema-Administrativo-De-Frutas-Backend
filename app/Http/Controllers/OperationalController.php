<?php

namespace App\Http\Controllers;

use App\Services\ActivityRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OperationalController extends Controller
{
    public function categorias()
    {
        return response()->json(
            DB::table('categorias_gasto')->orderBy('nombre')
                ->get(['id', 'nombre', 'activa'])
        );
    }

    public function guardarCategoria(Request $request)
    {
        $request->merge(['nombre' => trim((string) $request->input('nombre', ''))]);
        $datos = $request->validate([
            'id' => 'nullable|integer|min:1',
            'nombre' => 'required|string|max:80',
            'activa' => 'required|boolean',
        ]);

        try {
            $id = DB::transaction(function () use ($datos, $request) {
                $id = $datos['id'] ?? null;
                $antes = $id ? DB::table('categorias_gasto')
                    ->where('id', $id)->lockForUpdate()->first() : null;
                abort_if($id && !$antes, 404, 'Categoría no encontrada.');

                $nombre = $datos['nombre'];
                $normalizado = mb_strtolower($nombre);
                $duplicado = DB::table('categorias_gasto')
                    ->where('nombre_normalizado', $normalizado);

                if ($id) {
                    $duplicado->where('id', '!=', $id);
                }

                if ($duplicado->exists()) {
                    $this->categoriaDuplicada();
                }

                $valores = [
                    'nombre' => $nombre,
                    'nombre_normalizado' => $normalizado,
                    'activa' => $datos['activa'],
                    'updated_at' => now(),
                ];

                if ($id) {
                    DB::table('categorias_gasto')->where('id', $id)->update($valores);
                } else {
                    $id = DB::table('categorias_gasto')->insertGetId(
                        $valores + ['created_at' => now()]
                    );
                }

                app(ActivityRecorder::class)->registrar(
                    'categoria_gasto', $id, $antes ? 'actualizar' : 'registrar',
                    (int) $request->user()->id, $antes, $valores
                );

                return $id;
            }, 3);
        } catch (QueryException $error) {
            if (($error->errorInfo[0] ?? '') === '23505') {
                $this->categoriaDuplicada();
            }

            throw $error;
        }

        return response()->json(['id' => $id, 'mensaje' => 'Categoría guardada.']);
    }

    public function historial(Request $request)
    {
        $reglas = [
            'entidad' => 'nullable|in:usuario,proveedores,clientes,compra,venta,'.
                'gasto,perdida,categoria_gasto',
            'registro_id' => 'nullable|integer|min:1',
            'usuario_id' => 'nullable|integer|min:1',
            'accion' => 'nullable|string|max:40',
            'buscar' => 'nullable|string|max:100',
            'desde' => 'nullable|date_format:Y-m-d',
            'hasta' => ['nullable', 'date_format:Y-m-d'],
            'page' => 'nullable|integer|min:1',
        ];

        if ($request->filled('desde')) {
            $reglas['hasta'][] = 'after_or_equal:desde';
        }

        $datos = $request->validate($reglas);
        $query = DB::table('registro_actividad as a')
            ->leftJoin('users as u', 'u.id', '=', 'a.usuario_id')
            ->select('a.*', 'u.name as responsable');

        foreach (['entidad', 'registro_id', 'usuario_id', 'accion'] as $campo) {
            if (!empty($datos[$campo])) {
                $query->where('a.'.$campo, $datos[$campo]);
            }
        }

        foreach (['desde' => '>=', 'hasta' => '<='] as $campo => $operador) {
            if (!empty($datos[$campo])) {
                $query->whereDate('a.created_at', $operador, $datos[$campo]);
            }
        }

        if (!empty($datos['buscar'])) {
            $buscar = '%'.mb_strtolower(trim($datos['buscar'])).'%';
            $query->where(function ($query) use ($buscar) {
                $query->whereRaw('LOWER(u.name) LIKE ?', [$buscar])
                    ->orWhereRaw('LOWER(a.accion) LIKE ?', [$buscar])
                    ->orWhereRaw('LOWER(a.motivo) LIKE ?', [$buscar])
                    ->orWhereRaw('CAST(a.registro_id AS TEXT) LIKE ?', [$buscar]);
            });
        }

        return response()->json($query->orderByDesc('a.id')->paginate(7));
    }

    private function categoriaDuplicada(): never
    {
        throw ValidationException::withMessages([
            'nombre' => 'Ya existe una categoría con ese nombre.',
        ]);
    }
}
