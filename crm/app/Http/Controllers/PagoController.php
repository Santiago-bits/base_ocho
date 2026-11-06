<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Pago;
use App\Support\Formato;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PagoController extends Controller
{
    public function store(Request $request, Cliente $cliente): RedirectResponse
    {
        $request->merge(['monto' => Formato::leerMonto($request->input('monto'))]);

        $datos = $request->validate([
            'fecha_pago' => ['required', 'date'],
            'monto' => ['required', 'numeric', 'gt:0'],
            'periodo' => ['required', 'regex:/^(inicial|\d{4}-(0[1-9]|1[0-2]))$/'],
            'nota' => ['nullable', 'string', 'max:255'],
        ], [
            'periodo.regex' => 'Elegí el mes que paga.',
        ]);

        $pago = $cliente->pagos()->create($datos + ['usuario_id' => $request->user()->id]);

        return redirect()->route('clientes.show', $cliente)
            ->with('ok', 'Pago registrado: '.$pago->etiquetaPeriodo().' por '.Formato::moneda($pago->monto).'.');
    }

    public function destroy(Pago $pago): RedirectResponse
    {
        $cliente = $pago->cliente;
        $pago->delete();

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Pago borrado.');
    }
}
