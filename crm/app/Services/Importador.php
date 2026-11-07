<?php

namespace App\Services;

use App\Enums\Canal;
use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\Importacion;
use App\Models\Rubro;
use App\Support\Normalizador;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Importa empresas desde filas ya mapeadas en el navegador.
 *
 * Cada fila llega como: {_fila, nombre, ciudad, direccion, telefono, whatsapp,
 * email, web_redes, fuente, notas, estado_envio, estado}.
 */
class Importador
{
    /** Largo máximo de cada campo (igual que en la migración). */
    private const LARGOS = [
        'nombre' => 200, 'ciudad' => 120, 'direccion' => 255, 'telefono' => 60,
        'whatsapp' => 60, 'email' => 190, 'web_redes' => 255, 'fuente' => 120,
        'notas' => 5000, 'estado_envio' => 1000,
    ];

    /** Campos que se copian a la empresa (estado_envio solo va al historial). */
    private const CAMPOS_EMPRESA = [
        'nombre', 'ciudad', 'direccion', 'telefono', 'whatsapp', 'email', 'web_redes', 'fuente',
    ];

    /** @return array<string,mixed> */
    public function limpiar(array $fila): array
    {
        $out = ['_fila' => (int) ($fila['_fila'] ?? 0)];
        foreach (self::LARGOS as $campo => $largo) {
            $valor = $fila[$campo] ?? null;
            $valor = is_scalar($valor) ? trim(preg_replace('/\s+/u', ' ', (string) $valor)) : '';
            $out[$campo] = $valor === '' ? null : Str::limit($valor, $largo, '');
        }
        $out['estado'] = EstadoEmpresa::tryFrom((string) ($fila['estado'] ?? '')) ?? EstadoEmpresa::SinContactar;

        return $out;
    }

    /**
     * Clasifica cada fila: nueva, duplicada (ya existe en el sistema),
     * repetida (aparece antes en el mismo archivo) o invalida (sin nombre).
     *
     * @param  list<array>  $filas  filas ya pasadas por limpiar()
     * @return array<int,array{tipo:string, motivo:?string, coincidencia:?array}>
     */
    public function analizar(array $filas): array
    {
        $normas = array_map(fn (array $f) => $this->normas($f), $filas);
        [$porTelefono, $porEmail] = $this->buscarExistentes($normas);

        $vistos = [];
        $resultado = [];
        foreach ($filas as $i => $fila) {
            if ($fila['nombre'] === null) {
                $resultado[$i] = ['tipo' => 'invalida', 'motivo' => 'Falta el nombre', 'coincidencia' => null];

                continue;
            }

            $n = $normas[$i];
            $existente = null;
            $motivo = null;
            foreach ($n['telefonos'] as $tel) {
                if (isset($porTelefono[$tel])) {
                    $existente = $porTelefono[$tel];
                    $motivo = 'teléfono';
                    break;
                }
            }
            if (! $existente && $n['email'] && isset($porEmail[$n['email']])) {
                $existente = $porEmail[$n['email']];
                $motivo = 'email';
            }

            $claves = array_map(fn ($t) => "t:$t", $n['telefonos']);
            if ($n['email']) {
                $claves[] = "e:{$n['email']}";
            }
            $repite = null;
            foreach ($claves as $clave) {
                if (isset($vistos[$clave])) {
                    $repite = $vistos[$clave];
                    break;
                }
            }
            foreach ($claves as $clave) {
                $vistos[$clave] ??= $i;
            }

            $resultado[$i] = match (true) {
                $existente !== null => [
                    'tipo' => 'duplicada',
                    'motivo' => "Ya existe (coincide el $motivo)",
                    'coincidencia' => [
                        'id' => $existente->id,
                        'nombre' => $existente->nombre,
                        'rubro' => $existente->rubro?->nombre,
                        'ciudad' => $existente->ciudad,
                        'estado' => $existente->estado->etiqueta(),
                    ],
                ],
                $repite !== null => [
                    'tipo' => 'repetida',
                    'motivo' => 'Repite la fila '.($filas[$repite]['_fila'] ?: $repite + 1).' del archivo',
                    'coincidencia' => null,
                ],
                default => ['tipo' => 'nueva', 'motivo' => null, 'coincidencia' => null],
            };
        }

        return $resultado;
    }

    /**
     * Guarda las filas. $decisiones indica, por índice de fila duplicada,
     * 'actualizar' o 'saltear' (por defecto se saltea).
     *
     * @param  list<array>  $filasCrudas
     * @param  array<int|string,string>  $decisiones
     */
    public function importar(Rubro $rubro, string $archivo, array $filasCrudas, array $decisiones, ?int $usuarioId): Importacion
    {
        $filas = array_map(fn ($f) => $this->limpiar((array) $f), array_values($filasCrudas));
        // Se vuelve a analizar en el servidor: no confiamos en lo que mandó el navegador.
        $analisis = $this->analizar($filas);

        return DB::transaction(function () use ($rubro, $archivo, $filas, $analisis, $decisiones, $usuarioId) {
            $nuevas = $actualizadas = $salteadas = 0;

            foreach ($filas as $i => $fila) {
                $tipo = $analisis[$i]['tipo'];

                if ($tipo === 'nueva') {
                    $empresa = new Empresa(array_intersect_key($fila, array_flip(self::CAMPOS_EMPRESA)));
                    $empresa->rubro_id = $rubro->id;
                    $empresa->notas = $fila['notas'];
                    $empresa->estado = $fila['estado'];
                    $empresa->save();

                    if ($fila['estado'] !== EstadoEmpresa::SinContactar) {
                        $empresa->cambiosEstado()->create([
                            'usuario_id' => $usuarioId,
                            'estado_anterior' => null,
                            'estado_nuevo' => $fila['estado']->value,
                        ]);
                    }
                    $this->registrarHistorial($empresa, $fila, $archivo, $rubro, $usuarioId, false);
                    $nuevas++;

                    continue;
                }

                if ($tipo === 'duplicada' && ($decisiones[$i] ?? 'saltear') === 'actualizar') {
                    $empresa = Empresa::find($analisis[$i]['coincidencia']['id']);
                    if (! $empresa) {
                        $salteadas++;

                        continue;
                    }
                    // Solo pisamos con valores que vienen cargados; lo vacío no borra nada.
                    foreach (self::CAMPOS_EMPRESA as $campo) {
                        if ($fila[$campo] !== null) {
                            $empresa->{$campo} = $fila[$campo];
                        }
                    }
                    if ($fila['notas'] && ! str_contains((string) $empresa->notas, $fila['notas'])) {
                        $empresa->notas = trim($empresa->notas."\n".$fila['notas']);
                    }
                    $empresa->save();

                    // El estado solo avanza si la empresa todavía no estaba contactada.
                    if ($empresa->estado === EstadoEmpresa::SinContactar && $fila['estado'] !== EstadoEmpresa::SinContactar) {
                        $empresa->cambiarEstado($fila['estado'], $usuarioId);
                    }
                    $this->registrarHistorial($empresa, $fila, $archivo, $rubro, $usuarioId, true);
                    $actualizadas++;

                    continue;
                }

                $salteadas++;
            }

            return Importacion::create([
                'rubro_id' => $rubro->id,
                'usuario_id' => $usuarioId,
                'archivo' => Str::limit($archivo, 250, ''),
                'filas' => count($filas),
                'nuevas' => $nuevas,
                'actualizadas' => $actualizadas,
                'salteadas' => $salteadas,
            ]);
        });
    }

    /** "Estado envío" y "Notas" del Excel quedan como registro del historial. */
    private function registrarHistorial(Empresa $empresa, array $fila, string $archivo, Rubro $rubro, ?int $usuarioId, bool $actualizacion): void
    {
        $lineas = [
            ($actualizacion ? 'Datos actualizados desde ' : 'Importado desde ')."«{$archivo}» (rubro {$rubro->nombre}).",
        ];
        if ($fila['estado_envio']) {
            $lineas[] = 'Estado envío: '.$fila['estado_envio'];
        }
        if ($fila['notas']) {
            $lineas[] = 'Notas: '.$fila['notas'];
        }

        $empresa->contactos()->create([
            'usuario_id' => $usuarioId,
            'fecha_hora' => now(),
            'canal' => Canal::Importado,
            'mensaje' => implode("\n", $lineas),
        ]);
    }

    /** @return array{telefonos:list<string>, email:?string} */
    private function normas(array $fila): array
    {
        $telefonos = array_values(array_unique(array_filter([
            Normalizador::telefono($fila['telefono']),
            Normalizador::telefono($fila['whatsapp']),
        ])));

        return ['telefonos' => $telefonos, 'email' => Normalizador::email($fila['email'])];
    }

    /**
     * @param  list<array{telefonos:list<string>, email:?string}>  $normas
     * @return array{0:array<string,Empresa>, 1:array<string,Empresa>}
     */
    private function buscarExistentes(array $normas): array
    {
        $telefonos = array_values(array_unique(array_merge(...array_column($normas, 'telefonos') ?: [[]])));
        $emails = array_values(array_unique(array_filter(array_column($normas, 'email'))));

        /** @var Collection<int,Empresa> $existentes */
        $existentes = collect();
        foreach (array_chunk($telefonos, 400) as $lote) {
            $existentes = $existentes->merge(
                Empresa::with('rubro:id,nombre')->whereIn('telefono_norm', $lote)->orWhereIn('whatsapp_norm', $lote)->get()
            );
        }
        foreach (array_chunk($emails, 400) as $lote) {
            $existentes = $existentes->merge(Empresa::with('rubro:id,nombre')->whereIn('email_norm', $lote)->get());
        }

        $porTelefono = [];
        $porEmail = [];
        foreach ($existentes->sortBy('id') as $empresa) {
            foreach ([$empresa->telefono_norm, $empresa->whatsapp_norm] as $tel) {
                if ($tel) {
                    $porTelefono[$tel] ??= $empresa;
                }
            }
            if ($empresa->email_norm) {
                $porEmail[$empresa->email_norm] ??= $empresa;
            }
        }

        return [$porTelefono, $porEmail];
    }
}
