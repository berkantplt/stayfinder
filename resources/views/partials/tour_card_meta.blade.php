{{--
    Kart bilgi satırı: ulaşım, vize durumu, kalkış şehirleri.

    Hepsi opsiyonel — veri yoksa hiçbir şey basılmaz (boş satır bırakmaz).
    Vize üç durumlu: acenta işaretlememişse (null) satıra girmez, "Vizesiz" denmez.
    İkonlar SVG (emoji işletim sistemine göre farklı çiziliyordu).
--}}
@php
    $ulasim = $tour->transport_short_label;
    $vize = $tour->visa_label;
    $kalkis = $tour->departure_label;
@endphp
@if($ulasim || $vize || $kalkis)
    <div class="card-meta card-facts" style="display:flex;flex-wrap:wrap;gap:4px 10px;margin-top:2px;">
        @if($ulasim)
            <span style="display:inline-flex;align-items:center;gap:4px;">
                @if($tour->transport_type === 'ucak')
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 14l8-2 4-8 2 1-2 8 6 3-1 2-7-1-3 4-2-1 1-5z"/></svg>
                @else
                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="3" width="16" height="15" rx="3"/><path d="M4 10h16M8 18v2M16 18v2"/></svg>
                @endif{{ $ulasim }}
            </span>
        @endif
        @if($vize)
            <span style="display:inline-flex;align-items:center;gap:4px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="3" width="14" height="18" rx="2"/><circle cx="12" cy="10" r="2.5"/><path d="M8.5 16.5h7"/></svg>{{ $vize }}
            </span>
        @endif
        @if($kalkis)
            <span style="display:inline-flex;align-items:center;gap:4px;">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>{{ $kalkis }}
            </span>
        @endif
    </div>
@endif
