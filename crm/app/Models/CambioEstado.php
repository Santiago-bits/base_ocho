<?php

namespace App\Models;

use App\Enums\EstadoEmpresa;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CambioEstado extends Model
{
    protected $table = 'cambios_estado';

    public const UPDATED_AT = null;

    protected $fillable = ['empresa_id', 'usuario_id', 'estado_anterior', 'estado_nuevo'];

    protected function casts(): array
    {
        return [
            'estado_anterior' => EstadoEmpresa::class,
            'estado_nuevo' => EstadoEmpresa::class,
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
