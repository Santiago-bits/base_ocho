@extends('layouts.app')
@section('titulo', $plantilla->exists ? 'Editar plantilla' : 'Nueva plantilla')
@use('App\Enums\Canal')

@section('contenido')
    <a href="{{ route('plantillas.index') }}" class="volver">← Plantillas</a>
    <h1>{{ $plantilla->exists ? 'Editar plantilla' : 'Nueva plantilla' }}</h1>

    <form method="post" action="{{ $plantilla->exists ? route('plantillas.update', $plantilla) : route('plantillas.store') }}" class="card form">
        @csrf
        @if ($plantilla->exists) @method('put') @endif

        <div class="form-grid">
            <label class="ancho">Nombre *
                <input type="text" name="nombre" required maxlength="120" value="{{ old('nombre', $plantilla->nombre) }}" placeholder="Ej.: Inmobiliarias – primer contacto">
            </label>
            <label>Rubro
                <select name="rubro_id">
                    <option value="">Todos los rubros</option>
                    @foreach ($rubros as $rubro)
                        <option value="{{ $rubro->id }}" @selected(old('rubro_id', $plantilla->rubro_id) == $rubro->id)>{{ $rubro->nombre }}</option>
                    @endforeach
                </select>
            </label>
            <label>Canal *
                <select name="canal" required>
                    @foreach (Canal::opciones() as $valor => $etiqueta)
                        <option value="{{ $valor }}" @selected(old('canal', $plantilla->canal?->value) === $valor)>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </label>
            <label class="ancho">Asunto <span class="ayuda">(solo para email)</span>
                <input type="text" name="asunto" maxlength="200" value="{{ old('asunto', $plantilla->asunto) }}">
            </label>
            <label class="ancho">Mensaje *
                <textarea name="cuerpo" id="cuerpo" rows="10" required maxlength="5000">{{ old('cuerpo', $plantilla->cuerpo) }}</textarea>
            </label>
        </div>
        <div class="chips">
            <span class="muted chico">Insertar:</span>
            @foreach (['{nombre}', '{ciudad}', '{rubro}', '{direccion}', '{web}'] as $variable)
                <button type="button" class="chip" data-insertar="{{ $variable }}" data-destino="#cuerpo">{{ $variable }}</button>
            @endforeach
        </div>
        <label class="check"><input type="checkbox" name="activa" value="1" @checked(old('activa', $plantilla->activa ?? true))> Activa</label>

        <div class="botonera">
            <button type="submit" class="btn">Guardar plantilla</button>
        </div>
    </form>

    @if ($plantilla->exists)
        <form method="post" action="{{ route('plantillas.destroy', $plantilla) }}" data-confirmar="¿Borrar la plantilla? Si ya se usó, solo se desactiva.">
            @csrf @method('delete')
            <button type="submit" class="btn btn-peligro btn-chico">Borrar plantilla</button>
        </form>
    @endif
@endsection
