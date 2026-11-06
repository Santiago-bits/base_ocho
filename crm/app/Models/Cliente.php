<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    protected $table = 'clientes';

    protected $fillable = ['empresa_id', 'servicio', 'fecha_alta', 'monto_inicial', 'abono_mensual', 'activo', 'notas'];

    protected function casts(): array
    {
        return [
            'fecha_alta' => 'date',
            'monto_inicial' => 'decimal:2',
            'abono_mensual' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class)->orderByDesc('fecha_pago')->orderByDesc('id');
    }

    /**
     * Abonos mensuales desde el alta hasta el próximo vencimiento.
     *
     * El monto inicial cubre el primer mes; el primer abono vence un mes después
     * del alta, siempre el mismo día del mes (o el último día si ese mes es más corto).
     *
     * @return list<array{periodo:string, vence:CarbonImmutable, pagado:bool, estado:string}>
     */
    public function cuotas(?CarbonImmutable $hoy = null, int $diasAviso = 5): array
    {
        $hoy ??= CarbonImmutable::today();
        $alta = CarbonImmutable::parse($this->fecha_alta);
        $pagados = $this->relationLoaded('pagos')
            ? $this->pagos->pluck('periodo')->all()
            : $this->pagos()->pluck('periodo')->all();
        $pagados = array_flip($pagados);

        $cuotas = [];
        for ($n = 1; $n <= 240; $n++) {
            $vence = $alta->addMonthsNoOverflow($n);
            $periodo = $vence->format('Y-m');
            $pagado = isset($pagados[$periodo]);
            $estado = match (true) {
                $pagado => 'pagado',
                $vence->lt($hoy) => 'vencido',
                $vence->lte($hoy->addDays($diasAviso)) => 'proximo',
                default => 'futuro',
            };
            $cuotas[] = compact('periodo', 'vence', 'pagado', 'estado');

            // Cortamos en la primera cuota futura (la siguiente a cobrar).
            if ($vence->gt($hoy)) {
                break;
            }
        }

        return $cuotas;
    }

    /**
     * Resumen para listados: 'vencido', 'proximo', 'al_dia' o 'inactivo', con las
     * cuotas vencidas y la próxima a cobrar.
     *
     * @return array{estado:string, vencidas:list<array>, proxima:?array}
     */
    public function estadoAbono(int $diasAviso = 5): array
    {
        if (! $this->activo || (float) $this->abono_mensual <= 0) {
            return ['estado' => 'inactivo', 'vencidas' => [], 'proxima' => null];
        }

        $cuotas = $this->cuotas(null, $diasAviso);
        $vencidas = array_values(array_filter($cuotas, fn ($c) => $c['estado'] === 'vencido'));
        $pendientes = array_values(array_filter($cuotas, fn ($c) => ! $c['pagado'] && $c['estado'] !== 'vencido'));
        $proxima = $pendientes[0] ?? null;

        $estado = match (true) {
            $vencidas !== [] => 'vencido',
            $proxima !== null && $proxima['estado'] === 'proximo' => 'proximo',
            default => 'al_dia',
        };

        return compact('estado', 'vencidas', 'proxima');
    }
}
