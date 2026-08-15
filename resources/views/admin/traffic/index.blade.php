@extends('layouts.app')
@section('title', 'Trafik — Admin')

@php
    $baseParams = array_filter([
        'range' => $range !== 'all' ? $range : null,
        'agency_id' => $agencyId,
        'q' => request('q'),
    ]);
@endphp

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="max-width:94%;margin:0 auto 24px;">
                <h1 style="font-size:24px;font-weight:700;color:#0f172a;">Trafik</h1>
                <p style="color:#64748b;font-size:14px;margin-top:4px;">
                    Hangi tur tıklanıyor, hangisi sadece görüntüleniyor — {{ mb_strtolower($rangeLabel) }}.
                </p>
            </div>

            {{-- Metrik sekmeleri --}}
            <div style="max-width:94%;margin:0 auto 16px;display:flex;gap:8px;">
                @foreach(['clicks' => 'Tıklama', 'views' => 'Görüntülenme'] as $key => $label)
                    <a href="{{ route('admin.traffic', $baseParams + ['metric' => $key]) }}"
                       style="padding:9px 18px;border-radius:999px;font-size:14px;font-weight:700;text-decoration:none;border:1px solid {{ $metric === $key ? 'transparent' : '#e2e8f0' }};background:{{ $metric === $key ? ($key === 'clicks' ? '#ec4899' : '#8b5cf6') : '#fff' }};color:{{ $metric === $key ? '#fff' : '#475569' }};">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- Filtreler --}}
            <div class="card" style="margin:0 auto 24px;padding:20px;max-width:94%;">
                <form action="{{ route('admin.traffic') }}" method="GET" style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">
                    <input type="hidden" name="metric" value="{{ $metric }}">

                    <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                        <label style="font-size:13px;color:#475569;">Arama (Tur, Destinasyon)</label>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Tur ara..." style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;">
                    </div>

                    <div class="form-group" style="width:160px;margin-bottom:0;">
                        <label style="font-size:13px;color:#475569;">Tarih Aralığı</label>
                        <select name="range" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;background:#fff;">
                            @foreach($ranges as $value => $label)
                                <option value="{{ $value }}" {{ $range === (string) $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="form-group" style="width:180px;margin-bottom:0;">
                        <label style="font-size:13px;color:#475569;">Acenta</label>
                        <select name="agency_id" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;background:#fff;">
                            <option value="">Tümü</option>
                            @foreach($agencies as $agency)
                                <option value="{{ $agency->id }}" {{ $agencyId === $agency->id ? 'selected' : '' }}>{{ $agency->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div style="display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary" style="height:41px;padding:0 20px;">Uygula</button>
                        @if(request()->except(['page', 'metric']))
                            <a href="{{ route('admin.traffic', ['metric' => $metric]) }}" class="btn btn-outline" style="height:41px;padding:0 20px;display:flex;align-items:center;">Temizle</a>
                        @endif
                    </div>
                </form>
            </div>

            {{-- Özet kartları --}}
            <div style="max-width:94%;margin:0 auto 24px;display:grid;grid-template-columns:repeat(4,1fr);gap:16px;">
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:24px;font-weight:800;color:#ec4899;">{{ number_format($totals['clicks']) }}</div>
                    <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Tıklama</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:24px;font-weight:800;color:#8b5cf6;">{{ number_format($totals['views']) }}</div>
                    <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Görüntülenme</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:24px;font-weight:800;color:#10b981;">{{ $totals['ctr'] !== null ? $totals['ctr'].'%' : '—' }}</div>
                    <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Dönüşüm (tıklama ÷ görüntülenme)</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:24px;font-weight:800;color:#6366f1;">{{ number_format($totals['tours_with_traffic']) }}</div>
                    <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Trafik alan tur</div>
                </div>
            </div>

            {{-- Günlük grafik --}}
            <div class="card" style="max-width:94%;margin:0 auto 24px;padding:24px;">
                <h3 style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:20px;">Son {{ $chartDays }} Gün — Günlük Kırılım</h3>
                <div style="height:260px;"><canvas id="trafficChart"></canvas></div>
            </div>

            {{-- Tablo --}}
            <div style="max-width:94%;margin:0 auto;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
                    <div style="font-size:13px;color:#64748b;">
                        {{ $tours->total() }} tur listeleniyor — {{ $metric === 'clicks' ? 'tıklamaya' : 'görüntülenmeye' }} göre sıralı.
                    </div>
                    <a href="{{ route('admin.tours', ['traffic' => $metric === 'clicks' ? 'clicked' : 'viewed', 'sort' => $metric === 'clicks' ? 'clicks_desc' : 'views_desc']) }}"
                       style="font-size:13px;color:var(--accent);font-weight:600;text-decoration:none;">Tur listesinde aç →</a>
                </div>

                @if($tours->isEmpty())
                    <div class="card" style="text-align:center;padding:40px;color:#64748b;">
                        Bu aralıkta trafik alan tur yok.
                    </div>
                @else
                    <div class="card" style="padding:0;overflow:hidden;margin:0;">
                        <table class="table" style="margin:0;border:none;">
                            <thead>
                                <tr>
                                    <th>Tur</th>
                                    <th>Acenta</th>
                                    <th style="text-align:right;">Tıklama</th>
                                    <th style="text-align:right;">Görüntülenme</th>
                                    <th style="text-align:right;">Dönüşüm</th>
                                    <th>Son Tıklama</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tours as $tour)
                                    @php
                                        $rowClicks = (int) $tour->range_clicks;
                                        $rowViews = (int) $tour->range_views;
                                    @endphp
                                    <tr style="border-bottom:1px solid var(--border-light);">
                                        <td style="font-weight:600;">
                                            <a href="{{ route('admin.traffic.show', $tour) }}" style="color:#0f172a;text-decoration:none;">{{ $tour->title }}</a>
                                            <div style="font-size:12px;color:#94a3b8;font-weight:400;">{{ $tour->destination }}</div>
                                        </td>
                                        <td>{{ optional($tour->agency)->name ?? '—' }}</td>
                                        <td style="text-align:right;font-weight:{{ $metric === 'clicks' ? 800 : 600 }};color:{{ $rowClicks > 0 ? '#ec4899' : '#cbd5e1' }};">{{ number_format($rowClicks) }}</td>
                                        <td style="text-align:right;font-weight:{{ $metric === 'views' ? 800 : 600 }};color:{{ $rowViews > 0 ? '#8b5cf6' : '#cbd5e1' }};">{{ number_format($rowViews) }}</td>
                                        <td style="text-align:right;color:#475569;">{{ $rowViews > 0 ? round($rowClicks / $rowViews * 100, 1).'%' : '—' }}</td>
                                        <td style="color:#64748b;font-size:13px;">
                                            {{ $tour->last_click_at ? \Illuminate\Support\Carbon::parse($tour->last_click_at)->format('d.m.Y H:i') : '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div style="margin-top:16px;">{{ $tours->links() }}</div>
                @endif

                <p style="font-size:12px;color:#94a3b8;margin:16px 0 32px;line-height:1.6;">
                    <strong>Tüm zamanlar</strong> tur sayaçlarından okunur ve dashboard kutularıyla birebir aynıdır.
                    <strong>Son 7/30/90 gün</strong> ham kayıtlardan hesaplanır; bu kayıtlar {{ $retentionDays }} gün sonra silindiği için
                    “Son Tıklama” sütunu da en fazla {{ $retentionDays }} gün geriye gider.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
const ctx = document.getElementById('trafficChart').getContext('2d');

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
