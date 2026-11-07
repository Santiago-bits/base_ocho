<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Configuración clave/valor (límite diario de WhatsApp, días de aviso de abonos). */
class Ajuste extends Model
{
    protected $table = 'ajustes';

    protected $primaryKey = 'clave';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['clave', 'valor'];

    public const DEFAULTS = [
        'limite_whatsapp_diario' => '8',
        'dias_aviso_abono' => '5',
    ];

    /** @var array<string,?string>|null */
    private static ?array $cache = null;

    public static function valor(string $clave): ?string
    {
        self::$cache ??= self::query()->pluck('valor', 'clave')->all();

        return self::$cache[$clave] ?? self::DEFAULTS[$clave] ?? null;
    }

    public static function entero(string $clave): int
    {
        return (int) self::valor($clave);
    }

    public static function guardar(string $clave, ?string $valor): void
    {
        self::query()->updateOrCreate(['clave' => $clave], ['valor' => $valor]);
        self::$cache = null;
    }
}
