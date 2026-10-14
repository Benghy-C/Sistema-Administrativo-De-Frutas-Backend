<?php

namespace Tests\Unit;

use App\Services\CompraWriter;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompraWriterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->integer('estado');
        });

        Schema::create('fruta', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->integer('estado');
        });

        Schema::create('compra', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_compra')->unique();
            $table->integer('id_proveedor');
            $table->date('fecha');
            $table->text('observacion');
            $table->decimal('precio_total', 12, 2);
            $table->decimal('cost_adici', 12, 2);
            $table->integer('estado');
            $table->timestamp('updated_at')->nullable();
            $table->integer('usuid_alt');
        });

        Schema::create('compra_fruta', function (Blueprint $table) {
            $table->id();
            $table->integer('id_pedido');
            $table->integer('id_fruta');
            $table->integer('cantidad');
            $table->decimal('precio_sub_total', 12, 2);
            $table->integer('canta');
            $table->integer('cantb');
            $table->integer('cantc');
            $table->decimal('precioa', 12, 2);
            $table->decimal('preciob', 12, 2);
            $table->decimal('precioc', 12, 2);
            $table->integer('estado');
        });

        Schema::create('camara_refigeracion', function (Blueprint $table) {
            $table->increments('id_camara');
            $table->integer('id_compra');
            $table->integer('id_fruta');
            $table->timestamp('fecha_registro');
            $table->integer('cantidad_camara');
            $table->integer('canta');
            $table->integer('cantb');
            $table->integer('cantc');
        });
    }

    public function test_crear_guarda_compra_detalle_y_lote(): void
    {
        $datos = $this->datosCompra();
        $id = app(CompraWriter::class)->crear($datos, 7)[0]['numid'];

        $this->assertDatabaseHas('compra', [
            'id' => $id,
            'codigo_compra' => 'COMP-001',
            'id_proveedor' => $datos['s_id_provedor'],
            'precio_total' => '20.00',
            'cost_adici' => '2.00',
            'estado' => 1,
            'usuid_alt' => 7,
        ]);
        $this->assertDatabaseHas('compra_fruta', [
            'id_pedido' => $id,
            'id_fruta' => $datos['s_id_fru'],
            'cantidad' => 3,
            'precio_sub_total' => '18.00',
            'canta' => 1,
            'cantb' => 1,
            'cantc' => 1,
            'estado' => 1,
        ]);
        $this->assertDatabaseHas('camara_refigeracion', [
            'id_compra' => $id,
            'id_fruta' => $datos['s_id_fru'],
            'cantidad_camara' => 3,
            'canta' => 1,
            'cantb' => 1,
            'cantc' => 1,
        ]);
    }

    public function test_crear_reintento_identico_no_duplica_compra(): void
    {
        $writer = app(CompraWriter::class);
        $datos = $this->datosCompra();

        $primera = $writer->crear($datos, 7);
        $reintento = $writer->crear($datos, 7);

        $this->assertSame($primera[0]['numid'], $reintento[0]['numid']);
        $this->assertTrue($reintento[0]['reintento']);
        $this->assertDatabaseCount('compra', 1);
        $this->assertDatabaseCount('compra_fruta', 1);
        $this->assertDatabaseCount('camara_refigeracion', 1);
    }

    public function test_crear_rechaza_reutilizar_codigo_con_datos_distintos(): void
    {
        $writer = app(CompraWriter::class);
        $datos = $this->datosCompra();
        $writer->crear($datos, 7);

        try {
            $writer->crear(array_merge($datos, ['s_observacion' => 'Otra compra']), 7);
            $this->fail('Debió rechazarse el código asociado a otros datos.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('compra', $exception->errors());
        }

        $this->assertDatabaseCount('compra', 1);
    }

    public function test_crear_rechaza_proveedor_inactivo_sin_guardar_compra(): void
    {
        $datos = $this->datosCompra(proveedorActivo: false);

        try {
            app(CompraWriter::class)->crear($datos, 7);
            $this->fail('Debió rechazarse el proveedor inactivo.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('compra', $exception->errors());
        }

        $this->assertDatabaseCount('compra', 0);
        $this->assertDatabaseCount('compra_fruta', 0);
        $this->assertDatabaseCount('camara_refigeracion', 0);
    }

    private function datosCompra(bool $proveedorActivo = true): array
    {
        $proveedor = DB::table('proveedores')->insertGetId([
            'estado' => $proveedorActivo ? 1 : 0,
        ]);
        $fruta = DB::table('fruta')->insertGetId([
            'descripcion' => 'Plátano',
            'estado' => 1,
        ]);

        return [
            's_codigo' => 'COMP-001',
            's_id_provedor' => $proveedor,
            's_fecha' => '2026-10-12',
            's_observacion' => 'Compra de prueba',
            's_total' => 20,
            's_cost_adi' => 2,
            's_id_fru' => $fruta,
            's_cantidad' => 3,
            's_cantA' => 1,
            's_cantB' => 1,
            's_cantC' => 1,
            's_precioA' => 5,
            's_precioB' => 6,
            's_precioC' => 7,
            's_subtotal' => 18,
        ];
    }
}
