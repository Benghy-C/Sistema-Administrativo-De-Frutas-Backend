<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class ProvedorController extends ContactController
{
    protected string $tabla = 'proveedores';

    public function index()
    {
        return response()->json([DB::table('proveedores')->orderBy('nombre')->get()]);
    }
}
