@extends('layouts.app')
@section('title', 'Tur Ekle — Acenta Paneli')

@section('styles')
<style>
    .pricing-option-card {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px;
        background: #fff;
    }

    .pricing-option-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 10px;
    }

    .departure-preview-wrap {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 10px;
    }

    .departure-preview-item {
        background: var(--accent-bg);
        border-radius: var(--radius);
        padding: 8px 12px;
        font-size: 13px;
        color: var(--text-sec);
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .date-picker-wrap {
        position: relative;
    }

    .departure-picker-input {
        cursor: pointer;
        background: #fff;
    }

    .multi-calendar-panel {
        position: absolute;
        top: calc(100% + 8px);
        left: 0;
        width: 310px;
        background: #fff;
        border: 1px solid var(--border);
        border-radius: 12px;
        box-shadow: 0 12px 24px rgba(15, 23, 42, 0.14);
        padding: 10px;
        z-index: 80;
        display: none;
    }

    .multi-calendar-panel.open {
        display: block;
    }

    .multi-calendar-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 8px;
    }

    .cal-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--text);
    }

    .cal-nav-btn {
        border: 1px solid var(--border);
        background: #fff;
        width: 28px;
        height: 28px;
        border-radius: 8px;
        font-size: 15px;
        line-height: 1;
        cursor: pointer;
        color: var(--text-sec);
    }

    .multi-calendar-weekdays,
    .multi-calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 5px;
    }

    .multi-calendar-weekdays {
        margin-bottom: 6px;
    }

    .multi-calendar-weekdays span {
        font-size: 11px;
        color: var(--text-muted);
        text-align: center;
        font-weight: 600;
    }

    .cal-day,
    .cal-day-placeholder {
        width: 100%;
        height: 34px;
        border-radius: 8px;
        border: none;
        font-size: 13px;
    }

    .cal-day {
        background: #f8fafc;
        color: var(--text);
        cursor: pointer;
    }

    .cal-day:hover {
        background: #e2e8f0;
    }

    .cal-day.selected {
        background: #2563eb;
        color: #fff;
        font-weight: 700;
    }

    .cal-day.taken {
        background: #bfdbfe;
        color: #1e3a8a;
        font-weight: 700;
        cursor: not-allowed;
    }

    .cal-day.taken:hover {
        background: #bfdbfe;
    }

    .cal-day.disabled {
        opacity: 0.4;
        cursor: not-allowed;
        background: #f8fafc;
        color: #94a3b8;
    }

    .date-entry {
        border: 1px solid var(--border);
        border-radius: var(--radius);
        background: #fff;
        overflow: hidden;
    }

    .date-entry-head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        padding: 10px 14px;
        cursor: pointer;
        background: var(--accent-bg);
        user-select: none;
    }

    .date-entry-head:hover {
        filter: brightness(0.98);
    }

    .date-entry-title {
        font-size: 14px;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .date-entry-caret {
        transition: transform 0.15s ease;
        color: var(--text-muted);
    }

    .date-entry.open .date-entry-caret {
        transform: rotate(90deg);
    }

    .date-entry-body {
        padding: 14px;
        border-top: 1px solid var(--border);
    }

    .date-entry-summary {
        font-size: 12px;
        font-weight: 500;
        color: var(--text-muted);
    }

    .form-row.form-row-3 {
        grid-template-columns: repeat(3, minmax(0, 1fr));
    }

    @media (max-width: 1024px) {
        .form-row.form-row-3 {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 768px) {
        .form-row.form-row-3 {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <a href="{{ route('agency.tours.index') }}" class="btn btn-outline btn-sm">← Geri</a>
                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Yeni Tur Ekle</h1>
            </div>

            <div style="max-width:94%; margin:0 auto;">
                <div style="max-width:960px;">
                    @if($errors->any())
                        <div class="alert alert-error" style="margin-bottom: 24px;">
                            @foreach($errors->all() as $e) {{ $e }}<br> @endforeach
                        </div>
                    @endif

                    @unless($canCreateTours)
                        <div class="alert alert-error" style="margin-bottom: 24px;">
                            Tur paylaşabilmek için önce en az 1 kategori için aylık yetki satın almalısınız.
                            <a href="{{ route('agency.category-licenses.index') }}" style="font-weight:700;color:inherit;text-decoration:underline;">Kategori Yetkilendirme Merkezi</a>
                        </div>
                    @endunless

                    <div class="stat-card" style="padding:32px;">
                @if($canCreateTours)
                <form method="POST" action="{{ route('agency.tours.store') }}" enctype="multipart/form-data">
                    @csrf

                    @include('agency.tours._import_panel')

                    <div class="form-group"><label>Tur Adı *</label><input type="text" name="title" value="{{ old('title') }}" required></div>
                    <div class="form-group">
                        <label>Kategori Yetkisi *</label>
                        <select name="category_id" required>
                            <option value="">Kategori Seçin</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat->id }}" {{ old('category_id') == $cat->id ? 'selected' : '' }}>{{ $cat->icon }} {{ $cat->name }}</option>
                                @foreach($cat->children as $child)
                                    <option value="{{ $child->id }}" {{ old('category_id') == $child->id ? 'selected' : '' }}>&nbsp;&nbsp;↳ {{ $child->icon }} {{ $child->name }}</option>
                                @endforeach
                            @endforeach
                        </select>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:6px;">
                            Uygun kategori bulamadın mı? <a href="{{ route('agency.category-requests.index') }}" style="color:var(--accent);font-weight:600;">Admine kategori talebi oluştur</a>.
                        </div>
                    </div>
                    <div class="form-row form-row-3">
                        <div class="form-group"><label>Destinasyon *</label><input type="text" name="destination" value="{{ old('destination') }}" required placeholder="Ör: Antalya"></div>
                        <div class="form-group"><label>Süre (gün) *</label><input type="number" id="durationDaysInput" name="duration_days" value="{{ old('duration_days', 1) }}" min="1" required></div>
                        <div class="form-group">
                            <label>Ulaşım</label>
                            <select name="transport_type" id="transportTypeInput">
                                <option value="">Belirtilmedi</option>
                                @foreach(\App\Models\Tour::TRANSPORT_TYPES as $kod => $ad)
                                    <option value="{{ $kod }}" {{ old('transport_type', '') === $kod ? 'selected' : '' }}>{{ $ad }}</option>
                                @endforeach
                            </select>
                            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Kartta "Gidiş Dönüş Otobüs" şeklinde görünür.</div>
                        </div>
                        <div class="form-group">
                            <label>Gece</label>
                            <input type="number" id="durationNightsInput" name="duration_nights" value="{{ old('duration_nights') }}" min="0" max="255" placeholder="Boş = gün − 1">
                            <div style="font-size:11px;color:#94a3b8;margin-top:4px;">Turda "7 gece 8 gün" yazacak. Boş bırakırsan gün−1 varsayılır; konaklamasız günübirlik turda 0 yaz.</div>
                        </div>
                        <div class="form-group">
                            <label>Para Birimi *</label>
                            <select name="currency" id="currencySelect" required>
                                @foreach($currencyOptions as $currencyCode => $currencyMeta)
                                    <option value="{{ $currencyCode }}" {{ old('currency', 'TRY') === $currencyCode ? 'selected' : '' }}>
                                        {{ $currencyCode }} ({{ $currencyMeta['symbol'] }}) - {{ $currencyMeta['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    @include('agency.tours._city_fields', [
                        'cityOptions' => \App\Support\TurkishCities::all(),
                        'selectedDepartureCity' => old('departure_city', ''),
                        'selectedStopCities' => old('stop_cities', []),
                    ])

                    <div class="form-group">
                        <label>Tur Tarihleri ve Fiyatları *</label>
                        @php
                            $rawPricingOptions = old('pricing_options', []);
                            if (!is_array($rawPricingOptions)) {
                                $rawPricingOptions = [];
                            }

                            $legacyDepartureDates = old('departure_dates', []);
                            if (!is_array($legacyDepartureDates)) {
                                $legacyDepartureDates = [];
                            }
                            $legacyDepartureDates = array_values(array_filter(array_map(fn($d) => trim((string) $d), $legacyDepartureDates)));

                            if (empty($rawPricingOptions) && !empty($legacyDepartureDates)) {
                                $rawPricingOptions[] = [
                                    'price' => old('price', ''),
                                    'departure_dates' => $legacyDepartureDates,
                                ];
                            }

                            $oldPricingOptions = collect($rawPricingOptions)
                                ->map(function ($option) {
                                    if (!is_array($option)) {
                                        $option = [];
                                    }

                                    $rawDates = $option['departure_dates'] ?? [];
                                    if (!is_array($rawDates)) {
                                        $rawDates = [];
                                    }

                                    $dates = array_values(array_unique(array_filter(array_map(
                                        fn($d) => trim((string) $d),
                                        $rawDates
                                    ))));
                                    sort($dates);

                                    $packages = $option['packages'] ?? [];
                                    if (!is_array($packages)) {
                                        $packages = [];
                                    }

                                    return [
                                        'price' => trim((string) ($option['price'] ?? '')),
                                        'departure_dates' => $dates,
                                        'packages' => array_values($packages),
                                    ];
                                })
                                ->filter(fn($option) => $option['price'] !== '' || count($option['departure_dates']) > 0)
                                ->values()
                                ->all();

                            if (empty($oldPricingOptions)) {
                                $oldPricingOptions = [['price' => old('price', ''), 'departure_dates' => []]];
                            }
                        @endphp

                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
                            Takvimden tur tarihlerini seç. Her tarihe tıklayınca altında o tarihin paket/oda fiyatlarını gir.
                        </div>

                        <div class="date-picker-wrap" style="margin-bottom:14px;max-width:340px;">
                            <input type="text" id="masterDatePicker" class="departure-picker-input" placeholder="📅 Takvimden tarih ekle" readonly>
                            <div class="multi-calendar-panel" id="masterCalendarPanel">
                                <div class="multi-calendar-header">
                                    <button type="button" class="cal-nav-btn" id="masterCalPrev">‹</button>
                                    <div class="cal-title" id="masterCalTitle"></div>
                                    <button type="button" class="cal-nav-btn" id="masterCalNext">›</button>
                                </div>
                                <div class="multi-calendar-weekdays">
                                    <span>P</span><span>S</span><span>Ç</span><span>P</span><span>C</span><span>C</span><span>P</span>
                                </div>
                                <div class="multi-calendar-grid" id="masterCalGrid"></div>
                            </div>
                        </div>

                        <div id="pricingOptionsContainer" style="display:flex;flex-direction:column;gap:8px;"></div>
                        <div id="noDatesHint" style="font-size:13px;color:var(--text-muted);padding:8px 0;">Henüz tarih seçilmedi.</div>
                        <div id="pricingOptionsHidden"></div>
                    </div>

                    <div class="form-group"><label>Açıklama</label><textarea name="description">{{ old('description') }}</textarea></div>
                    <div class="form-group"><label>Dahil Olanlar (her satıra bir madde)</label><textarea name="included">{{ old('included') }}</textarea></div>
                    <div class="form-group"><label>Dahil Olmayanlar</label><textarea name="excluded">{{ old('excluded') }}</textarea></div>
                    <div class="form-group">
                        <label>Tur Programı (gün gün)</label>
                        <div id="itineraryContainer" style="display:flex;flex-direction:column;gap:12px;"></div>
                        <button type="button" class="btn btn-outline btn-sm" style="margin-top:8px;" onclick="addItineraryDay()">+ Gün Ekle</button>
                    </div>
                    <div class="form-group"><label>Kalkış / Biniş Noktaları</label><textarea name="departure_points" rows="3" placeholder="21:00 Yenibosna&#10;21:30 Mecidiyeköy">{{ old('departure_points') }}</textarea></div>
                    <div class="form-group"><label>Konaklama / Otel Bilgisi</label><textarea name="hotel_info" rows="2" placeholder="5★ Suhan Cappadocia Hotel & Spa">{{ old('hotel_info') }}</textarea></div>
                    <div class="form-group"><label>Ekstra Tur ve Aktiviteler</label><textarea name="extras" rows="2">{{ old('extras') }}</textarea></div>
                    <div class="form-group"><label>İptal / İade Koşulları</label><textarea name="cancellation_policy" rows="2">{{ old('cancellation_policy') }}</textarea></div>
                    <div class="form-group"><label>Rehber Bilgisi / Notları</label><textarea name="guide_info" rows="2">{{ old('guide_info') }}</textarea></div>
                    <div class="form-group"><label>Hareket Sıklığı</label><input type="text" name="frequency" value="{{ old('frequency') }}" placeholder="Örn: Her Cuma kesin hareketli"></div>
                    @include('agency.tours._gallery', ['initialImages' => old('gallery', [])])
                    <div class="form-group"><label>Tur Linki (Acentanın tur sayfası)</label><input type="url" name="tour_url" value="{{ old('tour_url') }}" placeholder="https://acenta.com/bu-tur"></div>
                    <div style="display:flex;gap:12px;">
                        <button type="submit" class="btn btn-primary">Turu Kaydet</button>
                        <a href="{{ route('agency.tours.index') }}" class="btn btn-outline">İptal</a>
                    </div>
                    </div>
                </form>
                @else
                    <div style="text-align:center;padding:32px 16px;">
                        <div style="font-size:15px;color:#475569;margin-bottom:16px;">Önce kategori yetkisi satın alın, ardından bu alandan tur paylaşın.</div>
                        <a href="{{ route('agency.category-licenses.index') }}" class="btn btn-primary">Kategori Yetkilendirme Merkezi</a>
                    </div>
                    </div>
                @endif
                </div>
            </div>

            @if($canCreateTours)
            <script>
            const initialPricingOptions = @json($oldPricingOptions);
            const minSelectableDate = '{{ now()->toDateString() }}';
            const currencySymbols = @json(collect($currencyOptions)->map(fn($meta) => (string) ($meta['symbol'] ?? '₺'))->toArray());
            const monthNamesTr = ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
            const roomTypes = @json(\App\Models\Tour::ROOM_TYPES);
            let pricingOptions = [];

            // --- Paket / oda-tipi fiyat matrisi yardımcıları ---
            function escapeAttr(s) { return String(s == null ? '' : s).replace(/"/g, '&quot;'); }
            // type=number alanlarında "e/E/+/-" üstel/işaret karakterlerini engelle (hayalet "e" sorunu)
            function blockExponentKeys(inp) {
                inp.addEventListener('keydown', function (ev) {
                    if (['e', 'E', '+', '-'].indexOf(ev.key) !== -1) { ev.preventDefault(); }
                });
            }
            function emptyPackage() {
                var prices = {};
                Object.keys(roomTypes).forEach(function (t) { prices[t] = { old: '', new: '', note: '' }; });
                return { hotel: '', prices: prices };
            }
            function normalizePackagesJs(raw) {
                if (!Array.isArray(raw)) return [];
                return raw.map(function (p) {
                    var prices = {};
                    Object.keys(roomTypes).forEach(function (t) {
                        // İçe aktarma: p.prices[t] (nested); eski form girdisi: p[t] (flat)
                        var v = (p && p.prices && p.prices[t]) || (p && p[t]) || {};
                        prices[t] = {
                            old: (v.old !== null && v.old !== undefined) ? String(v.old) : '',
                            new: (v.new !== null && v.new !== undefined) ? String(v.new) : '',
                            note: (v.note !== null && v.note !== undefined) ? String(v.note) : ''
                        };
                    });
                    return { hotel: (p && p.hotel) || '', prices: prices };
                });
            }
            function addPackage(optionId) {
                var o = getOptionById(optionId); if (!o) return;
                if (!o.packages) o.packages = [];
                o.packages.push(emptyPackage());
                renderPricingOptions();
            }
            function removePackage(optionId, j) {
                var o = getOptionById(optionId); if (!o || !o.packages) return;
                o.packages.splice(j, 1);
                renderPricingOptions();
            }
            function renderPackages(option, card) {
                var wrap = card.querySelector('.option-packages');
                if (!wrap) return;
                wrap.innerHTML = '';
                (option.packages || []).forEach(function (pkg, j) {
                    var box = document.createElement('div');
                    box.style.cssText = 'border:1px solid var(--border-light);border-radius:8px;padding:10px;margin-top:8px;background:#fff;';
                    var rows = '';
                    Object.keys(roomTypes).forEach(function (t) {
                        rows += '<div style="font-size:12px;">'
                            + '<div style="color:var(--text-muted);margin-bottom:2px;">' + roomTypes[t] + '</div>'
                            + '<input type="number" class="pkg-old" data-t="' + t + '" placeholder="Eski" min="0" step="0.01" value="' + escapeAttr(pkg.prices[t].old) + '" style="width:100%;margin-bottom:4px;">'
                            + '<input type="number" class="pkg-new" data-t="' + t + '" placeholder="İndirimli" min="0" step="0.01" value="' + escapeAttr(pkg.prices[t].new) + '" style="width:100%;margin-bottom:4px;">'
                            + '<input type="text" class="pkg-note" data-t="' + t + '" placeholder="Fiyat yoksa açıklama (ör. Kabul edilmiyor)" value="' + escapeAttr(pkg.prices[t].note) + '" style="width:100%;font-size:11px;">'
                            + '</div>';
                    });
                    box.innerHTML = '<div style="display:flex;gap:8px;align-items:center;margin-bottom:8px;">'
                        + '<input type="text" class="pkg-hotel" placeholder="Otel / Paket Adı (örn: 5★ Suhan Cappadocia)" value="' + escapeAttr(pkg.hotel) + '" style="flex:1;">'
                        + '<button type="button" class="btn btn-danger btn-sm pkg-remove">Kaldır</button></div>'
                        + '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;">' + rows + '</div>';
                    box.querySelector('.pkg-hotel').oninput = function () { option.packages[j].hotel = this.value; syncPricingOptionInputs(); };
                    box.querySelector('.pkg-remove').onclick = function () { removePackage(option.id, j); };
                    box.querySelectorAll('.pkg-old').forEach(function (inp) { blockExponentKeys(inp); inp.oninput = function () { option.packages[j].prices[this.dataset.t].old = this.value; syncPricingOptionInputs(); }; });
                    box.querySelectorAll('.pkg-new').forEach(function (inp) { blockExponentKeys(inp); inp.oninput = function () { option.packages[j].prices[this.dataset.t].new = this.value; syncPricingOptionInputs(); }; });
                    box.querySelectorAll('.pkg-note').forEach(function (inp) { inp.oninput = function () { option.packages[j].prices[this.dataset.t].note = this.value; syncPricingOptionInputs(); }; });
                    wrap.appendChild(box);
                });
            }

            // --- Gün gün program builder ---
            let itineraryDays = [];
            function renderItinerary() {
                var c = document.getElementById('itineraryContainer');
                if (!c) return;
                c.innerHTML = '';
                itineraryDays.forEach(function (day, i) {
                    var card = document.createElement('div');
                    card.style.cssText = 'border:1px solid var(--border-light);border-radius:10px;padding:12px;background:#fafafa;';
                    var head = document.createElement('div');
                    head.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;';
                    head.innerHTML = '<strong>' + (i + 1) + '. Gün</strong>';
                    var rm = document.createElement('button');
                    rm.type = 'button'; rm.className = 'btn btn-danger btn-sm'; rm.textContent = 'Kaldır';
                    rm.onclick = function () { removeItineraryDay(i); };
                    head.appendChild(rm);
                    var ti = document.createElement('input');
                    ti.type = 'text'; ti.placeholder = 'Başlık (örn: Tuz Gölü – Ihlara – Avanos)';
                    ti.name = 'itinerary[' + i + '][title]'; ti.value = day.title || '';
                    ti.style.cssText = 'width:100%;margin-bottom:8px;';
                    ti.oninput = function () { itineraryDays[i].title = this.value; };
                    var ta = document.createElement('textarea');
                    ta.placeholder = 'O günün detaylı açıklaması'; ta.rows = 4;
                    ta.name = 'itinerary[' + i + '][content]'; ta.value = day.content || '';
                    ta.style.cssText = 'width:100%;';
                    ta.oninput = function () { itineraryDays[i].content = this.value; };
                    card.appendChild(head); card.appendChild(ti); card.appendChild(ta);
                    c.appendChild(card);
                });
            }
            function addItineraryDay(title, content) {
                itineraryDays.push({ title: title || '', content: content || '' });
                renderItinerary();
            }
            function removeItineraryDay(i) {
                itineraryDays.splice(i, 1);
                if (itineraryDays.length === 0) itineraryDays.push({ title: '', content: '' });
                renderItinerary();
            }
            function setItinerary(days) {
                itineraryDays = (Array.isArray(days) && days.length)
                    ? days.map(function (d) { return { title: (d && d.title) || '', content: (d && d.content) || '' }; })
                    : [{ title: '', content: '' }];
                renderItinerary();
            }

            function previewImg(i){
                var r = new FileReader();
                r.onload = function(e){
                    var p = document.getElementById('imgPreview');
                    p.src = e.target.result;
                    p.style.display = 'block';
                };
                if (i.files[0]) r.readAsDataURL(i.files[0]);
            }

            // URL'den içe aktarma JS'i _import_panel partial'ında (create+edit ortak)

            function toYmd(dateObj) {
                const yyyy = dateObj.getFullYear();
                const mm = String(dateObj.getMonth() + 1).padStart(2, '0');
                const dd = String(dateObj.getDate()).padStart(2, '0');
                return yyyy + '-' + mm + '-' + dd;
            }

            function parseYmd(ymd) {
                const parts = (ymd || '').split('-').map(Number);
                if (parts.length !== 3 || parts.some(Number.isNaN)) {
                    return null;
                }

                return new Date(parts[0], parts[1] - 1, parts[2]);
            }

            function formatTr(ymd) {
                const parts = ymd.split('-');
                return parts[2] + '-' + parts[1] + '-' + parts[0];
            }

            function calculateReturnDate(ymd, durationDays) {
                const base = new Date(ymd + 'T00:00:00');
                const offset = Math.max(1, durationDays) - 1;
                base.setDate(base.getDate() + offset);
                return toYmd(base);
            }

            function uniqueSortedDates(dates) {
                return Array.from(new Set((dates || []).map(String))).sort();
            }

            function getSelectedCurrencyCode() {
                const currencySelect = document.getElementById('currencySelect');
                return currencySelect ? currencySelect.value : 'TRY';
            }

            function getSelectedCurrencySymbol() {
                const code = getSelectedCurrencyCode();
                return currencySymbols[code] || '₺';
            }

            // ---- Tarih bazlı fiyat satırları (her tarih kendi paket/oda matrisi) ----
            // Dahili durum: pricingOptions[i] = { id, date:'YYYY-MM-DD', price, packages, open }
            // Backend sözleşmesi korunur: her satır tek-tarihli bir pricing_option olarak gönderilir.
            let masterCalView = null;

            function createDateEntry(entry) {
                return {
                    id: Date.now() + Math.floor(Math.random() * 1000000) + pricingOptions.length,
                    date: String((entry && entry.date) || ''),
                    price: String((entry && entry.price) || ''),
                    packages: normalizePackagesJs((entry && entry.packages) || []),
                    open: !!(entry && entry.open),
                };
            }

            function getOptionById(optionId) {
                return pricingOptions.find(function(o) { return o.id === optionId; });
            }

            function sortEntries() {
                pricingOptions.sort(function(a, b) { return a.date < b.date ? -1 : (a.date > b.date ? 1 : 0); });
            }

            function selectedDatesSet() {
                var s = {};
                pricingOptions.forEach(function(o) { if (o.date) s[o.date] = true; });
                return s;
            }

            function setDateEntries(entries) {
                pricingOptions = [];
                var seen = {};
                (entries || []).forEach(function(e) {
                    if (!e || !e.date || seen[e.date]) return;
                    seen[e.date] = true;
                    pricingOptions.push(createDateEntry(e));
                });
                sortEntries();
                renderPricingOptions();
                renderMasterCalendar();
            }

            function toggleDate(ymd) {
                var exists = pricingOptions.some(function(o) { return o.date === ymd; });
                if (exists) {
                    pricingOptions = pricingOptions.filter(function(o) { return o.date !== ymd; });
                } else {
                    pricingOptions.push(createDateEntry({ date: ymd, price: '', packages: [], open: true }));
                }
                sortEntries();
                renderPricingOptions();
                renderMasterCalendar();
            }

            function removeDateEntry(optionId) {
                pricingOptions = pricingOptions.filter(function(o) { return o.id !== optionId; });
                renderPricingOptions();
                renderMasterCalendar();
            }

            function updateOptionPrice(optionId, value) {
                var o = getOptionById(optionId);
                if (!o) return;
                o.price = value;
                syncPricingOptionInputs();
            }

            function toggleEntryOpen(optionId) {
                var o = getOptionById(optionId);
                if (!o) return;
                o.open = !o.open;
                renderPricingOptions();
            }

            // ---- Tek (master) takvim ----
            function getMasterCalView() {
                if (masterCalView) return masterCalView;
                var first = pricingOptions.length ? pricingOptions[0].date : null;
                var base = parseYmd(first) || parseYmd(minSelectableDate) || new Date();
                masterCalView = { year: base.getFullYear(), month: base.getMonth() };
                return masterCalView;
            }

            function changeMasterMonth(diff) {
                var v = getMasterCalView();
                var y = v.year, m = v.month + diff;
                while (m < 0) { m += 12; y -= 1; }
                while (m > 11) { m -= 12; y += 1; }
                masterCalView = { year: y, month: m };
                renderMasterCalendar();
            }

            function closeAllCalendars() {
                document.querySelectorAll('.multi-calendar-panel.open').forEach(function(p) { p.classList.remove('open'); });
            }

            function renderMasterCalendar() {
                var titleEl = document.getElementById('masterCalTitle');
                var gridEl = document.getElementById('masterCalGrid');
                var picker = document.getElementById('masterDatePicker');
                if (!gridEl || !titleEl) return;

                var view = getMasterCalView();
                titleEl.textContent = monthNamesTr[view.month] + ' ' + view.year;

                var count = pricingOptions.length;
                if (picker) picker.value = count ? (count + ' tarih seçili') : '';

                gridEl.innerHTML = '';
                var selected = selectedDatesSet();
                var firstDayIndex = new Date(view.year, view.month, 1).getDay();
                var daysInMonth = new Date(view.year, view.month + 1, 0).getDate();

                for (var i = 0; i < firstDayIndex; i += 1) {
                    var blank = document.createElement('div');
                    blank.className = 'cal-day-placeholder';
                    gridEl.appendChild(blank);
                }

                for (var day = 1; day <= daysInMonth; day += 1) {
                    (function(day) {
                        var ymd = toYmd(new Date(view.year, view.month, day));
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'cal-day';
                        btn.textContent = String(day);
                        if (selected[ymd]) btn.classList.add('selected');
                        if (minSelectableDate && ymd < minSelectableDate) {
                            btn.classList.add('disabled');
                            btn.disabled = true;
                        } else {
                            btn.addEventListener('click', function(ev) {
                                ev.stopPropagation();
                                toggleDate(ymd);
                            });
                        }
                        gridEl.appendChild(btn);
                    })(day);
                }
            }

            function syncPricingOptionInputs() {
                var hidden = document.getElementById('pricingOptionsHidden');
                hidden.innerHTML = '';

                pricingOptions.forEach(function(option, index) {
                    var priceInput = document.createElement('input');
                    priceInput.type = 'hidden';
                    priceInput.name = 'pricing_options[' + index + '][price]';
                    priceInput.value = option.price;
                    hidden.appendChild(priceInput);

                    if (option.date) {
                        var dateInput = document.createElement('input');
                        dateInput.type = 'hidden';
                        dateInput.name = 'pricing_options[' + index + '][departure_dates][]';
                        dateInput.value = option.date;
                        hidden.appendChild(dateInput);
                    }

                    (option.packages || []).forEach(function(pkg, j) {
                        var hasData = (pkg.hotel && pkg.hotel.trim() !== '')
                            || Object.keys(roomTypes).some(function(t){ return pkg.prices[t].old || pkg.prices[t].new || (pkg.prices[t].note && pkg.prices[t].note.trim() !== ''); });
                        if (!hasData) return;
                        var h = document.createElement('input');
                        h.type = 'hidden';
                        h.name = 'pricing_options[' + index + '][packages][' + j + '][hotel]';
                        h.value = pkg.hotel || '';
                        hidden.appendChild(h);
                        Object.keys(roomTypes).forEach(function(t) {
                            ['old', 'new', 'note'].forEach(function(sub) {
                                var v = pkg.prices[t][sub];
                                if (v === '' || v === null || v === undefined) return;
                                var inp = document.createElement('input');
                                inp.type = 'hidden';
                                inp.name = 'pricing_options[' + index + '][packages][' + j + '][' + t + '][' + sub + ']';
                                inp.value = v;
                                hidden.appendChild(inp);
                            });
                        });
                    });
                });
            }

            function renderPricingOptions() {
                var container = document.getElementById('pricingOptionsContainer');
                var hint = document.getElementById('noDatesHint');
                if (!container) return;
                container.innerHTML = '';

                sortEntries();
                if (hint) hint.style.display = pricingOptions.length ? 'none' : 'block';

                var durationInput = document.getElementById('durationDaysInput');
                var durationDays = parseInt((durationInput && durationInput.value) || '1', 10);

                pricingOptions.forEach(function(option) {
                    var entry = document.createElement('div');
                    entry.className = 'date-entry' + (option.open ? ' open' : '');
                    entry.dataset.optionId = String(option.id);

                    var ret = option.date ? calculateReturnDate(option.date, durationDays) : '';
                    var dateLabel = option.date ? formatTr(option.date) : '—';
                    var retLabel = ret ? (' → ' + formatTr(ret)) : '';

                    var pkgCount = (option.packages || []).filter(function(p){
                        return (p.hotel && p.hotel.trim() !== '') || Object.keys(roomTypes).some(function(t){ return p.prices[t].old || p.prices[t].new; });
                    }).length;
                    var summary = pkgCount ? (pkgCount + ' paket') : (option.price ? (option.price + ' ' + getSelectedCurrencySymbol()) : 'fiyat girilmedi');

                    entry.innerHTML = `
                        <div class="date-entry-head">
                            <span class="date-entry-title"><span class="date-entry-caret">▸</span>${dateLabel}${retLabel}</span>
                            <span style="display:flex;align-items:center;gap:10px;">
                                <span class="date-entry-summary">${summary}</span>
                                <button type="button" class="btn btn-outline btn-sm remove-date-btn">Sil</button>
                            </span>
                        </div>
                        <div class="date-entry-body" style="${option.open ? '' : 'display:none;'}">
                            <div class="form-group" style="margin-bottom:10px;max-width:240px;">
                                <label>Fiyat (${getSelectedCurrencySymbol()}) — paket yoksa</label>
                                <input type="number" class="option-price-input" min="0" step="0.01" value="${escapeAttr(option.price)}" placeholder="Tek fiyat">
                            </div>
                            <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px;">Paket / Oda Fiyatları (otel + oda/yaş tipi, eski + indirimli)</div>
                            <div class="option-packages"></div>
                            <button type="button" class="btn btn-outline btn-sm add-package-btn" style="margin-top:8px;">+ Paket / Otel Ekle</button>
                        </div>
                    `;

                    container.appendChild(entry);

                    var head = entry.querySelector('.date-entry-head');
                    head.addEventListener('click', function(ev) {
                        if (ev.target.closest('.remove-date-btn')) return;
                        toggleEntryOpen(option.id);
                    });

                    entry.querySelector('.remove-date-btn').addEventListener('click', function(ev) {
                        ev.stopPropagation();
                        removeDateEntry(option.id);
                    });

                    var priceInput = entry.querySelector('.option-price-input');
                    blockExponentKeys(priceInput);
                    priceInput.addEventListener('input', function() { updateOptionPrice(option.id, this.value); });

                    renderPackages(option, entry);
                    var addPkgBtn = entry.querySelector('.add-package-btn');
                    if (addPkgBtn) addPkgBtn.addEventListener('click', function() { addPackage(option.id); });
                });

                syncPricingOptionInputs();
            }

            document.addEventListener('click', closeAllCalendars);

            document.addEventListener('DOMContentLoaded', function() {
                // Başlangıç: eski (validasyon hatası) girdileri tarih satırlarına genişlet
                var initial = [];
                (initialPricingOptions || []).forEach(function(option) {
                    var dates = (option && option.departure_dates) || [];
                    var pkgs = (option && option.packages) || [];
                    var price = (option && option.price) || '';
                    (Array.isArray(dates) ? dates : []).forEach(function(d) {
                        initial.push({ date: d, price: price, packages: pkgs });
                    });
                });
                setDateEntries(initial);

                var picker = document.getElementById('masterDatePicker');
                var panel = document.getElementById('masterCalendarPanel');
                picker.addEventListener('click', function(ev) {
                    ev.stopPropagation();
                    var isOpen = panel.classList.contains('open');
                    closeAllCalendars();
                    if (!isOpen) { panel.classList.add('open'); renderMasterCalendar(); }
                });
                panel.addEventListener('click', function(ev) { ev.stopPropagation(); });
                document.getElementById('masterCalPrev').addEventListener('click', function() { changeMasterMonth(-1); });
                document.getElementById('masterCalNext').addEventListener('click', function() { changeMasterMonth(1); });
                renderMasterCalendar();

                setItinerary(@json(old('itinerary', [])));

                document.getElementById('durationDaysInput').addEventListener('input', renderPricingOptions);
                var currencySelect = document.getElementById('currencySelect');
                if (currencySelect) currencySelect.addEventListener('change', renderPricingOptions);
            });
            </script>
            @endif
        </div>
    </div>
</div>
@endsection
