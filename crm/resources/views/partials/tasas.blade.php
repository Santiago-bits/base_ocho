@use('App\Support\Formato')
@php
    $bloques = [
        'rubro' => ['Por rubro', 'Rubro'],
        'plantilla' => ['Por plantilla', 'Plantilla'],
        'canal' => ['Por canal', 'Canal'],
        'franja' => ['Por franja horaria', 'Franja'],
    ];
@endphp
<div class="grid-2">
    @foreach ($bloques as $clave => [$titulo, $columna])
        <div class="card">
            <div class="card-cab">
                <h3>{{ $titulo }}</h3>
                @if (! empty($tasas[$clave]) && ($exportable ?? false))
                    <button type="button" class="btn-link" data-exportar-tabla="#tasa-{{ $clave }}" data-nombre="tasa-{{ $clave }}">Exportar</button>
                @endif
            </div>
            @if (empty($tasas[$clave]))
                <p class="muted">Sin envíos en el período.</p>
            @else
                <table class="tabla tabla-chica" id="tasa-{{ $clave }}">
                    <thead>
                        <tr><th>{{ $columna }}</th><th class="num">Envíos</th><th class="num">Resp.</th><th>Tasa</th></tr>
                    </thead>
                    <tbody>
                        @foreach ($tasas[$clave] as $nombre => $fila)
                            <tr>
                                <td>{{ $nombre }}</td>
                                <td class="num">{{ $fila['enviados'] }}</td>
                                <td class="num">{{ $fila['respondieron'] }}</td>
                                <td class="celda-tasa">
                                    <span class="barra" aria-hidden="true"><span style="width: {{ min(100, $fila['tasa']) }}%"></span></span>
                                    {{ Formato::porcentaje($fila['tasa']) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @endforeach
</div>
<p class="muted chico">Tasa = envíos marcados como «Respondió» sobre el total de envíos. Los pendientes cuentan como no respondidos hasta que los marquen. Franjas: mañana 6–13 h, tarde 13–20 h, noche 20–6 h.</p>
