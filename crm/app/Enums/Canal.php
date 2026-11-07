<?php

namespace App\Enums;

enum Canal: string
{
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Llamada = 'llamada';
    case Visita = 'visita';
    /** Registro creado al importar un Excel. No cuenta en las estadísticas. */
    case Importado = 'importado';

    public function etiqueta(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::Email => 'Email',
            self::Llamada => 'Llamada',
            self::Visita => 'Visita',
            self::Importado => 'Importado',
        };
    }

    /** Canales que se pueden elegir al registrar un contacto. @return array<string,string> */
    public static function opciones(): array
    {
        $out = [];
        foreach ([self::WhatsApp, self::Email, self::Llamada, self::Visita] as $caso) {
            $out[$caso->value] = $caso->etiqueta();
        }

        return $out;
    }

    /** @return list<string> */
    public static function salientes(): array
    {
        return array_keys(self::opciones());
    }
}
