<?php

namespace Tests\Unit;

use App\Services\VentaWriter;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Pruebas unitarias para VentaWriter::normalizar().
 *
 * Este método contiene la lógica pura de negocio que:
 * - Castea campos enteros (cliente, fruta, cantidades).
 * - Convierte precios a centimos y los reformatea con 2 decimales.
 * - Valida que cajas > 0 tengan precio > 0.
 * - Valida que la suma de calidades (A+B+C) = cantiVen.
 * - Valida que subtotal + flete + estiba = totalVen.
 * - Rechaza importes inconsistentes.
 */
class VentaWriterNormalizarTest extends TestCase
{
    private function datosValidos(array $override = []): array
    {
        return array_merge([
            's_codigo'   => 'VEN-001',
            's_idClie'   => '5',
            's_id_fru'   => '1',
            's_cantiVen' => '30',
            's_cantA'    => '10',
            's_cantB'    => '10',
            's_cantC'    => '10',
            's_precioA'  => '5.00',
            's_precioB'  => '3.50',
            's_precioC'  => '2.00',
            's_subtotal' => '105.00', // 10*5 + 10*3.50 + 10*2 = 50+35+20 = 105
            's_flete'    => '10.00',
            's_estiva'   => '5.00',
            's_totalVen' => '120.00', // 105 + 10 + 5 = 120
            's_fecha'    => '2026-10-13',
        ], $override);
    }

    private function normalizar(array $datos = []): array
    {
        return app(VentaWriter::class)->normalizar($this->datosValidos($datos));
    }

    // ─── Tests de normalización exitosa ───

    public function test_normalizar_castea_campos_enteros(): void
    {
        $resultado = $this->normalizar();

        $this->assertSame(5, $resultado['s_idClie']);
        $this->assertSame(1, $resultado['s_id_fru']);
        $this->assertSame(30, $resultado['s_cantiVen']);
        $this->assertSame(10, $resultado['s_cantA']);
        $this->assertSame(10, $resultado['s_cantB']);
        $this->assertSame(10, $resultado['s_cantC']);
    }

    public function test_normalizar_conserva_codigo_original(): void
    {
        $resultado = $this->normalizar(['s_codigo' => 'MI-CODIGO-123']);

        $this->assertSame('MI-CODIGO-123', $resultado['s_codigo']);
    }

    public function test_normalizar_formatea_precios_con_dos_decimales(): void
    {
        $resultado = $this->normalizar();

        $this->assertSame('5.00', $resultado['s_precioA']);
        $this->assertSame('3.50', $resultado['s_precioB']);
        $this->assertSame('2.00', $resultado['s_precioC']);
    }

    public function test_normalizar_calcula_subtotal_y_total_correctamente(): void
    {
        $resultado = $this->normalizar();

        $this->assertSame('105.00', $resultado['s_subtotal']);
        $this->assertSame('10.00', $resultado['s_flete']);
        $this->assertSame('5.00', $resultado['s_estiva']);
        $this->assertSame('120.00', $resultado['s_totalVen']);
    }

    public function test_normalizar_conserva_fecha(): void
    {
        $resultado = $this->normalizar(['s_fecha' => '2026-01-15']);

        $this->assertSame('2026-01-15', $resultado['s_fecha']);
    }

    public function test_normalizar_acepta_calidad_con_cero_cajas_y_precio_cero(): void
    {
        // Si cantC = 0, precioC se pone a 0.00 automáticamente
        $resultado = $this->normalizar([
            's_cantiVen' => '20',
            's_cantA'    => '10',
            's_cantB'    => '10',
            's_cantC'    => '0',
            's_precioC'  => '0',
            's_subtotal' => '85.00', // 10*5 + 10*3.50 = 85
            's_totalVen' => '100.00', // 85 + 10 + 5 = 100
        ]);

        $this->assertSame(0, $resultado['s_cantC']);
        $this->assertSame('0.00', $resultado['s_precioC']);
    }

    // ─── Tests de rechazo (ValidationException) ───

    public function test_normalizar_rechaza_precio_cero_con_cajas_positivas(): void
    {
        $this->expectException(ValidationException::class);

        $this->normalizar([
            's_precioA' => '0',
        ]);
    }

    public function test_normalizar_rechaza_suma_calidades_distinta_al_total(): void
    {
        $this->expectException(ValidationException::class);

        $this->normalizar([
            's_cantiVen' => '50', // A+B+C = 30, no 50
        ]);
    }

    public function test_normalizar_rechaza_total_inconsistente(): void
    {
        $this->expectException(ValidationException::class);

        $this->normalizar([
            's_totalVen' => '999.99', // No coincide con subtotal + flete + estiba
        ]);
    }

    public function test_normalizar_rechaza_subtotal_inconsistente(): void
    {
        $this->expectException(ValidationException::class);

        $this->normalizar([
            's_subtotal' => '200.00', // El real es 105.00
        ]);
    }

    public function test_normalizar_rechaza_cero_cajas_totales(): void
    {
        $this->expectException(ValidationException::class);

        $this->normalizar([
            's_cantiVen' => '0',
            's_cantA'    => '0',
            's_cantB'    => '0',
            's_cantC'    => '0',
            's_subtotal' => '0.00',
            's_totalVen' => '15.00',
        ]);
    }
}
