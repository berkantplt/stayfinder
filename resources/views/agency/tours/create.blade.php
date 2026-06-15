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

                    {{-- URL'den İçe Aktar --}}
                    <div id="importPanel" style="border:1px dashed var(--accent);border-radius:12px;padding:16px;margin-bottom:24px;background:#f8fafc;">
                        <div style="font-weight:700;margin-bottom:4px;">🔗 URL'den İçe Aktar</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
                            Kendi sitenizdeki tur sayfasının linkini yapıştırın; başlık, destinasyon, açıklama, süre, fiyat ve tarihleri otomatik dolduralım. Kategoriyi ve görseli siz seçersiniz, kaydetmeden önce kontrol edin.
                        </div>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <input type="url" id="importUrl" placeholder="https://acenta.com/tur-sayfasi" style="flex:1;min-width:240px;margin:0;">
                            <button type="button" id="importBtn" class="btn btn-primary" onclick="importFromUrl()">Bilgileri Getir</button>
                        </div>
                        <div id="importStatus" style="font-size:13px;margin-top:10px;display:none;"></div>
                    </div>

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

                    <div class="form-group">
                        <label>Fiyat Bazlı Tarih Blokları *</label>
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

                                    return [
                                        'price' => trim((string) ($option['price'] ?? '')),
                                        'departure_dates' => $dates,
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
                            Önce fiyatı yaz, sonra o fiyata ait günleri takvimden seç. Yeni fiyat için alttan yeni blok ekle.
                        </div>

                        <div id="pricingOptionsContainer" style="display:flex;flex-direction:column;gap:12px;"></div>
                        <div id="pricingOptionsHidden"></div>

                        <button type="button" class="btn btn-outline btn-sm" style="margin-top:10px;" onclick="addPricingOption()">+ Yeni Fiyat Bloğu</button>
                    </div>

                    <div class="form-group"><label>Açıklama</label><textarea name="description">{{ old('description') }}</textarea></div>
                    <div class="form-group"><label>Dahil Olanlar (her satıra bir madde)</label><textarea name="included">{{ old('included') }}</textarea></div>
                    <div class="form-group"><label>Dahil Olmayanlar</label><textarea name="excluded">{{ old('excluded') }}</textarea></div>
                    <div class="form-group">
                        <label>Tur Resmi</label>
                        <input type="file" name="image" accept="image/*" onchange="previewImg(this)" style="padding:8px;">
                        <img id="imgPreview" style="display:none;max-height:120px;border-radius:8px;margin-top:8px;">
                    </div>
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
            let pricingOptions = [];
            let calendarViewByOptionId = {};

            function previewImg(i){
                var r = new FileReader();
                r.onload = function(e){
                    var p = document.getElementById('imgPreview');
                    p.src = e.target.result;
                    p.style.display = 'block';
                };
                if (i.files[0]) r.readAsDataURL(i.files[0]);
            }

            // --- URL'den İçe Aktar ---
            function showImportStatus(msg, type) {
                var s = document.getElementById('importStatus');
                if (!s) return;
                s.style.display = 'block';
                s.textContent = msg;
                s.style.color = type === 'error' ? '#b91c1c' : (type === 'success' ? '#15803d' : '#475569');
            }

            function setVal(selector, value) {
                var el = document.querySelector(selector);
                if (el && value !== null && value !== undefined && value !== '') el.value = value;
            }

            function applyImported(data, sourceUrl) {
                data = data || {};
                setVal('input[name="title"]', data.title);
                setVal('input[name="destination"]', data.destination);
                if (data.duration_days) setVal('#durationDaysInput', data.duration_days);
                setVal('textarea[name="description"]', data.description);
                setVal('textarea[name="included"]', data.included);
                setVal('textarea[name="excluded"]', data.excluded);
                setVal('input[name="tour_url"]', sourceUrl);

                if (data.currency) {
                    var sel = document.getElementById('currencySelect');
                    if (sel && [].some.call(sel.options, function(o){ return o.value === data.currency; })) {
                        sel.value = data.currency;
                    }
                }

                var price = (data.price !== null && data.price !== undefined) ? String(data.price) : '';
                var dates = Array.isArray(data.departure_dates) ? data.departure_dates : [];
                if (price !== '' || dates.length) {
                    pricingOptions = [createPricingOption({ price: price, departure_dates: dates })];
                    calendarViewByOptionId = {};
                    renderPricingOptions();
                }

                var cat = document.querySelector('select[name="category_id"]');
                if (cat) {
                    cat.style.outline = '2px solid var(--accent)';
                    cat.focus();
                    setTimeout(function(){ cat.style.outline = ''; }, 4000);
                }
            }

            function importFromUrl() {
                var url = (document.getElementById('importUrl').value || '').trim();
                var btn = document.getElementById('importBtn');
                if (!url) { showImportStatus('Lütfen bir URL girin.', 'error'); return; }

                var oldLabel = btn.textContent;
                btn.disabled = true;
                btn.textContent = 'Getiriliyor…';
                showImportStatus('Sayfa okunuyor, lütfen bekleyin…', 'info');

                var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                fetch('{{ route('agency.tours.import') }}', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                    body: JSON.stringify({ url: url })
                })
                .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, body: j }; }); })
                .then(function(res){
                    if (!res.body || !res.body.ok) {
                        throw new Error((res.body && res.body.message) || 'İçe aktarma başarısız.');
                    }
                    applyImported(res.body.data, url);
                    showImportStatus('Bilgiler dolduruldu. Lütfen kategori seçin, kontrol edip kaydedin.', 'success');
                })
                .catch(function(e){ showImportStatus(e.message || 'İçe aktarma başarısız.', 'error'); })
                .finally(function(){ btn.disabled = false; btn.textContent = oldLabel; });
            }

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
                return parts[2] + '.' + parts[1] + '.' + parts[0];
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

            function createPricingOption(option) {
                return {
                    id: Date.now() + Math.floor(Math.random() * 100000),
                    price: String((option && option.price) || ''),
                    departure_dates: uniqueSortedDates((option && option.departure_dates) || []),
                };
            }

            function ensureAtLeastOneOption() {
                if (pricingOptions.length === 0) {
                    pricingOptions.push(createPricingOption({ price: '', departure_dates: [] }));
                }
            }

            function getOptionById(optionId) {
                return pricingOptions.find(function(option) {
                    return option.id === optionId;
                });
            }

            function isDateSelectedInOtherOption(currentOptionId, ymd) {
                return pricingOptions.some(function(option) {
                    return option.id !== currentOptionId && option.departure_dates.includes(ymd);
                });
            }

            function addPricingOption() {
                pricingOptions.push(createPricingOption({ price: '', departure_dates: [] }));
                renderPricingOptions();
            }

            function removePricingOption(optionId) {
                pricingOptions = pricingOptions.filter(function(option) {
                    return option.id !== optionId;
                });
                delete calendarViewByOptionId[optionId];
                ensureAtLeastOneOption();
                renderPricingOptions();
            }

            function updateOptionPrice(optionId, value) {
                const option = getOptionById(optionId);
                if (!option) return;
                option.price = value;
                syncPricingOptionInputs();
            }

            function removeOptionDate(optionId, ymd) {
                const option = getOptionById(optionId);
                if (!option) return;

                option.departure_dates = option.departure_dates.filter(function(date) {
                    return date !== ymd;
                });
                syncPricingOptionInputs();
                renderPricingOptions();
            }

            function closeAllCalendars() {
                document.querySelectorAll('.multi-calendar-panel.open').forEach(function(panel) {
                    panel.classList.remove('open');
                });
            }

            function getInitialCalendarView(option) {
                if (calendarViewByOptionId[option.id]) {
                    return calendarViewByOptionId[option.id];
                }

                const firstSelected = option.departure_dates[0];
                const minDate = parseYmd(minSelectableDate);
                const baseDate = parseYmd(firstSelected) || minDate || new Date();
                return {
                    year: baseDate.getFullYear(),
                    month: baseDate.getMonth(),
                };
            }

            function updatePickerLabel(option, pickerInput) {
                const count = option.departure_dates.length;

                if (count === 0) {
                    pickerInput.value = '';
                    return;
                }

                if (count <= 2) {
                    pickerInput.value = option.departure_dates.map(formatTr).join(', ');
                    return;
                }

                pickerInput.value = count + ' tarih seçildi';
            }

            function renderCalendar(option, card) {
                const panel = card.querySelector('.multi-calendar-panel');
                const titleEl = panel.querySelector('.cal-title');
                const gridEl = panel.querySelector('.multi-calendar-grid');
                const pickerInput = card.querySelector('.departure-picker-input');
                const view = getInitialCalendarView(option);
                calendarViewByOptionId[option.id] = view;

                titleEl.textContent = monthNamesTr[view.month] + ' ' + view.year;
                gridEl.innerHTML = '';
                updatePickerLabel(option, pickerInput);

                const firstDayIndex = new Date(view.year, view.month, 1).getDay();
                const daysInMonth = new Date(view.year, view.month + 1, 0).getDate();

                for (let i = 0; i < firstDayIndex; i += 1) {
                    const blankCell = document.createElement('div');
                    blankCell.className = 'cal-day-placeholder';
                    gridEl.appendChild(blankCell);
                }

                for (let day = 1; day <= daysInMonth; day += 1) {
                    const dayDate = new Date(view.year, view.month, day);
                    const ymd = toYmd(dayDate);
                    const dayBtn = document.createElement('button');
                    dayBtn.type = 'button';
                    dayBtn.className = 'cal-day';
                    dayBtn.textContent = String(day);
                    const selectedInCurrent = option.departure_dates.includes(ymd);
                    const selectedInOther = isDateSelectedInOtherOption(option.id, ymd);

                    if (selectedInCurrent) {
                        dayBtn.classList.add('selected');
                    } else if (selectedInOther) {
                        dayBtn.classList.add('taken');
                        dayBtn.title = 'Bu tarih başka fiyat bloğunda seçili';
                    }

                    if (minSelectableDate && ymd < minSelectableDate) {
                        dayBtn.classList.add('disabled');
                        dayBtn.disabled = true;
                    } else if (selectedInOther && !selectedInCurrent) {
                        dayBtn.disabled = true;
                    } else {
                        dayBtn.addEventListener('click', function() {
                            const currentOption = getOptionById(option.id);
                            if (!currentOption) return;

                            if (currentOption.departure_dates.includes(ymd)) {
                                currentOption.departure_dates = currentOption.departure_dates.filter(function(date) {
                                    return date !== ymd;
                                });
                            } else {
                                currentOption.departure_dates = currentOption.departure_dates.concat([ymd]);
                            }

                            currentOption.departure_dates = uniqueSortedDates(currentOption.departure_dates);
                            const previewEl = card.querySelector('.option-preview');
                            renderOptionPreview(currentOption, previewEl);
                            syncPricingOptionInputs();
                            renderCalendar(currentOption, card);
                        });
                    }

                    gridEl.appendChild(dayBtn);
                }
            }

            function renderOptionPreview(option, previewEl) {
                const durationInput = document.getElementById('durationDaysInput');
                const durationDays = parseInt(durationInput.value || '1', 10);

                previewEl.innerHTML = '';
                option.departure_dates = uniqueSortedDates(option.departure_dates);

                option.departure_dates.forEach(function(ymd) {
                    const returnYmd = calculateReturnDate(ymd, durationDays);

                    const pill = document.createElement('div');
                    pill.className = 'departure-preview-item';

                    const text = document.createElement('span');
                    text.textContent = formatTr(ymd) + ' -> ' + formatTr(returnYmd);
                    pill.appendChild(text);

                    const removeBtn = document.createElement('button');
                    removeBtn.type = 'button';
                    removeBtn.className = 'btn btn-outline btn-sm';
                    removeBtn.style.padding = '2px 8px';
                    removeBtn.textContent = 'x';
                    removeBtn.addEventListener('click', function() {
                        removeOptionDate(option.id, ymd);
                    });
                    pill.appendChild(removeBtn);

                    previewEl.appendChild(pill);
                });
            }

            function syncPricingOptionInputs() {
                const hiddenContainer = document.getElementById('pricingOptionsHidden');
                hiddenContainer.innerHTML = '';

                pricingOptions.forEach(function(option, index) {
                    const priceInput = document.createElement('input');
                    priceInput.type = 'hidden';
                    priceInput.name = 'pricing_options[' + index + '][price]';
                    priceInput.value = option.price;
                    hiddenContainer.appendChild(priceInput);

                    uniqueSortedDates(option.departure_dates).forEach(function(ymd) {
                        const dateInput = document.createElement('input');
                        dateInput.type = 'hidden';
                        dateInput.name = 'pricing_options[' + index + '][departure_dates][]';
                        dateInput.value = ymd;
                        hiddenContainer.appendChild(dateInput);
                    });
                });
            }

            function changeCalendarMonth(option, card, diff) {
                const current = getInitialCalendarView(option);
                let year = current.year;
                let month = current.month + diff;

                while (month < 0) {
                    month += 12;
                    year -= 1;
                }
                while (month > 11) {
                    month -= 12;
                    year += 1;
                }

                calendarViewByOptionId[option.id] = { year: year, month: month };
                renderCalendar(option, card);
            }

            function renderPricingOptions() {
                const container = document.getElementById('pricingOptionsContainer');

                container.innerHTML = '';

                pricingOptions.forEach(function(option, index) {
                    const card = document.createElement('div');
                    card.className = 'pricing-option-card';
                    card.dataset.optionId = String(option.id);

                    const canRemove = pricingOptions.length > 1;
                    card.innerHTML = `
                        <div class="pricing-option-head">
                            <div style="font-size:13px;font-weight:700;">Fiyat Bloğu #${index + 1}</div>
                            ${canRemove ? '<button type="button" class="btn btn-outline btn-sm remove-option-btn">Bloğu Sil</button>' : ''}
                        </div>
                        <div class="form-row">
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Fiyat (${getSelectedCurrencySymbol()}) *</label>
                                <input type="number" class="option-price-input" min="0" step="0.01" value="${option.price}">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label>Gidiş Tarihleri *</label>
                                <div class="date-picker-wrap">
                                    <input type="text" class="departure-picker-input" placeholder="Takvimden bu fiyata ait günleri seç" readonly>
                                    <div class="multi-calendar-panel">
                                        <div class="multi-calendar-header">
                                            <button type="button" class="cal-nav-btn cal-prev">‹</button>
                                            <div class="cal-title"></div>
                                            <button type="button" class="cal-nav-btn cal-next">›</button>
                                        </div>
                                        <div class="multi-calendar-weekdays">
                                            <span>P</span><span>S</span><span>Ç</span><span>P</span><span>C</span><span>C</span><span>P</span>
                                        </div>
                                        <div class="multi-calendar-grid"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="departure-preview-wrap option-preview"></div>
                    `;

                    container.appendChild(card);

                    const removeButton = card.querySelector('.remove-option-btn');
                    if (removeButton) {
                        removeButton.addEventListener('click', function() {
                            removePricingOption(option.id);
                        });
                    }

                    const priceInput = card.querySelector('.option-price-input');
                    priceInput.addEventListener('input', function() {
                        updateOptionPrice(option.id, this.value);
                    });

                    const pickerInput = card.querySelector('.departure-picker-input');
                    const calendarPanel = card.querySelector('.multi-calendar-panel');
                    const prevBtn = card.querySelector('.cal-prev');
                    const nextBtn = card.querySelector('.cal-next');

                    pickerInput.addEventListener('click', function(event) {
                        event.stopPropagation();
                        const isOpen = calendarPanel.classList.contains('open');
                        closeAllCalendars();
                        if (!isOpen) {
                            calendarPanel.classList.add('open');
                            renderCalendar(option, card);
                        }
                    });

                    calendarPanel.addEventListener('click', function(event) {
                        event.stopPropagation();
                    });

                    prevBtn.addEventListener('click', function() {
                        changeCalendarMonth(option, card, -1);
                    });

                    nextBtn.addEventListener('click', function() {
                        changeCalendarMonth(option, card, 1);
                    });

                    const previewEl = card.querySelector('.option-preview');
                    renderOptionPreview(option, previewEl);
                    updatePickerLabel(option, pickerInput);
                });

                syncPricingOptionInputs();
            }

            document.addEventListener('click', closeAllCalendars);

            document.addEventListener('DOMContentLoaded', function() {
                pricingOptions = (initialPricingOptions || []).map(function(option) {
                    return createPricingOption(option);
                });

                ensureAtLeastOneOption();
                renderPricingOptions();

                document.getElementById('durationDaysInput').addEventListener('input', function() {
                    renderPricingOptions();
                });
                const currencySelect = document.getElementById('currencySelect');
                if (currencySelect) {
                    currencySelect.addEventListener('change', function() {
                        renderPricingOptions();
                    });
                }
            });
            </script>
            @endif
        </div>
    </div>
</div>
@endsection
