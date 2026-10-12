<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ClienteController extends ContactController
{
    protected string $tabla = 'clientes';

    public function index()
    {
        $respuesta = DB::table('clientes')
            ->select('id as id_clie', 'nombres as nombre_clie', 'cel', 'telefono',
                'correo', 'descripcion', 'estado')
            ->selectSub(function ($query) {
                $query->from('registro_actividad')
                    ->selectRaw('MIN(created_at)')
                    ->whereColumn('registro_id', 'clientes.id')
                    ->where('entidad', 'clientes')
                    ->where('accion', 'registrar');
            }, 'fecha_inscripcion')
            ->orderBy('nombres')
            ->get();

        return response()->json([$respuesta]);
    }
}
