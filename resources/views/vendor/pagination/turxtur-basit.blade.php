{{-- simplePaginate için: yalnız önceki/sonraki --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Sayfalama">
        <div>
            @if ($paginator->onFirstPage())
                <span class="cursor-default" aria-disabled="true"><span>‹ Önceki</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev">‹ Önceki</a>
            @endif

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next">Sonraki ›</a>
            @else
                <span class="cursor-default" aria-disabled="true"><span>Sonraki ›</span></span>
            @endif
        </div>
    </nav>
@endif
