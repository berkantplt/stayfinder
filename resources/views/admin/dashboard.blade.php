@extends('layouts.app')
@section('title', 'Admin Panel — StayFinder')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:32px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Dashboard</h1>
                <div style="font-size:13px;color:#64748b;display:flex;align-items:center;gap:6px;">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M12 6v6l4 2"></path></svg>
                    Veriler 5 dakika önce güncellendi
                </div>
            </div>

        {{-- Stats Cards (Clickable) --}}
        <style>
            .stat-card-link { text-decoration:none; color:inherit; display:block; transition:all 0.25s ease; }
            .stat-card-link:hover .stat-card { transform:translateY(-3px); box-shadow:0 14px 30px -8px rgba(0,0,0,0.12) !important; }
            .stat-card-link .stat-card { cursor:pointer; }
            .stat-card-link .stat-card div[style*="font-size:13px"] { white-space:nowrap; font-size:12px !important; }
        </style>
        <div style="display:grid;grid-template-columns:repeat(6,1fr);gap:16px;margin-bottom:32px;">
            <a href="{{ route('admin.agencies') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#10b981;">{{ $stats['agencies'] }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Acenta</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring1"></canvas></div>
                </div>
            </a>
            <a href="{{ route('admin.tours') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#10b981;">{{ $stats['activeTours'] }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Aktif Tur</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring2"></canvas></div>
                </div>
            </a>
            <a href="{{ route('admin.tours') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#6366f1;">{{ $stats['tours'] }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Toplam Tur</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring3"></canvas></div>
                </div>
            </a>
            <a href="{{ route('admin.agencies') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#f59e0b;">{{ $stats['users'] }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Kullanıcı</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring4"></canvas></div>
                </div>
            </a>
            <a href="{{ route('admin.tours') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#ec4899;">{{ $stats['clicks'] }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Toplam Tıklama</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring5"></canvas></div>
                </div>
            </a>
            <a href="{{ route('admin.tours') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#8b5cf6;">{{ $stats['views'] }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Görüntülenme</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring6"></canvas></div>
                </div>
            </a>
        </div>



        {{-- Charts --}}
        <div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;margin-bottom:32px;">
            <div class="stat-card" style="padding:24px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                    <span style="font-size:16px;">📈</span>
                    <h3 style="font-size:16px;font-weight:700;color:#0f172a;letter-spacing:-0.3px;">Son 30 Gün — Tıklama & Görüntülenme</h3>
                </div>
                <div style="position:relative;height:300px;width:100%;">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
            <div class="stat-card" style="padding:24px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                    <span style="font-size:16px;">📍</span>
                    <h3 style="font-size:16px;font-weight:700;color:#0f172a;letter-spacing:-0.3px;">Popüler Destinasyonlar</h3>
                </div>
                <div style="position:relative;height:300px;width:100%;">
                    <canvas id="destChart"></canvas>
                </div>
            </div>
        </div>

        {{-- Agency Table --}}
        <div class="stat-card" style="padding:24px;">
            <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;color:#0f172a;">Acenta Bazlı Tur Sayıları</h2>
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;text-align:left;">
                    <thead><tr><th style="padding-left:0;">Acenta</th><th>Tur Sayısı</th><th>Durum</th></tr></thead>
                    <tbody>
                        @foreach($agencies as $a)
                        <tr>
                            <td style="font-weight:600;padding-left:0;">{{ $a->name }}</td>
                            <td>{{ $a->tours_count }}</td>
                            <td>
                                <span class="badge" style="background:{{ $a->is_active ? '#d1fae5;color:#065f46' : '#fef2f2;color:#991b1b' }};border:none;padding:6px 12px;border-radius:20px;font-weight:600;">
                                    {{ $a->is_active ? 'Aktif' : 'Pasif' }}
                                </span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// Mini Ring Charts Helper
function renderRing(id, hexColor, fillPercent) {
    const ctx = document.getElementById(id).getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [fillPercent, 100 - fillPercent],
                backgroundColor: [hexColor, hexColor + '20'], // 20 adds roughly 12% opacity to hex
                borderWidth: 0,
                hoverOffset: 0,
                cutout: '75%'
            }]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            plugins: { tooltip: { enabled: false }, legend: { display: false } },
            animation: { animateRotate: true, duration: 1500 }
        }
    });
}

// Render Rings
renderRing('ring1', '#10b981', 35); // Acenta
renderRing('ring2', '#10b981', 85); // Aktif Tur
renderRing('ring3', '#6366f1', 95); // Toplam Tur
renderRing('ring4', '#f59e0b', 55); // Kullanıcı
renderRing('ring5', '#ec4899', 25); // Toplam Tıklama
renderRing('ring6', '#8b5cf6', 75); // Görüntülenme

// Daily Clicks & Views Premium Line Chart
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
const gradientClick = dailyCtx.createLinearGradient(0, 0, 0, 300);
gradientClick.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
gradientClick.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

const gradientView = dailyCtx.createLinearGradient(0, 0, 0, 300);
gradientView.addColorStop(0, 'rgba(139, 92, 246, 0.35)');
gradientView.addColorStop(1, 'rgba(139, 92, 246, 0.0)');

new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: @json($chartLabels),
        datasets: [
            {
                label: 'Tıklama',
                data: @json($clickData),
                borderColor: '#10b981',
                backgroundColor: gradientClick,
                fill: true,
                tension: 0.45,
                pointRadius: 0,
                pointHoverRadius: 6,
                borderWidth: 2.5,
            },
            {
                label: 'Görüntülenme',
                data: @json($viewData),
                borderColor: '#8b5cf6',
                backgroundColor: gradientView,
                fill: true,
                tension: 0.45,
                pointRadius: 0,
                pointHoverRadius: 6,
                borderWidth: 2.5,
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { 
                position: 'top', 
                align: 'center',
                labels: { boxWidth: 10, usePointStyle: true, font: { family: 'inherit', size: 13, weight: 600 }, color: '#64748b', padding: 20 } 
            },
            tooltip: { backgroundColor: 'rgba(15, 23, 42, 0.9)', titleFont: { size: 13 }, bodyFont: { size: 13 }, padding: 12, cornerRadius: 8 }
        },
        scales: {
            x: { 
                grid: { display: false, drawBorder: false },
                ticks: { maxTicksLimit: 7, font: { family: 'inherit', size: 12 }, color: '#94a3b8' } 
            },
            y: { 
                beginAtZero: true, 
                grid: { color: '#f1f5f9', drawBorder: false },
                ticks: { stepSize: 1, font: { family: 'inherit', size: 12 }, color: '#94a3b8', padding: 10 } 
            }
        }
    }
});

// Destinations doughnut chart
var destLabels = @json($topDestinations->keys());
var destData = @json($topDestinations->values());
var destColors = ['#10b981','#3b82f6','#f59e0b','#ec4899','#8b5cf6','#14b8a6','#f97316','#ef4444'];
new Chart(document.getElementById('destChart').getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: destLabels,
        datasets: [{
            data: destData,
            backgroundColor: destColors.slice(0, destData.length),
            borderWidth: 2,
            borderColor: '#ffffff',
            hoverOffset: 4,
            cutout: '50%'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { 
                position: 'right', 
                labels: { boxWidth: 12, usePointStyle: true, font: { family: 'inherit', size: 12, weight: 500 }, color: '#475569', padding: 16 } 
            }
        }
    }
});
</script>
@endpush
