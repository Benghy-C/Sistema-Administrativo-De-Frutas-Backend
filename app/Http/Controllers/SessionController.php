<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\OrderReader;
use App\Services\OrderEditorReader;

class SessionController extends Controller
{
    public function show(Request $request, OrderReader $orders, OrderEditorReader $editor): JsonResponse
    {
        $user = $request->user();
        $access = $user->apiAccess();
        $profile = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $access['roles'],
            'permissions' => $access['permissions'],
        ];

        if ($request->query('include') === 'orders') {
            $input = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);
            if (in_array('admin', $access['roles'], true)
                || in_array('read-compra', $access['permissions'], true)) {
                $profile['initial_orders'] = [
                    'date' => $input['date'],
                    'rows' => $orders->forDate($input['date']),
                ];
            }
        }

        if ($request->query('include') === 'editor') {
            $input = $request->validate(['id' => ['required', 'integer', 'min:1', 'max:2147483647']]);
            if (in_array('admin', $access['roles'], true)
                || in_array('update-compra', $access['permissions'], true)) {
                $profile['initial_editor'] = [
                    'id' => (int) $input['id'],
                    'data' => $editor->read((int) $input['id']),
                ];
            }
        }
        return response()->json($profile)->header('Cache-Control', 'no-store');
    }
}
