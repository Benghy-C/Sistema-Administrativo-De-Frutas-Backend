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
            ->orderBy('nombres')
            ->get();

        return response()->json([$respuesta]);
    }
}
