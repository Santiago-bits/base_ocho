<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pago extends Model
{
    protected $table = 'pagos';

    protected $fillable = ['cliente_id', 'usuario_id', 'fecha_pago', 'monto', 'periodo', 'nota'];

    protected function casts(): array
    {
        return [
            'fecha_pago' => 'date',
            'monto' => 'decimal:2',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function etiquetaPeriodo(): string
    {
        return self::nombrePeriodo($this->periodo);
    }

    public static function nombrePeriodo(string $periodo): string
    {
        if ($periodo === 'inicial') {
            return 'Pago inicial';
        }
        [$anio, $mes] = explode('-', $periodo) + [null, null];
        $meses = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

        return ucfirst($meses[(int) $mes] ?? $periodo).' '.$anio;
    }
}
