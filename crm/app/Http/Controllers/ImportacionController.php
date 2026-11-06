<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEmpresa;
use App\Models\Empresa;
use App\Models\Importacion;
use App\Models\Rubro;
use App\Services\Importador;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * El archivo se lee en el navegador (SheetJS); acá llegan las filas ya mapeadas como JSON.
 */
class ImportacionController extends Controller
{
    private const MAX_FILAS = 5000;

    public function index(): View
    {
        return view('importar.index', [
            'rubros' => Rubro::where('activo', true)->orderBy('nombre')->get(),
            'importaciones' => Importacion::with(['rubro', 'usuario'])->latest()->limit(10)->get(),
            'config' => [
                'campos' => Empresa::CAMPOS_DATOS + ['estado_envio' => 'Estado envío (va al historial)'],
                'estados' => EstadoEmpresa::opciones(),
                'urls' => [
                    'revisar' => route('importar.revisar'),
                    'confirmar' => route('importar.confirmar'),
                    'empresas' => route('empresas.index'),
                ],
            ],
        ]);
    }

    /** Paso 3: detectar nuevas, duplicadas y repetidas antes de guardar. */
    public function revisar(Request $request, Importador $importador): JsonResponse
    {
        $request->validate([
            'filas' => ['required', 'array', 'max:'.self::MAX_FILAS],
            'filas.*' => ['array'],
        ]);

        $filas = array_map(fn ($f) => $importador->limpiar($f), array_values($request->input('filas')));
        $analisis = $importador->analizar($filas);

        $resumen = array_count_values(array_column($analisis, 'tipo')) + ['nueva' => 0, 'duplicada' => 0, 'repetida' => 0, 'invalida' => 0];

        return response()->json(['analisis' => $analisis, 'resumen' => $resumen]);
    }

    /** Paso 4: guardar. */
    public function confirmar(Request $request, Importador $importador): JsonResponse
    {
        $datos = $request->validate([
            'rubro_id' => ['nullable', 'required_without:rubro_nuevo', 'exists:rubros,id'],
            'rubro_nuevo' => ['nullable', 'required_without:rubro_id', 'string', 'max:100'],
            'archivo' => ['required', 'string', 'max:255'],
            'filas' => ['required', 'array', 'max:'.self::MAX_FILAS],
            'filas.*' => ['array'],
            'decisiones' => ['nullable', 'array'],
            'decisiones.*' => ['in:actualizar,saltear'],
        ]);

        $rubro = ! empty($datos['rubro_id'])
            ? Rubro::findOrFail($datos['rubro_id'])
            : Rubro::firstOrCreate(['nombre' => trim($datos['rubro_nuevo'])], ['activo' => true]);

        if (! $rubro->activo) {
            throw ValidationException::withMessages(['rubro_id' => 'Ese rubro está desactivado.']);
        }

        $importacion = $importador->importar(
            $rubro,
            $datos['archivo'],
            $datos['filas'],
            $datos['decisiones'] ?? [],
            $request->user()->id,
        );

        return response()->json([
            'ok' => true,
            'resumen' => $importacion->only(['filas', 'nuevas', 'actualizadas', 'salteadas']),
            'url' => route('empresas.index', ['rubro' => $rubro->id, 'orden' => 'recientes']),
        ]);
    }
}
