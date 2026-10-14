<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsurePermission;
use Illuminate\Http\Request;
use Tests\Feature\ApiSecurityTest;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Pruebas unitarias para el middleware EnsurePermission.
 *
 * Verifica que:
 * - Un usuario con el permiso requerido pasa el middleware.
 * - Un usuario admin pasa cuando se requiere 'admin'.
 * - Un usuario sin permiso recibe 403.
 * - Basta con tener UNO de los permisos listados (OR lógico).
 */
class EnsurePermissionMiddlewareTest extends ApiSecurityTest
{
    private function runMiddleware(User $user, string ...$permissions): int
    {
        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsurePermission;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]), ...$permissions);

        return $response->getStatusCode();
    }

    public function test_usuario_con_permiso_pasa(): void
    {
        $user = User::create(['name' => 'Perm', 'email' => 'perm@example.invalid', 'password' => 'pass']);
        $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);
        $perm = Permission::create(['name' => 'create-compra', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $user->assignRole($role);

        $this->assertSame(200, $this->runMiddleware($user, 'create-compra'));
    }

    public function test_usuario_sin_permiso_recibe_403(): void
    {
        $user = User::create(['name' => 'NoPerm', 'email' => 'noperm@example.invalid', 'password' => 'pass']);

        $this->assertSame(403, $this->runMiddleware($user, 'create-compra'));
    }

    public function test_admin_pasa_cuando_se_requiere_admin(): void
    {
        $user = User::create(['name' => 'Admin', 'email' => 'adm@example.invalid', 'password' => 'pass']);
        $user->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web']));

        $this->assertSame(200, $this->runMiddleware($user, 'admin'));
    }

    public function test_admin_no_pasa_si_no_se_lista_admin(): void
    {
        $user = User::create(['name' => 'AdminNo', 'email' => 'admno@example.invalid', 'password' => 'pass']);
        $user->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web']));

        // El middleware solo comprueba 'admin' si está en la lista de permisos
        // Si pedimos 'create-compra' y el admin no tiene ese permiso directo → 403
        $this->assertSame(403, $this->runMiddleware($user, 'create-compra'));
    }

    public function test_con_multiples_permisos_basta_uno(): void
    {
        $user = User::create(['name' => 'Multi', 'email' => 'multi@example.invalid', 'password' => 'pass']);
        $perm = Permission::create(['name' => 'read-venta', 'guard_name' => 'web']);
        $user->givePermissionTo($perm);

        // Pide read-compra O read-venta; el usuario tiene read-venta → pasa
        $this->assertSame(200, $this->runMiddleware($user, 'read-compra', 'read-venta'));
    }

    public function test_respuesta_403_es_json_con_mensaje(): void
    {
        $user = User::create(['name' => 'Json', 'email' => 'json@example.invalid', 'password' => 'pass']);

        $request = Request::create('/test', 'GET');
        $request->setUserResolver(fn () => $user);

        $middleware = new EnsurePermission;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]), 'create-compra');

        $this->assertSame(403, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('message', $body);
    }
}
