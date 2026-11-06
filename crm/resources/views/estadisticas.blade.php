@extends('layouts.app')
@section('titulo', 'Estadísticas')
@use('App\Support\Formato')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Estadísticas</h1>
            <p class="muted">Del {{ $desde->format('d/m/Y') }} al {{ $hasta->format('d/m/Y') }}</p>
        </div>
    </div>

    <form method="get" class="card">
        <div class="form-grid">
            <label>Desde <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"></label>
            <label>Hasta <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"></label>
        </div>
        <div class="botonera">
            <a class="chip" href="{{ route('estadisticas', ['desde' => today()->subDays(7)->format('Y-m-d')]) }}">7 días</a>
            <a class="chip" href="{{ route('estadisticas') }}">30 días</a>
            <a class="chip" href="{{ route('estadisticas', ['desde' => today()->subDays(90)->format('Y-m-d')]) }}">90 días</a>
            <a class="chip" href="{{ route('estadisticas', ['desde' => '2000-01-01']) }}">Todo</a>
            <span style="flex:1"></span>
            <button type="submit" class="btn">Ver</button>
        </div>
    </form>

    <div class="kpis">
        <div class="kpi"><div class="valor">{{ $tasas['total']['enviados'] }}</div><div class="etiqueta">Envíos</div></div>
        <div class="kpi"><div class="valor">{{ $tasas['total']['respondieron'] }}</div><div class="etiqueta">Respondieron</div></div>
        <div class="kpi"><div class="valor">{{ Formato::porcentaje($tasas['total']['tasa']) }}</div><div class="etiqueta">Tasa de respuesta</div></div>
        <div class="kpi"><div class="valor">{{ $tasas['total']['pendientes'] }}</div><div class="etiqueta">Sin marcar respuesta</div></div>
    </div>

    @include('partials.tasas', ['tasas' => $tasas, 'exportable' => true])

    <section class="card" style="margin-top:16px">
        <div class="card-cab">
            <h2>Embudo por rubro (estado actual)</h2>
            <button type="button" class="btn-link" data-exportar-tabla="#tabla-embudo" data-nombre="embudo-por-rubro">Exportar</button>
        </div>
        <div class="tabla-scroll">
            <table class="tabla tabla-chica" id="tabla-embudo">
                <thead>
                    <tr>
                        <th>Rubro</th>
                        @foreach ($estados as $estado)<th class="num">{{ $estado->etiqueta() }}</th>@endforeach
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($rubros as $rubro)
                        @php $fila = ($conteos[$rubro->id] ?? collect())->pluck('total', 'estado'); @endphp
                        <tr>
                            <td><a href="{{ route('empresas.index', ['rubro' => $rubro->id]) }}">{{ $rubro->nombre }}</a></td>
                            @foreach ($estados as $estado)
                                <td class="num">{{ $fila[$estado->value] ?? 0 }}</td>
                            @endforeach
                            <td class="num"><strong>{{ $fila->sum() }}</strong></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($estados) + 2 }}" class="muted">Sin rubros.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/xlsx.full.min.js') }}" defer></script>
@endpush
