<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $user = $request->user();
        foreach ($permissions as $permission) {
            if ($permission === 'admin'
                ? $user->hasRole('admin', 'web')
                : $user->checkPermissionTo($permission, 'web')) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'No tienes permiso para esta operación.'], 403);
    }
}
