@extends('layouts.app')
@section('title', $tour->title . ' — turXtur')
@section('description', \Illuminate\Support\Str::limit(strip_tags($tour->description), 150))
@if($tour->image)
    @section('og_image', url($tour->image))
@endif

@push('head')
<script type="application/ld+json">
{
  "@@context": "https://schema.org/",
  "@@type": "Product",
  "name": "{{ $tour->title }}",
  "image": "{{ $tour->image ? url($tour->image) : asset('images/og-default.png') }}",
  "description": "{{ \Illuminate\Support\Str::limit(strip_tags($tour->description), 150) }}",
  "brand": {
    "@@type": "Brand",
    "name": "{{ $tour->agency->name ?? 'turXtur' }}"
  },
  "offers": {
    "@@type": "Offer",
    "url": "{{ request()->url() }}",
    "priceCurrency": "{{ $tour->currency ?? 'TRY' }}",
    "price": "{{ $tour->price }}",
    "availability": "https://schema.org/InStock",
    "validFrom": "{{ $tour->departure_date ? $tour->departure_date->toIso8601String() : now()->toIso8601String() }}"
  }
}
</script>
<style>
    .tour-tabs { display:flex; gap:2px; border-bottom:2px solid var(--border); margin:8px 0 20px; overflow-x:auto; scrollbar-width:none; position:sticky; top:70px; background:var(--bg); z-index:50; }
    .tour-tabs::-webkit-scrollbar { display:none; }
    .tour-tab { background:none; border:none; cursor:pointer; font-family:var(--font); font-size:13.5px; font-weight:800; letter-spacing:0.4px; text-transform:uppercase; padding:12px 16px; color:var(--text-sec); border-bottom:3px solid transparent; margin-bottom:-2px; white-space:nowrap; transition:color .2s, border-color .2s; }
    .tour-tab:hover { color:var(--accent); }
    .tour-tab.active { color:var(--accent); border-bottom-color:var(--accent); }
    .tour-tab-count { display:inline-block; background:var(--accent); color:#fff; font-size:11px; font-weight:700; padding:1px 7px; border-radius:999px; margin-left:4px; vertical-align:1px; }
    .tour-tab-panel[hidden] { display:none; }

    /* Mobil kompakt fiyat kartı: tek satır fiyat + acenta, ince buton üçlüsü */
    .p-tel-short { display:none; }
    @media(max-width:768px) {
        #priceCard { display:flex; flex-wrap:wrap; align-items:baseline; column-gap:8px; row-gap:2px; padding:10px 12px !important; border-width:1px !important; border-radius:14px !important; }
        #priceCard .p-badge { margin:0 !important; }
        #priceCard .p-badge .badge { font-size:10px; padding:3px 8px; }
        #priceCard .p-price { margin:0 !important; }
        #priceCard .p-price .price-tag { font-size:22px !important; }
        #priceCard .p-agency { margin:0 0 0 auto !important; }
        #priceCard .p-agency a { font-size:13px !important; }
        #priceCard .p-ctas { flex-basis:100%; flex-direction:row !important; gap:6px !important; margin-top:8px; }
        #priceCard .p-ctas .btn { width:auto !important; padding:9px 8px; font-size:13px; }
        #priceCard .p-go { flex:1.6; }
        #priceCard .p-call { flex:1; }
        #priceCard .p-mail { flex:none; width:74px; padding:9px 0 !important; font-size:17px !important; }
        #priceCard .p-tel-full, #priceCard .p-mail-text { display:none; }
        #priceCard .p-tel-short { display:inline; }
        #priceCard #campaign-countdown { flex-basis:100%; margin:6px 0 0 !important; padding:6px 10px !important; }
        #priceCard #campaign-countdown div:last-child { font-size:14px !important; }
        #mPriceSlot:not(:empty) { margin-bottom:4px; }
    }
</style>
@endpush

@section('content')
<div class="container">
    <div class="section">
        {{-- Breadcrumb --}}
        <div style="font-size:13px;color:var(--text-muted);margin-bottom:20px;">
            <a href="{{ route('home') }}" style="color:var(--accent);">Ana Sayfa</a> ›
            <a href="{{ route('tours.index') }}" style="color:var(--accent);">Turlar</a> ›
            {{ $tour->title }}
        </div>

        <div class="detail-grid">
            {{-- Left: Tour Info --}}
            <div>
                @php
                    $gallery = is_array($tour->images) && count($tour->images) ? $tour->images : ($tour->image ? [$tour->image] : []);
                @endphp
                @if(count($gallery))
                    <div style="margin-bottom:20px;">
                        <img id="galleryMain" src="{{ $gallery[0] }}" alt="{{ $tour->title }}" style="width:100%;height:340px;object-fit:cover;border-radius:var(--radius-lg);">
                        @if(count($gallery) > 1)
                            <div style="display:flex;gap:8px;overflow-x:auto;margin-top:10px;padding-bottom:4px;">
                                @foreach($gallery as $img)
                                    <img src="{{ $img }}" alt="{{ $tour->title }}" loading="lazy"
                                        onclick="document.getElementById('galleryMain').src=this.src;"
                                        style="width:90px;height:64px;object-fit:cover;border-radius:8px;cursor:pointer;flex:0 0 auto;border:2px solid transparent;"
                                        onmouseover="this.style.borderColor='var(--accent)';" onmouseout="this.style.borderColor='transparent';">
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                {{-- AI danışman barı: aramadan gelen kullanıcı bağlamını kaybetmesin --}}
                @if(!empty($aiContext))
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;background:#eef2ff;border:1px solid #c7d2fe;border-radius:12px;padding:10px 14px;margin-bottom:14px;font-size:13px;color:#3730a3;">
                        <span>🤖 Aradığın: “{{ \Illuminate\Support\Str::limit($aiContext['query'], 60) }}”</span>
                        @if($aiContext['compatibility'] !== null)
                            <span style="font-weight:700;">· %{{ round($aiContext['compatibility'] * 100) }} uyumlu</span>
                        @endif
                        @foreach($aiContext['checks'] as $check)
                            <span>· {{ $check }}</span>
                        @endforeach
                        <a href="{{ route('ai.search') }}" style="margin-left:auto;font-weight:700;color:#4338ca;text-decoration:none;white-space:nowrap;">← Sohbete dön</a>
                    </div>
                @endif

                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;margin-bottom:10px;line-height:1.3;">{{ $tour->title }}</h1>

                <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px;">
                    @if($tour->category)
                        <a href="{{ route('tours.index', ['category' => $tour->category->slug]) }}" class="badge badge-accent" style="text-decoration:none;">{{ $tour->category->icon }} {{ $tour->category->name }}</a>
                    @endif
                    <span class="badge badge-accent">📍 {{ $tour->destination }}</span>
                    <span class="badge badge-accent">⏱ {{ $tour->duration_days }} gün</span>
                </div>

                @php
                    // Kalkış + duraklar tek liste: yolcu hepsinden binebilir → hepsi "kalkış şehri"
                    $boardingCities = collect([$tour->departure_city])
                        ->merge(is_array($tour->stop_cities) ? $tour->stop_cities : [])
                        ->filter()
                        ->unique()
                        ->values();
                @endphp
                @if($boardingCities->count())
                    <div style="margin-bottom:16px;font-size:14px;color:var(--text-sec);">
                        <strong>🚌 Kalkış Şehirleri:</strong>
                        @foreach($boardingCities as $city)
                            <a href="{{ route('tours.index', ['departure_city' => $city]) }}" style="color:var(--accent);text-decoration:none;">{{ $city }}</a>{{ ! $loop->last ? ',' : '' }}
                        @endforeach
                    </div>
                @endif

                {{-- Mobilde kompakt fiyat kartı buraya taşınır (JS ile — tek DOM, iki yuva) --}}
                <div id="mPriceSlot"></div>

                {{-- #yorumlar derin linki için görünür çapa: tarayıcı buraya kaydırır,
                     sekme JS'i paneli açar (gizli panele id konursa scroll çalışmaz) --}}
                <div id="yorumlar" style="scroll-margin-top:90px;"></div>

                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif

                @php
                    $upcomingDates = $tour->dates
                        ->filter(fn($date) => $date->departure_date && $date->departure_date->greaterThanOrEqualTo(now()->startOfDay()))
                        ->sortBy('departure_date')
                        ->values();
                    $allDates = $tour->dates
                        ->sortBy('departure_date')
                        ->values();

                    // Kampanya aktifken tarih kartlarında da indirimli fiyat göster
                    $activeCampaignForDates = $tour->activeCampaign;
                    $datePriceRenderer = function ($date) use ($tour, $activeCampaignForDates) {
                        $original = (float) ($date->price ?? $tour->price);
                        $currency = $tour->currency_symbol;

                        if (!$activeCampaignForDates) {
                            return '<span style="display:inline-flex;align-items:center;padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;margin-left:6px;">'
                                . number_format($original, 0, ',', '.') . ' ' . e($currency)
                                . '</span>';
                        }

                        // Pro-rata: tur.price'tan campaign indirimi oranı, date.price'a uygulanır
                        $tourBase = max((float) $tour->price, 0.01);
                        $discountRate = max(0.0, min(1.0, ($tourBase - (float) $activeCampaignForDates->discount_price) / $tourBase));
                        $discounted = round($original * (1 - $discountRate), 2);
                        $pctOff = (int) round($discountRate * 100);

                        return '<span style="margin-left:6px;display:inline-flex;align-items:center;gap:6px;">'
                            . '<s style="color:#94a3b8;font-size:11px;">' . number_format($original, 0, ',', '.') . ' ' . e($currency) . '</s>'
                            . '<span style="padding:2px 8px;border-radius:999px;background:#dcfce7;color:#166534;font-size:11px;font-weight:700;">'
                            . number_format($discounted, 0, ',', '.') . ' ' . e($currency)
                            . '</span>'
                            . ($pctOff > 0 ? '<span style="font-size:10px;color:#16a34a;font-weight:700;">-%' . $pctOff . '</span>' : '')
                            . '</span>';
                    };

                    $hasPricingBlocks = is_array($tour->pricing_blocks) && count($tour->pricing_blocks);

                    $detailSections = [
                        ['🚌', 'Kalkış / Biniş Noktaları', $tour->departure_points],
                        ['🏨', 'Konaklama', $tour->hotel_info],
                        ['➕', 'Ekstra Tur ve Aktiviteler', $tour->extras],
                        ['👤', 'Rehber', $tour->guide_info],
                        ['↩️', 'İptal / İade Koşulları', $tour->cancellation_policy],
                    ];

                    // Sekmeler: içeriği boş olan sekme hiç gösterilmez
                    $hasProgram = is_array($tour->itinerary) && count($tour->itinerary);
                    $hasPrices  = $hasPricingBlocks || $upcomingDates->count() || $allDates->count() || $tour->departure_date || $priceData->count() >= 2;
                    $hasGeneral = trim((string) $tour->description) !== ''
                        || collect($detailSections)->contains(fn ($s) => trim((string) $s[2]) !== '')
                        || trim((string) $tour->frequency) !== '';

                    $tourTabList = array_values(array_filter([
                        $hasProgram ? 'program' : null,
                        $hasPrices ? 'tarihler' : null,
                        $hasGeneral ? 'genel' : null,
                        'yorumlar',
                    ]));
                    $defaultTab = $tourTabList[0];
                @endphp

                {{-- Sekme barı --}}
                <div class="tour-tabs" id="tourTabs" role="tablist">
                    @if($hasProgram)
                        <button type="button" class="tour-tab{{ $defaultTab === 'program' ? ' active' : '' }}" data-tab="program">Tur Programı</button>
                    @endif
                    @if($hasPrices)
                        <button type="button" class="tour-tab{{ $defaultTab === 'tarihler' ? ' active' : '' }}" data-tab="tarihler">Tarihler ve Fiyatlar</button>
                    @endif
                    @if($hasGeneral)
                        <button type="button" class="tour-tab{{ $defaultTab === 'genel' ? ' active' : '' }}" data-tab="genel">Genel Bilgiler</button>
                    @endif
                    <button type="button" class="tour-tab{{ $defaultTab === 'yorumlar' ? ' active' : '' }}" data-tab="yorumlar">Yorumlar
                        @if($reviews->count())<span class="tour-tab-count">{{ $reviews->count() }}</span>@endif
                    </button>
                </div>

                {{-- Sekme: Tur Programı --}}
                @if($hasProgram)
                <div class="tour-tab-panel" data-tab-panel="program" @if($defaultTab !== 'program') hidden @endif>
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;">
                        <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">📋 Tur Programı</h3>
                        @foreach($tour->itinerary as $i => $day)
                            @php
                                // Başlıkta zaten "N. Gün" / "Gün N" ön eki varsa temizle (sayfa kendi ekliyor)
                                $dayTitle = trim($day['title'] ?? '');
                                $dayTitle = preg_replace('/^\s*\d+\s*\.?\s*g[üu]n\s*[:\-–—]?\s*/iu', '', $dayTitle);
                                $dayTitle = preg_replace('/^\s*g[üu]n\s*\d+\s*[:\-–—]?\s*/iu', '', $dayTitle);
                                $dayTitle = trim($dayTitle);
                            @endphp
                            <div style="margin-bottom:14px;padding-bottom:14px;{{ ! $loop->last ? 'border-bottom:1px dashed var(--border);' : '' }}">
                                <div style="font-weight:700;color:#0f172a;margin-bottom:4px;">{{ $i + 1 }}. Gün{{ $dayTitle !== '' ? ': '.$dayTitle : '' }}</div>
                                @if(! empty($day['content']))
                                    <div style="color:var(--text-sec);line-height:1.8;font-size:14px;white-space:pre-line;">{{ $day['content'] }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
                @endif

                {{-- Sekme: Tarihler ve Fiyatlar --}}
                @if($hasPrices)
                <div class="tour-tab-panel" data-tab-panel="tarihler" @if($defaultTab !== 'tarihler') hidden @endif>
                    {{-- Tarih ızgarası yalnızca paket fiyat tablosu YOKKEN gösterilir;
                         fiyat blokları varsa tarihler zaten aşağıdaki tabloda var (tekrar olmasın) --}}
                    @if(! $hasPricingBlocks)
                    @if($upcomingDates->count())
                    <div style="margin-bottom:20px;">
                        <div style="font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:8px;">📅 Kalkış Tarihleri</div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            @foreach($upcomingDates as $date)
                            <div style="background:var(--accent-bg);border-radius:var(--radius);padding:8px 14px;font-size:13px;">
                                <span style="font-weight:600;">{{ $date->departure_date->format('d-m-Y') }}</span>
                                <span style="color:var(--text-muted);margin:0 3px;">→</span>
                                <span style="font-weight:600;">{{ $date->return_date->format('d-m-Y') }}</span>
                                {!! $datePriceRenderer($date) !!}
                                @if($date->label)
                                    <span class="badge badge-accent" style="font-size:10px;margin-left:4px;">{{ $date->label }}</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @elseif($allDates->count())
                    <div style="margin-bottom:20px;">
                        <div style="font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:8px;">📅 Tur Tarihleri</div>
                        <div style="display:flex;flex-wrap:wrap;gap:8px;">
                            @foreach($allDates as $date)
                            <div style="background:var(--accent-bg);border-radius:var(--radius);padding:8px 14px;font-size:13px;{{ $date->departure_date->isPast() ? 'opacity:0.7;' : '' }}">
                                <span style="font-weight:600;">{{ $date->departure_date->format('d-m-Y') }}</span>
                                <span style="color:var(--text-muted);margin:0 3px;">→</span>
                                <span style="font-weight:600;">{{ $date->return_date->format('d-m-Y') }}</span>
                                {!! $datePriceRenderer($date) !!}
                                @if($date->label)
                                    <span class="badge badge-accent" style="font-size:10px;margin-left:4px;">{{ $date->label }}</span>
                                @endif
                            </div>
                            @endforeach
                        </div>
                    </div>
                    @elseif($tour->departure_date)
                    <div style="margin-bottom:16px;">
                        <span class="badge badge-accent">📅 {{ $tour->departure_date->format('d-m-Y') }} — {{ $tour->return_date?->format('d-m-Y') }}</span>
                    </div>
                    @endif
                    @endif

                    {{-- Fiyat tablosu: tarihe tıklanınca paket/oda-tipi fiyat matrisi açılır --}}
                    @if($hasPricingBlocks)
                        @php $roomTypeLabels = \App\Models\Tour::ROOM_TYPES; $priceCurrency = $tour->currency_symbol; @endphp
                        <div style="margin-bottom:24px;">
                            <div style="font-size:13px;font-weight:600;color:var(--text-muted);margin-bottom:8px;">💰 Tarih ve Paket Fiyatları</div>
                            @foreach($tour->pricing_blocks as $block)
                                @php
                                    $blockDates = collect($block['dates'] ?? [])
                                        ->map(fn ($d) => \Illuminate\Support\Carbon::parse($d))
                                        ->sort()
                                        ->values();
                                    $packages = array_values(array_filter((array) ($block['packages'] ?? []), 'is_array'));
                                    // Bu blokta veri girilmiş oda/yaş tiplerini ROOM_TYPES sırasında topla
                                    $activeTypes = [];
                                    foreach (array_keys($roomTypeLabels) as $type) {
                                        foreach ($packages as $pkg) {
                                            $cell = $pkg['prices'][$type] ?? null;
                                            if (is_array($cell) && (($cell['old'] ?? null) !== null || ($cell['new'] ?? null) !== null || trim((string) ($cell['note'] ?? '')) !== '')) {
                                                $activeTypes[] = $type;
                                                break;
                                            }
                                        }
                                    }
                                @endphp
                                @if($blockDates->count() && count($packages))
                                {{-- name: aynı gruptaki details'lerden yalnız biri açık kalır (modern tarayıcı native) --}}
                                <details class="pricing-acc" name="pricing-dates" style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);margin-bottom:10px;overflow:hidden;">
                                    <summary style="cursor:pointer;padding:12px 16px;font-weight:600;font-size:14px;list-style:none;display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                                        <span style="color:var(--accent);">📅</span>
                                        @foreach($blockDates as $bd)
                                            @php $bdReturn = $bd->copy()->addDays(max(1, (int) $tour->duration_days) - 1); @endphp
                                            <span style="background:var(--accent-bg);border-radius:999px;padding:2px 10px;font-size:12px;{{ $bd->isPast() ? 'opacity:0.6;' : '' }}">{{ $bd->format('d-m-Y') }} → {{ $bdReturn->format('d-m-Y') }}</span>
                                        @endforeach
                                        <span style="color:var(--text-muted);font-size:12px;font-weight:500;margin-left:auto;">fiyatları gör ▾</span>
                                    </summary>
                                    <div style="overflow-x:auto;border-top:1px solid var(--border);">
                                        <table style="width:100%;border-collapse:collapse;font-size:13px;min-width:520px;">
                                            <thead>
                                                <tr style="background:var(--accent-bg);">
                                                    <th style="text-align:left;padding:10px 12px;font-weight:700;">Paket / Otel</th>
                                                    @foreach($activeTypes as $type)
                                                        <th style="text-align:right;padding:10px 12px;font-weight:700;white-space:nowrap;">{{ $roomTypeLabels[$type] }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($packages as $pkg)
                                                    <tr style="border-top:1px solid var(--border);">
                                                        <td style="padding:10px 12px;font-weight:600;">{{ ($pkg['hotel'] ?? '') !== '' ? $pkg['hotel'] : 'Standart Paket' }}</td>
                                                        @foreach($activeTypes as $type)
                                                            @php
                                                                $cell = $pkg['prices'][$type] ?? [];
                                                                $old = is_array($cell) ? ($cell['old'] ?? null) : null;
                                                                $new = is_array($cell) ? ($cell['new'] ?? null) : null;
                                                                $note = is_array($cell) ? trim((string) ($cell['note'] ?? '')) : '';
                                                            @endphp
                                                            <td style="padding:10px 12px;text-align:right;white-space:nowrap;">
                                                                @if($old !== null && $new !== null && (float) $old > (float) $new)
                                                                    <s style="color:#94a3b8;font-size:12px;">{{ number_format((float) $old, 0, ',', '.') }}</s>
                                                                    <span style="font-weight:700;color:#166534;margin-left:4px;">{{ number_format((float) $new, 0, ',', '.') }} {{ $priceCurrency }}</span>
                                                                @elseif($new !== null)
                                                                    <span style="font-weight:700;">{{ number_format((float) $new, 0, ',', '.') }} {{ $priceCurrency }}</span>
                                                                @elseif($old !== null)
                                                                    <span style="font-weight:700;">{{ number_format((float) $old, 0, ',', '.') }} {{ $priceCurrency }}</span>
                                                                @elseif($note !== '')
                                                                    <span style="color:var(--text-muted);font-size:12px;font-weight:600;">{{ $note }}</span>
                                                                @else
                                                                    <span style="color:var(--text-muted);">—</span>
                                                                @endif
                                                            </td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
                                @endif
                            @endforeach
                        </div>
                        <script>
                        // Tekli akordeon güvencesi: bir tarih paketi açılınca açık olan diğeri
                        // kapanır. Modern tarayıcılar bunu <details name="..."> ile native yapar;
                        // bu dinleyici desteklemeyen eski tarayıcılar için aynı davranışı sağlar.
                        document.querySelectorAll('details.pricing-acc').forEach(function (d) {
                            d.addEventListener('toggle', function () {
                                if (!d.open) return;
                                document.querySelectorAll('details.pricing-acc[open]').forEach(function (o) {
                                    if (o !== d) o.open = false;
                                });
                            });
                        });
                        </script>
                    @endif

                    {{-- Price History Chart --}}
                    @if($priceData->count() >= 2)
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:24px;margin-bottom:16px;">
                        <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                            <span style="font-size:18px;">📊</span>
                            <h3 style="font-size:16px;font-weight:700;color:#0f172a;">Fiyat Geçmişi (Son 30 Gün)</h3>
                            @php
                                $firstPrice = $priceData->first();
                                $lastPrice  = $priceData->last();
                                $diff = $lastPrice - $firstPrice;
                                $pct  = $firstPrice > 0 ? round(($diff / $firstPrice) * 100) : 0;
                            @endphp
                            @if($diff != 0)
                            <span style="font-size:13px;font-weight:700;color:{{ $diff < 0 ? '#059669' : '#dc2626' }};background:{{ $diff < 0 ? '#d1fae5' : '#fef2f2' }};padding:4px 12px;border-radius:20px;">
                                {{ $diff < 0 ? '↓' : '↑' }} %{{ abs($pct) }} {{ $diff < 0 ? 'düşüş' : 'artış' }}
                            </span>
                            @endif
                        </div>
                        <div style="position:relative;height:200px;">
                            <canvas id="priceHistoryChart"></canvas>
                        </div>
                    </div>
                    @endif
                </div>
                @endif

                {{-- Sekme: Genel Bilgiler --}}
                @if($hasGeneral)
                <div class="tour-tab-panel" data-tab-panel="genel" @if($defaultTab !== 'genel') hidden @endif>
                    @if($tour->description)
                        <p style="color:var(--text-sec);line-height:1.8;margin-bottom:24px;font-size:15px;">{{ $tour->description }}</p>
                    @endif

                    @foreach($detailSections as [$icon, $heading, $body])
                        @if(trim((string) $body) !== '')
                            <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;">
                                <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">{{ $icon }} {{ $heading }}</h3>
                                <div style="color:var(--text-sec);line-height:1.8;font-size:14px;white-space:pre-line;">{{ trim($body) }}</div>
                            </div>
                        @endif
                    @endforeach

                    @if($tour->frequency)
                        <div style="color:var(--text-muted);font-size:13px;margin-bottom:16px;">🔁 {{ $tour->frequency }}</div>
                    @endif
                </div>
                @endif

                {{-- Sekme: Yorumlar --}}
                <div class="tour-tab-panel" data-tab-panel="yorumlar" @if($defaultTab !== 'yorumlar') hidden @endif>
                    <div style="display:flex;align-items:center;gap:16px;margin-bottom:24px;">
                        <h2 style="font-size:20px;font-weight:700;">Yorumlar</h2>
                        @if($avgRating)
                            <div style="display:flex;align-items:center;gap:6px;background:var(--accent-bg);border-radius:20px;padding:4px 14px;">
                                <span style="color:#f59e0b;font-size:16px;">★</span>
                                <span style="font-weight:700;color:var(--accent);">{{ $avgRating }}</span>
                                <span style="font-size:13px;color:var(--text-muted);">({{ $reviews->count() }} yorum)</span>
                            </div>
                        @else
                            <span style="font-size:13px;color:var(--text-muted);">Henüz yorum yok</span>
                        @endif
                    </div>

                    {{-- Write Review Form --}}
                    @auth
                        @if(!$userReview)
                        <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:24px;">
                            <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">Yorum Yaz</h3>
                            <form method="POST" action="{{ route('reviews.store', $tour) }}">
                                @csrf
                                @if($errors->any())
                                    <div class="alert alert-error">{{ $errors->first() }}</div>
                                @endif
                                <div class="form-group">
                                    <label>Puanınız</label>
                                    <div style="display:flex;gap:8px;">
                                        @for($i = 1; $i <= 5; $i++)
                                        <label style="cursor:pointer;font-size:24px;" title="{{ $i }} yıldız">
                                            <input type="radio" name="rating" value="{{ $i }}" style="display:none;" {{ old('rating') == $i ? 'checked' : ($i == 5 ? 'checked' : '') }}>
                                            <span style="color:#f59e0b;">★</span>
                                        </label>
                                        @endfor
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Yorumunuz</label>
                                    <textarea name="comment" placeholder="Bu tur hakkında deneyiminizi paylaşın..." rows="3">{{ old('comment') }}</textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">Yorum Yap</button>
                            </form>
                        </div>
                        @endif
                    @else
                        <div style="background:var(--accent-bg);border-radius:var(--radius);padding:16px;margin-bottom:24px;text-align:center;">
                            <a href="{{ route('login') }}" style="color:var(--accent);font-weight:600;">Giriş yapın</a> ve bu tur hakkında yorum yapın.
                        </div>
                    @endauth

                    {{-- Reviews List --}}
                    @forelse($reviews as $review)
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:18px;margin-bottom:10px;">
                        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div style="width:38px;height:38px;background:var(--accent-bg);border-radius:50%;display:flex;align-items:center;justify-content:center;font-weight:700;color:var(--accent);">{{ mb_substr($review->user->name, 0, 1) }}</div>
                                <div>
                                    <div style="font-weight:600;font-size:14px;">{{ $review->user->name }}</div>
                                    <div style="font-size:12px;color:var(--text-muted);">{{ $review->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="color:#f59e0b;font-size:14px;">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</div>
                                @if(auth()->check() && auth()->id() === $review->user_id)
                                    <form method="POST" action="{{ route('reviews.destroy', $review) }}" style="display:inline;">
                                        @csrf @method('DELETE')
                                        <button type="submit" style="font-size:12px;color:#dc2626;background:none;border:none;cursor:pointer;font-family:var(--font);">Sil</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                        <p style="font-size:14px;color:var(--text-sec);line-height:1.7;">{{ $review->comment }}</p>
                    </div>
                    @empty
                        <div style="text-align:center;padding:32px;color:var(--text-muted);">
                            <div style="font-size:36px;margin-bottom:8px;">⭐</div>
                            Henüz yorum yapılmamış. İlk yorumu sen yap!
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- Right: Pricing & Agency --}}
            <div class="detail-sidebar">


                @auth
                @php $isFav = auth()->user()->hasFavorited($tour); @endphp
                <form method="POST" action="{{ route('favorites.toggle', $tour) }}" style="margin-bottom:12px;">
                    @csrf
                    <button type="submit" style="width:100%;padding:11px;border:1.5px solid {{ $isFav ? '#ef4444' : 'var(--border)' }};border-radius:10px;background:{{ $isFav ? '#fef2f2' : 'var(--white)' }};color:{{ $isFav ? '#ef4444' : 'var(--text-sec)' }};font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;">
                        {{ $isFav ? '❤️ Favorilerden Çıkar' : '🤍 Favorilere Ekle' }}
                    </button>
                </form>
                @endauth

                {{-- Compare button --}}
                <div style="margin-bottom:16px;">
                    <button type="button" class="compare-toggle" data-tour-id="{{ $tour->id }}" onclick="window.toggleCompare({{ $tour->id }})" style="width:100%;padding:11px;border:1.5px solid var(--border);border-radius:10px;background:var(--white);color:var(--text-sec);font-family:var(--font);font-size:14px;font-weight:600;cursor:pointer;transition:all .2s;">
                        + Karşılaştır
                    </button>
                </div>

                {{-- Main Price Card --}}
                @php $campaign = $tour->activeCampaign; @endphp
                <div id="dPriceSlot"></div>
                <div id="priceCard" style="background:var(--white);border:2px solid {{ $campaign ? 'var(--green)' : 'var(--accent)' }};border-radius:var(--radius-lg);padding:24px;margin-bottom:16px;">
                    @if($campaign)
                        {{-- Campaign active --}}
                        <div class="p-badge" style="margin-bottom:6px;">
                            <span class="badge badge-green">🏷️ {{ $campaign->label }}</span>
                        </div>
                        <div class="p-price" style="margin:10px 0 4px;">
                            <span style="text-decoration:line-through;color:var(--text-muted);font-size:16px;">{{ $tour->formatted_price }}</span>
                            <span class="price-tag cheapest" style="font-size:32px;margin-left:8px;color:var(--green);">{{ $campaign->formatted_discount_price }}</span>
                            <span class="price-sm"> / kişi</span>
                        </div>
                        {{-- Countdown --}}
                        <div id="campaign-countdown" style="background:linear-gradient(135deg,#fef3c7,#fde68a);border-radius:var(--radius);padding:10px 14px;margin:12px 0 8px;text-align:center;">
                            <div style="font-size:11px;font-weight:600;color:#92400e;margin-bottom:4px;">⏰ Kampanya Bitiyor</div>
                            <div id="countdown-timer" style="font-size:18px;font-weight:800;color:#d97706;font-variant-numeric:tabular-nums;"></div>
                        </div>
                        <script>
                        (function(){
                            var end = new Date("{{ $campaign->ends_at->toIso8601String() }}").getTime();
                            var el = document.getElementById('countdown-timer');
                            function tick(){
                                var now = Date.now(), diff = end - now;
                                if(diff <= 0){ el.textContent = 'Süre doldu!'; return; }
                                var d = Math.floor(diff/86400000), h = Math.floor((diff%86400000)/3600000),
                                    m = Math.floor((diff%3600000)/60000), s = Math.floor((diff%60000)/1000);
                                el.textContent = (d > 0 ? d + 'g ' : '') + h + 's ' + m + 'dk ' + s + 'sn';
                                setTimeout(tick, 1000);
                            }
                            tick();
                        })();
                        </script>
                    @else
                        <div class="p-badge" style="margin-bottom:4px;">
                            <span class="badge badge-green">🏆 En Ucuz</span>
                        </div>
                        <div class="p-price" style="margin:12px 0 4px;">
                            <span class="price-tag cheapest" style="font-size:32px;">{{ $tour->formatted_price }}</span>
                            <span class="price-sm"> / kişi</span>
                        </div>
                    @endif
                    <div class="p-agency" style="margin-bottom:16px;">
                        <a href="{{ route('agencies.show', $tour->agency) }}" style="color:var(--accent);font-weight:600;font-size:15px;">{{ $tour->agency->name }}</a>
                    </div>
                    <div class="p-ctas" style="display:flex;flex-direction:column;gap:10px;">
                        @php $mainUrl = $tour->tour_url ?: $tour->agency->website_url; @endphp
                        @if($mainUrl)
                            <a href="{{ route('tour.redirect', $tour) }}" target="_blank" class="btn btn-primary p-go" style="width:100%;">🌐 Tura Git →</a>
                        @endif
                        @if($tour->agency->phone)
                            <a href="tel:{{ $tour->agency->phone }}" class="btn btn-outline p-call" style="width:100%;">📞 <span class="p-tel-full">{{ $tour->agency->phone }}</span><span class="p-tel-short">Ara</span></a>
                        @endif
                        @if($tour->agency->email)
                            <a href="mailto:{{ $tour->agency->email }}" class="btn btn-outline p-mail" style="width:100%;">✉️<span class="p-mail-text"> E-posta Gönder</span></a>
                        @endif
                    </div>
                </div>

                @if($tour->included)
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;">
                        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">✅ Dahil Olanlar</h3>
                        <ul style="list-style:none;">
                            @foreach(explode("\n", $tour->included) as $item)
                                @php $line = ltrim(trim($item), "•-*–— \t"); @endphp
                                @if($line !== '')
                                    <li style="padding:4px 0;color:var(--text-sec);font-size:14px;">• {{ $line }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if($tour->excluded)
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;">
                        <h3 style="font-size:15px;font-weight:700;margin-bottom:10px;">❌ Dahil Olmayanlar</h3>
                        <ul style="list-style:none;">
                            @foreach(explode("\n", $tour->excluded) as $item)
                                @php $line = ltrim(trim($item), "•-*–— \t"); @endphp
                                @if($line !== '')
                                    <li style="padding:4px 0;color:var(--text-sec);font-size:14px;">• {{ $line }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- Other Agencies --}}
                @if($otherOffers->count())
                <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">Diğer Acentalar</h3>
                    <div style="display:flex;flex-direction:column;gap:10px;">
                        @foreach($otherOffers as $offer)
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px;background:var(--bg);border-radius:10px;">
                            <div>
                                <div style="font-weight:600;font-size:14px;">{{ $offer->agency->name }}</div>
                                <div style="font-size:12px;color:var(--text-muted);">
                                    @php $diff = round((($offer->price - $tour->price) / $tour->price) * 100); @endphp
                                    @if($diff > 0) +%{{ $diff }} daha pahalı @endif
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div class="price-tag" style="font-size:16px;">{{ $offer->formatted_price }}</div>
                                @php $offerUrl = $offer->tour_url ?: $offer->agency->website_url; @endphp
                                @if($offerUrl)
                                    <a href="{{ route('tour.redirect', $offer) }}" target="_blank" style="font-size:12px;color:var(--accent);font-weight:600;">Tura Git →</a>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Sekme geçişi: içerik DOM'da kalır (SEO), yalnız görünürlük değişir.
// Derin link: #program / #tarihler / #genel / #yorumlar
(function () {
    var tabs = document.querySelectorAll('.tour-tab');
    if (!tabs.length) return;

    function activate(id, updateHash) {
        var target = document.querySelector('.tour-tab[data-tab="' + id + '"]');
        if (!target) return;
        tabs.forEach(function (b) { b.classList.toggle('active', b === target); });
        document.querySelectorAll('.tour-tab-panel').forEach(function (p) {
            p.hidden = p.getAttribute('data-tab-panel') !== id;
        });
        // Grafik gizli panelde sıfır boyut render eder; sekme ilk açıldığında çiz
        if (id === 'tarihler' && window.__initPriceChart) window.__initPriceChart();
        if (updateHash && history.replaceState) history.replaceState(null, '', '#' + id);
    }

    tabs.forEach(function (b) {
        b.addEventListener('click', function () { activate(b.dataset.tab, true); });
    });

    var hash = (location.hash || '').replace('#', '');
    if (hash && document.querySelector('.tour-tab[data-tab="' + hash + '"]')) {
        activate(hash, false);
    } else if (@json($errors->any())) {
        // Yorum formu hatayla dönünce kullanıcı formu kaybetmesin
        activate('yorumlar', false);
    }

    window.addEventListener('hashchange', function () {
        var h = (location.hash || '').replace('#', '');
        if (h) activate(h, false);
    });
})();

// Fiyat kartı: mobilde başlığın altına, masaüstünde sidebar'a (tek DOM, iki yuva)
(function () {
    const card = document.getElementById('priceCard');
    const mSlot = document.getElementById('mPriceSlot');
    const dSlot = document.getElementById('dPriceSlot');
    if (!card || !mSlot || !dSlot) return;
    function placePriceCard() {
        const target = window.innerWidth <= 768 ? mSlot : dSlot;
        if (card.parentElement !== target) target.appendChild(card);
    }
    placePriceCard();
    window.addEventListener('resize', placePriceCard);
})();
</script>
@if(isset($priceData) && $priceData->count() >= 2)
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
<script>
// Grafik yalnız görünürken çizilir (gizli sekmede canvas 0 boyut olur)
window.__initPriceChart = function () {
    if (window.__priceChartDone) return;
    var canvas = document.getElementById('priceHistoryChart');
    if (!canvas || typeof Chart === 'undefined') return;
    window.__priceChartDone = true;

    const ctx = canvas.getContext('2d');
    const currencySymbol = @json($tour->currency_symbol);

    // Create gradient
    const gradient = ctx.createLinearGradient(0, 0, 0, 200);
    gradient.addColorStop(0, 'rgba(13, 148, 136, 0.4)'); // Teal transparent
    gradient.addColorStop(1, 'rgba(13, 148, 136, 0.0)');

    new Chart(ctx, {
        type: 'line',
        data: {
            labels: @json($priceLabels),
            datasets: [{
                label: 'Fiyat (' + currencySymbol + ')',
                data: @json($priceData),
                borderColor: '#0d9488', // Accent teal
                backgroundColor: gradient,
                borderWidth: 3,
                fill: true,
                tension: 0.4, // Smooth curves
                pointBackgroundColor: '#ffffff',
                pointBorderColor: '#0d9488',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    titleFont: { size: 13, family: 'Inter' },
                    bodyFont: { size: 14, family: 'Inter', weight: 'bold' },
                    padding: 12,
                    cornerRadius: 8,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            return new Intl.NumberFormat('tr-TR').format(context.raw) + ' ' + currencySymbol;
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: { display: false, drawBorder: false },
                    ticks: { font: { family: 'Inter', size: 12 }, color: '#64748b' }
                },
                y: {
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: {
                        font: { family: 'Inter', size: 12 },
                        color: '#64748b',
                        callback: function(value) { return new Intl.NumberFormat('tr-TR').format(value); }
                    }
                }
            }
        }
    });
};
document.addEventListener('DOMContentLoaded', function () {
    var panel = document.querySelector('[data-tab-panel="tarihler"]');
    if (panel && !panel.hidden) window.__initPriceChart();
});
</script>
@endif
@endpush
