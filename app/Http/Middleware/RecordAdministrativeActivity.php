<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ActivityRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecordAdministrativeActivity
{
    public function handle(Request $request, Closure $next)
    {
        $acciones = [
            'api/auth/make/user' => 'registrar',
            'api/auth/update/user' => 'actualizar',
            'api/auth/cambiar-estado/user' => 'cambiar_estado',
            'api/auth/cambiar-contra/user' => 'restablecer_contraseña',
            'api/auth/roler-user/asignar' => 'asignar_rol',
            'api/compra/store' => 'registrar',
            'api/compra/update' => 'actualizar',
            'api/compra/editar-estado' => 'cambiar_estado',
        ];
        $accion = $acciones[$request->path()] ?? null;
        if (!$accion) return $next($request);

        return DB::transaction(function () use ($request, $next, $accion) {
            $compra = str_starts_with($request->path(), 'api/compra/');
            $id = (int) ($request->s_id ?? $request->s_id_user ?? $request->s_id_compra ?? 0);
            $antes = $id ? $this->snapshot($id, $compra) : null;
            $response = $next($request);
            if (!$response->isSuccessful()) return $response;
            $datos = json_decode($response->getContent(), true);
            $resultado = $datos[0][0] ?? $datos;
            if (isset($resultado['error']) && (int) $resultado['error'] !== 0) return $response;
            if (!empty($resultado['reintento'])) return $response;
            $id = $id ?: (int) ($resultado['numid'] ?? $resultado['id'] ?? 0);
            if ($id > 0) {
                app(ActivityRecorder::class)->registrar($compra ? 'compra' : 'usuario', $id, $accion,
                    (int) $request->user()->id, $antes, $this->snapshot($id, $compra));
            }

            return $response;
        }, 3);
    }

    private function snapshot(int $id, bool $compra): ?array
    {
        if ($compra) {
            $registro = DB::table('compra')->where('id', $id)->lockForUpdate()->first();
            if (!$registro) return null;

            return ['compra' => $registro,
                'detalle' => DB::table('compra_fruta')->where('id_pedido', $id)->get(),
                'lotes' => DB::table('camara_refigeracion')->where('id_compra', $id)->get()];
        }
        $usuario = User::with('roles')->find($id);
        if (!$usuario) return null;

        return $usuario->only(['id', 'name', 'email', 'identificador', 'user_estado'])
            + ['roles' => $usuario->roles->pluck('name')->sort()->values()->all()];
    }
}
