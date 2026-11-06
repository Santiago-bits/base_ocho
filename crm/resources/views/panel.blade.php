@extends('layouts.app')
@section('titulo', 'Panel')
@use('App\Enums\EstadoEmpresa')
@use('App\Support\Formato')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Hola, {{ auth()->user()->name }}</h1>
            <p class="muted">{{ ucfirst($hoy->locale('es')->isoFormat('dddd D [de] MMMM')) }}</p>
        </div>
        <div class="acciones">
            <a href="{{ route('empresas.index', ['sin_wa' => 1]) }}" class="btn btn-sec">Sin contactar con WhatsApp</a>
            <a href="{{ route('empresas.create') }}" class="btn">+ Nueva empresa</a>
        </div>
    </div>

    @if ($whatsappHoy >= $limiteWhatsapp)
        <div class="alerta error" role="alert">
            <strong>Límite de WhatsApp alcanzado:</strong> hoy ya van {{ $whatsappHoy }} de {{ $limiteWhatsapp }}.
            Paren por hoy para que no suspendan la cuenta.
        </div>
    @elseif ($whatsappHoy >= $limiteWhatsapp - 2 && $whatsappHoy > 0)
        <div class="alerta aviso">Hoy van {{ $whatsappHoy }} de {{ $limiteWhatsapp }} WhatsApp. Quedan {{ $limiteWhatsapp - $whatsappHoy }}.</div>
    @endif

    @if ($totalEmpresas === 0)
        <div class="card">
            <h2>Todavía no hay empresas cargadas</h2>
            <p class="muted">Empezá creando los rubros en Ajustes y después importá los Excels.</p>
            <div class="botonera">
                <a href="{{ route('ajustes') }}" class="btn btn-sec">1. Crear rubros</a>
                <a href="{{ route('importar') }}" class="btn">2. Importar Excel</a>
            </div>
        </div>
    @endif

    <div class="kpis">
        <div class="kpi">
            <div class="valor">{{ $contactosHoy }}</div>
            <div class="etiqueta">Contactos hoy</div>
        </div>
        <div class="kpi">
            <div class="valor">{{ $contactosSemana }}</div>
            <div class="etiqueta">Contactos esta semana</div>
        </div>
        <div @class(['kpi', 'alerta-kpi' => $whatsappHoy >= $limiteWhatsapp, 'aviso-kpi' => $whatsappHoy < $limiteWhatsapp && $whatsappHoy >= $limiteWhatsapp - 2 && $whatsappHoy > 0])>
            <div class="valor">{{ $whatsappHoy }}<span class="muted" style="font-size:1rem"> / {{ $limiteWhatsapp }}</span></div>
            <div class="etiqueta">WhatsApp hoy</div>
            <div class="medidor"><span style="width: {{ $limiteWhatsapp > 0 ? min(100, round($whatsappHoy * 100 / $limiteWhatsapp)) : 0 }}%"></span></div>
        </div>
        <div class="kpi">
            <div class="valor">{{ $totalEmpresas }}</div>
            <div class="etiqueta">Empresas cargadas</div>
        </div>
    </div>

    <div class="grid-2">
        <section class="card">
            <div class="card-cab">
                <h2>Seguimientos para hoy</h2>
                @if ($seguimientosHoy->isNotEmpty())
                    <a href="{{ route('empresas.index', ['seguimiento' => 'hoy']) }}" class="chico">Ver todos</a>
                @endif
            </div>
            @forelse ($seguimientosHoy as $empresa)
                @if ($loop->first)<ul class="lista">@endif
                <li>
                    <span>
                        <a href="{{ route('empresas.show', $empresa) }}">{{ $empresa->nombre }}</a>
                        <span class="sub">{{ $empresa->proxima_accion ?: 'Seguimiento' }} · {{ $empresa->rubro->nombre }}</span>
                    </span>
                    @include('partials.estado', ['estado' => $empresa->estado])
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="vacio">No hay seguimientos para hoy.</p>
            @endforelse
        </section>

        <section class="card">
            <div class="card-cab">
                <h2>Seguimientos vencidos</h2>
                @if ($seguimientosVencidos->isNotEmpty())
                    <a href="{{ route('empresas.index', ['seguimiento' => 'vencidos', 'orden' => 'proxima']) }}" class="chico">Ver todos</a>
                @endif
            </div>
            @forelse ($seguimientosVencidos as $empresa)
                @if ($loop->first)<ul class="lista">@endif
                <li>
                    <span>
                        <a href="{{ route('empresas.show', $empresa) }}">{{ $empresa->nombre }}</a>
                        <span class="sub">{{ $empresa->proxima_accion ?: 'Seguimiento' }} · {{ $empresa->rubro->nombre }}</span>
                    </span>
                    <span class="pastilla error">{{ $empresa->proxima_accion_fecha->format('d/m') }}</span>
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="vacio">Nada vencido. 👌</p>
            @endforelse
        </section>
    </div>

    @if ($abonos->isNotEmpty())
        <section class="card" style="margin-top:16px">
            <div class="card-cab">
                <h2>Abonos vencidos y por vencer</h2>
                <a href="{{ route('clientes.index') }}" class="chico">Ver clientes</a>
            </div>
            <ul class="lista">
                @foreach ($abonos as ['cliente' => $cliente, 'abono' => $abono])
                    <li>
                        <span>
                            <a href="{{ route('clientes.show', $cliente) }}">{{ $cliente->empresa->nombre }}</a>
                            <span class="sub">{{ $cliente->servicio }} · {{ Formato::moneda($cliente->abono_mensual) }}/mes</span>
                        </span>
                        @if ($abono['estado'] === 'vencido')
                            <span class="pastilla error">{{ count($abono['vencidas']) }} {{ count($abono['vencidas']) === 1 ? 'mes vencido' : 'meses vencidos' }}</span>
                        @else
                            <span class="pastilla aviso">Vence {{ $abono['proxima']['vence']->format('d/m') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <div class="grid-2">
        <section class="card">
            <h2>Empresas por estado</h2>
            <ul class="lista">
                @foreach (EstadoEmpresa::cases() as $estado)
                    <li>
                        <a href="{{ route('empresas.index', ['estado' => $estado->value]) }}">@include('partials.estado', ['estado' => $estado])</a>
                        <strong class="num">{{ $porEstado[$estado->value] ?? 0 }}</strong>
                    </li>
                @endforeach
            </ul>
        </section>
        <section class="card">
            <h2>Empresas por rubro</h2>
            @forelse ($porRubro as $rubro)
                @if ($loop->first)<ul class="lista">@endif
                <li>
                    <a href="{{ route('empresas.index', ['rubro' => $rubro->id]) }}">{{ $rubro->nombre }}</a>
                    <strong class="num">{{ $rubro->empresas_count }}</strong>
                </li>
                @if ($loop->last)</ul>@endif
            @empty
                <p class="vacio">Todavía no hay rubros. <a href="{{ route('ajustes') }}">Crealos en Ajustes</a>.</p>
            @endforelse
        </section>
    </div>

    <section style="margin-top:24px">
        <div class="encabezado">
            <h2>Tasa de respuesta · últimos 30 días</h2>
            <a href="{{ route('estadisticas') }}" class="btn btn-sec btn-chico">Ver estadísticas</a>
        </div>
        @include('partials.tasas', ['tasas' => $tasas])
    </section>
@endsection
