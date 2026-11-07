<?php

namespace App\Models;

use App\Enums\Canal;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plantilla extends Model
{
    protected $table = 'plantillas';

    protected $fillable = ['rubro_id', 'nombre', 'canal', 'asunto', 'cuerpo', 'activa'];

    protected function casts(): array
    {
        return [
            'canal' => Canal::class,
            'activa' => 'boolean',
        ];
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class);
    }

    /** Plantillas activas que sirven para un rubro (las del rubro y las generales). */
    public function scopeParaRubro(Builder $query, ?int $rubroId): Builder
    {
        return $query->where('activa', true)
            ->where(fn (Builder $q) => $q->whereNull('rubro_id')->orWhere('rubro_id', $rubroId))
            ->orderBy('nombre');
    }

    public function completar(Empresa $empresa, ?string $texto = null): string
    {
        return strtr($texto ?? $this->cuerpo, $empresa->variablesPlantilla());
    }
}
