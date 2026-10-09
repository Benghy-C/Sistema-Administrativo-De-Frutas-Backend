<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRolesTest extends TestCase
{
    use \Tests\Support\CreatesActivitySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createActivitySchema();
        if (config('database.default') !== 'sqlite' || config('database.connections.sqlite.database') !== ':memory:') {
            throw new \RuntimeException('Las pruebas requieren SQLite en memoria.');
        }
        foreach (['0001_01_01_000000_create_users_table.php', '2026_07_24_001351_create_personal_access_tokens_table.php', '2026_07_27_160155_create_permission_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('users', function (Blueprint $t) {
            $t->string('identificador')->nullable();
            $t->integer('user_estado')->default(1);
        });
        $admin = User::create(['name' => 'Admin', 'email' => 'admin@example.invalid', 'password' => 'password']);
        $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web']));
        $this->withToken($admin->createToken('web-session', [$admin->passwordAbility()], now()->addHour())->plainTextToken);
    }

    private function payload(int $role): array
    {
        return ['s_nombre' => 'Prueba', 's_email' => 'new@example.invalid', 's_password' => 'Password-123', 's_password_confirmation' => 'Password-123', 's_documento' => '12345678', 's_id_rol' => $role];
    }

    public function test_create_assigns_role_and_login_returns_permissions(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::create(['name' => 'read-compra', 'guard_name' => 'web']));
        $this->postJson('/api/auth/make/user', $this->payload($role->id))->assertOk()->assertJsonPath('0.0.error', 0);
        $user = User::where('email', 'new@example.invalid')->firstOrFail();
        $this->assertTrue($user->hasRole('viewer'));
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['p_email' => 'new@example.invalid', 'p_password' => 'Password-123'])->assertOk()->assertJsonPath('cliente.roles.0', 'viewer')->assertJsonPath('cliente.permissions.0', 'read-compra');
    }

    public function test_invalid_role_creates_no_user(): void
    {
        $this->postJson('/api/auth/make/user', $this->payload(999))->assertUnprocessable();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.invalid']);
    }

    public function test_role_failure_rolls_back_creation(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        DB::statement('DROP TABLE model_has_roles');
        $this->postJson('/api/auth/make/user', $this->payload($role->id))->assertServerError();
        $this->assertDatabaseMissing('users', ['email' => 'new@example.invalid']);
    }

    public function test_edit_assigns_role_to_existing_user_and_preserves_password(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $user = User::create(['name' => 'Anterior', 'email' => 'new@example.invalid', 'password' => 'existing-hash']);
        $data = $this->payload($role->id);
        unset($data['s_password']);
        $data['s_id'] = $user->id;
        $this->postJson('/api/auth/update/user', $data)->assertOk();
        $this->assertTrue($user->fresh()->hasRole('viewer'));
        $this->assertSame('existing-hash', $user->fresh()->password);
    }

    public function test_catalog_and_registration_are_admin_only(): void
    {
        Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $this->getJson('/api/auth/roles')->assertOk()->assertJsonCount(2);
        $user = User::create(['name' => 'Consulta', 'email' => 'viewer@example.invalid', 'password' => 'password']);
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken);
        $this->getJson('/api/auth/roles')->assertForbidden();
        $this->postJson('/api/auth/make/user', $this->payload(1))->assertForbidden();
    }

    public function test_last_active_administrator_cannot_lose_access(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $admin = User::where('email', 'admin@example.invalid')->firstOrFail();
        $data = $this->payload($role->id);
        unset($data['s_password']);
        $data['s_id'] = $admin->id;
        $this->postJson('/api/auth/update/user', $data)->assertUnprocessable()->assertJsonValidationErrors('s_id_rol');
        $this->assertTrue($admin->fresh()->hasRole('admin'));
        $this->assertSame('admin@example.invalid', $admin->fresh()->email);
    }

    public function test_duplicate_email_is_rejected_case_insensitively(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $data = $this->payload($role->id);
        $data['s_email'] = 'ADMIN@example.invalid';
        $this->postJson('/api/auth/make/user', $data)->assertUnprocessable()->assertJsonValidationErrors('s_email');
        $this->assertDatabaseCount('users',1);
    }
    public function test_duplicate_dni_is_rejected(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        User::create(['name' => 'Existente', 'email' => 'existing@example.invalid',
            'password' => 'password'])->forceFill(['identificador' => '12345678'])->save();
        $this->postJson('/api/auth/make/user', $this->payload($role->id))
            ->assertUnprocessable()->assertJsonValidationErrors('s_documento');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.invalid']);
    }

    public function test_new_password_requires_matching_confirmation(): void
    {
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $data = $this->payload($role->id);
        $data['s_password_confirmation'] = 'Otra-clave';
        $this->postJson('/api/auth/make/user', $data)
            ->assertUnprocessable()->assertJsonValidationErrors('s_password');
        $this->assertDatabaseMissing('users', ['email' => 'new@example.invalid']);
    }

    public function test_last_active_administrator_cannot_be_deactivated(): void
    {
        $admin = User::where('email', 'admin@example.invalid')->firstOrFail();
        $this->postJson('/api/auth/cambiar-estado/user', ['s_id_user' => $admin->id])
            ->assertUnprocessable()->assertJsonValidationErrors('s_id_user');
        $this->assertSame(1, $admin->fresh()->user_estado);
        $this->assertSame(1, $admin->tokens()->count());
    }
}