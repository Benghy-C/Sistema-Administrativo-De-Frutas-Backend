<?php

namespace App\Http\Controllers;

use App\Services\ActivityRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CajaChicaController extends Controller
{
    public function store(Request $request)
    {
        $datos = $request->validate([
            's_monto' => 'required|numeric|gt:0|max:999999999|decimal:0,2',
            's_desc' => 'required|string|min:1|max:120',
            's_fecha' => 'required|date_format:Y-m-d H:i:s|before_or_equal:'.now('America/Lima')->format('Y-m-d H:i:s'),
            'categoria_id' => 'required|integer|min:1',
            'operacion' => 'required|uuid',
        ]);
        $datos['s_desc'] = trim($datos['s_desc']);
        if ($datos['s_desc'] === '') {
            throw ValidationException::withMessages(['s_desc' => 'Escribe la descripción del gasto.']);
        }
        $usuario = (int) $request->user()->id;
        $hash = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));
        $id = DB::transaction(function () use ($datos, $usuario, $hash) {
            if (DB::getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',
                    ['gasto:'.$datos['operacion']]);
            }
            $confirmacion = DB::table('gasto_confirmaciones')->where('operacion', $datos['operacion'])
                ->lockForUpdate()->first();
            if ($confirmacion) {
                if ($confirmacion->solicitud_hash !== $hash || (int) $confirmacion->usuario_id !== $usuario) {
                    throw ValidationException::withMessages(['gasto' => 'La operación ya pertenece a otro gasto.']);
                }

                return $confirmacion->gasto_id;
            }
            $categoria = DB::table('categorias_gasto')->where('id', $datos['categoria_id'])->sharedLock()->first();
            if (!$categoria || !$categoria->activa) {
                throw ValidationException::withMessages(['categoria_id' => 'Selecciona una categoría activa.']);
            }
            $id = DB::table('cjchica')->insertGetId([
                'descripcion' => $datos['s_desc'], 'mmonto' => $datos['s_monto'],
                'id_user_ins' => $usuario, 'fecha_registro' => $datos['s_fecha'],
                'categoria_id' => $categoria->id,
            ], 'id_caja');
            DB::table('gasto_confirmaciones')->insert([
                'operacion' => $datos['operacion'], 'gasto_id' => $id,
                'usuario_id' => $usuario, 'solicitud_hash' => $hash,
            ]);
            app(ActivityRecorder::class)->registrar('gasto', $id, 'registrar', $usuario, null, $datos);

            return $id;
        }, 3);

        return response()->json([[['error' => 0, 'numid' => $id, 'mensa' => 'Gasto registrado.']]]);
    }

    public function show(Request $request)
    {
        $datos = $request->validate([
            's_fecha_ini' => 'required|date',
            's_fecha_fin' => 'required|date|after_or_equal:s_fecha_ini',
            'buscar' => 'nullable|string|max:120',
            'categoria_id' => 'nullable|integer|min:1',
        ]);
        $query = $this->consulta()->whereBetween('g.fecha_registro', [$datos['s_fecha_ini'], $datos['s_fecha_fin']]);
        if (!empty($datos['buscar'])) {
            $query->whereRaw('LOWER(g.descripcion) LIKE ?', ['%'.mb_strtolower($datos['buscar']).'%']);
        }
        if (!empty($datos['categoria_id'])) {
            $query->where('g.categoria_id', $datos['categoria_id']);
        }

        return response()->json([$query->orderByDesc('g.fecha_registro')->orderByDesc('g.id_caja')->get()]);
    }

    public function index()
    {
        return response()->json([$this->consulta()->orderByDesc('g.fecha_registro')->get()]);
    }

    private function consulta()
    {
        return DB::table('cjchica as g')
            ->leftJoin('categorias_gasto as c', 'c.id', '=', 'g.categoria_id')
            ->leftJoin('users as u', 'u.id', '=', 'g.id_user_ins')
            ->select('g.id_caja as id', 'g.descripcion as descri', 'g.mmonto as monto',
                'g.fecha_registro as fecha_regi', 'g.categoria_id', 'c.nombre as categoria',
                'u.name as responsable');
    }
}
