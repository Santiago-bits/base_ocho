<?php

namespace App\Models;

use App\Enums\EstadoEmpresa;
use App\Support\Normalizador;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Empresa extends Model
{
    protected $table = 'empresas';

    /** Campos de datos que se pueden cargar a mano o desde un Excel. */
    public const CAMPOS_DATOS = [
        'nombre' => 'Nombre',
        'ciudad' => 'Ciudad',
        'direccion' => 'Dirección',
        'telefono' => 'Teléfono',
        'whatsapp' => 'WhatsApp',
        'email' => 'Email',
        'web_redes' => 'Web / Redes',
        'fuente' => 'Fuente',
        'notas' => 'Notas',
    ];

    protected $fillable = [
        'rubro_id', 'nombre', 'ciudad', 'direccion', 'telefono', 'whatsapp', 'email',
        'web_redes', 'fuente', 'notas', 'estado', 'proxima_accion', 'proxima_accion_fecha',
        'responsable_id',
    ];

    protected function casts(): array
    {
        return [
            'estado' => EstadoEmpresa::class,
            'proxima_accion_fecha' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Mantener siempre actualizadas las columnas normalizadas.
        static::saving(function (Empresa $empresa) {
            $empresa->telefono_norm = Normalizador::telefono($empresa->telefono);
            $empresa->whatsapp_norm = Normalizador::telefono($empresa->whatsapp);
            $empresa->email_norm = Normalizador::email($empresa->email);
        });
    }

    public function rubro(): BelongsTo
    {
        return $this->belongsTo(Rubro::class);
    }

    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function contactos(): HasMany
    {
        return $this->hasMany(Contacto::class);
    }

    public function cambiosEstado(): HasMany
    {
        return $this->hasMany(CambioEstado::class);
    }

    public function cliente(): HasOne
    {
        return $this->hasOne(Cliente::class);
    }

    /** Número para wa.me: el WhatsApp cargado o, si no hay, el teléfono. */
    public function numeroWhatsapp(): ?string
    {
        return $this->whatsapp_norm ?: $this->telefono_norm;
    }

    /** Cambia el estado dejando registro en cambios_estado. */
    public function cambiarEstado(EstadoEmpresa $nuevo, ?int $usuarioId): void
    {
        $anterior = $this->estado;
        if ($anterior === $nuevo) {
            return;
        }
        $this->estado = $nuevo;
        $this->save();

        $this->cambiosEstado()->create([
            'usuario_id' => $usuarioId,
            'estado_anterior' => $anterior?->value,
            'estado_nuevo' => $nuevo->value,
        ]);
    }

    /** Variables disponibles para las plantillas. @return array<string,string> */
    public function variablesPlantilla(): array
    {
        return [
            '{nombre}' => (string) $this->nombre,
            '{ciudad}' => (string) $this->ciudad,
            '{rubro}' => (string) $this->rubro?->nombre,
            '{direccion}' => (string) $this->direccion,
            '{web}' => (string) $this->web_redes,
        ];
    }
}
