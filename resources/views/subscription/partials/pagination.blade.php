@if($paginator->hasPages())
    <nav class="subscription-pagination" aria-label="{{ $label }}">
        @if($paginator->onFirstPage())<span>Anterior</span>@else<a href="{{ $paginator->previousPageUrl() }}">Anterior</a>@endif
        <span>Página {{ $paginator->currentPage() }} de {{ $paginator->lastPage() }}</span>
        @if($paginator->hasMorePages())<a href="{{ $paginator->nextPageUrl() }}">Siguiente</a>@else<span>Siguiente</span>@endif
    </nav>
@endif
