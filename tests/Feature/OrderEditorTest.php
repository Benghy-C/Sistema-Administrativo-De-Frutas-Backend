<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\OrderEditorReader;
use Spatie\Permission\Models\Permission;

class OrderEditorTest extends ApiSecurityTest
{
    public function test_read_permission_cannot_open_the_editor(): void
    {
        $user = User::create(['name' => 'Prueba', 'email' => 'editor@example.invalid', 'password' => 'password']);
        $user->givePermissionTo(Permission::create(['name' => 'read-compra', 'guard_name' => 'web']));
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        $this->mock(OrderEditorReader::class)->shouldReceive('read')->never();
        $this->withToken($token)->getJson('/api/compra/23/edicion')->assertForbidden();
        $this->withToken($token)->getJson('/api/user?include=editor&id=23')->assertOk()->assertJsonMissingPath('initial_editor');
    }

    public function test_update_permission_receives_editor_data_and_unknown_order_is_404(): void
    {
        $user = User::create(['name' => 'Prueba', 'email' => 'editor@example.invalid', 'password' => 'password']);
        $user->givePermissionTo(Permission::create(['name' => 'update-compra', 'guard_name' => 'web']));
        $token = $user->createToken('web-session', [$user->passwordAbility()], now()->addHour())->plainTextToken;
        $reader = $this->mock(OrderEditorReader::class);
        $reader->shouldReceive('read')->twice()->with(23)->andReturn(['order' => [['id' => 23]], 'items' => [], 'providers' => [], 'fruits' => []]);
        $reader->shouldReceive('read')->once()->with(24)->andReturn(['order' => [], 'items' => [], 'providers' => [], 'fruits' => []]);
        $this->withToken($token)->getJson('/api/compra/23/edicion')->assertOk()->assertJsonPath('order.0.id', 23);
        $this->withToken($token)->getJson('/api/user?include=editor&id=23')->assertOk()->assertJsonPath('initial_editor.data.order.0.id', 23);
        $this->withToken($token)->getJson('/api/compra/24/edicion')->assertNotFound()->assertJsonMissingPath('providers');
        $this->withToken($token)->getJson('/api/compra/0/edicion')->assertUnprocessable();
    }
}
