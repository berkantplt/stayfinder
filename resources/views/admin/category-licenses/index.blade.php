@extends('layouts.app')
@section('title', 'Kategori Yetkilendirme — Genel Bakış')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <div style="font-size:13px;font-weight:700;color:#0ea5e9;letter-spacing:0.3px;text-transform:uppercase;">Kategori Yetkilendirme</div>
                    <h1 style="font-size:28px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;margin-top:6px;">Genel Bakış</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:4px;">Yetkilendirme gelirini, kategori talebini ve son 6 aylık akışı tek ekranda izleyin.</div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="{{ route('admin.category-licenses.pricing') }}" class="btn btn-outline">Kategori Tarifesi</a>
                    <a href="{{ route('admin.category-licenses.access') }}" class="btn btn-primary">Acenta Erişimleri</a>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:94%;margin:0 auto 18px;">
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Gerçek Aylık Gelir</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ number_format($stats['actual_monthly_revenue'], 0, ',', '.') }} TL</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Aylık Portföy Değeri</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ number_format($stats['portfolio_monthly_value'], 0, ',', '.') }} TL</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Gerçek Aktif Abonelik</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['active_subscriptions'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Toplam Yetki Talebi</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['combined_demand'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Toplam Sipariş</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['total_orders'] }}</div>
                </div>
            </div>

            <div style="max-width:94%;margin:0 auto 24px;padding:12px 16px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:13px;line-height:1.6;">
                Gerçek gelir ve siparişler sadece satın alınmış kategori aboneliklerinden hesaplanır. Portföy değeri ve talep tarafı ise geçiş erişimli acentaların aktif kullandığı kategorileri de içerir.
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:16px;max-width:94%;margin:0 auto 24px;">
                <a href="{{ route('admin.category-licenses.pricing') }}" class="stat-card" style="padding:20px;text-decoration:none;color:inherit;">
                    <div style="font-size:14px;font-weight:700;color:#0f172a;">Kategori Tarifesi</div>
                    <div style="font-size:13px;color:#64748b;margin-top:6px;">{{ $stats['category_count'] }} kategori, ortalama {{ number_format($stats['average_monthly_price'], 0, ',', '.') }} TL / ay</div>
                </a>
                <a href="{{ route('admin.category-licenses.access') }}" class="stat-card" style="padding:20px;text-decoration:none;color:inherit;">
                    <div style="font-size:14px;font-weight:700;color:#0f172a;">Acenta Erişimleri</div>
                    <div style="font-size:13px;color:#64748b;margin-top:6px;">{{ $stats['legacy_agencies'] }} geçiş erişimli acenta, {{ $stats['legacy_active_tours'] }} aktif tur</div>
                </a>
                <a href="{{ route('admin.category-licenses.orders') }}" class="stat-card" style="padding:20px;text-decoration:none;color:inherit;">
                    <div style="font-size:14px;font-weight:700;color:#0f172a;">Siparişler</div>
                    <div style="font-size:13px;color:#64748b;margin-top:6px;">Kategori satın alma geçmişini ayrı ekranda takip edin.</div>
                </a>
            </div>

            <div style="display:grid;grid-template-columns:minmax(0,1.55fr) minmax(320px,1fr);gap:24px;max-width:94%;margin:0 auto 24px;">
                <div class="stat-card" style="padding:24px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                        <div>
                            <h2 style="font-size:18px;font-weight:700;color:#0f172a;">Aylık Gelir ve Sipariş Trendi</h2>
                            <div style="font-size:13px;color:#64748b;margin-top:4px;">Son 6 ayın gerçek sipariş ve lisans aktivasyon akışı.</div>
                        </div>
                    </div>
                    <div style="position:relative;height:300px;">
                        <canvas id="licenseTrendChart"></canvas>
                    </div>
                </div>

                <div class="stat-card" style="padding:24px;">
                    <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                        <div>
                            <h2 style="font-size:18px;font-weight:700;color:#0f172a;">En Çok Talep Gören 10 Kategori</h2>
                            <div style="font-size:13px;color:#64748b;margin-top:4px;">Gerçek abonelik + legacy aktif kullanım birlikte hesaplanır.</div>
                        </div>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:14px;">
                        @php($maxDemand = max(1, (int) $topDemandCategories->max('combined_demand_count')))
                        @forelse($topDemandCategories as $category)
                            <div>
                                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                    <div>
                                        <div style="font-weight:700;color:#0f172a;">{{ $category->icon }} {{ $category->name }}</div>
                                        <div style="font-size:12px;color:#94a3b8;margin-top:4px;">
                                            {{ $category->active_subscriptions_count }} gerçek lisans · {{ $category->legacy_demand_count }} legacy talep
                                        </div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-size:18px;font-weight:800;color:#0f172a;">{{ $category->combined_demand_count }}</div>
                                        <div style="font-size:12px;color:#94a3b8;">{{ number_format((float) $category->portfolio_monthly_value, 0, ',', '.') }} TL</div>
                                    </div>
                                </div>
                                <div style="height:8px;border-radius:999px;background:#e2e8f0;margin-top:10px;overflow:hidden;">
                                    <div style="height:100%;border-radius:999px;background:linear-gradient(90deg,#10b981,#3b82f6);width:{{ min(100, round(($category->combined_demand_count / $maxDemand) * 100)) }}%;"></div>
                                </div>
                            </div>
                        @empty
                            <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                                Talep analizi için kategori verisi bulunamadı.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const trendCanvas = document.getElementById('licenseTrendChart');

    if (!trendCanvas || typeof Chart === 'undefined') {
        return;
    }

    const ctx = trendCanvas.getContext('2d');
    const revenueGradient = ctx.createLinearGradient(0, 0, 0, 300);
    revenueGradient.addColorStop(0, 'rgba(16, 185, 129, 0.30)');
    revenueGradient.addColorStop(1, 'rgba(16, 185, 129, 0.02)');

    new Chart(ctx, {
        data: {
            labels: @json($trendData['labels']),
            datasets: [
                {
                    type: 'bar',
                    label: 'Gerçek gelir (TL)',
                    data: @json($trendData['revenueData']),
                    backgroundColor: revenueGradient,
                    borderColor: '#10b981',
                    borderWidth: 1.5,
                    borderRadius: 10,
                    yAxisID: 'y',
                },
                {
                    type: 'line',
                    label: 'Sipariş',
                    data: @json($trendData['ordersData']),
                    borderColor: '#2563eb',
                    backgroundColor: '#2563eb',
                    borderWidth: 2.5,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    yAxisID: 'y1',
                },
                {
                    type: 'line',
                    label: 'Aktive edilen kategori',
                    data: @json($trendData['activationData']),
                    borderColor: '#f97316',
                    backgroundColor: '#f97316',
                    borderWidth: 2.5,
                    tension: 0.35,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    yAxisID: 'y1',
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
                    align: 'start',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 10,
                        color: '#475569',
                        font: { family: 'inherit', size: 12, weight: 600 }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { color: '#94a3b8', font: { family: 'inherit', size: 12 } }
                },
                y: {
                    beginAtZero: true,
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: {
                        color: '#94a3b8',
                        callback: function(value) {
                            return new Intl.NumberFormat('tr-TR').format(value) + ' TL';
                        }
                    }
                },
                y1: {
                    beginAtZero: true,
                    position: 'right',
                    grid: { display: false, drawBorder: false },
                    ticks: { color: '#94a3b8', precision: 0 }
                }
            }
        }
    });
});
</script>
@endpush
