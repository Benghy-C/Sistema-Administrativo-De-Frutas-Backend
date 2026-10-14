<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiSecurityTest;

/**
 * Pruebas unitarias para los endpoints de autenticación (login/logout).
 *
 * Se verifican:
 * - Login exitoso devuelve token, expires_at y datos del cliente.
 * - Login con credenciales incorrectas devuelve 401.
 * - Login de usuario inactivo devuelve 401 con ACCOUNT_INACTIVE.
 * - Logout revoca el token actual.
 * - El token expirado no funciona.
 * - La contraseña nunca se expone en la respuesta.
 */
class AuthenticationTest extends ApiSecurityTest
{
    public function test_login_exitoso_devuelve_token_y_datos(): void
    {
        User::create([
            'name' => 'María', 'email' => 'maria@test.invalid',
            'password' => Hash::make('Contra-segura-123'), 'user_estado' => 1,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'p_email' => 'maria@test.invalid',
            'p_password' => 'Contra-segura-123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'expires_at', 'cliente' => ['id', 'nombre', 'email']])
            ->assertJsonPath('cliente.nombre', 'María')
            ->assertJsonPath('error', '0');
    }

    public function test_login_email_inexistente_devuelve_401(): void
    {
        $this->postJson('/api/auth/login', [
            'p_email' => 'noexiste@test.invalid',
            'p_password' => 'cualquierpassword',
        ])->assertUnauthorized();
    }

    public function test_login_password_incorrecta_devuelve_401(): void
    {
        User::create([
            'name' => 'Pedro', 'email' => 'pedro@test.invalid',
            'password' => Hash::make('LaContraReal'), 'user_estado' => 1,
        ]);

        $this->postJson('/api/auth/login', [
            'p_email' => 'pedro@test.invalid',
            'p_password' => 'ContraEquivocada',
        ])->assertUnauthorized()
          ->assertJsonPath('error', '100');
    }

    public function test_login_usuario_inactivo_devuelve_account_inactive(): void
    {
        User::create([
            'name' => 'Inactivo', 'email' => 'inactivo@test.invalid',
            'password' => Hash::make('MiContra123'), 'user_estado' => 0,
        ]);

        $this->postJson('/api/auth/login', [
            'p_email' => 'inactivo@test.invalid',
            'p_password' => 'MiContra123',
        ])->assertUnauthorized()
          ->assertJsonPath('code', 'ACCOUNT_INACTIVE');
    }

    public function test_login_no_expone_password_en_respuesta(): void
    {
        User::create([
            'name' => 'Seguro', 'email' => 'seguro@test.invalid',
            'password' => Hash::make('Password-123'), 'user_estado' => 1,
        ]);

        $response = $this->postJson('/api/auth/login', [
            'p_email' => 'seguro@test.invalid',
            'p_password' => 'Password-123',
        ]);

        $response->assertOk()
            ->assertJsonMissingPath('cliente.password')
            ->assertJsonMissingPath('password');
    }

    public function test_login_valida_campos_requeridos(): void
    {
        $this->postJson('/api/auth/login', [])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['p_email' => 'a@b.c'])->assertUnprocessable();
        $this->postJson('/api/auth/login', ['p_password' => 'algo'])->assertUnprocessable();
    }

    public function test_login_respuesta_tiene_header_cache_control_no_store(): void
    {
        User::create([
            'name' => 'Cache', 'email' => 'cache@test.invalid',
            'password' => Hash::make('Password-123'), 'user_estado' => 1,
        ]);

        $this->postJson('/api/auth/login', [
            'p_email' => 'cache@test.invalid',
            'p_password' => 'Password-123',
        ])->assertOk()
          ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_logout_revoca_token_del_usuario(): void
    {
        $user = User::create([
            'name' => 'Logout', 'email' => 'logout@test.invalid',
            'password' => Hash::make('Password-123'), 'user_estado' => 1,
        ]);
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;

        $this->withToken($token)->postJson('/api/auth/logout')->assertNoContent();

        // El token ya no sirve
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_token_expirado_es_rechazado(): void
    {
        $user = User::create([
            'name' => 'Expirado', 'email' => 'expirado@test.invalid',
            'password' => Hash::make('Password-123'), 'user_estado' => 1,
        ]);
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->subMinute())->plainTextToken;

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    }
}
