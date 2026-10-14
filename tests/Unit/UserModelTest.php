<?php

namespace Tests\Unit;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\Feature\ApiSecurityTest;

/**
 * Pruebas unitarias para el modelo User.
 *
 * Se prueban los métodos de negocio:
 * - passwordAbility(): genera un HMAC determinista basado en el hash de la contraseña.
 * - apiAccess(): devuelve roles y permisos del usuario consultando la BD.
 */
class UserModelTest extends ApiSecurityTest
{
    public function test_password_ability_empieza_con_prefijo_password(): void
    {
        $user = User::create([
            'name' => 'Test', 'email' => 'test@example.invalid',
            'password' => Hash::make('mi-password'),
        ]);

        $ability = $user->passwordAbility();

        $this->assertStringStartsWith('password:', $ability);
    }

    public function test_password_ability_es_determinista_para_misma_password(): void
    {
        $user = User::create([
            'name' => 'Test', 'email' => 'determinista@example.invalid',
            'password' => Hash::make('mi-password'),
        ]);

        $primero = $user->passwordAbility();
        $segundo = $user->passwordAbility();

        $this->assertSame($primero, $segundo);
    }

    public function test_password_ability_cambia_al_cambiar_password(): void
    {
        $user = User::create([
            'name' => 'Test', 'email' => 'cambio@example.invalid',
            'password' => Hash::make('password-vieja'),
        ]);

        $antes = $user->passwordAbility();
        $user->password = Hash::make('password-nueva');
        $despues = $user->passwordAbility();

        $this->assertNotSame($antes, $despues);
    }

    public function test_api_access_usuario_sin_roles_devuelve_arrays_vacios(): void
    {
        $user = User::create([
            'name' => 'Sin Rol', 'email' => 'sinrol@example.invalid',
            'password' => 'password',
        ]);

        $access = $user->apiAccess();

        $this->assertArrayHasKey('roles', $access);
        $this->assertArrayHasKey('permissions', $access);
        $this->assertEmpty($access['roles']);
        $this->assertEmpty($access['permissions']);
    }

    public function test_api_access_incluye_roles_asignados(): void
    {
        $user = User::create([
            'name' => 'Admin', 'email' => 'admin@example.invalid',
            'password' => 'password',
        ]);
        $role = \Spatie\Permission\Models\Role::create(['name' => 'operador', 'guard_name' => 'web']);
        $user->assignRole($role);

        $access = $user->apiAccess();

        $this->assertContains('operador', $access['roles']);
    }

    public function test_api_access_incluye_permisos_del_rol(): void
    {
        $user = User::create([
            'name' => 'ConPermisos', 'email' => 'permisos@example.invalid',
            'password' => 'password',
        ]);
        $role = \Spatie\Permission\Models\Role::create(['name' => 'lector', 'guard_name' => 'web']);
        $permission = \Spatie\Permission\Models\Permission::create(['name' => 'read-compra', 'guard_name' => 'web']);
        $role->givePermissionTo($permission);
        $user->assignRole($role);

        $access = $user->apiAccess();

        $this->assertContains('read-compra', $access['permissions']);
    }

    public function test_api_access_no_duplica_permisos(): void
    {
        $user = User::create([
            'name' => 'Doble', 'email' => 'doble@example.invalid',
            'password' => 'password',
        ]);
        $perm = \Spatie\Permission\Models\Permission::create(['name' => 'read-venta', 'guard_name' => 'web']);
        $role = \Spatie\Permission\Models\Role::create(['name' => 'vendedor', 'guard_name' => 'web']);
        $role->givePermissionTo($perm);
        $user->assignRole($role);
        // Asignar el mismo permiso directamente
        $user->givePermissionTo($perm);

        $access = $user->apiAccess();

        // read-venta debe aparecer una sola vez
        $count = array_count_values($access['permissions'])['read-venta'] ?? 0;
        $this->assertSame(1, $count);
    }

    public function test_campos_password_y_remember_token_ocultos(): void
    {
        $user = User::create([
            'name' => 'Oculto', 'email' => 'oculto@example.invalid',
            'password' => 'password',
        ]);

        $array = $user->toArray();

        $this->assertArrayNotHasKey('password', $array);
        $this->assertArrayNotHasKey('remember_token', $array);
    }
}
