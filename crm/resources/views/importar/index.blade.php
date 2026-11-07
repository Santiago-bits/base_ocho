@extends('layouts.app')
@section('titulo', 'Importar Excel')

@section('contenido')
    <div class="encabezado">
        <div>
            <h1>Importar Excel</h1>
            <p class="muted">Archivos .xlsx, .xls o .csv. El archivo se lee en tu navegador; solo se suben las filas.</p>
        </div>
    </div>

    <div class="pasos" id="pasos">
        <span data-paso="1" class="activo">1. Archivo</span>
        <span data-paso="2">2. Columnas</span>
        <span data-paso="3">3. Duplicados</span>
        <span data-paso="4">4. Listo</span>
    </div>

    <div id="error-import" class="alerta error" hidden></div>

    {{-- Paso 1 --}}
    <section class="card" data-panel="1">
        <h2>1. Elegí el archivo y el rubro</h2>
        <div class="form-grid">
            <label>Rubro
                <select id="rubro">
                    <option value="">Elegí un rubro</option>
                    @foreach ($rubros as $rubro)
                        <option value="{{ $rubro->id }}">{{ $rubro->nombre }}</option>
                    @endforeach
                    <option value="nuevo">+ Rubro nuevo…</option>
                </select>
            </label>
            <label id="rubro-nuevo-wrap" hidden>Nombre del rubro nuevo
                <input type="text" id="rubro-nuevo" maxlength="100" placeholder="Ej.: Ferreterías">
            </label>
            <label class="ancho">Archivo
                <input type="file" id="archivo" accept=".xlsx,.xls,.csv,.ods">
            </label>
            <label id="hoja-wrap" hidden>Hoja
                <select id="hoja"></select>
            </label>
        </div>
        <p id="info-archivo" class="muted" style="margin-top:12px"></p>
        <div class="botonera">
            <button type="button" class="btn" id="ir-paso-2" disabled>Siguiente: mapear columnas</button>
        </div>
    </section>

    {{-- Paso 2 --}}
    <section class="card" data-panel="2" hidden>
        <h2>2. ¿Qué es cada columna?</h2>
        <p class="muted">Ya elegí lo que me pareció por el nombre de cada columna. Revisalo. Las columnas que no te sirvan dejalas en «No importar».
            Si dos columnas van al mismo campo, la segunda se agrega a las notas.</p>
        <div class="tabla-scroll">
            <table class="tabla tabla-responsive">
                <thead><tr><th>Columna del Excel</th><th>Ejemplos</th><th>Campo del sistema</th></tr></thead>
                <tbody id="mapeo"></tbody>
            </table>
        </div>

        <div id="estados-wrap" hidden style="margin-top:16px">
            <h3>¿A qué estado corresponde cada valor de «Estado envío»?</h3>
            <p class="muted chico">El texto original queda igual en el historial; esto solo define el estado inicial de la empresa.</p>
            <div class="tabla-scroll">
                <table class="tabla tabla-responsive">
                    <thead><tr><th>Valor en el Excel</th><th class="num">Filas</th><th>Estado en el sistema</th></tr></thead>
                    <tbody id="mapeo-estados"></tbody>
                </table>
            </div>
        </div>

        <div class="botonera">
            <button type="button" class="btn btn-sec" data-volver="1">← Volver</button>
            <button type="button" class="btn" id="ir-paso-3">Siguiente: revisar duplicados</button>
        </div>
    </section>

    {{-- Paso 3 --}}
    <section class="card" data-panel="3" hidden>
        <h2>3. Revisión</h2>
        <div class="resumen-import" id="resumen"></div>

        <div id="duplicadas-wrap" hidden>
            <div class="card-cab">
                <h3>Ya existen en el sistema</h3>
                <span class="chips">
                    <button type="button" class="chip" data-todas="actualizar">Actualizar todas</button>
                    <button type="button" class="chip" data-todas="saltear">Saltear todas</button>
                </span>
            </div>
            <p class="muted chico">«Actualizar» completa los datos con lo que trae el Excel (lo vacío no borra nada) y suma una nota al historial.</p>
            <div class="tabla-scroll">
                <table class="tabla tabla-responsive">
                    <thead><tr><th>Fila del Excel</th><th>Ya cargada como</th><th>Qué hago</th></tr></thead>
                    <tbody id="duplicadas"></tbody>
                </table>
            </div>
        </div>

        <details id="otras-wrap" hidden style="margin-top:12px">
            <summary>Filas que no se van a importar</summary>
            <ul class="lista" id="otras"></ul>
        </details>

        <details id="nuevas-wrap" hidden style="margin-top:12px">
            <summary>Ver empresas nuevas</summary>
            <ul class="lista" id="nuevas"></ul>
        </details>

        <div class="botonera">
            <button type="button" class="btn btn-sec" data-volver="2">← Volver</button>
            <button type="button" class="btn" id="confirmar">Importar</button>
        </div>
    </section>

    {{-- Paso 4 --}}
    <section class="card" data-panel="4" hidden>
        <h2>4. ¡Listo!</h2>
        <div id="resultado"></div>
        <div class="botonera">
            <a href="#" class="btn" id="ver-importadas">Ver empresas importadas</a>
            <a href="{{ route('importar') }}" class="btn btn-sec">Importar otro archivo</a>
        </div>
    </section>

    @if ($importaciones->isNotEmpty())
        <section class="card">
            <h2>Últimas importaciones</h2>
            <table class="tabla tabla-responsive">
                <thead><tr><th>Archivo</th><th>Rubro</th><th>Fecha</th><th class="num">Nuevas</th><th class="num">Actualizadas</th><th class="num">Salteadas</th></tr></thead>
                <tbody>
                    @foreach ($importaciones as $imp)
                        <tr>
                            <td class="principal">{{ $imp->archivo }}</td>
                            <td data-label="Rubro">{{ $imp->rubro->nombre }}</td>
                            <td data-label="Fecha">{{ $imp->created_at->format('d/m/Y H:i') }} · {{ $imp->usuario?->name }}</td>
                            <td data-label="Nuevas" class="num">{{ $imp->nuevas }}</td>
                            <td data-label="Actualizadas" class="num">{{ $imp->actualizadas }}</td>
                            <td data-label="Salteadas" class="num">{{ $imp->salteadas }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <script type="application/json" id="config-import">@json($config)</script>
@endsection

@push('scripts')
    <script src="{{ asset('vendor/xlsx.full.min.js') }}"></script>
    <script src="{{ asset('js/importar.js') }}?v=1"></script>
@endpush
