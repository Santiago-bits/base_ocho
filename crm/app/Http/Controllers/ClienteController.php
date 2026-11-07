<?php

namespace App\Http\Controllers;

use App\Enums\EstadoEmpresa;
use App\Models\Ajuste;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Pago;
use App\Support\Formato;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(): View
    {
        $diasAviso = Ajuste::entero('dias_aviso_abono');
        $orden = ['vencido' => 0, 'proximo' => 1, 'al_dia' => 2, 'inactivo' => 3];

        $clientes = Cliente::with(['empresa.rubro', 'pagos'])->get()
            ->map(fn (Cliente $c) => ['cliente' => $c, 'abono' => $c->estadoAbono($diasAviso)])
            ->sortBy(fn ($x) => [$orden[$x['abono']['estado']], $x['cliente']->empresa->nombre])
            ->values();

        return view('clientes.index', [
            'clientes' => $clientes,
            'abonoTotal' => Cliente::where('activo', true)->sum('abono_mensual'),
        ]);
    }

    public function create(Empresa $empresa): View|RedirectResponse
    {
        if ($empresa->cliente) {
            return redirect()->route('clientes.show', $empresa->cliente);
        }

        return view('clientes.form', ['cliente' => new Cliente(['fecha_alta' => today()]), 'empresa' => $empresa]);
    }

    public function store(Request $request, Empresa $empresa): RedirectResponse
    {
        if ($empresa->cliente) {
            return redirect()->route('clientes.show', $empresa->cliente);
        }
        $datos = $this->validar($request);

        $cliente = DB::transaction(function () use ($empresa, $datos, $request) {
            $cliente = $empresa->cliente()->create($datos);
            $empresa->cambiarEstado(EstadoEmpresa::Cliente, $request->user()->id);

            if ($request->boolean('registrar_pago_inicial') && (float) $datos['monto_inicial'] > 0) {
                $cliente->pagos()->create([
                    'usuario_id' => $request->user()->id,
                    'fecha_pago' => $datos['fecha_alta'],
                    'monto' => $datos['monto_inicial'],
                    'periodo' => 'inicial',
                ]);
            }

            return $cliente;
        });

        return redirect()->route('clientes.show', $cliente)->with('ok', '¡Nuevo cliente cargado!');
    }

    public function show(Cliente $cliente): View
    {
        $cliente->load(['empresa.rubro', 'pagos.usuario']);
        $diasAviso = Ajuste::entero('dias_aviso_abono');

        return view('clientes.show', [
            'cliente' => $cliente,
            'cuotas' => array_reverse($cliente->cuotas(null, $diasAviso)),
            'abono' => $cliente->estadoAbono($diasAviso),
            'periodos' => $this->periodosParaPago($cliente),
        ]);
    }

    public function edit(Cliente $cliente): View
    {
        return view('clientes.form', ['cliente' => $cliente, 'empresa' => $cliente->empresa]);
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        $cliente->update($this->validar($request) + ['activo' => $request->boolean('activo')]);

        return redirect()->route('clientes.show', $cliente)->with('ok', 'Datos del cliente guardados.');
    }

    /** @return array<string,mixed> */
    private function validar(Request $request): array
    {
        $request->merge([
            'monto_inicial' => Formato::leerMonto($request->input('monto_inicial')),
            'abono_mensual' => Formato::leerMonto($request->input('abono_mensual')),
        ]);

        return $request->validate([
            'servicio' => ['required', 'string', 'max:200'],
            'fecha_alta' => ['required', 'date'],
            'monto_inicial' => ['required', 'numeric', 'min:0'],
            'abono_mensual' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    /**
     * Opciones del selector "mes que paga": pago inicial, los abonos impagos
     * (empezando por el más viejo) y hasta 6 meses adelantados.
     *
     * @return array<string,string>
     */
    private function periodosParaPago(Cliente $cliente): array
    {
        $pagados = array_flip($cliente->pagos->pluck('periodo')->all());
        $opciones = [];
        $alta = CarbonImmutable::parse($cliente->fecha_alta);
        $hasta = CarbonImmutable::today()->addMonths(6);

        for ($n = 1; $n <= 240; $n++) {
            $vence = $alta->addMonthsNoOverflow($n);
            if ($vence->gt($hasta)) {
                break;
            }
            $periodo = $vence->format('Y-m');
            if (! isset($pagados[$periodo])) {
                $opciones[$periodo] = Pago::nombrePeriodo($periodo).' (vence '.$vence->format('d/m').')';
            }
        }

        return $opciones + ['inicial' => 'Pago inicial'];
    }
}
