<?php

namespace Tests\Unit;

use App\Services\ContactWriter;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\ApiSecurityTest;

/**
 * Pruebas unitarias para ContactWriter.
 *
 * Se prueba la lógica del servicio guardar() y estado() que maneja
 * la creación/actualización de contactos (clientes y proveedores)
 * y el cambio de estado (activo/inactivo).
 */
class ContactWriterTest extends ApiSecurityTest
{
    protected function setUp(): void
    {
        parent::setUp();

        // Crear tabla clientes para testing
        \Illuminate\Support\Facades\Schema::create('clientes', function ($table) {
            $table->id();
            $table->string('nombres');
            $table->string('cel')->nullable();
            $table->string('telefono')->nullable();
            $table->string('correo')->default('');
            $table->text('descripcion')->default('');
            $table->integer('estado')->default(1);
            $table->timestamps();
        });

        // Crear tabla proveedores para testing
        \Illuminate\Support\Facades\Schema::create('proveedores', function ($table) {
            $table->id();
            $table->string('nombre');
            $table->string('cel')->nullable();
            $table->string('telefono')->nullable();
            $table->string('correo')->default('');
            $table->text('descripcion')->default('');
            $table->integer('estado')->default(1);
            $table->timestamp('fecha_inscripcion')->nullable();
            $table->timestamps();
        });
    }

    // ─── guardar(): Creación ───

    public function test_guardar_crea_cliente_nuevo(): void
    {
        $writer = app(ContactWriter::class);
        $datos = [
            's_nombre' => 'Juan Pérez',
            's_cel' => '987654321',
            's_telefono' => null,
            's_correo' => 'juan@email.com',
            's_descripcion' => 'Cliente frecuente',
        ];

        $id = $writer->guardar('clientes', $datos, 1);

        $this->assertGreaterThan(0, $id);
        $this->assertDatabaseHas('clientes', [
            'id' => $id,
            'nombres' => 'Juan Pérez',
            'cel' => '987654321',
            'correo' => 'juan@email.com',
            'estado' => 1,
        ]);
    }

    public function test_guardar_crea_proveedor_nuevo_con_fecha_inscripcion(): void
    {
        $writer = app(ContactWriter::class);
        $datos = [
            's_nombre' => 'Frutería del Norte',
            's_cel' => '912345678',
            's_correo' => '',
        ];

        $id = $writer->guardar('proveedores', $datos, 1);

        $proveedor = DB::table('proveedores')->where('id', $id)->first();
        $this->assertSame('Frutería del Norte', $proveedor->nombre);
        $this->assertNotNull($proveedor->fecha_inscripcion);
        $this->assertSame(1, (int) $proveedor->estado);
    }

    public function test_guardar_actualiza_cliente_existente(): void
    {
        $writer = app(ContactWriter::class);

        $id = $writer->guardar('clientes', [
            's_nombre' => 'Original',
            's_cel' => '111111111',
            's_correo' => '',
        ], 1);

        $writer->guardar('clientes', [
            's_id' => $id,
            's_nombre' => 'Actualizado',
            's_cel' => '222222222',
            's_correo' => 'nuevo@email.com',
        ], 1);

        $this->assertDatabaseHas('clientes', [
            'id' => $id,
            'nombres' => 'Actualizado',
            'cel' => '222222222',
            'correo' => 'nuevo@email.com',
        ]);
    }

    public function test_guardar_contacto_inexistente_lanza_404(): void
    {
        $writer = app(ContactWriter::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $writer->guardar('clientes', [
            's_id' => 9999,
            's_nombre' => 'No existe',
            's_cel' => '111111111',
        ], 1);
    }

    public function test_guardar_registra_actividad(): void
    {
        $writer = app(ContactWriter::class);

        $id = $writer->guardar('clientes', [
            's_nombre' => 'Con Registro',
            's_cel' => '111111111',
        ], 42);

        $this->assertDatabaseHas('registro_actividad', [
            'entidad' => 'clientes',
            'registro_id' => $id,
            'usuario_id' => 42,
            'accion' => 'registrar',
        ]);
    }

    // ─── estado() ───

    public function test_estado_cambia_activo_a_inactivo(): void
    {
        $id = DB::table('clientes')->insertGetId([
            'nombres' => 'Estado', 'cel' => '111111111', 'estado' => 1,
        ]);

        $writer = app(ContactWriter::class);
        $writer->estado('clientes', $id, 1);

        $this->assertDatabaseHas('clientes', ['id' => $id, 'estado' => 0]);
    }

    public function test_estado_cambia_inactivo_a_activo(): void
    {
        $id = DB::table('clientes')->insertGetId([
            'nombres' => 'Inactivo', 'cel' => '111111111', 'estado' => 0,
        ]);

        $writer = app(ContactWriter::class);
        $writer->estado('clientes', $id, 1);

        $this->assertDatabaseHas('clientes', ['id' => $id, 'estado' => 1]);
    }

    public function test_estado_registra_actividad_con_antes_y_despues(): void
    {
        $id = DB::table('clientes')->insertGetId([
            'nombres' => 'Actividad', 'cel' => '111111111', 'estado' => 1,
        ]);

        $writer = app(ContactWriter::class);
        $writer->estado('clientes', $id, 7);

        $registro = DB::table('registro_actividad')
            ->where('entidad', 'clientes')
            ->where('accion', 'cambiar_estado')
            ->first();

        $this->assertNotNull($registro);
        $this->assertSame(7, (int) $registro->usuario_id);

        $antes = json_decode($registro->antes, true);
        $despues = json_decode($registro->despues, true);
        $this->assertSame(1, (int) $antes['estado']);
        $this->assertSame(0, (int) $despues['estado']);
    }

    public function test_estado_contacto_inexistente_lanza_404(): void
    {
        $writer = app(ContactWriter::class);

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);

        $writer->estado('clientes', 9999, 1);
    }
}
