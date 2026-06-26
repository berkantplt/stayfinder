@extends('layouts.app')
@section('title', 'Turları Karşılaştır — ' . count($tours) . ' Tur Gösteriliyor')

@section('content')
<div class="container" style="padding:40px 20px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;flex-wrap:wrap;gap:16px;">
        <div>
            <a href="{{ route('tours.index') }}" class="btn btn-outline" style="margin-bottom:16px;">← Turlara Dön</a>
            <h1 style="font-size:32px;font-weight:800;letter-spacing:-1px;color:#0f172a;">Turları Karşılaştır</h1>
            <p style="color:#64748b;font-size:15px;margin-top:8px;">Seçtiğiniz {{ count($tours) }} turun özelliklerini ve fiyatlarını yan yana inceleyin.</p>
        </div>
        <div>
            <button onclick="clearAndReturn()" class="btn btn-outline" style="border-color:#ef4444;color:#ef4444;">Karşılaştırmayı Temizle</button>
        </div>
    </div>

    <div class="compare-grid" style="display:grid;grid-template-columns:repeat({{ count($tours) }}, 1fr);gap:24px;overflow-x:auto;padding-bottom:20px;">
        @foreach($tours as $tour)
        <div class="card compare-tour-card" data-tour-id="{{ $tour->id }}" style="padding:0;overflow:hidden;display:flex;flex-direction:column;border:2px solid transparent;transition:all 0.3s;min-width:300px;position:relative;" onmouseover="this.style.borderColor='var(--accent)'" onmouseout="this.style.borderColor='transparent'">
            <button
                type="button"
                onclick="removeComparedTour({{ $tour->id }})"
                title="Bu turu karşılaştırmadan çıkar"
                style="position:absolute;top:10px;right:10px;z-index:5;width:30px;height:30px;border:none;border-radius:999px;background:rgba(15,23,42,0.72);color:#fff;font-size:18px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;"
            >×</button>
            <div style="position:relative;">
                @if($tour->image)
                    <img src="{{ $tour->image }}" alt="{{ $tour->title }}" style="width:100%;height:220px;object-fit:cover;">
                @else
                    <div style="width:100%;height:220px;background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:48px;">🏖️</div>
                @endif
                <div style="position:absolute;top:16px;left:16px;background:rgba(255,255,255,0.9);backdrop-filter:blur(4px);padding:6px 12px;border-radius:20px;font-size:12px;font-weight:600;color:#0f172a;">
                    {{ $tour->duration_days }} Gün
                </div>
            </div>

            <div style="padding:24px;flex:1;display:flex;flex-direction:column;">
                <div style="margin-bottom:12px;">
                    @if($tour->category)
                        <span class="badge badge-accent">{{ $tour->category->icon }} {{ $tour->category->name }}</span>
                    @endif
                </div>
                
                <h3 style="font-size:20px;font-weight:700;color:#0f172a;line-height:1.4;margin-bottom:8px;">
                    <a href="{{ route('tours.show', $tour) }}">{{ $tour->title }}</a>
                </h3>
                <div style="color:#64748b;font-size:14px;margin-bottom:20px;">Düzenleyen: <strong>{{ optional($tour->agency)->name }}</strong></div>

                <div style="flex:1;">
                    <ul style="list-style:none;padding:0;margin:0;">
                        <li style="padding:12px 0;border-bottom:1px solid #f1f5f9;display:flex;align-items:flex-start;gap:12px;">
                            <span style="font-size:18px;opacity:0.6;">📍</span>
                            <div>
                                <div style="font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase;margin-bottom:2px;">Destinasyon</div>
                                <div style="color:#0f172a;font-weight:500;">{{ $tour->destination }}</div>
                            </div>
                        </li>
                        <li style="padding:12px 0;border-bottom:1px solid #f1f5f9;display:flex;align-items:flex-start;gap:12px;">
                            <span style="font-size:18px;opacity:0.6;">📅</span>
                            <div>
                                <div style="font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase;margin-bottom:2px;">Kalkış Tarihleri</div>
                                <div style="color:#0f172a;font-weight:500;">
                                    @if($tour->dates->count())
                                        {{ $tour->dates->first()->departure_date->format('d-m-Y') }}
                                        @if($tour->dates->count() > 1) <span style="color:#64748b;font-size:13px;">(+{{ $tour->dates->count() - 1 }} tarih)</span> @endif
                                    @elseif($tour->departure_date)
                                        {{ $tour->departure_date->format('d-m-Y') }}
                                    @else
                                        Belirtilmemiş
                                    @endif
                                </div>
                            </div>
                        </li>
                        <li style="padding:12px 0;border-bottom:1px solid #f1f5f9;display:flex;align-items:flex-start;gap:12px;">
                            <span style="font-size:18px;opacity:0.6;color:#10b981;">✓</span>
                            <div>
                                <div style="font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase;margin-bottom:4px;">Fiyata Dahil Olanlar</div>
                                <div style="color:#475569;font-size:14px;line-height:1.6;">{!! nl2br(e($tour->included ?: 'Detaylar tur sayfasında.')) !!}</div>
                            </div>
                        </li>
                        <li style="padding:12px 0;display:flex;align-items:flex-start;gap:12px;">
                            <span style="font-size:18px;opacity:0.6;color:#ef4444;">✗</span>
                            <div>
                                <div style="font-size:12px;color:#94a3b8;font-weight:600;text-transform:uppercase;margin-bottom:4px;">Dahil Olmayanlar</div>
                                <div style="color:#475569;font-size:14px;line-height:1.6;">{!! nl2br(e($tour->excluded ?: 'Belirtilmemiş.')) !!}</div>
                            </div>
                        </li>
                    </ul>
                </div>

                <div style="margin-top:24px;padding-top:20px;border-top:1px solid #e2e8f0;display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div style="font-size:12px;color:#64748b;font-weight:500;margin-bottom:2px;">Kişi Başı</div>
                        @php $campaign = $tour->activeCampaign; @endphp
                        @if($campaign)
                            <div style="text-decoration:line-through;color:#94a3b8;font-size:14px;">{{ $tour->formatted_price }}</div>
                            <div style="color:#059669;font-size:24px;font-weight:800;letter-spacing:-0.5px;">{{ $campaign->formatted_discount_price }}</div>
                        @else
                            <div style="font-size:24px;font-weight:800;color:#0f172a;letter-spacing:-0.5px;">{{ $tour->formatted_price }}</div>
                        @endif
                    </div>
                    <a href="{{ route('tours.show', $tour) }}" class="btn btn-primary">İncele</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>
</div>

<script>
function normalizeCompareIds(ids) {
    const uniq = new Set();
    return (ids || [])
        .map(function(id) { return parseInt(id, 10); })
        .filter(function(id) {
            if (!Number.isInteger(id) || id <= 0 || uniq.has(id)) return false;
            uniq.add(id);
            return true;
        });
}

function getStoredCompareIds() {
    try {
        return normalizeCompareIds(JSON.parse(localStorage.getItem('compared_tours') || '[]'));
    } catch (e) {
        return [];
    }
}

function getDisplayedCompareIds() {
    const cards = Array.from(document.querySelectorAll('.compare-tour-card'));
    return normalizeCompareIds(cards.map(function(card) {
        return card.getAttribute('data-tour-id');
    }));
}

function getCompareIds() {
    const displayedIds = getDisplayedCompareIds();
    if (displayedIds.length) return displayedIds;
    return getStoredCompareIds();
}

function removeComparedTour(tourId) {
    const targetId = parseInt(tourId, 10);
    const remaining = getCompareIds().filter(function(id) { return id !== targetId; });

    localStorage.setItem('compared_tours', JSON.stringify(remaining));
    if (typeof window.updateCompareUI === 'function') window.updateCompareUI();

    if (remaining.length < 2) {
        alert('Karşılaştırma için en az 2 tur gerekir. Lütfen yeniden tur seç.');
        window.location.href = "{{ route('tours.index') }}";
        return;
    }

    const query = remaining.map(function(id) {
        return 'ids[]=' + encodeURIComponent(id);
    }).join('&');
    window.location.href = "{{ route('tours.compare') }}" + '?' + query;
}

function clearAndReturn() {
    if(confirm('Karşılaştırma listesini temizlemek istediğinize emin misiniz?')) {
        if(typeof window.clearCompare === 'function') {
            window.clearCompare();
        } else {
            localStorage.setItem('compared_tours', '[]');
            const compareBar = document.getElementById('compare-bar');
            if (compareBar) compareBar.style.display = 'none';
        }
        window.location.href = "{{ route('tours.index') }}";
    }
}

// Make sure that the sticky bar is hidden on this specific page since we are already viewing the comparison.
document.addEventListener('DOMContentLoaded', () => {
    const bar = document.getElementById('compare-bar');
    if(bar) bar.style.display = 'none';
});
</script>
@endsection
