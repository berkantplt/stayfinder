{{--
    Breadcrumb — görünür navigasyon + BreadcrumbList yapısal verisi.

    Google SERP'te ham URL yerine bu kırıntı yolunu gösterir (tıklama oranını
    artırır) ve site hiyerarşisini anlar. Ayrıca iç link ağını güçlendirir:
    her tur sayfası kategorisine ve destinasyonuna link vermiş olur.

    Kullanım — son eleman URL'siz (bulunulan sayfa):
      @include('partials.breadcrumb', ['items' => [
          ['name' => 'Turlar', 'url' => route('tours.index')],
          ['name' => $tour->title],
      ]])

    "Ana Sayfa" otomatik eklenir, tekrar yazmaya gerek yok.

    @var array<int, array{name: string, url?: string}> $items
--}}
@php
    $crumbs = array_values(array_filter($items ?? [], fn ($i) => !empty($i['name'])));
    array_unshift($crumbs, ['name' => 'Ana Sayfa', 'url' => route('home')]);
@endphp

@if (count($crumbs) > 1)
    <nav aria-label="Sayfa yolu" class="breadcrumb">
        <ol>
            @foreach ($crumbs as $crumb)
                <li>
                    @if (!empty($crumb['url']) && !$loop->last)
                        <a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a>
                    @else
                        <span aria-current="page">{{ $crumb['name'] }}</span>
                    @endif
                    @unless ($loop->last)
                        <span class="breadcrumb-sep" aria-hidden="true">›</span>
                    @endunless
                </li>
            @endforeach
        </ol>
    </nav>

    @include('partials.json-ld', ['data' => [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => collect($crumbs)->map(fn ($crumb, $i) => array_filter([
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $crumb['name'],
            // Son eleman (bulunulan sayfa) item almaz — Google'ın önerdiği biçim.
            'item' => $crumb['url'] ?? null,
        ]))->values()->all(),
    ]])

    @once
        @push('head')
            <style>
                .breadcrumb { font-size:13px; color:var(--text-muted); margin-bottom:18px; }
                .breadcrumb ol { list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
                .breadcrumb li { display:flex; align-items:center; gap:6px; min-width:0; }
                .breadcrumb a { color:var(--accent); }
                .breadcrumb a:hover { text-decoration:underline; }
                .breadcrumb [aria-current="page"] { color:var(--text-sec); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:min(60vw, 420px); }
                .breadcrumb-sep { color:var(--text-muted); }
                @media (max-width:768px) { .breadcrumb { font-size:12px; margin-bottom:12px; } }
            </style>
        @endpush
    @endonce
@endif
