@extends('layouts.app')
@section('title', 'Tur İstatistikleri — Acenta Paneli')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:28px;">
                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">📊 Tur İstatistikleri</h1>
            </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
            {{-- Most Viewed --}}
            <div class="stat-card" style="padding:24px;">
                <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;color:#0f172a;">👁️ En Çok Görüntülenen</h3>
                @forelse($topViewed as $item)
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #f1f5f9;">
                    <a href="{{ route('agency.tours.show', $item->tour) }}" style="font-weight:600;font-size:14px;color:#0f172a;">{{ $item->tour->title }}</a>
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
                    <a href="{{ route('agency.tours.show', $item->tour) }}" style="font-weight:600;font-size:14px;color:#0f172a;">{{ $item->tour->title }}</a>
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

        {{-- Popular Dates --}}
        <div class="stat-card" style="padding:24px;">
            <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;color:#0f172a;">📅 Yaklaşan Popüler Tarihler</h3>
            @forelse($popularDates as $date)
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f1f5f9;">
                <div>
                    <span style="font-weight:600;font-size:14px;color:#0f172a;">{{ $date->tour->title }}</span>
                    @if($date->label)<span class="badge badge-accent" style="font-size:10px;margin-left:6px;">{{ $date->label }}</span>@endif
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:600;font-size:13px;color:#0f172a;">📅 {{ $date->departure_date->format('d M Y') }} → {{ $date->return_date->format('d M Y') }}</div>
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
