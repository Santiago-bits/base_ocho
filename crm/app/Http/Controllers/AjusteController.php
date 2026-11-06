<?php

namespace App\Http\Controllers;

use App\Models\Ajuste;
use App\Models\Rubro;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AjusteController extends Controller
{
    public function index(): View
    {
        return view('ajustes', [
            'rubros' => Rubro::withCount('empresas')->orderBy('nombre')->get(),
            'limiteWhatsapp' => Ajuste::entero('limite_whatsapp_diario'),
            'diasAviso' => Ajuste::entero('dias_aviso_abono'),
            'usuarios' => User::orderBy('name')->get(),
        ]);
    }

    public function guardar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'limite_whatsapp_diario' => ['required', 'integer', 'min:1', 'max:500'],
            'dias_aviso_abono' => ['required', 'integer', 'min:0', 'max:60'],
        ]);
        foreach ($datos as $clave => $valor) {
            Ajuste::guardar($clave, (string) $valor);
        }

        return back()->with('ok', 'Ajustes guardados.');
    }

    public function rubroStore(Request $request): RedirectResponse
    {
        $datos = $request->validate(['nombre' => ['required', 'string', 'max:100', 'unique:rubros,nombre']]);
        Rubro::create($datos + ['activo' => true]);

        return back()->with('ok', "Rubro «{$datos['nombre']}» creado.");
    }

    public function rubroUpdate(Request $request, Rubro $rubro): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('rubros', 'nombre')->ignore($rubro->id)],
        ]);
        $rubro->update($datos + ['activo' => $request->boolean('activo')]);

        return back()->with('ok', 'Rubro guardado.');
    }

    public function password(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'actual' => ['required', 'current_password'],
            'nueva' => ['required', 'string', 'min:8', 'confirmed'],
        ]);
        $request->user()->update(['password' => $datos['nueva']]);

        return back()->with('ok', 'Contraseña cambiada.');
    }
}
