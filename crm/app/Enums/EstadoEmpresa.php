<?php

namespace App\Enums;

enum EstadoEmpresa: string
{
    case SinContactar = 'sin_contactar';
    case Contactado = 'contactado';
    case Respondio = 'respondio';
    case Interesado = 'interesado';
    case DemoAgendada = 'demo_agendada';
    case PropuestaEnviada = 'propuesta_enviada';
    case Negociando = 'negociando';
    case Cliente = 'cliente';
    case Perdido = 'perdido';
    case NoContactar = 'no_contactar';

    public function etiqueta(): string
    {
        return match ($this) {
            self::SinContactar => 'Sin contactar',
            self::Contactado => 'Contactado',
            self::Respondio => 'Respondió',
            self::Interesado => 'Interesado',
            self::DemoAgendada => 'Demo/Reunión agendada',
            self::PropuestaEnviada => 'Propuesta enviada',
            self::Negociando => 'Negociando',
            self::Cliente => 'Cliente',
            self::Perdido => 'Perdido',
            self::NoContactar => 'No contactar',
        };
    }

    /** Posición en el embudo (No contactar queda al final). */
    public function orden(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    /** @return array<string,string> valor => etiqueta, en orden del embudo */
    public static function opciones(): array
    {
        $out = [];
        foreach (self::cases() as $caso) {
            $out[$caso->value] = $caso->etiqueta();
        }

        return $out;
    }
}
