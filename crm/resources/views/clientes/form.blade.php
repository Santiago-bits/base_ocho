@extends('layouts.app')
@section('titulo', $cliente->exists ? 'Editar cliente' : 'Alta de cliente')
@use('App\Support\Formato')

@section('contenido')
    <a href="{{ $cliente->exists ? route('clientes.show', $cliente) : route('empresas.show', $empresa) }}" class="volver">← Volver</a>
    <h1>{{ $cliente->exists ? 'Editar cliente' : 'Alta de cliente' }}</h1>
    <p class="muted">{{ $empresa->nombre }}</p>

    <form method="post" action="{{ $cliente->exists ? route('clientes.update', $cliente) : route('clientes.store', $empresa) }}" class="card form">
        @csrf
        @if ($cliente->exists) @method('put') @endif

        <div class="form-grid">
            <label class="ancho">Servicio contratado *
                <input type="text" name="servicio" required maxlength="200" value="{{ old('servicio', $cliente->servicio) }}" placeholder="Ej.: Catálogo online">
            </label>
            <label>Fecha de alta *
                <input type="date" name="fecha_alta" required value="{{ old('fecha_alta', $cliente->fecha_alta?->format('Y-m-d')) }}">
                <span class="ayuda">El abono vence ese mismo día de cada mes.</span>
            </label>
            <span></span>
            <label>Monto inicial *
                <input type="text" name="monto_inicial" inputmode="decimal" required
                       value="{{ old('monto_inicial', $cliente->exists ? Formato::numero($cliente->monto_inicial) : '') }}" placeholder="Ej.: 350.000">
                <span class="ayuda">Cubre el primer mes.</span>
            </label>
            <label>Abono mensual *
                <input type="text" name="abono_mensual" inputmode="decimal" required
                       value="{{ old('abono_mensual', $cliente->exists ? Formato::numero($cliente->abono_mensual) : '') }}" placeholder="Ej.: 40.000">
                <span class="ayuda">El primer abono vence un mes después del alta.</span>
            </label>
            <label class="ancho">Notas
                <textarea name="notas" rows="3" maxlength="5000">{{ old('notas', $cliente->notas) }}</textarea>
            </label>
        </div>

        @if ($cliente->exists)
            <label class="check"><input type="checkbox" name="activo" value="1" @checked(old('activo', $cliente->activo))> Cliente activo (si lo desmarcás no se avisan más abonos)</label>
        @else
            <label class="check"><input type="checkbox" name="registrar_pago_inicial" value="1" @checked(old('registrar_pago_inicial', true))> Ya pagó el monto inicial (lo registro como pago con la fecha de alta)</label>
        @endif

        <div class="botonera">
            <button type="submit" class="btn">{{ $cliente->exists ? 'Guardar' : 'Dar de alta' }}</button>
        </div>
    </form>
@endsection
