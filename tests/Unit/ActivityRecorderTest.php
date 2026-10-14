<?php

namespace Tests\Unit;

use App\Services\ActivityRecorder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Tests\Support\CreatesActivitySchema;

/**
 * Pruebas unitarias para ActivityRecorder::registrar().
 *
 * Se verifica que el servicio inserta correctamente registros de actividad
 * en la tabla 'registro_actividad', con los campos serializados a JSON
 * cuando corresponde.
 */
class ActivityRecorderTest extends TestCase
{
    use CreatesActivitySchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createActivitySchema();
    }

    public function test_registrar_inserta_registro_con_todos_los_campos(): void
    {
        $recorder = new ActivityRecorder;

        $recorder->registrar(
            'compra', 42, 'registrar', 1,
            ['campo' => 'viejo'], ['campo' => 'nuevo'], 'Motivo de prueba'
        );

        $this->assertDatabaseCount('registro_actividad', 1);
        $registro = DB::table('registro_actividad')->first();

        $this->assertSame('compra', $registro->entidad);
        $this->assertSame(42, (int) $registro->registro_id);
        $this->assertSame(1, (int) $registro->usuario_id);
        $this->assertSame('registrar', $registro->accion);
        $this->assertSame('Motivo de prueba', $registro->motivo);
        $this->assertSame('{"campo":"viejo"}', $registro->antes);
        $this->assertSame('{"campo":"nuevo"}', $registro->despues);
    }

    public function test_registrar_acepta_antes_y_despues_null(): void
    {
        $recorder = new ActivityRecorder;

        $recorder->registrar('venta', 10, 'cancelar', 2);

        $registro = DB::table('registro_actividad')->first();

        $this->assertNull($registro->antes);
        $this->assertNull($registro->despues);
        $this->assertNull($registro->motivo);
    }

    public function test_registrar_solo_antes_sin_despues(): void
    {
        $recorder = new ActivityRecorder;

        $recorder->registrar('perdida', 5, 'eliminar', 3, ['estado' => 1]);

        $registro = DB::table('registro_actividad')->first();

        $this->assertSame('{"estado":1}', $registro->antes);
        $this->assertNull($registro->despues);
    }

    public function test_registrar_multiples_inserta_multiples_registros(): void
    {
        $recorder = new ActivityRecorder;

        $recorder->registrar('compra', 1, 'registrar', 1);
        $recorder->registrar('compra', 1, 'actualizar', 2);
        $recorder->registrar('compra', 1, 'cancelar', 1);

        $this->assertDatabaseCount('registro_actividad', 3);
    }

    public function test_registrar_serializa_arrays_anidados(): void
    {
        $recorder = new ActivityRecorder;

        $datos = ['detalles' => [['fruta' => 'Plátano', 'cantidad' => 10]]];
        $recorder->registrar('venta', 99, 'registrar', 1, null, $datos);

        $registro = DB::table('registro_actividad')->first();
        $despues = json_decode($registro->despues, true);

        $this->assertSame('Plátano', $despues['detalles'][0]['fruta']);
        $this->assertSame(10, $despues['detalles'][0]['cantidad']);
    }
}
