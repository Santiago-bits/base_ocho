@extends('layouts.app')
@section('titulo', $cliente->empresa->nombre)
@use('App\Support\Formato')
@use('App\Models\Pago')

@section('contenido')
    <a href="{{ route('clientes.index') }}" class="volver">← Clientes</a>

    <div class="ficha-cabecera">
        <div>
            <h1>{{ $cliente->empresa->nombre }}</h1>
            <p class="muted">{{ $cliente->servicio }} · <a href="{{ route('empresas.show', $cliente->empresa) }}">ver ficha de la empresa</a></p>
        </div>
        @if (! $cliente->activo)
            <span class="pastilla">Inactivo</span>
        @endif
    </div>

    @if ($abono['estado'] === 'vencido')
        <div class="alerta error">
            Abono vencido:
            {{ collect($abono['vencidas'])->map(fn ($c) => Pago::nombrePeriodo($c['periodo']).' (venció el '.$c['vence']->format('d/m').')')->join(', ', ' y ') }}.
        </div>
    @elseif ($abono['estado'] === 'proximo')
        <div class="alerta aviso">El abono de {{ Pago::nombrePeriodo($abono['proxima']['periodo']) }} vence el {{ $abono['proxima']['vence']->format('d/m') }}.</div>
    @endif

    <div class="ficha-grid">
        <div>
            <section class="card">
                <h2>Registrar pago</h2>
                <form method="post" action="{{ route('pagos.store', $cliente) }}" class="form">
                    @csrf
                    <div class="form-grid">
                        <label>Mes que paga
                            <select name="periodo" required>
                                @foreach ($periodos as $valor => $texto)
                                    <option value="{{ $valor }}" @selected(old('periodo') === $valor)>{{ $texto }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Monto
                            <input type="text" name="monto" inputmode="decimal" required value="{{ old('monto', Formato::numero($cliente->abono_mensual)) }}">
                        </label>
                        <label>Fecha de pago
                            <input type="date" name="fecha_pago" required value="{{ old('fecha_pago', today()->format('Y-m-d')) }}">
                        </label>
                        <label>Nota
                            <input type="text" name="nota" maxlength="255" value="{{ old('nota') }}" placeholder="Transferencia, efectivo…">
                        </label>
                    </div>
                    <div class="botonera"><button type="submit" class="btn">Registrar pago</button></div>
                </form>
            </section>

            <section class="card">
                <div class="card-cab">
                    <h2>Pagos</h2>
                    @if ($cliente->pagos->isNotEmpty())
                        <button type="button" class="btn-link" data-exportar-tabla="#tabla-pagos" data-nombre="pagos-{{ \Illuminate\Support\Str::slug($cliente->empresa->nombre) }}">Exportar</button>
                    @endif
                </div>
                @if ($cliente->pagos->isEmpty())
                    <p class="vacio">Sin pagos registrados.</p>
                @else
                    <table class="tabla tabla-responsive" id="tabla-pagos">
                        <thead><tr><th>Mes</th><th>Fecha</th><th class="num">Monto</th><th>Nota</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($cliente->pagos as $pago)
                                <tr>
                                    <td class="principal"><strong>{{ $pago->etiquetaPeriodo() }}</strong></td>
                                    <td data-label="Fecha">{{ $pago->fecha_pago->format('d/m/Y') }}</td>
                                    <td data-label="Monto" class="num">{{ Formato::moneda($pago->monto) }}</td>
                                    <td data-label="Nota">{{ $pago->nota }} <span class="muted chico">{{ $pago->usuario?->name }}</span></td>
                                    <td data-label="">
                                        <form method="post" action="{{ route('pagos.destroy', $pago) }}" data-confirmar="¿Borrar este pago?">
                                            @csrf @method('delete')
                                            <button type="submit" class="btn-link peligro">Borrar</button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </section>
        </div>

        <div>
            <section class="card">
                <div class="card-cab">
                    <h2>Servicio</h2>
                    <a href="{{ route('clientes.edit', $cliente) }}" class="chico">Editar</a>
                </div>
                <dl class="datos">
                    <dt>Servicio</dt><dd>{{ $cliente->servicio }}</dd>
                    <dt>Alta</dt><dd>{{ $cliente->fecha_alta->format('d/m/Y') }}</dd>
                    <dt>Monto inicial</dt><dd>{{ Formato::moneda($cliente->monto_inicial) }}</dd>
                    <dt>Abono mensual</dt><dd>{{ Formato::moneda($cliente->abono_mensual) }}</dd>
                    <dt>Vence</dt><dd>el {{ $cliente->fecha_alta->day }} de cada mes</dd>
                </dl>
                @if ($cliente->notas)
                    <div class="t-msg" style="margin-top:12px">{{ $cliente->notas }}</div>
                @endif
            </section>

            <section class="card">
                <h2>Abonos</h2>
                @if (! $cliente->activo || (float) $cliente->abono_mensual <= 0)
                    <p class="vacio">Sin abono mensual activo.</p>
                @else
                    <ul class="lista">
                        @foreach (array_slice($cuotas, 0, 12) as $cuota)
                            <li>
                                <span>{{ Pago::nombrePeriodo($cuota['periodo']) }} <span class="sub">vence {{ $cuota['vence']->format('d/m/Y') }}</span></span>
                                @switch($cuota['estado'])
                                    @case('pagado') <span class="pastilla ok">Pagado</span> @break
                                    @case('vencido') <span class="pastilla error">Vencido</span> @break
                                    @case('proximo') <span class="pastilla aviso">Por vencer</span> @break
                                    @default <span class="pastilla">Pendiente</span>
                                @endswitch
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/xlsx.full.min.js') }}" defer></script>
@endpush
