@extends('layouts.app')
@section('titulo', 'Plantillas')
@use('App\Support\Formato')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Plantillas de mensajes</h1>
            <p class="muted">Variables: <code>{nombre}</code> <code>{ciudad}</code> <code>{rubro}</code> <code>{direccion}</code> <code>{web}</code></p>
        </div>
        <div class="acciones">
            <a href="{{ route('plantillas.create') }}" class="btn">+ Nueva plantilla</a>
        </div>
    </div>

    @forelse ($grupos as $rubro => $plantillas)
        <h2 style="margin-top:8px">{{ $rubro }}</h2>
        <div class="grid-3" style="margin-bottom:16px">
            @foreach ($plantillas as $p)
                @php $tasa = $p->enviados > 0 ? round($p->respondidos * 100 / $p->enviados, 1) : 0; @endphp
                <article class="card" @style(['opacity: .6' => ! $p->activa])>
                    <div class="card-cab">
                        <h3><a href="{{ route('plantillas.edit', $p) }}" style="text-decoration:none">{{ $p->nombre }}</a></h3>
                        <span class="pastilla">{{ $p->canal->etiqueta() }}</span>
                    </div>
                    @unless ($p->activa) <p><span class="pastilla">Desactivada</span></p> @endunless
                    <div class="plantilla-cuerpo">{{ $p->cuerpo }}</div>
                    <p class="chico" style="margin-top:10px">
                        <strong>{{ $p->enviados }}</strong> envíos · <strong>{{ $p->respondidos }}</strong> respondieron
                        @if ($p->enviados > 0) · <strong>{{ Formato::porcentaje($tasa) }}</strong> @endif
                    </p>
                </article>
            @endforeach
        </div>
    @empty
        <div class="card">
            <p class="vacio">Todavía no hay plantillas. Creá una por rubro para poder comparar cuál responde mejor.</p>
        </div>
    @endforelse
@endsection
