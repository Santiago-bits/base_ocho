<?php

namespace App\Http\Controllers;

use App\Enums\Canal;
use App\Enums\EstadoEmpresa;
use App\Models\Ajuste;
use App\Models\Contacto;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactoController extends Controller
{
    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        $datos = $request->validate([
            'canal' => ['required', Rule::in(Canal::salientes())],
            'fecha_hora' => ['nullable', 'date'],
            'plantilla_id' => ['nullable', 'exists:plantillas,id'],
            'mensaje' => ['nullable', 'string', 'max:5000'],
            'respondio' => ['nullable', Rule::in(['si', 'no'])],
            'resultado' => ['nullable', 'string', 'max:5000'],
        ]);
        $usuarioId = $request->user()->id;

        $empresa->contactos()->create([
            ...$datos,
            'usuario_id' => $usuarioId,
            'fecha_hora' => $datos['fecha_hora'] ?? now(),
            'pdf_enviado' => $request->boolean('pdf_enviado'),
        ]);

        // Avance automático: el primer contacto pasa la empresa a "Contactado",
        // y si ya respondió, a "Respondió".
        $respondio = ($datos['respondio'] ?? null) === 'si';
        if ($empresa->estado === EstadoEmpresa::SinContactar) {
            $empresa->cambiarEstado($respondio ? EstadoEmpresa::Respondio : EstadoEmpresa::Contactado, $usuarioId);
        } elseif ($respondio && $empresa->estado === EstadoEmpresa::Contactado) {
            $empresa->cambiarEstado(EstadoEmpresa::Respondio, $usuarioId);
        }

        $redirect = redirect()->route('empresas.show', $empresa)->with('ok', 'Contacto registrado.');

        if ($datos['canal'] === Canal::WhatsApp->value) {
            $hoy = PanelController::whatsappHoy();
            $limite = Ajuste::entero('limite_whatsapp_diario');
            if ($hoy >= $limite) {
                $redirect->with('aviso', "Hoy ya van {$hoy} WhatsApp (límite {$limite}). Conviene parar hasta mañana para que no suspendan la cuenta.");
            }
        }

        return $redirect;
    }

    /** Marcar la respuesta de un envío y anotar el resultado. */
    public function update(Request $request, Contacto $contacto): RedirectResponse
    {
        $datos = $request->validate([
            'respondio' => ['nullable', Rule::in(['si', 'no', 'pendiente'])],
            'resultado' => ['nullable', 'string', 'max:5000'],
        ]);

        if (array_key_exists('respondio', $datos)) {
            $datos['respondio'] = $datos['respondio'] === 'pendiente' ? null : $datos['respondio'];
        }
        $contacto->update($datos);

        $empresa = $contacto->empresa;
        if ($contacto->respondio === 'si' && in_array($empresa->estado, [EstadoEmpresa::SinContactar, EstadoEmpresa::Contactado], true)) {
            $empresa->cambiarEstado(EstadoEmpresa::Respondio, $request->user()->id);
        }

        return redirect()->route('empresas.show', $empresa)->with('ok', 'Contacto actualizado.');
    }

    public function destroy(Contacto $contacto): RedirectResponse
    {
        $empresa = $contacto->empresa;
        $contacto->delete();

        return redirect()->route('empresas.show', $empresa)->with('ok', 'Registro borrado del historial.');
    }
}
