{{-- URL'den İçe Aktar — create ve edit formlarının ORTAK paneli + JS'i.
     Hedef alan/fonksiyonlar (setItinerary, setDateEntries, setGalleryImages,
     setStopCities, isVisaMode) sayfanın kendi script'inde tanımlıdır; buradaki
     fonksiyonlar tıklama anında çalıştığı için yükleme sırası sorun olmaz. --}}
<div id="importPanel" style="border:1px dashed var(--accent);border-radius:12px;padding:16px;margin-bottom:24px;background:#f8fafc;">
    <div style="font-weight:700;margin-bottom:4px;">🔗 URL'den İçe Aktar</div>
    <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
        Kendi sitenizdeki tur sayfasının linkini yapıştırın; başlık, destinasyon, açıklama, süre, fiyat ve tarihleri otomatik dolduralım. Dolu alanların üzerine yazılır — kaydetmeden önce kontrol edin.
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="url" id="importUrl" placeholder="https://acenta.com/tur-sayfasi" style="flex:1;min-width:240px;margin:0;">
        <button type="button" id="importBtn" class="btn btn-primary" onclick="importFromUrl(false)">Bilgileri Getir</button>
        <button type="button" id="importDeepBtn" class="btn btn-outline" onclick="importFromUrl(true)" title="Açılır tarih menüleri gibi dinamik içerikleri de tarar (daha yavaş)">🔍 Derin Tarama</button>
    </div>
    <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">Tarihler veya bilgiler eksik geldiyse "Derin Tarama"yı dene (sayfayı gerçek tarayıcıda açar, ~20 sn sürebilir).</div>
    <div id="importStatus" style="font-size:13px;margin-top:10px;display:none;"></div>
</div>

<script>
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
    if (Array.isArray(data.itinerary) && data.itinerary.length) setItinerary(data.itinerary);
    setVal('textarea[name="departure_points"]', data.departure_points);
    setVal('textarea[name="hotel_info"]', data.hotel_info);
    setVal('textarea[name="extras"]', data.extras);
    setVal('textarea[name="cancellation_policy"]', data.cancellation_policy);
    setVal('textarea[name="guide_info"]', data.guide_info);
    setVal('input[name="frequency"]', data.frequency);
    setVal('input[name="tour_url"]', sourceUrl);

    // Vize bilgileri: yanıt vize anahtarlarını içeriyorsa ("Vizeli" modda her
    // zaman döner) null/boş değer alanı TEMİZLER — önceki import'un metinleri
    // yeni turun kutularında kalmasın.
    ['visa_general', 'visa_documents', 'visa_fees', 'visa_notes'].forEach(function(k) {
        if (!(k in data)) return;
        var el = document.querySelector('textarea[name="' + k + '"]');
        if (el) el.value = data[k] || '';
    });

    // Görseller: URL'den çekilenleri galeriye ekle
    if (typeof window.setGalleryImages === 'function' && Array.isArray(data.image_urls)) {
        window.setGalleryImages(data.image_urls);
    }

    // Kalkış + durak şehirleri
    if (data.departure_city) {
        var dcSel = document.getElementById('departureCitySelect');
        if (dcSel && [].some.call(dcSel.options, function(o){ return o.value === data.departure_city; })) {
            dcSel.value = data.departure_city;
        }
    }
    if (typeof window.setStopCities === 'function' && Array.isArray(data.stop_cities)) {
        window.setStopCities(data.stop_cities);
    }

    if (data.currency) {
        var sel = document.getElementById('currencySelect');
        if (sel && [].some.call(sel.options, function(o){ return o.value === data.currency; })) {
            sel.value = data.currency;
        }
    }

    // Her tarih kendi satırı: paket matrisi varsa bloğun tarihlerini tek tek aç;
    // yoksa düz tarih listesini (varsa tek fiyatla) tarih satırlarına dök.
    var entries = [];
    if (Array.isArray(data.pricing_blocks) && data.pricing_blocks.length) {
        data.pricing_blocks.forEach(function(block) {
            var pkgs = Array.isArray(block.packages) ? block.packages : [];
            (Array.isArray(block.dates) ? block.dates : []).forEach(function(d) {
                entries.push({ date: d, price: '', packages: pkgs });
            });
        });
    } else {
        var price = (data.price !== null && data.price !== undefined) ? String(data.price) : '';
        (Array.isArray(data.departure_dates) ? data.departure_dates : []).forEach(function(d) {
            entries.push({ date: d, price: price, packages: [] });
        });
    }
    if (entries.length) {
        setDateEntries(entries);
    }

    var cat = document.querySelector('select[name="category_id"]');
    if (cat) {
        cat.style.outline = '2px solid var(--accent)';
        cat.focus();
        setTimeout(function(){ cat.style.outline = ''; }, 4000);
    }
}

function importFromUrl(deep) {
    var url = (document.getElementById('importUrl').value || '').trim();
    var btn = document.getElementById(deep ? 'importDeepBtn' : 'importBtn');
    if (!url) { showImportStatus('Lütfen bir URL girin.', 'error'); return; }

    var oldLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = deep ? 'Derin taranıyor…' : 'Getiriliyor…';
    showImportStatus(deep
        ? 'Sayfa gerçek tarayıcıda açılıyor, tüm tarihler taranıyor (~20 sn)…'
        : 'Sayfa okunuyor, lütfen bekleyin…', 'info');

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    fetch('{{ route('agency.tours.import') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ url: url, deep: deep ? 1 : 0, visa: (typeof isVisaMode === 'function' && isVisaMode()) ? 1 : 0 })
    })
    .then(function(r){ return r.json().then(function(j){ return { ok: r.ok, body: j }; }); })
    .then(function(res){
        if (!res.body || !res.body.ok) {
            throw new Error((res.body && res.body.message) || 'İçe aktarma başarısız.');
        }
        applyImported(res.body.data, url);
        var warns = (res.body.data && res.body.data.warnings) || [];
        if (warns.length) {
            showImportStatus('Bilgiler dolduruldu, ancak dikkat: ' + warns.join(' '), 'error');
        } else {
            showImportStatus('Bilgiler dolduruldu. Lütfen kategoriyi ve alanları kontrol edip kaydedin.', 'success');
        }
    })
    .catch(function(e){ showImportStatus(e.message || 'İçe aktarma başarısız.', 'error'); })
    .finally(function(){ btn.disabled = false; btn.textContent = oldLabel; });
}
</script>
