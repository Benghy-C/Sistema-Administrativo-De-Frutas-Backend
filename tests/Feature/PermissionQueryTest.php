<?php

namespace Tests\Feature;

use App\Models\User;
use App\Http\Middleware\EnsurePermission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PermissionQueryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['0001_01_01_000000_create_users_table.php', '2026_07_27_160155_create_permission_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
    }

    public function test_permission_query_count(): void
    {
        $user = User::create(['name' => 'Prueba', 'email' => 'query@example.invalid', 'password' => Hash::make('Test-password-123')]);
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::create(['name' => 'read-compra', 'guard_name' => 'web']));
        $user->assignRole($role);
        $fresh = User::findOrFail($user->id);
        DB::enableQueryLog();
        $access = $fresh->apiAccess();
        $this->assertSame(['viewer'], $access['roles']);
        $this->assertSame(['read-compra'], $access['permissions']);
        $this->assertCount(1, DB::getQueryLog());
        $request = Request::create('/api/compra/index', 'POST');
        $request->setUserResolver(fn () => User::findOrFail($user->id));
        $requestUser = $request->user();
        $request->setUserResolver(fn () => $requestUser);
        DB::flushQueryLog();
        $response = (new EnsurePermission)->handle($request, fn () => response()->json([]), 'read-compra');
        $this->assertCount(1, DB::getQueryLog());
        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_direct_permissions_and_roles_keep_their_scope(): void
    {
        $user = User::create(['name' => 'Prueba', 'email' => 'direct@example.invalid', 'password' => 'unused']);
        $read = Permission::create(['name' => 'read-compra', 'guard_name' => 'web']);
        $write = Permission::create(['name' => 'create-compra', 'guard_name' => 'web']);
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $role->givePermissionTo($read);
        $user->assignRole($role);
        $user->givePermissionTo([$read, $write]);
        $other = Role::create(['name' => 'admin', 'guard_name' => 'api']);
        DB::table('model_has_roles')->insert(['role_id' => $other->id, 'model_id' => $user->id, 'model_type' => $user->getMorphClass()]);
        $otherUser = User::create(['name' => 'Otra', 'email' => 'other@example.invalid', 'password' => 'unused']);
        $otherUser->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web']));

        $access = $user->apiAccess();
        $this->assertSame(['viewer'], $access['roles']);
        $this->assertEqualsCanonicalizing(['read-compra', 'create-compra'], $access['permissions']);
        $request = Request::create('/api/test', 'POST');
        $request->setUserResolver(fn () => $user);
        $middleware = new EnsurePermission;
        $next = fn () => response()->json([]);
        $this->assertSame(403, $middleware->handle($request, $next, 'admin')->getStatusCode());
        $this->assertSame(200, $middleware->handle($request, $next, 'missing', 'read-compra')->getStatusCode());

        $user->revokePermissionTo($read);
        $role->revokePermissionTo($read);
        $this->assertSame(403, $middleware->handle($request, $next, 'read-compra')->getStatusCode());
        $this->assertSame(200, $middleware->handle($request, $next, 'create-compra')->getStatusCode());
        $user->assignRole(Role::where('name', 'admin')->where('guard_name', 'web')->firstOrFail());
        $this->assertSame(200, $middleware->handle($request, $next, 'admin')->getStatusCode());
    }
}
