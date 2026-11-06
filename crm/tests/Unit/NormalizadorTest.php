<?php

namespace Tests\Unit;

use App\Support\Formato;
use App\Support\Normalizador;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class NormalizadorTest extends TestCase
{
    public static function telefonos(): array
    {
        return [
            'internacional con 9' => ['+54 9 343 123-4567', '5493431234567'],
            'con 0 y 15' => ['0343 15 123-4567', '5493431234567'],
            'solo área y número' => ['343 1234567', '5493431234567'],
            'con 54 y 15' => ['54 343 15 1234567', '5493431234567'],
            'ya normalizado' => ['5493431234567', '5493431234567'],
            'Concordia con paréntesis' => ['(0345) 15-412-3456', '5493454123456'],
            'área de 4 dígitos' => ['03446 15 412345', '5493446412345'],
            'Buenos Aires' => ['11 15 1234 5678', '5491112345678'],
            'vacío' => ['', null],
            'texto' => ['no tiene', null],
        ];
    }

    #[DataProvider('telefonos')]
    public function test_normaliza_telefonos(string $entrada, ?string $esperado): void
    {
        $this->assertSame($esperado, Normalizador::telefono($entrada));
    }

    public function test_normaliza_emails(): void
    {
        $this->assertSame('info@empresa.com', Normalizador::email(' Info@Empresa.COM '));
        $this->assertSame('a@b.com', Normalizador::email('mailto:a@b.com'));
        $this->assertSame('a@b.com', Normalizador::email('a@b.com; otro@b.com'));
        $this->assertNull(Normalizador::email('no tiene'));
        $this->assertNull(Normalizador::email(null));
    }

    public function test_lee_montos_en_formato_argentino(): void
    {
        $this->assertSame('350000', Formato::leerMonto('350.000'));
        $this->assertSame('40000.50', Formato::leerMonto('$ 40.000,50'));
        $this->assertSame('40000', Formato::leerMonto('40000'));
        $this->assertSame(40000, Formato::leerMonto(40000));
        $this->assertSame('$ 350.000', Formato::moneda(350000));
    }
}
