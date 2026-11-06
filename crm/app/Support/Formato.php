<?php

namespace App\Support;

class Formato
{
    /** 350000 → "$ 350.000" (sin decimales si son cero). */
    public static function moneda(float|int|string|null $monto): string
    {
        return '$ '.self::numero($monto);
    }

    /** 350000 → "350.000"; 40000.5 → "40.000,50". */
    public static function numero(float|int|string|null $monto): string
    {
        $monto = (float) $monto;
        $decimales = floor($monto) == $monto ? 0 : 2;

        return number_format($monto, $decimales, ',', '.');
    }

    /**
     * Acepta montos como se escriben acá: "350.000", "$ 40.000,50", "40000".
     * Devuelve un string numérico ("350000", "40000.50") o el valor original si no se entiende.
     */
    public static function leerMonto(mixed $valor): mixed
    {
        if (! is_string($valor)) {
            return $valor;
        }
        $limpio = str_replace(['$', ' ', "\u{00A0}"], '', trim($valor));

        if (str_contains($limpio, ',')) {
            // Formato argentino: punto de miles, coma decimal.
            $limpio = str_replace(',', '.', str_replace('.', '', $limpio));
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $limpio)) {
            $limpio = str_replace('.', '', $limpio);
        }

        return is_numeric($limpio) ? $limpio : $valor;
    }

    public static function porcentaje(float $valor): string
    {
        return number_format($valor, $valor == floor($valor) ? 0 : 1, ',', '.').' %';
    }
}
