@extends('layouts.app')
@section('titulo', 'Empresas')
@use('App\Enums\EstadoEmpresa')
@use('App\Enums\Canal')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Empresas</h1>
            <p class="muted">{{ $empresas->total() }} {{ $empresas->total() === 1 ? 'resultado' : 'resultados' }}</p>
        </div>
        <div class="acciones">
            <button type="button" class="btn btn-sec" data-exportar-url="{{ route('empresas.exportar', request()->query()) }}" data-nombre="empresas">Exportar a Excel</button>
            <a href="{{ route('empresas.create') }}" class="btn">+ Nueva</a>
        </div>
    </div>

    <form method="get" action="{{ route('empresas.index') }}" class="card filtros">
        <div class="form-grid">
            <label class="busqueda">Buscar
                <input type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Nombre, teléfono o email" autocomplete="off">
            </label>
            <label>Rubro
                <select name="rubro">
                    <option value="">Todos</option>
                    @foreach ($rubros as $rubro)
                        <option value="{{ $rubro->id }}" @selected(($filtros['rubro'] ?? '') == $rubro->id)>{{ $rubro->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label>Ciudad
                <select name="ciudad">
                    <option value="">Todas</option>
                    @foreach ($ciudades as $ciudad)
                        <option value="{{ $ciudad }}" @selected(($filtros['ciudad'] ?? '') === $ciudad)>{{ $ciudad }}</option>
                    @endforeach
                </select>
            </label>
            <label>Estado
                <select name="estado">
                    <option value="">Todos</option>
                    @foreach (EstadoEmpresa::opciones() as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(($filtros['estado'] ?? '') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </label>
            <label>Contactada por
                <select name="canal">
                    <option value="">Cualquier canal</option>
                    @foreach (Canal::opciones() as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(($filtros['canal'] ?? '') === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </label>
            <label>Orden
                <select name="orden">
                    <option value="">Nombre</option>
                    <option value="recientes" @selected(($filtros['orden'] ?? '') === 'recientes')>Últimas modificadas</option>
                    <option value="proxima" @selected(($filtros['orden'] ?? '') === 'proxima')>Próxima acción</option>
                </select>
            </label>
        </div>
        <div class="botonera">
            <label class="check"><input type="checkbox" name="sin_wa" value="1" @checked(! empty($filtros['sin_wa']))> Sin contactar con WhatsApp</label>
            @if (! empty($filtros['seguimiento']))
                <input type="hidden" name="seguimiento" value="{{ $filtros['seguimiento'] }}">
                <span class="pastilla aviso">Seguimientos {{ $filtros['seguimiento'] === 'hoy' ? 'de hoy' : 'vencidos' }}</span>
            @endif
            <span style="flex:1"></span>
            <a href="{{ route('empresas.index') }}" class="btn btn-sec">Limpiar</a>
            <button type="submit" class="btn">Filtrar</button>
        </div>
    </form>

    <div class="card">
        @if ($empresas->isEmpty())
            <p class="vacio">No hay empresas con estos filtros.
                @if (empty(array_filter($filtros)))
                    <a href="{{ route('importar') }}">Importá un Excel</a> para empezar.
                @endif
            </p>
        @else
            <table class="tabla tabla-responsive">
                <thead>
                    <tr>
                        <th>Empresa</th>
                        <th>Ciudad</th>
                        <th>Estado</th>
                        <th>Próxima acción</th>
                        <th>Último contacto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($empresas as $empresa)
                        <tr>
                            <td class="principal">
                                <a href="{{ route('empresas.show', $empresa) }}" class="titulo-fila">{{ $empresa->nombre }}</a>
                                <div class="muted chico">{{ $empresa->rubro->nombre }}@if ($empresa->whatsapp_norm) · WhatsApp @endif</div>
                            </td>
                            <td data-label="Ciudad">{{ $empresa->ciudad ?: '—' }}</td>
                            <td data-label="Estado">@include('partials.estado', ['estado' => $empresa->estado])</td>
                            <td data-label="Próxima acción">
                                @if ($empresa->proxima_accion_fecha)
                                    <span @class(['pastilla', 'error' => $empresa->proxima_accion_fecha->isPast() && ! $empresa->proxima_accion_fecha->isToday(), 'aviso' => $empresa->proxima_accion_fecha->isToday()])>{{ $empresa->proxima_accion_fecha->format('d/m') }}</span>
                                    <span class="chico">{{ $empresa->proxima_accion }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td data-label="Último contacto">
                                {{ $empresa->ultimo_contacto ? \Carbon\Carbon::parse($empresa->ultimo_contacto)->format('d/m/Y') : '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            {{ $empresas->links() }}
        @endif
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/xlsx.full.min.js') }}" defer></script>
@endpush
