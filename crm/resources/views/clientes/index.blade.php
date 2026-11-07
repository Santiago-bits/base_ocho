@extends('layouts.app')
@section('titulo', 'Clientes')
@use('App\Support\Formato')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Clientes</h1>
            <p class="muted">{{ $clientes->count() }} {{ $clientes->count() === 1 ? 'cliente' : 'clientes' }} · Abonos activos: {{ Formato::moneda($abonoTotal) }}/mes</p>
        </div>
        <div class="acciones">
            @if ($clientes->isNotEmpty())
                <button type="button" class="btn btn-sec" data-exportar-tabla="#tabla-clientes" data-nombre="clientes">Exportar a Excel</button>
            @endif
        </div>
    </div>

    <div class="card">
        @if ($clientes->isEmpty())
            <p class="vacio">Todavía no hay clientes. Cuando una empresa pase a «Cliente», se carga acá con su servicio y abono.</p>
        @else
            <table class="tabla tabla-responsive" id="tabla-clientes">
                <thead>
                    <tr><th>Cliente</th><th>Servicio</th><th>Alta</th><th class="num">Abono</th><th>Estado del abono</th></tr>
                </thead>
                <tbody>
                    @foreach ($clientes as ['cliente' => $cliente, 'abono' => $abono])
                        <tr>
                            <td class="principal">
                                <a href="{{ route('clientes.show', $cliente) }}" class="titulo-fila">{{ $cliente->empresa->nombre }}</a>
                                <div class="muted chico">{{ $cliente->empresa->rubro->nombre }}</div>
                            </td>
                            <td data-label="Servicio">{{ $cliente->servicio }}</td>
                            <td data-label="Alta">{{ $cliente->fecha_alta->format('d/m/Y') }}</td>
                            <td data-label="Abono" class="num">{{ Formato::moneda($cliente->abono_mensual) }}</td>
                            <td data-label="Estado del abono">
                                @switch($abono['estado'])
                                    @case('vencido')
                                        <span class="pastilla error">{{ count($abono['vencidas']) }} {{ count($abono['vencidas']) === 1 ? 'mes vencido' : 'meses vencidos' }}</span>
                                        @break
                                    @case('proximo')
                                        <span class="pastilla aviso">Vence {{ $abono['proxima']['vence']->format('d/m') }}</span>
                                        @break
                                    @case('al_dia')
                                        <span class="pastilla ok">Al día</span>
                                        @if ($abono['proxima']) <span class="muted chico">próximo {{ $abono['proxima']['vence']->format('d/m') }}</span> @endif
                                        @break
                                    @default
                                        <span class="pastilla">Inactivo</span>
                                @endswitch
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/xlsx.full.min.js') }}" defer></script>
@endpush
