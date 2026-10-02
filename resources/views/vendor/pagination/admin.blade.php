@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Halaman" class="mt-4 flex items-center justify-between gap-3">
        @if ($paginator->onFirstPage())
            <span class="btn btn-quiet cursor-not-allowed opacity-50" aria-disabled="true">Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-quiet">Sebelumnya</a>
        @endif

        <p class="text-sm text-muted">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</p>

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-quiet">Berikutnya</a>
        @else
            <span class="btn btn-quiet cursor-not-allowed opacity-50" aria-disabled="true">Berikutnya</span>
        @endif
    </nav>
@endif
