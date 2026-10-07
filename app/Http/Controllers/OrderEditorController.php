<?php

namespace App\Http\Controllers;

use App\Services\OrderEditorReader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderEditorController extends Controller
{
    public function show(Request $request, string $id, OrderEditorReader $reader): JsonResponse
    {
        $input = validator(['id' => $id], ['id' => ['required', 'integer', 'min:1', 'max:2147483647']])->validate();
        $data = $reader->read((int) $input['id']);
        if (!$data['order']) {
            return response()->json(['message' => 'Pedido no encontrado.'], 404);
        }
        return response()->json($data)->header('Cache-Control', 'no-store');
    }
}
