<?php

namespace Tests\Unit;

use App\Services\GastoWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Support\CreatesActivitySchema;
use Tests\TestCase;

class GastoWriterTest extends TestCase
{
    use CreatesActivitySchema;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('categorias_gasto', function (Blueprint $table) {
            $table->id();
            $table->boolean('activa')->default(true);
        });

        Schema::create('cjchica', function (Blueprint $table) {
            $table->increments('id_caja');
            $table->string('descripcion');
            $table->decimal('mmonto', 10, 2);
            $table->integer('id_user_ins');
            $table->date('fecha_registro');
            $table->integer('categoria_id');
        });

        Schema::create('gasto_confirmaciones', function (Blueprint $table) {
            $table->string('operacion')->primary();
            $table->integer('gasto_id');
            $table->integer('usuario_id');
            $table->string('solicitud_hash');
        });

        $this->createActivitySchema();
    }

    public function test_crear_guarda_gasto_normalizado_y_registra_actividad(): void
    {
        $categoria = DB::table('categorias_gasto')->insertGetId(['activa' => true]);

        $id = app(GastoWriter::class)->crear([
            's_monto' => '12.5',
            's_desc' => '  Compra de fruta  ',
            's_fecha' => '2026-10-13',
            'categoria_id' => $categoria,
            'operacion' => 'gasto-001',
        ], 7);

        $this->assertDatabaseHas('cjchica', [
            'id_caja' => $id,
            'descripcion' => 'Compra de fruta',
            'mmonto' => '12.50',
            'id_user_ins' => 7,
            'fecha_registro' => '2026-10-13',
            'categoria_id' => $categoria,
        ]);
        $this->assertDatabaseHas('registro_actividad', [
            'entidad' => 'gasto',
            'registro_id' => $id,
            'usuario_id' => 7,
            'accion' => 'registrar',
        ]);
    }

    public function test_crear_reintento_identico_devuelve_gasto_existente(): void
    {
        $categoria = DB::table('categorias_gasto')->insertGetId(['activa' => true]);
        $writer = app(GastoWriter::class);
        $datos = [
            's_monto' => '12.5',
            's_desc' => ' Compra de fruta ',
            's_fecha' => '2026-10-13',
            'categoria_id' => $categoria,
            'operacion' => 'gasto-reintento',
        ];

        $id = $writer->crear($datos, 7);
        $reintento = $writer->crear(array_merge($datos, [
            's_monto' => '12.50',
            's_desc' => 'Compra de fruta',
        ]), 7);

        $this->assertSame($id, $reintento);
        $this->assertDatabaseCount('cjchica', 1);
        $this->assertDatabaseCount('registro_actividad', 1);
    }

    public function test_crear_rechaza_operacion_reutilizada_con_otros_datos_o_usuario(): void
    {
        $categoria = DB::table('categorias_gasto')->insertGetId(['activa' => true]);
        $writer = app(GastoWriter::class);
        $datos = [
            's_monto' => '12.5',
            's_desc' => 'Compra de fruta',
            's_fecha' => '2026-10-13',
            'categoria_id' => $categoria,
            'operacion' => 'gasto-conflicto',
        ];
        $writer->crear($datos, 7);

        try {
            $writer->crear(array_merge($datos, ['s_desc' => 'Otro gasto']), 7);
            $this->fail('Debió rechazarse la operación con datos distintos.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operacion', $exception->errors());
        }

        try {
            $writer->crear($datos, 8);
            $this->fail('Debió rechazarse la operación con otro usuario.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operacion', $exception->errors());
        }

        $this->assertDatabaseCount('cjchica', 1);
    }

    public function test_crear_rechaza_categoria_inactiva(): void
    {
        $categoria = DB::table('categorias_gasto')->insertGetId(['activa' => false]);

        try {
            app(GastoWriter::class)->crear([
                's_monto' => '12.5',
                's_desc' => 'Compra de fruta',
                's_fecha' => '2026-10-13',
                'categoria_id' => $categoria,
                'operacion' => 'gasto-categoria-inactiva',
            ], 7);
            $this->fail('Debió rechazarse la categoría inactiva.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('categoria_id', $exception->errors());
        }

        $this->assertDatabaseCount('cjchica', 0);
    }
}
