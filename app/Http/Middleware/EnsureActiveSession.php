<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user && (int) $user->user_estado !== 1) {
            return response()->json([
                'message' => 'Tu cuenta está inactiva. Contacta al administrador para recuperar el acceso.',
                'code' => 'ACCOUNT_INACTIVE',
            ], 401);
        }

        if (!$user || (int) $user->user_estado !== 1 || !$token
            || $token->name !== 'web-session' || !$token->can($user->passwordAbility())) {
            return response()->json(['message' => 'Sesión no válida.'], 401);
        }

        return $next($request);
    }
}
