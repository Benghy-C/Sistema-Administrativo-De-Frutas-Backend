<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\Support\CreatesActivitySchema;
use Tests\TestCase;

class UserRegistrationTest extends TestCase
{
    use CreatesActivitySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createActivitySchema();

        foreach ([
            '0001_01_01_000000_create_users_table.php',
            '2026_07_24_001351_create_personal_access_tokens_table.php',
            '2026_07_27_160155_create_permission_tables.php',
        ] as $file) {
            (require database_path('migrations/'.$file))->up();
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('identificador')->nullable();
            $table->integer('user_estado')->default(1);
        });

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@example.invalid',
            'password' => 'password',
        ]);
        $admin->assignRole(Role::create(['name' => 'admin', 'guard_name' => 'web']));

        $this->withToken($admin->createToken(
            'web-session',
            [$admin->passwordAbility()],
            now()->addHour()
        )->plainTextToken);
    }

    public function test_registro_crea_usuario_activo_con_contrasena_protegida_y_rol(): void
    {
        $role = Role::create(['name' => 'operador', 'guard_name' => 'web']);

        $response = $this->postJson('/api/auth/make/user', [
            's_nombre' => '  Ana Pérez  ',
            's_email' => 'ana@example.invalid',
            's_password' => 'Clave-segura-123',
            's_password_confirmation' => 'Clave-segura-123',
            's_documento' => '87654321',
            's_id_rol' => $role->id,
        ]);

        $response->assertOk()
            ->assertJsonPath('0.0.error', 0);

        $user = User::where('email', 'ana@example.invalid')->firstOrFail();

        $this->assertSame('Ana Pérez', $user->name);
        $this->assertSame(1, (int) $user->user_estado);
        $this->assertSame('87654321', $user->identificador);
        $this->assertTrue(Hash::check('Clave-segura-123', $user->password));
        $this->assertNotSame('Clave-segura-123', $user->password);
        $this->assertTrue($user->hasRole('operador'));
    }

    public function test_registro_rechaza_correo_duplicado_y_no_crea_usuario(): void
    {
        $role = Role::create(['name' => 'operador', 'guard_name' => 'web']);
        User::create([
            'name' => 'Existente',
            'email' => 'ana@example.invalid',
            'password' => 'hash-existente',
        ]);

        $this->postJson('/api/auth/make/user', [
            's_nombre' => 'Ana Pérez',
            's_email' => 'ANA@example.invalid',
            's_password' => 'Clave-segura-123',
            's_password_confirmation' => 'Clave-segura-123',
            's_documento' => '87654321',
            's_id_rol' => $role->id,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('s_email');

        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseMissing('users', ['identificador' => '87654321']);
    }
}
