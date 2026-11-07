<?php

namespace App\Http\Controllers;

use App\Enums\Canal;
use App\Enums\EstadoEmpresa;
use App\Models\Ajuste;
use App\Models\Empresa;
use App\Models\Plantilla;
use App\Models\Rubro;
use App\Support\Normalizador;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    public function index(Request $request): View
    {
        $empresas = $this->filtrar($request)
            ->with('rubro')
            ->withMax('contactos as ultimo_contacto', 'fecha_hora')
            ->paginate(30)
            ->withQueryString();

        return view('empresas.index', [
            'empresas' => $empresas,
            'rubros' => Rubro::orderBy('nombre')->get(),
            'ciudades' => Empresa::whereNotNull('ciudad')->distinct()->orderBy('ciudad')->pluck('ciudad'),
            'filtros' => $request->only(['q', 'rubro', 'ciudad', 'estado', 'canal', 'sin_wa', 'seguimiento', 'orden']),
        ]);
    }

    /** Devuelve el listado filtrado completo (sin paginar) para armar el Excel en el navegador. */
    public function exportar(Request $request): JsonResponse
    {
        $filas = $this->filtrar($request)
            ->with('rubro')
            ->withMax('contactos as ultimo_contacto', 'fecha_hora')
            ->get()
            ->map(fn (Empresa $e) => [
                'Nombre' => $e->nombre,
                'Rubro' => $e->rubro?->nombre,
                'Ciudad' => $e->ciudad,
                'Dirección' => $e->direccion,
                'Teléfono' => $e->telefono,
                'WhatsApp' => $e->whatsapp,
                'Email' => $e->email,
                'Web / Redes' => $e->web_redes,
                'Fuente' => $e->fuente,
                'Estado' => $e->estado->etiqueta(),
                'Próxima acción' => $e->proxima_accion,
                'Fecha próxima acción' => $e->proxima_accion_fecha?->format('d/m/Y'),
                'Último contacto' => $e->ultimo_contacto ? CarbonImmutable::parse($e->ultimo_contacto)->format('d/m/Y H:i') : null,
                'Notas' => $e->notas,
            ]);

        return response()->json(['filas' => $filas]);
    }

    public function create(Request $request): View
    {
        return view('empresas.form', [
            'empresa' => new Empresa(['rubro_id' => $request->integer('rubro') ?: null]),
            'rubros' => Rubro::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate($this->reglas());

        if (! $request->boolean('forzar') && ($parecidas = $this->buscarDuplicadas($datos))->isNotEmpty()) {
            return back()->withInput()->with('duplicadas', $parecidas);
        }

        $empresa = Empresa::create($datos + ['estado' => EstadoEmpresa::SinContactar]);

        return redirect()->route('empresas.show', $empresa)->with('ok', 'Empresa creada.');
    }

    public function show(Empresa $empresa): View
    {
        $empresa->load(['rubro', 'cliente']);

        $contactos = $empresa->contactos()->with(['usuario', 'plantilla'])
            ->orderByDesc('fecha_hora')->orderByDesc('id')->get();
        $cambios = $empresa->cambiosEstado()->with('usuario')->get();

        $historial = $contactos->map(fn ($c) => ['tipo' => 'contacto', 'fecha' => $c->fecha_hora, 'item' => $c])
            ->concat($cambios->map(fn ($c) => ['tipo' => 'estado', 'fecha' => $c->created_at, 'item' => $c]))
            ->sortByDesc(fn ($h) => $h['fecha']->timestamp)
            ->values();

        $plantillas = Plantilla::paraRubro($empresa->rubro_id)->get()->map(fn (Plantilla $p) => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'canal' => $p->canal->value,
            'asunto' => $p->asunto ? $p->completar($empresa, $p->asunto) : '',
            'cuerpo' => $p->completar($empresa),
        ]);

        return view('empresas.show', [
            'empresa' => $empresa,
            'historial' => $historial,
            'plantillas' => $plantillas,
            'whatsappHoy' => PanelController::whatsappHoy(),
            'limiteWhatsapp' => Ajuste::entero('limite_whatsapp_diario'),
        ]);
    }

    public function edit(Empresa $empresa): View
    {
        return view('empresas.form', [
            'empresa' => $empresa,
            'rubros' => Rubro::orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        $empresa->update($request->validate($this->reglas()));

        return redirect()->route('empresas.show', $empresa)->with('ok', 'Datos guardados.');
    }

    public function destroy(Empresa $empresa): RedirectResponse
    {
        $nombre = $empresa->nombre;
        $empresa->delete();

        return redirect()->route('empresas.index')->with('ok', "Se borró «{$nombre}» y todo su historial.");
    }

    public function estado(Request $request, Empresa $empresa): RedirectResponse
    {
        $datos = $request->validate(['estado' => ['required', Rule::enum(EstadoEmpresa::class)]]);
        $nuevo = EstadoEmpresa::from($datos['estado']);
        $anterior = $empresa->estado;

        $empresa->cambiarEstado($nuevo, $request->user()->id);

        // Si pasa a "Respondió" o más adelante, el último envío pendiente cuenta como respondido.
        $avanza = $nuevo->orden() >= EstadoEmpresa::Respondio->orden()
            && ! in_array($nuevo, [EstadoEmpresa::Perdido, EstadoEmpresa::NoContactar], true);
        if ($avanza && in_array($anterior, [EstadoEmpresa::SinContactar, EstadoEmpresa::Contactado], true)) {
            $empresa->contactos()->whereIn('canal', Canal::salientes())->whereNull('respondio')
                ->orderByDesc('fecha_hora')->first()?->update(['respondio' => 'si']);
        }

        if ($nuevo === EstadoEmpresa::Cliente && ! $empresa->cliente()->exists()) {
            return redirect()->route('clientes.create', $empresa)
                ->with('ok', 'Estado cambiado a Cliente. Cargá los datos del servicio.');
        }

        return back()->with('ok', "Estado cambiado a {$nuevo->etiqueta()}.");
    }

    public function proximaAccion(Request $request, Empresa $empresa): RedirectResponse
    {
        if ($request->boolean('limpiar')) {
            $empresa->update(['proxima_accion' => null, 'proxima_accion_fecha' => null]);

            return back()->with('ok', 'Próxima acción completada.');
        }

        $empresa->update($request->validate([
            'proxima_accion' => ['nullable', 'string', 'max:255'],
            'proxima_accion_fecha' => ['required', 'date'],
        ]));

        return back()->with('ok', 'Próxima acción guardada.');
    }

    /** @return array<string,array> */
    private function reglas(): array
    {
        return [
            'rubro_id' => ['required', 'exists:rubros,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'ciudad' => ['nullable', 'string', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:60'],
            'whatsapp' => ['nullable', 'string', 'max:60'],
            'email' => ['nullable', 'email', 'max:190'],
            'web_redes' => ['nullable', 'string', 'max:255'],
            'fuente' => ['nullable', 'string', 'max:120'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function buscarDuplicadas(array $datos)
    {
        $telefonos = array_filter([Normalizador::telefono($datos['telefono'] ?? null), Normalizador::telefono($datos['whatsapp'] ?? null)]);
        $email = Normalizador::email($datos['email'] ?? null);
        if (! $telefonos && ! $email) {
            return collect();
        }

        return Empresa::where(function (Builder $q) use ($telefonos, $email) {
            if ($telefonos) {
                $q->whereIn('telefono_norm', $telefonos)->orWhereIn('whatsapp_norm', $telefonos);
            }
            if ($email) {
                $q->orWhere('email_norm', $email);
            }
        })->get(['id', 'nombre', 'ciudad']);
    }

    private function filtrar(Request $request): Builder
    {
        $query = Empresa::query();

        if ($request->filled('rubro')) {
            $query->where('rubro_id', $request->integer('rubro'));
        }
        if ($request->filled('ciudad')) {
            $query->where('ciudad', $request->string('ciudad'));
        }
        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }
        if ($request->filled('canal')) {
            $query->whereHas('contactos', fn (Builder $q) => $q->where('canal', $request->string('canal')));
        }
        if ($request->boolean('sin_wa')) {
            $query->where('estado', EstadoEmpresa::SinContactar->value)->whereNotNull('whatsapp_norm');
        }
        if ($request->input('seguimiento') === 'hoy') {
            $query->whereDate('proxima_accion_fecha', today()->toDateString());
        } elseif ($request->input('seguimiento') === 'vencidos') {
            $query->whereDate('proxima_accion_fecha', '<', today()->toDateString());
        }
        if ($request->filled('q')) {
            $texto = addcslashes(trim((string) $request->input('q')), '%_\\');
            $digitos = ltrim(Normalizador::digitos($texto), '0');
            $query->where(function (Builder $q) use ($texto, $digitos) {
                $q->where('nombre', 'like', "%{$texto}%")->orWhere('email', 'like', "%{$texto}%");
                if (strlen($digitos) >= 4) {
                    $q->orWhere('telefono_norm', 'like', "%{$digitos}%")->orWhere('whatsapp_norm', 'like', "%{$digitos}%");
                }
            });
        }

        return match ($request->input('orden')) {
            'recientes' => $query->orderByDesc('updated_at'),
            'proxima' => $query->orderByRaw('proxima_accion_fecha is null')->orderBy('proxima_accion_fecha'),
            default => $query->orderBy('nombre'),
        };
    }
}
