<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0F2440">
    <title>@yield('titulo', 'Panel') · CRM BaseOcho</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/crm.css') }}?v=1">
</head>
<body class="@auth con-sesion @endauth">
@auth
    @php
        $menu = [
            ['panel', 'Panel', 'panel'],
            ['empresas.index', 'Empresas', 'empresas.*'],
            ['clientes.index', 'Clientes', 'clientes.*'],
            ['plantillas.index', 'Plantillas', 'plantillas.*'],
            ['importar', 'Importar', 'importar*'],
            ['estadisticas', 'Estadísticas', 'estadisticas'],
            ['ajustes', 'Ajustes', 'ajustes'],
        ];
    @endphp
    <header class="topbar">
        <a href="{{ route('panel') }}" class="marca">
            <svg viewBox="0 0 64 96" class="marca-logo" aria-hidden="true"><g fill="none" stroke="currentColor" stroke-width="13"><rect x="6.5" y="6.5" width="51" height="41" rx="11"/><rect x="6.5" y="48.5" width="51" height="41" rx="11"/></g></svg>
            <span>base ocho <small>CRM</small></span>
        </a>
        <nav class="nav-escritorio" aria-label="Principal">
            @foreach ($menu as [$ruta, $texto, $patron])
                <a href="{{ route($ruta) }}" @class(['activo' => request()->routeIs($patron)])>{{ $texto }}</a>
            @endforeach
        </nav>
        <form method="post" action="{{ route('logout') }}" class="salir">
            @csrf
            <span class="muted usuario">{{ auth()->user()->name }}</span>
            <button type="submit" class="btn-link">Salir</button>
        </form>
    </header>
@endauth

<main class="contenedor">
    @if (session('ok'))
        <div class="alerta ok" role="status">{{ session('ok') }}</div>
    @endif
    @if (session('aviso'))
        <div class="alerta aviso" role="alert">{{ session('aviso') }}</div>
    @endif
    @if ($errors->any())
        <div class="alerta error" role="alert">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('contenido')
</main>

@auth
    <nav class="nav-movil" aria-label="Principal">
        <a href="{{ route('panel') }}" @class(['activo' => request()->routeIs('panel')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z"/></svg>Panel
        </a>
        <a href="{{ route('empresas.index') }}" @class(['activo' => request()->routeIs('empresas.*')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 10h4a1 1 0 0 1 1 1v10M8 8h3M8 12h3M8 16h3M2 21h20"/></svg>Empresas
        </a>
        <a href="{{ route('clientes.index') }}" @class(['activo' => request()->routeIs('clientes.*')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3v18M17 7H9.5a3 3 0 0 0 0 6h5a3 3 0 0 1 0 6H6"/></svg>Clientes
        </a>
        <a href="{{ route('importar') }}" @class(['activo' => request()->routeIs('importar*')])>
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 15V3M7 8l5-5 5 5M4 15v4a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-4"/></svg>Importar
        </a>
        <details class="mas">
            <summary><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 12h.01M12 12h.01M19 12h.01"/></svg>Más</summary>
            <div class="mas-menu">
                <a href="{{ route('plantillas.index') }}">Plantillas</a>
                <a href="{{ route('estadisticas') }}">Estadísticas</a>
                <a href="{{ route('ajustes') }}">Ajustes</a>
                <form method="post" action="{{ route('logout') }}">@csrf<button type="submit">Salir</button></form>
            </div>
        </details>
    </nav>
@endauth

<script src="{{ asset('js/crm.js') }}?v=1" defer></script>
@stack('scripts')
</body>
</html>
