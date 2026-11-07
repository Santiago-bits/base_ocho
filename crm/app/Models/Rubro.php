<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rubro extends Model
{
    protected $table = 'rubros';

    protected $fillable = ['nombre', 'activo'];

    protected function casts(): array
    {
        return ['activo' => 'boolean'];
    }

    public function empresas(): HasMany
    {
        return $this->hasMany(Empresa::class);
    }

    public function plantillas(): HasMany
    {
        return $this->hasMany(Plantilla::class);
    }
}
