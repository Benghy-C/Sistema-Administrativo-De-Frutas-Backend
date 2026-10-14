<?php

namespace Tests\Unit;

use App\Services\ReportDetails;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias para ReportDetails.
 *
 * Se prueban los métodos que contienen lógica pura (sin BD):
 * - columnas(): devuelve la estructura de columnas según el tipo de reporte.
 * - texto(): genera la representación en texto plano de los detalles de un registro.
 */
class ReportDetailsTest extends TestCase
{
    private ReportDetails $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReportDetails;
    }

    // ─── columnas() ───

    public function test_columnas_compras_devuelve_cinco_columnas(): void
    {
        $columnas = $this->service->columnas('compras');

        $this->assertCount(5, $columnas);
        $this->assertSame('fruta', $columnas[0]['clave']);
        $this->assertSame('calidad', $columnas[1]['clave']);
        $this->assertSame('cajas', $columnas[2]['clave']);
        $this->assertSame('precio', $columnas[3]['clave']);
        $this->assertSame('subtotal', $columnas[4]['clave']);
    }

    public function test_columnas_envios_devuelve_cinco_columnas(): void
    {
        $columnas = $this->service->columnas('envios');

        $this->assertCount(5, $columnas);
        $this->assertSame('lote', $columnas[0]['clave']);
        $this->assertSame('calidad', $columnas[1]['clave']);
        $this->assertSame('cajas', $columnas[2]['clave']);
        $this->assertSame('devueltas', $columnas[3]['clave']);
        $this->assertSame('saldo', $columnas[4]['clave']);
    }

    public function test_columnas_tipo_desconocido_devuelve_array_vacio(): void
    {
        $this->assertSame([], $this->service->columnas('otro'));
    }

    public function test_columnas_cada_elemento_tiene_clave_titulo_tipo(): void
    {
        foreach (['compras', 'envios'] as $tipo) {
            foreach ($this->service->columnas($tipo) as $columna) {
                $this->assertArrayHasKey('clave', $columna);
                $this->assertArrayHasKey('titulo', $columna);
                $this->assertArrayHasKey('tipo', $columna);
            }
        }
    }

    public function test_columnas_tipos_validos(): void
    {
        $tiposValidos = ['texto', 'numero', 'dinero'];

        foreach (['compras', 'envios'] as $tipo) {
            foreach ($this->service->columnas($tipo) as $columna) {
                $this->assertContains($columna['tipo'], $tiposValidos,
                    "Tipo '{$columna['tipo']}' no es válido en reporte '{$tipo}'");
            }
        }
    }

    // ─── texto() ───

    public function test_texto_sin_detalle_compras_devuelve_sin_registrar(): void
    {
        $fila = (object) ['detalle' => []];

        $this->assertSame('Sin registrar', $this->service->texto('compras', $fila));
    }

    public function test_texto_sin_detalle_envios_devuelve_origen_no_registrado(): void
    {
        $fila = (object) ['detalle' => []];

        $this->assertSame('Origen no registrado', $this->service->texto('envios', $fila));
    }

    public function test_texto_sin_propiedad_detalle_devuelve_fallback(): void
    {
        $fila = (object) [];

        $this->assertSame('Sin registrar', $this->service->texto('compras', $fila));
    }

    public function test_texto_compras_incluye_fruta_calidad_cajas_precio(): void
    {
        $fila = (object) ['detalle' => [
            (object) ['fruta' => 'Plátano', 'calidad' => 'A', 'cajas' => 10, 'precio' => '5.00', 'subtotal' => 50.0],
        ]];

        $texto = $this->service->texto('compras', $fila);

        $this->assertStringContainsString('Plátano', $texto);
        $this->assertStringContainsString('Calidad A', $texto);
        $this->assertStringContainsString('10 cajas', $texto);
        $this->assertStringContainsString('S/ 5.00', $texto);
    }

    public function test_texto_compras_con_precio_null_dice_sin_registrar(): void
    {
        $fila = (object) ['detalle' => [
            (object) ['fruta' => 'Plátano', 'calidad' => 'B', 'cajas' => 5, 'precio' => null, 'subtotal' => null],
        ]];

        $texto = $this->service->texto('compras', $fila);

        $this->assertStringContainsString('Sin registrar', $texto);
    }

    public function test_texto_envios_incluye_lote_calidad_cajas_devueltas_saldo(): void
    {
        $fila = (object) ['detalle' => [
            (object) ['lote' => 'LOT-0001', 'calidad' => 'A', 'cajas' => 20, 'devueltas' => 5, 'saldo' => 15],
        ]];

        $texto = $this->service->texto('envios', $fila);

        $this->assertStringContainsString('LOT-0001', $texto);
        $this->assertStringContainsString('Calidad A', $texto);
        $this->assertStringContainsString('20 cajas', $texto);
        $this->assertStringContainsString('Devueltas: 5', $texto);
        $this->assertStringContainsString('Fuera de cámara: 15', $texto);
    }

    public function test_texto_multiples_detalles_se_separan_con_pipe(): void
    {
        $fila = (object) ['detalle' => [
            (object) ['lote' => 'LOT-0001', 'calidad' => 'A', 'cajas' => 10, 'devueltas' => 0, 'saldo' => 10],
            (object) ['lote' => 'LOT-0002', 'calidad' => 'B', 'cajas' => 5, 'devueltas' => 2, 'saldo' => 3],
        ]];

        $texto = $this->service->texto('envios', $fila);

        $this->assertStringContainsString(' | ', $texto);
        $this->assertStringContainsString('LOT-0001', $texto);
        $this->assertStringContainsString('LOT-0002', $texto);
    }
}
