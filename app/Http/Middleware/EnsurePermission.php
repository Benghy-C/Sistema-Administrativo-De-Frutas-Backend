<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $access = $request->user()->apiAccess();
        foreach ($permissions as $permission) {
            if ($permission === 'admin'
                ? in_array('admin', $access['roles'], true)
                : in_array($permission, $access['permissions'], true)) {
                return $next($request);
            }
        }

        return response()->json(['message' => 'No tienes permiso para esta operación.'], 403);
    }
}
