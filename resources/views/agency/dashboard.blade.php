@extends('layouts.app')
@section('title', 'Acenta Paneli — ' . $agency->name)

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:0;">
            {{-- Profile Header --}}
            <div style="display:flex;align-items:center;gap:20px;margin-bottom:32px;flex-wrap:wrap;">
                @if($agency->logo)
                    <img src="{{ $agency->logo }}" alt="{{ $agency->name }}" style="width:56px;height:56px;border-radius:14px;object-fit:cover;box-shadow:0 4px 12px rgba(0,0,0,0.08);">
                @else
                    <div style="width:56px;height:56px;background:linear-gradient(135deg,#10b981,#059669);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:24px;box-shadow:0 4px 12px rgba(16,185,129,0.3);">🏢</div>
                @endif
            <div style="flex:1;">
                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">{{ $agency->name }}</h1>
                <div style="font-size:13px;color:#64748b;">{{ $agency->email }} · {{ $agency->phone }}</div>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('agency.profile') }}" class="btn btn-outline btn-sm">⚙️ Profil</a>
                <a href="{{ route('agency.tours.index') }}" class="btn btn-outline btn-sm">Turlarım →</a>
                <a href="{{ route('agency.stats') }}" class="btn btn-outline btn-sm">📊 İstatistik</a>
                <a href="{{ route('agency.campaigns.index') }}" class="btn btn-outline btn-sm">🏷️ Kampanyalar</a>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        {{-- Click Stats (Clickable with Mini Rings) --}}
        <style>
            .stat-card-link { text-decoration:none; color:inherit; display:block; transition:all 0.25s ease; }
            .stat-card-link:hover .stat-card { transform:translateY(-3px); box-shadow:0 14px 30px -8px rgba(0,0,0,0.12) !important; }
            .stat-card-link .stat-card { cursor:pointer; }
            .stat-card-link .stat-card div[style*="font-size:13px"] { white-space:nowrap; }
        </style>
        <div class="grid-4" style="margin-bottom:32px;">
            <a href="{{ route('agency.stats') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#10b981;">{{ $todayClicks }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Bugünkü Tıklama</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring1"></canvas></div>
                </div>
            </a>
            <a href="{{ route('agency.stats') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#3b82f6;">{{ $weekClicks }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Son 7 Gün</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring2"></canvas></div>
                </div>
            </a>
            <a href="{{ route('agency.stats') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#059669;">{{ $monthClicks }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Son 30 Gün</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring3"></canvas></div>
                </div>
            </a>
            <a href="{{ route('agency.stats') }}" class="stat-card-link">
                <div class="stat-card" style="padding:20px;display:flex;justify-content:space-between;align-items:center;">
                    <div>
                        <div style="font-size:24px;font-weight:800;color:#f59e0b;">{{ $totalClicks }}</div>
                        <div style="font-size:13px;color:#64748b;font-weight:600;margin-top:2px;">Toplam Tıklama</div>
                    </div>
                    <div style="width:48px;height:48px;"><canvas id="ring4"></canvas></div>
                </div>
            </a>
        </div>

        {{-- Charts Row --}}
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:32px;">
            <div class="stat-card" style="padding:24px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                    <span style="font-size:16px;">📈</span>
                    <h3 style="font-size:16px;font-weight:700;color:#0f172a;letter-spacing:-0.3px;">Son 30 Gün Tıklama</h3>
                </div>
                <div style="position:relative;height:280px;width:100%;">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
            <div class="stat-card" style="padding:24px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:24px;">
                    <span style="font-size:16px;">🕐</span>
                    <h3 style="font-size:16px;font-weight:700;color:#0f172a;letter-spacing:-0.3px;">Saatlik Dağılım</h3>
                </div>
                <div style="position:relative;height:280px;width:100%;">
                    <canvas id="hourlyChart"></canvas>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 320px;gap:32px;">
            {{-- Left: Tours with clicks --}}
            <div class="stat-card" style="padding:24px;">
                <h2 style="font-size:18px;font-weight:700;margin-bottom:20px;color:#0f172a;">Tur Bazlı Tıklamalar</h2>
                <div style="overflow-x:auto;">
                    <table class="table" style="width:100%;text-align:left;">
                        <thead>
                            <tr><th style="padding-left:0;">Tur</th><th>Destinasyon</th><th>Fiyat</th><th>Tıklama</th><th>Durum</th></tr>
                        </thead>
                        <tbody>
                            @foreach($agency->activeTours->sortByDesc(fn($t) => $tourClicks[$t->id] ?? 0) as $tour)
                            <tr>
                                <td style="padding-left:0;"><a href="{{ route('agency.tours.show', $tour) }}" style="font-weight:600;color:#0f172a;">{{ $tour->title }}</a></td>
                                <td>{{ $tour->destination }}</td>
                                <td>{{ $tour->formatted_price }}</td>
                                <td>
                                    <span style="font-weight:700;color:var(--accent);font-size:16px;">{{ $tourClicks[$tour->id] ?? 0 }}</span>
                                </td>
                                <td>
                                    <span class="badge" style="background:{{ $tour->is_active ? '#d1fae5;color:#065f46' : '#fef2f2;color:#991b1b' }};border:none;padding:6px 12px;border-radius:20px;font-weight:600;">
                                        {{ $tour->is_active ? 'Aktif' : 'Pasif' }}
                                    </span>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Right: Agency Info --}}
            <div>
                <div class="stat-card" style="padding:24px;">
                    <h3 style="font-size:16px;font-weight:700;margin-bottom:20px;color:#0f172a;">Acenta Bilgileri</h3>

                    <div style="margin-bottom:14px;">
                        <div style="font-size:12px;color:#94a3b8;font-weight:500;">Acenta Adı</div>
                        <div style="font-weight:600;color:#0f172a;margin-top:2px;">{{ $agency->name }}</div>
                    </div>

                    @if($agency->email)
                    <div style="margin-bottom:14px;">
                        <div style="font-size:12px;color:#94a3b8;font-weight:500;">E-posta</div>
                        <div style="font-weight:600;color:#0f172a;margin-top:2px;">{{ $agency->email }}</div>
                    </div>
                    @endif

                    @if($agency->phone)
                    <div style="margin-bottom:14px;">
                        <div style="font-size:12px;color:#94a3b8;font-weight:500;">Telefon</div>
                        <div style="font-weight:600;color:#0f172a;margin-top:2px;">{{ $agency->phone }}</div>
                    </div>
                    @endif

                    @if($agency->website_url)
                    <div style="margin-bottom:14px;">
                        <div style="font-size:12px;color:#94a3b8;font-weight:500;">Web Sitesi</div>
                        <a href="{{ $agency->website_url }}" target="_blank" style="font-weight:600;color:var(--accent);margin-top:2px;display:block;">{{ $agency->website_url }}</a>
                    </div>
                    @endif

                    @if($agency->description)
                    <div style="margin-bottom:14px;">
                        <div style="font-size:12px;color:#94a3b8;font-weight:500;">Hakkında</div>
                        <div style="font-size:14px;color:#475569;margin-top:2px;">{{ $agency->description }}</div>
                    </div>
                    @endif

                    <div style="margin-top:20px;padding-top:20px;border-top:1px solid #f1f5f9;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;text-align:center;">
                            <div>
                                <div style="font-size:22px;font-weight:800;color:#10b981;">{{ $agency->activeTours->count() }}</div>
                                <div style="font-size:12px;color:#94a3b8;font-weight:500;">Aktif Tur</div>
                            </div>
                            <div>
                                @php $destinations = $agency->activeTours->pluck('destination')->unique()->count(); @endphp
                                <div style="font-size:22px;font-weight:800;color:#10b981;">{{ $destinations }}</div>
                                <div style="font-size:12px;color:#94a3b8;font-weight:500;">Destinasyon</div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- AI Talep Radarı: aramaların gördüğü ama karşılanamayan talep --}}
                @if(!empty($unmetDemand) || !empty($missedMatches))
                <div class="stat-card" style="padding:24px;margin-top:24px;">
                    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;color:#0f172a;">🤖 AI Talep Radarı</h3>
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:16px;">Son 30 günün AI aramalarından — gerçek kullanıcı talebi</div>

                    @if(!empty($unmetDemand))
                        <div style="font-size:13px;font-weight:700;color:#b45309;margin-bottom:8px;">Karşılanamayan aramalar</div>
                        @foreach($unmetDemand as $item)
                            <div style="font-size:13px;color:#475569;padding:6px 0;border-bottom:1px solid #f8fafc;">
                                <strong>{{ $item['count'] }} arama:</strong> {{ $item['criteria'] }}
                            </div>
                        @endforeach
                        <div style="font-size:11px;color:#94a3b8;margin-top:6px;margin-bottom:14px;">Bu kriterlere uyan tur eklersen bu aramalarda görünürsün.</div>
                    @endif

                    @if(!empty($missedMatches))
                        <div style="font-size:13px;font-weight:700;color:#b45309;margin-bottom:8px;">Turların ramak kala kaçırdığı aramalar</div>
                        @foreach($missedMatches as $item)
                            <div style="font-size:13px;color:#475569;padding:6px 0;border-bottom:1px solid #f8fafc;">
                                <strong>{{ \Illuminate\Support\Str::limit($item['tour_title'], 34) }}</strong> — {{ $item['count'] }} aramada eşiğin altında kaldı (en zayıf yön: {{ $item['weakest'] }})
                            </div>
                        @endforeach
                        <div style="font-size:11px;color:#94a3b8;margin-top:6px;">İpucu: turun ilgili alanını (program metni, tarih, fiyat) güçlendirmek görünürlüğü artırır.</div>
                    @endif
                </div>
                @endif

                {{-- AI Lead'leri: sohbetten ad+telefon bırakan sıcak müşteriler --}}
                @if(isset($aiLeads) && $aiLeads->isNotEmpty())
                <div class="stat-card" style="padding:24px;margin-top:24px;">
                    <h3 style="font-size:16px;font-weight:700;margin-bottom:6px;color:#0f172a;">📞 AI Sohbet Lead'leri</h3>
                    <div style="font-size:12px;color:#94a3b8;margin-bottom:16px;">Yapay zeka asistanına ad-telefon bırakan müşteriler — en kısa sürede arayın</div>
                    @foreach($aiLeads as $lead)
                        <div style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;font-size:13px;color:#475569;padding:8px 0;border-bottom:1px solid #f8fafc;">
                            <span style="background:{{ $lead->status === 'new' ? '#dcfce7' : '#f1f5f9' }};color:{{ $lead->status === 'new' ? '#15803d' : '#64748b' }};border-radius:999px;padding:2px 10px;font-size:11px;font-weight:700;">{{ $lead->status === 'new' ? 'Yeni' : ucfirst($lead->status) }}</span>
                            <span style="font-weight:700;color:#0f172a;">{{ $lead->name }}</span>
                            <a href="tel:{{ $lead->phone }}" style="color:var(--accent);font-weight:600;">{{ $lead->phone }}</a>
                            <span style="background:#eff6ff;color:#1d4ed8;border-radius:999px;padding:2px 10px;font-size:11px;">{{ $lead->intentLabel() }}</span>
                            @if($lead->tour)
                                <span style="color:#94a3b8;">·</span>
                                <span>{{ \Illuminate\Support\Str::limit($lead->tour->title, 40) }}</span>
                            @endif
                            <span style="margin-left:auto;color:#94a3b8;font-size:11px;">{{ $lead->created_at->diffForHumans() }}</span>
                        </div>
                        @if($lead->note)
                            <div style="font-size:11px;color:#94a3b8;padding:2px 0 6px;">{{ \Illuminate\Support\Str::limit($lead->note, 120) }}</div>
                        @endif
                    @endforeach
                </div>
                @endif
            </div>
        </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// Mini Ring Charts
function renderRing(id, hexColor, fillPercent) {
    const ctx = document.getElementById(id).getContext('2d');
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [fillPercent, 100 - fillPercent],
                backgroundColor: [hexColor, hexColor + '20'],
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

renderRing('ring1', '#10b981', {{ $totalClicks > 0 ? round($todayClicks / max($totalClicks, 1) * 100) : 0 }});
renderRing('ring2', '#3b82f6', {{ $totalClicks > 0 ? round($weekClicks / max($totalClicks, 1) * 100) : 0 }});
renderRing('ring3', '#059669', {{ $totalClicks > 0 ? round($monthClicks / max($totalClicks, 1) * 100) : 0 }});
renderRing('ring4', '#f59e0b', 100);

// Premium Line Chart
const dailyCtx = document.getElementById('dailyChart').getContext('2d');
const gradient = dailyCtx.createLinearGradient(0, 0, 0, 280);
gradient.addColorStop(0, 'rgba(16, 185, 129, 0.35)');
gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

new Chart(dailyCtx, {
    type: 'line',
    data: {
        labels: @json($chartLabels),
        datasets: [{
            label: 'Tıklama',
            data: @json($chartData),
            borderColor: '#10b981',
            backgroundColor: gradient,
            fill: true,
            tension: 0.45,
            pointRadius: 0,
            pointHoverRadius: 6,
            borderWidth: 2.5,
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
            legend: { display: false },
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

// Premium Bar Chart
const hourCtx = document.getElementById('hourlyChart').getContext('2d');
const barGradient = hourCtx.createLinearGradient(0, 0, 0, 280);
barGradient.addColorStop(0, 'rgba(59, 130, 246, 0.8)');
barGradient.addColorStop(1, 'rgba(59, 130, 246, 0.2)');

new Chart(hourCtx, {
    type: 'bar',
    data: {
        labels: @json($hourLabels),
        datasets: [{
            label: 'Tıklama',
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
