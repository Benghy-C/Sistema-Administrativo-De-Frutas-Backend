<?php

namespace Tests\Unit;

use App\Services\ShipmentNumber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Pruebas unitarias para ShipmentNumber::format().
 *
 * Se verifica que el método genera correctamente el código de envío
 * con el prefijo "ENV-" y relleno de ceros hasta 6 dígitos.
 */
class ShipmentNumberTest extends TestCase
{
    #[DataProvider('idsConFormato')]
    public function test_format_genera_codigo_con_ceros_a_la_izquierda(int $id, string $esperado): void
    {
        $this->assertSame($esperado, ShipmentNumber::format($id));
    }

    public static function idsConFormato(): array
    {
        return [
            'un dígito'           => [1,       'ENV-000001'],
            'dos dígitos'         => [42,      'ENV-000042'],
            'tres dígitos'        => [123,     'ENV-000123'],
            'exactamente seis'    => [999999,  'ENV-999999'],
            'más de seis dígitos' => [1000000, 'ENV-1000000'],
            'id grande'           => [1234567, 'ENV-1234567'],
        ];
    }

    public function test_format_siempre_empieza_con_prefijo_env(): void
    {
        $this->assertStringStartsWith('ENV-', ShipmentNumber::format(1));
        $this->assertStringStartsWith('ENV-', ShipmentNumber::format(9999999));
    }

    public function test_format_devuelve_string(): void
    {
        $this->assertIsString(ShipmentNumber::format(5));
    }

    public function test_format_longitud_minima_es_diez_caracteres(): void
    {
        // "ENV-" (4) + 6 dígitos = 10 caracteres mínimo
        $this->assertGreaterThanOrEqual(10, strlen(ShipmentNumber::format(1)));
    }
}
