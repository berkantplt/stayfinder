{{--
    Sayfalı listelerde önceki/sonraki sayfa bağlantıları.

    Kanonik zaten kendini gösteriyor (Seo::canonical page'i korur), bu etiketler
    de sayfa dizisinin parçalarını birbirine bağlar: Google 2. ve sonraki
    sayfalardaki turların varlığını böyle keşfeder. Aksi halde yalnız ilk 12 tur
    taranır, geri kalanı hiç görülmez.

    Kullanım:
      @include('partials.pagination-seo', ['paginator' => $tours])

    @var \Illuminate\Contracts\Pagination\Paginator $paginator
--}}
@isset($paginator)
    @push('head')
        @if ($paginator->currentPage() > 1)
            <link rel="prev" href="{{ \App\Support\Seo::withoutFirstPage($paginator->previousPageUrl()) }}">
        @endif
        @if ($paginator->hasMorePages())
            <link rel="next" href="{{ $paginator->nextPageUrl() }}">
        @endif
    @endpush
@endisset
