<?php

namespace Tests\Unit;

use App\Http\Middleware\EnsureActiveSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiSecurityTest;

/**
 * Pruebas unitarias para el middleware EnsureActiveSession.
 *
 * Verifica que:
 * - Un usuario activo con token 'web-session' y passwordAbility válido pasa.
 * - Un usuario inactivo recibe 401 con código ACCOUNT_INACTIVE.
 * - Un token con nombre distinto a 'web-session' es rechazado.
 * - Un token sin la ability de password es rechazado.
 */
class EnsureActiveSessionMiddlewareTest extends ApiSecurityTest
{
    public function test_usuario_activo_con_token_valido_pasa(): void
    {
        $user = User::create([
            'name' => 'Activo', 'email' => 'activo@example.invalid',
            'password' => Hash::make('password123'), 'user_estado' => 1,
        ]);
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour());

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);
        // Simular el token actual
        $user->withAccessToken($token->accessToken);

        $middleware = new EnsureActiveSession;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_usuario_inactivo_recibe_401_account_inactive(): void
    {
        $user = User::create([
            'name' => 'Inactivo', 'email' => 'inactivo@example.invalid',
            'password' => Hash::make('password123'), 'user_estado' => 0,
        ]);
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour());

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);
        $user->withAccessToken($token->accessToken);

        $middleware = new EnsureActiveSession;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(401, $response->getStatusCode());
        $body = json_decode($response->getContent(), true);
        $this->assertSame('ACCOUNT_INACTIVE', $body['code']);
    }

    public function test_token_legacy_nombre_incorrecto_es_rechazado(): void
    {
        $user = User::create([
            'name' => 'Legacy', 'email' => 'legacy@example.invalid',
            'password' => Hash::make('password123'), 'user_estado' => 1,
        ]);
        // Token con nombre 'auth_token' en vez de 'web-session'
        $token = $user->createToken('auth_token', [$user->passwordAbility()], now()->addHour());

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);
        $user->withAccessToken($token->accessToken);

        $middleware = new EnsureActiveSession;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_token_sin_password_ability_es_rechazado(): void
    {
        $user = User::create([
            'name' => 'SinAbility', 'email' => 'sinability@example.invalid',
            'password' => Hash::make('password123'), 'user_estado' => 1,
        ]);
        // Token 'web-session' pero SIN la ability de password
        $token = $user->createToken('web-session', ['api'], now()->addHour());

        $request = Request::create('/test');
        $request->setUserResolver(fn () => $user);
        $user->withAccessToken($token->accessToken);

        $middleware = new EnsureActiveSession;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(401, $response->getStatusCode());
    }

    public function test_sin_usuario_autenticado_recibe_401(): void
    {
        $request = Request::create('/test');
        $request->setUserResolver(fn () => null);

        $middleware = new EnsureActiveSession;
        $response = $middleware->handle($request, fn () => response()->json(['ok' => true]));

        $this->assertSame(401, $response->getStatusCode());
    }
}
