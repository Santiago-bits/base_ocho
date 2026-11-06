<?php

namespace App\Models;

use App\Enums\Canal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Contacto extends Model
{
    protected $table = 'contactos';

    protected $fillable = [
        'empresa_id', 'usuario_id', 'fecha_hora', 'canal', 'plantilla_id',
        'mensaje', 'pdf_enviado', 'respondio', 'resultado',
    ];

    protected function casts(): array
    {
        return [
            'fecha_hora' => 'datetime',
            'canal' => Canal::class,
            'pdf_enviado' => 'boolean',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(Plantilla::class);
    }

    /** Franja horaria del envío: mañana (6–13), tarde (13–20) o noche (20–6). */
    public function franja(): string
    {
        $hora = (int) $this->fecha_hora->format('G');

        return match (true) {
            $hora >= 6 && $hora < 13 => 'Mañana',
            $hora >= 13 && $hora < 20 => 'Tarde',
            default => 'Noche',
        };
    }

    public function etiquetaRespuesta(): string
    {
        return match ($this->respondio) {
            'si' => 'Respondió',
            'no' => 'No respondió',
            default => 'Pendiente',
        };
    }
}
