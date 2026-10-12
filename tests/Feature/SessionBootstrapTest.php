<?php

namespace Tests\Feature;

use App\Services\OrderReader;
use Spatie\Permission\Models\Permission;

class SessionBootstrapTest extends ApiSecurityTest
{
    public function test_orders_are_included_only_with_read_access(): void
    {
        $user = \App\Models\User::create(['name' => 'Carga', 'email' => 'bootstrap@example.invalid', 'password' => 'password']);
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        $this->mock(OrderReader::class)->shouldReceive('forDate')->never();
        $this->withToken($token)->getJson('/api/user?include=orders&date=2026-10-06')
            ->assertOk()->assertJsonMissingPath('initial_orders')->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_orders_use_the_requested_date_and_do_not_leak_passwords(): void
    {
        $user = \App\Models\User::create(['name' => 'Carga', 'email' => 'bootstrap@example.invalid', 'password' => 'password']);
        $user->givePermissionTo(Permission::create(['name' => 'read-compra', 'guard_name' => 'web']));
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        $this->mock(OrderReader::class)->shouldReceive('forDate')->once()
            ->with('2026-10-06')
            ->andReturn([(object) ['id' => 23]]);
        $response = $this->withToken($token)->getJson('/api/user?include=orders&date=2026-10-06');
        $response->assertOk()->assertJsonPath('initial_orders.rows.0.id', 23)
            ->assertJsonPath('initial_orders.date', '2026-10-06')->assertJsonMissingPath('password');
    }

    public function test_orders_without_a_date_include_the_complete_list(): void
    {
        $user = \App\Models\User::create(['name' => 'Carga', 'email' => 'allorders@example.invalid', 'password' => 'password']);
        $user->givePermissionTo(Permission::create(['name' => 'read-compra', 'guard_name' => 'web']));
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        $this->mock(OrderReader::class)->shouldReceive('forDate')->once()->with('')
            ->andReturn([(object) ['id' => 1], (object) ['id' => 23]]);
        $this->withToken($token)->getJson('/api/user?include=orders')->assertOk()
            ->assertJsonPath('initial_orders.date', '')->assertJsonCount(2, 'initial_orders.rows');
    }

    public function test_initial_orders_validate_the_date(): void
    {
        $user = \App\Models\User::create(['name' => 'Carga', 'email' => 'bootstrap@example.invalid', 'password' => 'password']);
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        $this->withToken($token)->getJson('/api/user?include=orders&date=invalid')->assertUnprocessable();

    }
}
