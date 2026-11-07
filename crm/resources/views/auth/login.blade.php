@extends('layouts.app')
@section('titulo', 'Entrar')

@section('contenido')
    <div class="login">
        <div class="card login-card">
            <div class="login-marca">
                <svg viewBox="0 0 64 96" class="marca-logo grande" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="13"><rect x="6.5" y="6.5" width="51" height="41" rx="11"/><rect x="6.5" y="48.5" width="51" height="41" rx="11"/></g></svg>
                <div>
                    <strong>base ocho</strong>
                    <span class="muted">Gestión de clientes</span>
                </div>
            </div>

            <form method="post" action="{{ route('login.entrar') }}" class="form">
                @csrf
                <label>Email
                    <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" inputmode="email">
                </label>
                <label>Contraseña
                    <input type="password" name="password" required autocomplete="current-password">
                </label>
                <label class="check">
                    <input type="checkbox" name="recordar" value="1" checked> Mantener la sesión en este dispositivo
                </label>
                <button type="submit" class="btn btn-primario btn-bloque">Entrar</button>
            </form>
        </div>
    </div>
@endsection
