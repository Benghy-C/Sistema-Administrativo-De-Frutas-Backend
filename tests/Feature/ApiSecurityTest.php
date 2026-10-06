<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ApiSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['0001_01_01_000000_create_users_table.php', '2026_07_24_001351_create_personal_access_tokens_table.php', '2026_07_27_160155_create_permission_tables.php'] as $file) {
            (require database_path('migrations/'.$file))->up();
        }
        Schema::table('users', fn (Blueprint $table) => $table->integer('user_estado')->default(1));
    }

    private function user(): User
    {
        return User::create(['name' => 'Prueba', 'email' => 'security@example.invalid', 'password' => Hash::make('Test-password-123')]);
    }

    public function test_all_business_routes_reject_anonymous_requests(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/') || $route->uri() === 'api/auth/login') continue;
            $method = in_array('GET', $route->methods()) ? 'GET' : 'POST';
            $this->json($method, '/'.$route->uri(), [])->assertUnauthorized();
        }
    }

    public function test_login_returns_expiring_token_without_password(): void
    {
        $this->user();
        $response = $this->postJson('/api/auth/login', ['p_email' => 'security@example.invalid', 'p_password' => 'Test-password-123']);
        $response->assertOk()->assertJsonPath('cliente.nombre', 'Prueba')->assertJsonStructure(['token', 'expires_at']);
        $this->assertArrayNotHasKey('password', $response->json('cliente'));
        $this->withToken($response->json('token'))->getJson('/api/user')->assertOk();
    }

    public function test_viewer_cannot_modify_accounts_or_orders(): void
    {
        $user = $this->user();
        $role = Role::create(['name' => 'viewer', 'guard_name' => 'web']);
        $role->givePermissionTo(Permission::create(['name' => 'read-compra', 'guard_name' => 'web']));
        $user->assignRole($role);
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        foreach (['auth/listar-usuarios', 'auth/cambiar-contra/user', 'compra/store', 'compra/update', 'envio/store'] as $path) {
            $this->withToken($token)->postJson('/api/'.$path, [])->assertForbidden();
        }
    }

    public function test_inactive_users_and_legacy_tokens_are_rejected(): void
    {
        $user = $this->user();
        $legacy = $user->createToken('auth_token')->plainTextToken;
        $this->withToken($legacy)->getJson('/api/user')->assertUnauthorized();
        $user->user_estado = 0;
        $user->save();
        $this->postJson('/api/auth/login', ['p_email' => $user->email, 'p_password' => 'Test-password-123'])->assertUnauthorized();
    }

    public function test_changed_password_invalidates_existing_tokens(): void
    {
        $user = $this->user();
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        $user->password = Hash::make('Changed-password-123');
        $user->save();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_logout_revokes_token(): void
    {
        $user = $this->user();
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_invalid_token_is_rejected_before_writes(): void
    {
        $this->withToken('invalid-token')->postJson('/api/auth/cambiar-contra/user', [])->assertUnauthorized();
    }

    public function test_admin_reaches_validation_and_expired_tokens_are_rejected(): void
    {
        $user = $this->user();
        $user->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web']));
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        $this->withToken($token)->postJson('/api/auth/make/user', [])->assertUnprocessable();
        $this->app['auth']->forgetGuards();
        $expired = $user->createToken('web-session', [$user->passwordAbility()], now()->subMinute())->plainTextToken;
        $this->withToken($expired)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_api_returns_json_401_without_accept_header(): void
    {
        $this->post('/api/compra/store', [])->assertUnauthorized()->assertJsonStructure(['message']);
    }
}
