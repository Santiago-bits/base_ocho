<?php

namespace App\Support;

/**
 * Normaliza teléfonos argentinos y emails para comparar duplicados.
 *
 * Todos estos quedan como 5493431234567:
 *   +54 9 343 123-4567 · 0343 15 123-4567 · 343 1234567 · 54 343 15 1234567
 *
 * Los números de 10 dígitos nacionales se guardan con prefijo 549 (formato de
 * WhatsApp) aunque sean fijos: sirve como clave de duplicados y para wa.me.
 */
class Normalizador
{
    public static function telefono(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $digitos = preg_replace('/\D+/', '', $valor);
        if ($digitos === '' || strlen($digitos) < 6) {
            return null;
        }

        if (str_starts_with($digitos, '00')) {
            $digitos = substr($digitos, 2);
        }

        $tieneCodigoPais = false;
        if (str_starts_with($digitos, '54') && strlen($digitos) >= 12) {
            $tieneCodigoPais = true;
            $digitos = substr($digitos, 2);
            if (str_starts_with($digitos, '9') && strlen($digitos) === 11) {
                $digitos = substr($digitos, 1);
            }
        }

        // Prefijo de larga distancia nacional.
        if (! $tieneCodigoPais && str_starts_with($digitos, '0')) {
            $digitos = ltrim($digitos, '0');
        }

        // Quitar el "15" de celular después del código de área (4, 3 o 2 dígitos).
        if (strlen($digitos) === 12) {
            foreach ([4, 3, 2] as $largoArea) {
                if (substr($digitos, $largoArea, 2) === '15') {
                    $digitos = substr($digitos, 0, $largoArea).substr($digitos, $largoArea + 2);
                    break;
                }
            }
        }

        if (strlen($digitos) === 10) {
            return '549'.$digitos;
        }

        // Número extranjero o incompleto: lo dejamos con sus dígitos tal cual.
        return $tieneCodigoPais ? '54'.$digitos : $digitos;
    }

    public static function email(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $email = mb_strtolower(trim($valor));
        // Algunos Excels traen "mailto:" o varios emails juntos: nos quedamos con el primero.
        $email = preg_replace('/^mailto:/', '', $email);
        $email = preg_split('/[\s,;\/]+/', $email)[0] ?? '';

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /** Solo dígitos, para buscar por teléfono en el listado. */
    public static function digitos(?string $valor): string
    {
        return preg_replace('/\D+/', '', (string) $valor);
    }
}
