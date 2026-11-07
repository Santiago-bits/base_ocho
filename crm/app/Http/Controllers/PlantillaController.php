<?php

namespace App\Http\Controllers;

use App\Enums\Canal;
use App\Models\Plantilla;
use App\Models\Rubro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PlantillaController extends Controller
{
    public function index(): View
    {
        $plantillas = Plantilla::with('rubro')
            ->withCount([
                'contactos as enviados' => fn ($q) => $q->whereIn('canal', Canal::salientes()),
                'contactos as respondidos' => fn ($q) => $q->where('respondio', 'si'),
            ])
            ->orderByDesc('activa')->orderBy('nombre')
            ->get()
            ->groupBy(fn (Plantilla $p) => $p->rubro?->nombre ?? 'Todos los rubros');

        return view('plantillas.index', ['grupos' => $plantillas]);
    }

    public function create(): View
    {
        return view('plantillas.form', [
            'plantilla' => new Plantilla(['canal' => Canal::WhatsApp, 'activa' => true]),
            'rubros' => Rubro::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Plantilla::create($this->validar($request));

        return redirect()->route('plantillas.index')->with('ok', 'Plantilla creada.');
    }

    public function edit(Plantilla $plantilla): View
    {
        return view('plantillas.form', [
            'plantilla' => $plantilla,
            'rubros' => Rubro::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Plantilla $plantilla): RedirectResponse
    {
        $plantilla->update($this->validar($request));

        return redirect()->route('plantillas.index')->with('ok', 'Plantilla guardada.');
    }

    /** Si ya se usó, se desactiva en vez de borrarla para no perder las estadísticas. */
    public function destroy(Plantilla $plantilla): RedirectResponse
    {
        if ($plantilla->contactos()->exists()) {
            $plantilla->update(['activa' => false]);

            return redirect()->route('plantillas.index')
                ->with('ok', 'La plantilla ya se usó en envíos: quedó desactivada para conservar las estadísticas.');
        }

        $plantilla->delete();

        return redirect()->route('plantillas.index')->with('ok', 'Plantilla borrada.');
    }

    /** @return array<string,mixed> */
    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'rubro_id' => ['nullable', 'exists:rubros,id'],
            'canal' => ['required', Rule::in(Canal::salientes())],
            'asunto' => ['nullable', 'string', 'max:200'],
            'cuerpo' => ['required', 'string', 'max:5000'],
        ]) + ['activa' => $request->boolean('activa')];
    }
}
