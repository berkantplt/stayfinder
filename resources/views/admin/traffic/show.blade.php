@extends('layouts.app')
@section('title', $tour->title.' — Trafik')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="max-width:94%;margin:0 auto 24px;">
                <a href="{{ route('admin.traffic') }}" style="font-size:13px;color:#64748b;text-decoration:none;">← Trafik</a>
                <h1 style="font-size:24px;font-weight:700;color:#0f172a;margin-top:8px;">{{ $tour->title }}</h1>
                <p style="color:#64748b;font-size:14px;margin-top:4px;">
                    {{ $tour->destination }} · {{ optional($tour->agency)->name ?? 'Acenta yok' }} ·
                    <a href="{{ route('tours.show', $tour) }}" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none;">Turu sitede aç ↗</a>
                </p>
            </div>

            {{-- Gün aralığı --}}
            <div style="max-width:94%;margin:0 auto 16px;display:flex;gap:8px;">
                @foreach([7, 30, 90] as $option)
                    <a href="{{ route('admin.traffic.show', [$tour, 'days' => $option]) }}"
                       style="padding:8px 16px;border-radius:999px;font-size:13px;font-weight:700;text-decoration:none;border:1px solid {{ $days === $option ? 'transparent' : '#e2e8f0' }};background:{{ $days === $option ? '#0f172a' : '#fff' }};color:{{ $days === $option ? '#fff' : '#475569' }};">
                        Son {{ $option }} gün
                    </a>
                @endforeach
            </div>

            {{-- Özet --}}
            <div style="max-width:94%;margin:0 auto 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:24px;font-weight:800;color:#ec4899;">{{ number_format($rangeClicks) }}</div>
                    <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Tıklama ({{ $days }} gün)</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:24px;font-weight:800;color:#8b5cf6;">{{ number_format($rangeViews) }}</div>
                    <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Görüntülenme ({{ $days }} gün)</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:24px;font-weight:800;color:#10b981;">{{ $rangeViews > 0 ? round($rangeClicks / $rangeViews * 100, 1).'%' : '—' }}</div>
                    <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Dönüşüm</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:24px;font-weight:800;color:#6366f1;">{{ number_format($tour->clicks_count) }} / {{ number_format($tour->views_count) }}</div>
                    <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Tüm zamanlar (tıklama / görüntülenme)</div>
                </div>
            </div>

            {{-- Grafik --}}
            <div class="card" style="max-width:94%;margin:0 auto 24px;padding:24px;">
                <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:20px;">Son {{ $days }} Gün — Günlük Kırılım</h3>
                <div style="height:260px;"><canvas id="tourTrafficChart"></canvas></div>
            </div>

            {{-- Son tıklamalar --}}
            <div class="card" style="max-width:94%;margin:0 auto;padding:0;overflow:hidden;">
                <div style="padding:20px;border-bottom:1px solid #f1f5f9;">
                    <h3 style="font-size:16px;font-weight:700;color:#0f172a;">Son Tıklamalar</h3>
                </div>
                @if($recentClicks->isEmpty())
                    <div style="padding:32px;text-align:center;color:#64748b;">Kayıtlı tıklama yok.</div>
                @else
                    <table class="table" style="margin:0;border:none;">
                        <thead><tr><th>Zaman</th><th>IP (maskeli)</th></tr></thead>
                        <tbody>
                            @foreach($recentClicks as $click)
                                <tr style="border-bottom:1px solid var(--border-light);">
                                    <td>{{ $click->clicked_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                    <td style="color:#64748b;font-family:monospace;font-size:13px;">
                                        {{ $click->ip_address ? preg_replace('/\.\d+$/', '.x', $click->ip_address) : '—' }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <p style="max-width:94%;margin:16px auto 32px;font-size:12px;color:#94a3b8;line-height:1.6;">
                Ham tıklama/görüntülenme kayıtları {{ $retentionDays }} gün sonra siliniyor; “Tüm zamanlar” kutusu ise tur sayaçlarından okunduğu için geçmişin tamamını kapsar.
            </p>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('tourTrafficChart').getContext('2d');

const gClick = ctx.createLinearGradient(0, 0, 0, 260);
gClick.addColorStop(0, 'rgba(236, 72, 153, 0.30)');
gClick.addColorStop(1, 'rgba(236, 72, 153, 0.0)');

const gView = ctx.createLinearGradient(0, 0, 0, 260);
gView.addColorStop(0, 'rgba(139, 92, 246, 0.30)');
gView.addColorStop(1, 'rgba(139, 92, 246, 0.0)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels: @json($chart['labels']),
        datasets: [
            { label: 'Tıklama', data: @json($chart['clicks']), borderColor: '#ec4899', backgroundColor: gClick, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 6, borderWidth: 2.5 },
            { label: 'Görüntülenme', data: @json($chart['views']), borderColor: '#8b5cf6', backgroundColor: gView, fill: true, tension: 0.4, pointRadius: 0, pointHoverRadius: 6, borderWidth: 2.5 }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { position: 'top', labels: { boxWidth: 10, usePointStyle: true, font: { size: 13, weight: 600 }, color: '#64748b', padding: 20 } },
            tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.9)', padding: 12, cornerRadius: 8 }
        },
        scales: {
            x: { grid: { display: false }, ticks: { maxTicksLimit: 8, font: { size: 12 }, color: '#94a3b8' } },
            y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { precision: 0, font: { size: 12 }, color: '#94a3b8', padding: 10 } }
        }
    }
});
</script>
@endpush
