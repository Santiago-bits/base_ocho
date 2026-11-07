<?php

namespace App\Services;

use App\Enums\Canal;
use App\Models\Contacto;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Tasa de respuesta = contactos marcados "Respondió" / contactos salientes.
 * Los registros de importación no cuentan.
 */
class Estadisticas
{
    /** @return array{total:array, rubro:array, plantilla:array, canal:array, franja:array} */
    public function tasas(?CarbonImmutable $desde = null, ?CarbonImmutable $hasta = null): array
    {
        $contactos = Contacto::query()
            ->whereIn('canal', Canal::salientes())
            ->when($desde, fn ($q) => $q->where('fecha_hora', '>=', $desde->startOfDay()))
            ->when($hasta, fn ($q) => $q->where('fecha_hora', '<=', $hasta->endOfDay()))
            ->with(['empresa:id,rubro_id', 'empresa.rubro:id,nombre', 'plantilla:id,nombre'])
            ->get(['id', 'empresa_id', 'canal', 'plantilla_id', 'fecha_hora', 'respondio']);

        return [
            'total' => $this->fila($contactos),
            'rubro' => $this->agrupar($contactos, fn (Contacto $c) => $c->empresa?->rubro?->nombre ?? 'Sin rubro'),
            'plantilla' => $this->agrupar($contactos, fn (Contacto $c) => $c->plantilla?->nombre ?? 'Sin plantilla'),
            'canal' => $this->agrupar($contactos, fn (Contacto $c) => $c->canal->etiqueta()),
            'franja' => $this->agrupar($contactos, fn (Contacto $c) => $c->franja(), ['Mañana', 'Tarde', 'Noche']),
        ];
    }

    /** @return array<string,array> */
    private function agrupar(Collection $contactos, callable $clave, ?array $orden = null): array
    {
        $grupos = $contactos->groupBy($clave)->map(fn (Collection $g) => $this->fila($g));

        if ($orden !== null) {
            return collect($orden)->filter(fn ($k) => $grupos->has($k))
                ->mapWithKeys(fn ($k) => [$k => $grupos[$k]])->all();
        }

        return $grupos->sortByDesc('enviados')->all();
    }

    /** @return array{enviados:int, respondieron:int, no:int, pendientes:int, tasa:float} */
    private function fila(Collection $grupo): array
    {
        $enviados = $grupo->count();
        $si = $grupo->where('respondio', 'si')->count();
        $no = $grupo->where('respondio', 'no')->count();

        return [
            'enviados' => $enviados,
            'respondieron' => $si,
            'no' => $no,
            'pendientes' => $enviados - $si - $no,
            'tasa' => $enviados > 0 ? round($si * 100 / $enviados, 1) : 0.0,
        ];
    }
}
