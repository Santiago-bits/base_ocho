@extends('layouts.app')
@section('titulo', $empresa->nombre)
@use('App\Enums\EstadoEmpresa')
@use('App\Enums\Canal')
@use('App\Support\Formato')

@php
    $numeroWa = $empresa->numeroWhatsapp();
    $canalInicial = $numeroWa ? 'whatsapp' : ($empresa->email ? 'email' : 'llamada');
    $casos = EstadoEmpresa::cases();
    $siguiente = in_array($empresa->estado, [EstadoEmpresa::Cliente, EstadoEmpresa::Perdido, EstadoEmpresa::NoContactar], true)
        ? null
        : ($casos[$empresa->estado->orden() + 1] ?? null);
    $limiteAlcanzado = $whatsappHoy >= $limiteWhatsapp;
@endphp

@section('contenido')
    <a href="{{ route('empresas.index') }}" class="volver">← Empresas</a>

    <div class="ficha-cabecera">
        <div>
            <h1>{{ $empresa->nombre }}</h1>
            <p class="muted">{{ $empresa->rubro->nombre }}{{ $empresa->ciudad ? ' · '.$empresa->ciudad : '' }}</p>
        </div>
        @include('partials.estado', ['estado' => $empresa->estado])
    </div>

    @if ($empresa->estado === EstadoEmpresa::NoContactar)
        <div class="alerta error">Esta empresa está marcada como <strong>No contactar</strong>.</div>
    @endif

    <div class="acciones-rapidas">
        @if ($numeroWa)
            <a href="#contacto" class="btn btn-wa" data-canal-rapido="whatsapp">WhatsApp</a>
        @endif
        @if ($empresa->email)
            <a href="#contacto" class="btn" data-canal-rapido="email">Email</a>
        @endif
        @if ($empresa->telefono)
            <a href="tel:{{ preg_replace('/[^\d+]/', '', $empresa->telefono) }}" class="btn btn-sec">Llamar</a>
        @endif
        <a href="{{ route('empresas.edit', $empresa) }}" class="btn btn-sec">Editar datos</a>
    </div>

    <div class="ficha-grid">
        <div>
            {{-- Estado --}}
            <section class="card">
                <h2>Estado</h2>
                <form method="post" action="{{ route('empresas.estado', $empresa) }}" class="fila-form">
                    @csrf @method('patch')
                    <select name="estado" data-autosubmit aria-label="Estado">
                        @foreach (EstadoEmpresa::opciones() as $valor => $etiqueta)
                            <option value="{{ $valor }}" @selected($empresa->estado->value === $valor)>{{ $etiqueta }}</option>
                        @endforeach
                    </select>
                    <noscript><button type="submit" class="btn btn-sec">Guardar</button></noscript>
                </form>
                @if ($siguiente)
                    <form method="post" action="{{ route('empresas.estado', $empresa) }}" style="margin-top:8px">
                        @csrf @method('patch')
                        <input type="hidden" name="estado" value="{{ $siguiente->value }}">
                        <button type="submit" class="btn-link">Pasar a «{{ $siguiente->etiqueta() }}» →</button>
                    </form>
                @endif
            </section>

            {{-- Próxima acción --}}
            <section class="card">
                <h2>Próxima acción</h2>
                @if ($empresa->proxima_accion_fecha)
                    <p>
                        <span @class(['pastilla', 'error' => $empresa->proxima_accion_fecha->isPast() && ! $empresa->proxima_accion_fecha->isToday(), 'aviso' => $empresa->proxima_accion_fecha->isToday()])>
                            {{ ucfirst($empresa->proxima_accion_fecha->locale('es')->isoFormat('ddd D/M')) }}
                        </span>
                        {{ $empresa->proxima_accion ?: 'Seguimiento' }}
                    </p>
                @endif
                <form method="post" action="{{ route('empresas.proxima', $empresa) }}" class="form">
                    @csrf @method('patch')
                    <div class="form-grid">
                        <label>Qué hay que hacer
                            <input type="text" name="proxima_accion" value="{{ old('proxima_accion', $empresa->proxima_accion) }}" maxlength="255" placeholder="Ej.: seguimiento por WhatsApp">
                        </label>
                        <label>Fecha
                            <input type="date" name="proxima_accion_fecha" id="proxima-fecha" value="{{ old('proxima_accion_fecha', $empresa->proxima_accion_fecha?->format('Y-m-d')) }}" required>
                        </label>
                    </div>
                    <div class="chips">
                        <button type="button" class="chip" data-fecha-rapida="1" data-destino="#proxima-fecha">Mañana</button>
                        <button type="button" class="chip" data-fecha-rapida="3" data-destino="#proxima-fecha">En 3 días</button>
                        <button type="button" class="chip" data-fecha-rapida="lunes" data-destino="#proxima-fecha">Próximo lunes</button>
                        <button type="button" class="chip" data-fecha-rapida="7" data-destino="#proxima-fecha">En 1 semana</button>
                    </div>
                    <div class="botonera">
                        <button type="submit" class="btn btn-sec">Guardar próxima acción</button>
                    </div>
                </form>
                @if ($empresa->proxima_accion_fecha)
                    <form method="post" action="{{ route('empresas.proxima', $empresa) }}" style="margin-top:8px">
                        @csrf @method('patch')
                        <input type="hidden" name="limpiar" value="1">
                        <button type="submit" class="btn-link">✓ Marcar como hecha</button>
                    </form>
                @endif
            </section>

            {{-- Registrar contacto --}}
            <section class="card" id="contacto">
                <h2>Registrar contacto</h2>
                <form method="post" action="{{ route('contactos.store', $empresa) }}" class="form" id="form-contacto"
                      data-wa="{{ $numeroWa }}" data-email="{{ $empresa->email }}">
                    @csrf
                    <div class="chips" role="radiogroup" aria-label="Canal">
                        @foreach (Canal::opciones() as $valor => $etiqueta)
                            <label class="chip"><input type="radio" name="canal" value="{{ $valor }}" @checked(old('canal', $canalInicial) === $valor)> {{ $etiqueta }}</label>
                        @endforeach
                    </div>

                    <div class="solo-canal" data-canal="whatsapp">
                        @if (! $numeroWa)
                            <div class="alerta aviso">Esta empresa no tiene WhatsApp ni teléfono cargado.</div>
                        @elseif ($limiteAlcanzado)
                            <div class="alerta error">Hoy ya van {{ $whatsappHoy }} WhatsApp (límite {{ $limiteWhatsapp }}). Mejor dejarlo para mañana.</div>
                        @else
                            <p class="muted chico">Hoy van {{ $whatsappHoy }} de {{ $limiteWhatsapp }} WhatsApp.</p>
                        @endif
                    </div>

                    <label>Plantilla
                        <select name="plantilla_id" id="plantilla">
                            <option value="">Sin plantilla</option>
                            @foreach ($plantillas as $p)
                                <option value="{{ $p['id'] }}" data-canal="{{ $p['canal'] }}" @selected(old('plantilla_id') == $p['id'])>{{ $p['nombre'] }}</option>
                            @endforeach
                        </select>
                        @if ($plantillas->isEmpty())
                            <span class="ayuda">No hay plantillas para este rubro. <a href="{{ route('plantillas.create') }}">Crear una</a>.</span>
                        @endif
                    </label>

                    <label class="solo-canal" data-canal="email">Asunto
                        <input type="text" name="asunto" id="asunto" maxlength="200">
                    </label>

                    <label>Mensaje
                        <textarea name="mensaje" id="mensaje" rows="6" maxlength="5000">{{ old('mensaje') }}</textarea>
                    </label>

                    <label class="check"><input type="checkbox" name="pdf_enviado" value="1" @checked(old('pdf_enviado'))> Mandé el PDF</label>

                    <details>
                        <summary>Respuesta, resultado y fecha</summary>
                        <div class="form-grid">
                            <label>¿Respondió?
                                <select name="respondio">
                                    <option value="">Pendiente</option>
                                    <option value="si" @selected(old('respondio') === 'si')>Sí, respondió</option>
                                    <option value="no" @selected(old('respondio') === 'no')>No respondió</option>
                                </select>
                            </label>
                            <label>Fecha y hora
                                <input type="datetime-local" name="fecha_hora" value="{{ old('fecha_hora') }}">
                                <span class="ayuda">Vacío = ahora</span>
                            </label>
                            <label class="ancho">Respuesta o resultado
                                <textarea name="resultado" rows="3" maxlength="5000">{{ old('resultado') }}</textarea>
                            </label>
                        </div>
                    </details>

                    <div class="botonera">
                        @if ($numeroWa)
                            <button type="button" class="btn btn-wa solo-canal" data-canal="whatsapp" data-abrir="whatsapp">
                                {{ $limiteAlcanzado ? 'Abrir WhatsApp igual y registrar' : 'Abrir WhatsApp y registrar' }}
                            </button>
                        @endif
                        @if ($empresa->email)
                            <button type="button" class="btn solo-canal" data-canal="email" data-abrir="email">Abrir email y registrar</button>
                        @endif
                        <button type="submit" class="btn btn-sec">Solo registrar</button>
                    </div>
                    @if ($numeroWa)
                        <p class="chico solo-canal" data-canal="whatsapp" style="margin-top:8px">
                            <a href="https://wa.me/{{ $numeroWa }}" target="_blank" rel="noopener">Abrir el chat sin registrar nada</a>
                        </p>
                    @endif
                </form>
                <script type="application/json" id="plantillas-json">@json($plantillas)</script>
            </section>
        </div>

        <div>
            {{-- Datos --}}
            <section class="card">
                <div class="card-cab">
                    <h2>Datos</h2>
                    <a href="{{ route('empresas.edit', $empresa) }}" class="chico">Editar</a>
                </div>
                <dl class="datos">
                    <dt>Rubro</dt><dd>{{ $empresa->rubro->nombre }}</dd>
                    <dt>Ciudad</dt><dd>{{ $empresa->ciudad ?: '—' }}</dd>
                    <dt>Dirección</dt><dd>{{ $empresa->direccion ?: '—' }}</dd>
                    <dt>Teléfono</dt><dd>{{ $empresa->telefono ?: '—' }}</dd>
                    <dt>WhatsApp</dt><dd>{{ $empresa->whatsapp ?: '—' }}</dd>
                    <dt>Email</dt><dd>@if ($empresa->email)<a href="mailto:{{ $empresa->email }}">{{ $empresa->email }}</a>@else — @endif</dd>
                    <dt>Web / Redes</dt>
                    <dd>
                        @if ($empresa->web_redes && preg_match('#^https?://#i', $empresa->web_redes))
                            <a href="{{ $empresa->web_redes }}" target="_blank" rel="noopener">{{ $empresa->web_redes }}</a>
                        @else
                            {{ $empresa->web_redes ?: '—' }}
                        @endif
                    </dd>
                    <dt>Fuente</dt><dd>{{ $empresa->fuente ?: '—' }}</dd>
                    <dt>Alta</dt><dd>{{ $empresa->created_at->format('d/m/Y') }}</dd>
                </dl>
                @if ($empresa->notas)
                    <h3 style="margin-top:12px">Notas</h3>
                    <div class="t-msg">{{ $empresa->notas }}</div>
                @endif
            </section>

            {{-- Cliente --}}
            <section class="card">
                <h2>Cliente</h2>
                @if ($empresa->cliente)
                    @php $abono = $empresa->cliente->estadoAbono(\App\Models\Ajuste::entero('dias_aviso_abono')); @endphp
                    <p>{{ $empresa->cliente->servicio }} · {{ Formato::moneda($empresa->cliente->abono_mensual) }}/mes</p>
                    <p>
                        @if ($abono['estado'] === 'vencido')
                            <span class="pastilla error">Abono vencido</span>
                        @elseif ($abono['estado'] === 'proximo')
                            <span class="pastilla aviso">Vence {{ $abono['proxima']['vence']->format('d/m') }}</span>
                        @elseif ($abono['estado'] === 'al_dia')
                            <span class="pastilla ok">Al día</span>
                        @endif
                    </p>
                    <a href="{{ route('clientes.show', $empresa->cliente) }}" class="btn btn-sec btn-chico">Ver pagos</a>
                @else
                    <p class="muted">Todavía no es cliente.</p>
                    <a href="{{ route('clientes.create', $empresa) }}" class="btn btn-sec btn-chico">Dar de alta como cliente</a>
                @endif
            </section>
        </div>
    </div>

    {{-- Historial --}}
    <section class="card">
        <h2>Historial</h2>
        @if ($historial->isEmpty())
            <p class="vacio">Sin registros todavía.</p>
        @else
            <ol class="timeline">
                @foreach ($historial as $h)
                    @if ($h['tipo'] === 'estado')
                        @php $cambio = $h['item']; @endphp
                        <li class="t-estado">
                            <div class="t-cab muted">
                                {{ $cambio->created_at->format('d/m/Y H:i') }} · {{ $cambio->usuario?->name ?? 'Sistema' }} ·
                                Estado: {{ $cambio->estado_anterior?->etiqueta() ?? '—' }} → <strong>{{ $cambio->estado_nuevo->etiqueta() }}</strong>
                            </div>
                        </li>
                    @else
                        @php $c = $h['item']; $esImport = $c->canal === Canal::Importado; @endphp
                        <li class="t-contacto canal-{{ $c->canal->value }}">
                            <div class="t-cab">
                                <strong>{{ $c->canal->etiqueta() }}</strong>
                                <span class="muted">{{ $c->fecha_hora->format('d/m/Y H:i') }} · {{ $c->usuario?->name ?? 'Sistema' }}</span>
                                @if ($c->plantilla) <span class="pastilla">{{ $c->plantilla->nombre }}</span> @endif
                                @if ($c->pdf_enviado) <span class="pastilla">PDF</span> @endif
                                @unless ($esImport)
                                    <span @class(['pastilla', 'ok' => $c->respondio === 'si', 'error' => $c->respondio === 'no', 'aviso' => $c->respondio === null])>{{ $c->etiquetaRespuesta() }}</span>
                                @endunless
                            </div>
                            @if ($c->mensaje)
                                <div class="t-msg">{{ $c->mensaje }}</div>
                            @endif
                            @if ($c->resultado)
                                <div class="t-res"><strong>Resultado:</strong> {{ $c->resultado }}</div>
                            @endif
                            <div class="t-acciones">
                                @if (! $esImport && $c->respondio === null)
                                    <form method="post" action="{{ route('contactos.update', $c) }}">
                                        @csrf @method('patch')
                                        <input type="hidden" name="respondio" value="si">
                                        <button type="submit" class="btn btn-sec btn-chico">Respondió</button>
                                    </form>
                                    <form method="post" action="{{ route('contactos.update', $c) }}">
                                        @csrf @method('patch')
                                        <input type="hidden" name="respondio" value="no">
                                        <button type="submit" class="btn btn-sec btn-chico">No respondió</button>
                                    </form>
                                @endif
                                @unless ($esImport)
                                    <details>
                                        <summary>Anotar resultado</summary>
                                        <form method="post" action="{{ route('contactos.update', $c) }}" class="form">
                                            @csrf @method('patch')
                                            <select name="respondio">
                                                <option value="pendiente" @selected($c->respondio === null)>Pendiente</option>
                                                <option value="si" @selected($c->respondio === 'si')>Respondió</option>
                                                <option value="no" @selected($c->respondio === 'no')>No respondió</option>
                                            </select>
                                            <textarea name="resultado" rows="2" maxlength="5000" placeholder="Qué contestaron">{{ $c->resultado }}</textarea>
                                            <button type="submit" class="btn btn-sec btn-chico">Guardar</button>
                                        </form>
                                    </details>
                                @endunless
                                <form method="post" action="{{ route('contactos.destroy', $c) }}" data-confirmar="¿Borrar este registro del historial?">
                                    @csrf @method('delete')
                                    <button type="submit" class="btn-link peligro">Borrar</button>
                                </form>
                            </div>
                        </li>
                    @endif
                @endforeach
            </ol>
        @endif
    </section>
@endsection
