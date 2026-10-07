<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\OrderReader;

class SessionController extends Controller
{
    public function show(Request $request, OrderReader $orders): JsonResponse
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

        return response()->json($profile)->header('Cache-Control', 'no-store');
    }
}
