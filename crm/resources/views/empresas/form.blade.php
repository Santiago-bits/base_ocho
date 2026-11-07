@extends('layouts.app')
@section('titulo', $empresa->exists ? 'Editar '.$empresa->nombre : 'Nueva empresa')

@section('contenido')
    <a href="{{ $empresa->exists ? route('empresas.show', $empresa) : route('empresas.index') }}" class="volver">← Volver</a>
    <h1>{{ $empresa->exists ? 'Editar empresa' : 'Nueva empresa' }}</h1>

    @if (session('duplicadas'))
        <div class="alerta aviso">
            <strong>Ojo: ya hay empresas con el mismo teléfono o email.</strong>
            <ul>
                @foreach (session('duplicadas') as $dup)
                    <li><a href="{{ route('empresas.show', $dup->id) }}">{{ $dup->nombre }}</a>{{ $dup->ciudad ? ' · '.$dup->ciudad : '' }}</li>
                @endforeach
            </ul>
            Si igual es otra empresa, volvé a tocar «Crear empresa».
        </div>
    @endif

    <form method="post" action="{{ $empresa->exists ? route('empresas.update', $empresa) : route('empresas.store') }}" class="card form">
        @csrf
        @if ($empresa->exists) @method('put') @endif
        @if (session('duplicadas')) <input type="hidden" name="forzar" value="1"> @endif

        <div class="form-grid">
            <label class="ancho">Nombre *
                <input type="text" name="nombre" value="{{ old('nombre', $empresa->nombre) }}" required maxlength="200">
            </label>
            <label>Rubro *
                <select name="rubro_id" required>
                    <option value="">Elegí un rubro</option>
                    @foreach ($rubros as $rubro)
                        <option value="{{ $rubro->id }}" @selected(old('rubro_id', $empresa->rubro_id) == $rubro->id)>{{ $rubro->nombre }}</option>
                    @endforeach
                </select>
                @if ($rubros->isEmpty())
                    <span class="ayuda">No hay rubros: <a href="{{ route('ajustes') }}">crealos en Ajustes</a>.</span>
                @endif
            </label>
            <label>Ciudad
                <input type="text" name="ciudad" value="{{ old('ciudad', $empresa->ciudad) }}" maxlength="120">
            </label>
            <label class="ancho">Dirección
                <input type="text" name="direccion" value="{{ old('direccion', $empresa->direccion) }}" maxlength="255">
            </label>
            <label>Teléfono
                <input type="tel" name="telefono" value="{{ old('telefono', $empresa->telefono) }}" maxlength="60" inputmode="tel">
            </label>
            <label>WhatsApp
                <input type="tel" name="whatsapp" value="{{ old('whatsapp', $empresa->whatsapp) }}" maxlength="60" inputmode="tel">
                <span class="ayuda">Con código de área, ej.: 345 412-3456</span>
            </label>
            <label>Email
                <input type="email" name="email" value="{{ old('email', $empresa->email) }}" maxlength="190" inputmode="email">
            </label>
            <label>Web / Redes
                <input type="text" name="web_redes" value="{{ old('web_redes', $empresa->web_redes) }}" maxlength="255">
            </label>
            <label>Fuente
                <input type="text" name="fuente" value="{{ old('fuente', $empresa->fuente) }}" maxlength="120" placeholder="Google Maps, Instagram, recomendación…">
            </label>
            <label class="ancho">Notas
                <textarea name="notas" rows="4" maxlength="5000">{{ old('notas', $empresa->notas) }}</textarea>
            </label>
        </div>

        <div class="botonera">
            <button type="submit" class="btn">{{ $empresa->exists ? 'Guardar cambios' : 'Crear empresa' }}</button>
        </div>
    </form>

    @if ($empresa->exists)
        <form method="post" action="{{ route('empresas.destroy', $empresa) }}" data-confirmar="¿Borrar «{{ $empresa->nombre }}» con todo su historial? No se puede deshacer.">
            @csrf @method('delete')
            <button type="submit" class="btn btn-peligro btn-chico">Borrar empresa</button>
        </form>
    @endif
@endsection
