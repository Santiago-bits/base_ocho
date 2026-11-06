@if ($paginator->hasPages())
    <nav class="paginacion" aria-label="Paginación">
        @if ($paginator->onFirstPage())
            <span class="btn btn-sec deshabilitado">← Anterior</span>
        @else
            <a class="btn btn-sec" href="{{ $paginator->previousPageUrl() }}" rel="prev">← Anterior</a>
        @endif

        <span class="muted">Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>

        @if ($paginator->hasMorePages())
            <a class="btn btn-sec" href="{{ $paginator->nextPageUrl() }}" rel="next">Siguiente →</a>
        @else
            <span class="btn btn-sec deshabilitado">Siguiente →</span>
        @endif
    </nav>
@endif
