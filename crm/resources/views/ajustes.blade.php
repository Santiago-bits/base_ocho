@extends('layouts.app')
@section('titulo', 'Ajustes')

@section('contenido')
    <h1>Ajustes</h1>

    <div class="grid-2">
        <section class="card">
            <h2>Rubros</h2>
            @if ($rubros->isEmpty())
                <p class="vacio">Todavía no hay rubros. Creá los que usan (ej.: Inmobiliarias, Maquinaria, Concesionarias de motos).</p>
            @else
                <ul class="lista">
                    @foreach ($rubros as $rubro)
                        <li style="display:block">
                            <form method="post" action="{{ route('rubros.update', $rubro) }}" class="fila-form">
                                @csrf @method('patch')
                                <input type="text" name="nombre" value="{{ $rubro->nombre }}" required maxlength="100" aria-label="Nombre del rubro">
                                <label class="check"><input type="checkbox" name="activo" value="1" @checked($rubro->activo)> Activo</label>
                                <span class="muted chico">{{ $rubro->empresas_count }} empresas</span>
                                <button type="submit" class="btn btn-sec btn-chico">Guardar</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
            <form method="post" action="{{ route('rubros.store') }}" class="fila-form" style="margin-top:12px">
                @csrf
                <input type="text" name="nombre" required maxlength="100" placeholder="Nuevo rubro" aria-label="Nuevo rubro">
                <button type="submit" class="btn">Agregar</button>
            </form>
        </section>

        <section class="card">
            <h2>Avisos</h2>
            <form method="post" action="{{ route('ajustes.guardar') }}" class="form">
                @csrf @method('put')
                <label>Límite diario de WhatsApp
                    <input type="number" name="limite_whatsapp_diario" min="1" max="500" required value="{{ old('limite_whatsapp_diario', $limiteWhatsapp) }}">
                    <span class="ayuda">Se cuentan los WhatsApp registrados por los dos usuarios en el día.</span>
                </label>
                <label>Avisar abonos con cuántos días de anticipación
                    <input type="number" name="dias_aviso_abono" min="0" max="60" required value="{{ old('dias_aviso_abono', $diasAviso) }}">
                </label>
                <div class="botonera"><button type="submit" class="btn">Guardar</button></div>
            </form>
        </section>

        <section class="card">
            <h2>Cambiar mi contraseña</h2>
            <form method="post" action="{{ route('ajustes.password') }}" class="form">
                @csrf @method('put')
                <label>Contraseña actual <input type="password" name="actual" required autocomplete="current-password"></label>
                <label>Nueva contraseña <input type="password" name="nueva" required minlength="8" autocomplete="new-password"></label>
                <label>Repetir nueva <input type="password" name="nueva_confirmation" required minlength="8" autocomplete="new-password"></label>
                <div class="botonera"><button type="submit" class="btn">Cambiar contraseña</button></div>
            </form>
        </section>

        <section class="card">
            <h2>Usuarios</h2>
            <ul class="lista">
                @foreach ($usuarios as $usuario)
                    <li><span>{{ $usuario->name }}<span class="sub">{{ $usuario->email }}</span></span></li>
                @endforeach
            </ul>
            <p class="muted chico" style="margin-top:12px">Para agregar un usuario o resetear una contraseña, por SSH:<br>
                <code>php artisan crm:usuario email@ejemplo.com "Nombre"</code></p>
        </section>
    </div>
@endsection
