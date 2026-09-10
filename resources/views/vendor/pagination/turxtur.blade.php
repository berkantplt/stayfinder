{{-- turXtur sayfalama görünümü: Laravel'in Tailwind şablonu sınıfsız kalınca
     mobil + masaüstü bloğu birlikte ve İngilizce basıyordu. Tek blok, Türkçe;
     işaretleme layout'taki nav[role="navigation"] kurallarıyla uyumlu. --}}
@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Sayfalama">
        <div>
            @if ($paginator->onFirstPage())
                <span class="cursor-default" aria-disabled="true" aria-label="Önceki sayfa"><span>‹</span></span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Önceki sayfa">‹</a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="cursor-default" aria-disabled="true"><span>{{ $element }}</span></span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span aria-current="page"><span>{{ $page }}</span></span>
                        @else
                            <a href="{{ $url }}" aria-label="Sayfa {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Sonraki sayfa">›</a>
            @else
                <span class="cursor-default" aria-disabled="true" aria-label="Sonraki sayfa"><span>›</span></span>
            @endif
        </div>
        <div style="flex-basis:100%;justify-content:center;font-size:12.5px;color:var(--text-meta);margin-top:6px;">
            {{ $paginator->total() }} kayıttan {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} arası gösteriliyor
        </div>
    </nav>
@endif
