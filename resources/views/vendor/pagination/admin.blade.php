@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Halaman" class="mt-4 grid grid-cols-2 gap-3 sm:flex sm:items-center sm:justify-between">
        <p class="order-first col-span-2 text-center text-sm text-muted sm:order-none sm:col-span-1">Halaman {{ $paginator->currentPage() }} dari {{ $paginator->lastPage() }}</p>

        @if ($paginator->onFirstPage())
            <span class="btn btn-quiet cursor-not-allowed opacity-50 sm:order-first" aria-disabled="true">Sebelumnya</span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev" class="btn btn-quiet sm:order-first">Sebelumnya</a>
        @endif

        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next" class="btn btn-quiet sm:order-last">Berikutnya</a>
        @else
            <span class="btn btn-quiet cursor-not-allowed opacity-50 sm:order-last" aria-disabled="true">Berikutnya</span>
        @endif
    </nav>
@endif
