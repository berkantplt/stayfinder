{{--
    Kart alt bilgisi: ulaşım tipi ve kalkış şehirleri.

    İkisi de opsiyonel — veri yoksa hiçbir şey basılmaz (boş satır bırakmaz).
    Mevcut turların çoğunda bu alanlar boş; import ve acenta formu doldurdukça
    kendiliğinden görünür hale gelirler.
--}}
@php
    $ulasim = $tour->transport_label;
    $kalkis = $tour->departure_label;
@endphp
@if($ulasim || $kalkis)
    <div class="card-meta" style="display:flex;flex-wrap:wrap;gap:4px 10px;margin-top:2px;">
        @if($ulasim)
            <span style="display:inline-flex;align-items:center;gap:4px;">
                <span aria-hidden="true">{{ $tour->transport_type === 'otobus' ? '🚌' : ($tour->transport_type === 'ucak' ? '✈️' : ($tour->transport_type === 'gemi' || $tour->transport_type === 'feribot' ? '🚢' : ($tour->transport_type === 'tren' ? '🚆' : '🚗'))) }}</span>{{ $ulasim }}
            </span>
        @endif
        @if($kalkis)
            <span style="display:inline-flex;align-items:center;gap:4px;">
                <span aria-hidden="true">📍</span>{{ $kalkis }}
            </span>
        @endif
    </div>
@endif
