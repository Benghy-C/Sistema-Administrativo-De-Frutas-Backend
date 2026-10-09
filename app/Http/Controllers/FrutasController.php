<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;

class FrutasController extends Controller
{
    public function index()
    {
        return response()->json([
            DB::table('fruta')->where('estado', 1)->orderBy('descripcion')
                ->get(['id', 'descripcion', 'estado']),
        ]);
    }
}
