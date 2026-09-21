@extends('layouts.app')
@section('title', 'Tur İstatistikleri — Acenta Paneli')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section ist-neon" style="padding:0;">
            {{-- Başlık: turkuaz bant, kartlarla aynı hizada (%94 ortalı) --}}
            <div class="ist-baslik-kutu p-ham">
                <span class="ist-baslik-ikon" aria-hidden="true">📊</span>
                <h1>Tur İstatistikleri</h1>
            </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
            {{-- Most Viewed --}}
            <div class="stat-card" style="padding:24px;">
                <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;color:#0f172a;">👁️ En Çok Görüntülenen</h3>
                @forelse($topViewed as $item)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;">
                    @if($item->tour && ! $item->tour->trashed())
                        <a href="{{ route('agency.tours.show', $item->tour) }}" style="font-weight:600;font-size:14px;color:#0f172a;">{{ $item->tour->title }}</a>
                    @else
                        {{-- C8: arşivlenmiş/silinmiş turun geçmişi kalır, bağlantı verilmez --}}
                        <span style="font-weight:600;font-size:14px;color:#94a3b8;">{{ $item->tour?->title ?? 'Silinmiş tur' }} <small>(arşivde)</small></span>
                    @endif
                    <span style="font-weight:700;color:#3b82f6;font-size:16px;">{{ $item->views }}</span>
                </div>
                @empty
                <div style="color:#94a3b8;font-size:13px;padding:16px;text-align:center;">Veri yok</div>
                @endforelse
            </div>

            {{-- Most Clicked --}}
            <div class="stat-card" style="padding:24px;">
                <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;color:#0f172a;">🖱️ En Çok Tıklanan</h3>
                @forelse($topClicked as $item)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;">
                    @if($item->tour && ! $item->tour->trashed())
                        <a href="{{ route('agency.tours.show', $item->tour) }}" style="font-weight:600;font-size:14px;color:#0f172a;">{{ $item->tour->title }}</a>
                    @else
                        {{-- C8: arşivlenmiş/silinmiş turun geçmişi kalır, bağlantı verilmez --}}
                        <span style="font-weight:600;font-size:14px;color:#94a3b8;">{{ $item->tour?->title ?? 'Silinmiş tur' }} <small>(arşivde)</small></span>
                    @endif
                    <span style="font-weight:700;color:#10b981;font-size:16px;">{{ $item->clicks }}</span>
                </div>
                @empty
                <div style="color:#94a3b8;font-size:13px;padding:16px;text-align:center;">Veri yok</div>
                @endforelse
            </div>
        </div>

        {{-- Hourly chart --}}
        <div class="stat-card" style="padding:24px;margin-bottom:32px;">
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px;">
                <span style="font-size:16px;">🕐</span>
                <h3 style="font-size:16px;font-weight:700;color:#0f172a;">Saatlere Göre İlgi (Son 30 Gün - Görüntülenme)</h3>
            </div>
            <div style="position:relative;height:280px;">
                <canvas id="hourlyViewsChart"></canvas>
            </div>
        </div>

        {{-- C7: "Popüler" iddiası kaldırıldı — tarih bazlı tıklama kaydı yok; liste en yakın tarihe göre --}}
        <div class="stat-card" style="padding:24px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:4px;color:#0f172a;">📅 Yaklaşan Tarihler</h3>
            <div style="font-size:12px;color:#94a3b8;margin-bottom:16px;">En yakın 10 hareket tarihi. Tıklama sayısı tura aittir (son 30 gün), tarihe özel değildir.</div>
            @forelse($upcomingDates as $date)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f1f5f9;">
                <div>
                    <span style="font-weight:600;font-size:14px;color:#0f172a;">{{ $date->tour?->title ?? 'Silinmiş tur' }}</span>
                    @if($date->label)<span class="badge badge-accent" style="font-size:10px;margin-left:6px;">{{ $date->label }}</span>@endif
                    <div style="font-size:11px;color:#64748b;margin-top:2px;">🖱️ {{ $date->recent_clicks }} tıklama / 30 gün</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:600;font-size:13px;color:#0f172a;">📅 {{ $date->departure_date->format('d-m-Y') }} → {{ $date->return_date->format('d-m-Y') }}</div>
                    <div style="font-size:11px;color:#94a3b8;">{{ $date->departure_date->diffForHumans() }}</div>
                </div>
            </div>
            @empty
            <div style="color:#94a3b8;font-size:13px;padding:16px;text-align:center;">Yaklaşan tarih yok</div>
            @endforelse
        </div>
        </div>
    </div>
</div>
@endsection

@push('head')
<style>
    /* ── İstatistik sayfası: turkuaz başlık bandı + kartlarda turkuaz neon ──
       Panel katmanı .stat-card'a !important gölge/kenarlık basar (layouts/app 509-515);
       buradaki seçiciler daha özgül (body.panel-layout-active .ist-neon .stat-card) olduğu için onu ezer. */
    .ist-baslik-kutu {
        max-width:94%; margin:0 auto 28px;
        display:flex; align-items:center; gap:14px;
        padding:18px 26px; border-radius:20px;
        background:linear-gradient(135deg,#0d9488 0%,#14b8a6 55%,#2dd4bf 100%);
        color:#fff;
        box-shadow:0 0 0 1px rgba(45,212,191,.45), 0 0 22px rgba(45,212,191,.35), 0 12px 32px -12px rgba(13,148,136,.55);
    }
    .ist-baslik-ikon {
        flex:0 0 auto; width:44px; height:44px; border-radius:12px;
        display:flex; align-items:center; justify-content:center;
        font-size:22px; background:rgba(255,255,255,.18); box-shadow:inset 0 0 0 1px rgba(255,255,255,.25);
    }
    body.panel-layout-active .ist-baslik-kutu h1, .ist-baslik-kutu h1 {
        color:#fff; font-size:24px; font-weight:800; letter-spacing:-0.5px; margin:0;
        text-shadow:0 1px 2px rgba(15,118,110,.35);
    }
    body.panel-layout-active .ist-neon .stat-card, .ist-neon .stat-card {
        border:1px solid rgba(45,212,191,.65) !important;
        box-shadow:0 0 0 1px rgba(45,212,191,.35), 0 0 18px rgba(45,212,191,.45), 0 0 48px rgba(20,184,166,.22), 0 10px 40px -10px rgba(0,0,0,.06) !important;
        transition:box-shadow .25s ease, border-color .25s ease;
    }
    body.panel-layout-active .ist-neon .stat-card:hover, .ist-neon .stat-card:hover {
        border-color:rgba(45,212,191,.95) !important;
        box-shadow:0 0 0 1px rgba(45,212,191,.5), 0 0 26px rgba(45,212,191,.6), 0 0 70px rgba(20,184,166,.3), 0 12px 40px -10px rgba(0,0,0,.08) !important;
    }
    @media(max-width:768px){
        .ist-baslik-kutu { max-width:100%; padding:14px 18px; margin-bottom:20px; }
        .ist-baslik-ikon { width:38px; height:38px; font-size:19px; }
        body.panel-layout-active .ist-baslik-kutu h1, .ist-baslik-kutu h1 { font-size:20px; }
    }
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('hourlyViewsChart').getContext('2d');
const barGradient = ctx.createLinearGradient(0, 0, 0, 280);
barGradient.addColorStop(0, 'rgba(16, 185, 129, 0.8)');
barGradient.addColorStop(1, 'rgba(16, 185, 129, 0.15)');

new Chart(ctx, {
    type: 'bar',
    data: {
        labels: @json($hourLabels),
        datasets: [{
            label: 'Görüntülenme',
            data: @json($hourData),
            backgroundColor: barGradient,
            borderRadius: 6,
            borderSkipped: false,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.9)', titleFont: { size: 13 }, bodyFont: { size: 13 }, padding: 12, cornerRadius: 8 }
        },
        scales: {
            x: {
                grid: { display: false, drawBorder: false },
                ticks: { maxTicksLimit: 12, font: { family: 'inherit', size: 12 }, color: '#94a3b8' }
            },
            y: {
                beginAtZero: true,
                grid: { color: '#f1f5f9', drawBorder: false },
                ticks: { stepSize: 1, font: { family: 'inherit', size: 12 }, color: '#94a3b8', padding: 10 }
            }
        }
    }
});
</script>
@endpush
