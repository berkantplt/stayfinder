{{-- URL'den İçe Aktar — create ve edit formlarının ORTAK paneli + JS'i.
     Hedef alan/fonksiyonlar (setItinerary, setDateEntries, setGalleryImages,
     setStopCities) sayfanın kendi script'inde tanımlıdır; buradaki fonksiyonlar
     tıklama anında çalıştığı için yükleme sırası sorun olmaz. --}}
<div id="importPanel" style="border:1px dashed var(--accent);border-radius:12px;padding:16px;margin-bottom:24px;background:#f8fafc;">
    <div style="font-weight:700;margin-bottom:4px;">🔗 URL'den İçe Aktar</div>
    <div style="font-size:12px;color:var(--text-muted);margin-bottom:10px;">
        Kendi sitenizdeki tur sayfasının linkini yapıştırın; başlık, destinasyon, açıklama, süre, fiyat ve tarihleri otomatik dolduralım. Dolu alanların üzerine yazılır — kaydetmeden önce kontrol edin.
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <input type="url" id="importUrl" placeholder="https://acenta.com/tur-sayfasi" style="flex:1;min-width:240px;margin:0;">
        <button type="button" id="importBtn" class="btn btn-primary" onclick="importFromUrl()">Bilgileri Getir</button>
    </div>
    <div style="font-size:11px;color:var(--text-muted);margin-top:6px;">Sistem sayfayı en doğru şekilde okur; dinamik (JavaScript ile yüklenen) fiyat/tarih varsa gerçek tarayıcıda otomatik açar — bazı sayfalarda 30-60 sn sürebilir.</div>
    <div id="importStatus" style="font-size:13px;margin-top:10px;display:none;"></div>
</div>

<script>
function showImportStatus(msg, type) {
    var s = document.getElementById('importStatus');
    if (!s) return;
    s.style.display = 'block';
    s.textContent = msg;
    // error=kırmızı (gerçek başarısızlık), warn=amber (kısmi başarı, veri doldu
    // ama bir kısmı elle gerekiyor), success=yeşil, diğer=nötr.
    s.style.color = type === 'error' ? '#b91c1c'
        : type === 'warn' ? '#b45309'
        : type === 'success' ? '#15803d'
        : '#475569';
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
    if (data.duration_nights !== null && data.duration_nights !== undefined) setVal('#durationNightsInput', data.duration_nights);
    if (data.transport_type) setVal('#transportTypeInput', data.transport_type);
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

    // Her kalkış tarihi kendi satırı olur. Fiyat bloğu OLAN tarih tam paket/oda
    // matrisiyle gelir. Bloğu OLMAYAN tarihler (ör. etstur'da modal yalnız bir
    // tarihin tablosunu yükler) için:
    //   - turun başlangıç (çift kişilik) fiyatı, bloğun çift kişilik fiyatıyla
    //     AYNIYSA → fiyat tarihe göre değişmiyor demektir; aynı oda matrisini
    //     ŞABLON olarak tüm tarihlere uygula (acenta yine kontrol eder)
    //   - FARKLIYSA → fiyat tarihe göre değişkendir; sadece başlangıç fiyatını koy
    //     (yanlış oda fiyatı uydurma), acenta o tarihi elle girer
    var entries = [];
    var blockByDate = {};
    (Array.isArray(data.pricing_blocks) ? data.pricing_blocks : []).forEach(function(block) {
        var pkgs = Array.isArray(block.packages) ? block.packages : [];
        (Array.isArray(block.dates) ? block.dates : []).forEach(function(d) {
            blockByDate[d] = pkgs;
        });
    });

    // Tüm tarihler = departure_dates ∪ blok tarihleri (tekilleştirilip sıralanır)
    var allDates = {};
    (Array.isArray(data.departure_dates) ? data.departure_dates : []).forEach(function(d) { allDates[d] = true; });
    Object.keys(blockByDate).forEach(function(d) { allDates[d] = true; });

    // Şablon matrisi (ilk blok tarihinden). Kullanıcı kararı: NE OLURSA OLSUN her
    // tarih PAKET (matris) olarak gelsin. Modallı OTA'larda (etstur) tarih dropdown'ı
    // opak custom bileşen olduğundan her tarihin GERÇEK matrisi tek tek çekilemiyor;
    // bu yüzden ilk bloğun matrisi TÜM tarihlere ŞABLON olarak uygulanır. Fiyat
    // tarihe göre farklıysa acenta düzenler (sayfada tek fiyat bilgisi olduğundan
    // per-tarih fark zaten tespit edilemiyor).
    var templatePkgs = null;
    var blockDates = Object.keys(blockByDate).sort();
    if (blockDates.length && blockByDate[blockDates[0]] && blockByDate[blockDates[0]].length) {
        templatePkgs = blockByDate[blockDates[0]];
    }
    var startPrice = (data.price !== null && data.price !== undefined) ? String(data.price) : '';

    Object.keys(allDates).sort().forEach(function(d) {
        if (blockByDate[d] && blockByDate[d].length) {
            entries.push({ date: d, price: '', packages: blockByDate[d] });
        } else if (templatePkgs) {
            // Bloğu olmayan tarih → ilk bloğun matrisini şablonla (her tarih paket)
            entries.push({ date: d, price: '', packages: templatePkgs });
        } else {
            // Hiç matris yok (site fiyat tablosu vermiyor) → düz başlangıç fiyatı
            entries.push({ date: d, price: startPrice, packages: [] });
        }
    });
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

var _importInFlight = false;

function importFromUrl() {
    if (_importInFlight) return; // çift tıklama engellenir
    var url = (document.getElementById('importUrl').value || '').trim();
    var btn = document.getElementById('importBtn');
    if (!url) { showImportStatus('Lütfen bir URL girin.', 'error'); return; }

    // Dolu form üzerine yazma uyarısı: başlık dolu veya fiyat/tarih satırı varsa
    // (özellikle düzenleme sayfasında) onay iste — sessizce ezme.
    var titleFilled = (document.querySelector('input[name="title"]')?.value || '').trim() !== '';
    var hasPricingRows = (typeof pricingOptions !== 'undefined' && Array.isArray(pricingOptions) && pricingOptions.length > 0);
    if ((titleFilled || hasPricingRows) &&
        !confirm('Formdaki mevcut bilgilerin (tur adı, tarih/fiyat satırları dahil) üzerine yazılacak. Devam edilsin mi?')) {
        return;
    }

    _importInFlight = true;
    var oldLabel = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Getiriliyor…';
    showImportStatus('Sayfa okunuyor; dinamik içerik varsa gerçek tarayıcıda açılıyor, lütfen bekleyin…', 'info');

    var csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    // İstemci zaman aşımı: sunucu/proxy sonsuza dek asılı kalırsa kullanıcı
    // sonsuz "bekleyin" ekranında kalmasın. Akıllı akış SPA'da render yapabildiği
    // için pay geniş tutulur (sunucu bütçesi 150 sn).
    var controller = new AbortController();
    var timer = setTimeout(function(){ controller.abort(); }, 170000);

    fetch('{{ route('agency.tours.import') }}', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
        body: JSON.stringify({ url: url }),
        signal: controller.signal
    })
    .then(function(r){
        // 504/502/500'de gövde JSON değil HTML olabilir; parse'ı guard'la, aksi
        // halde "Unexpected token <" gibi anlamsız hata çıkar.
        return r.text().then(function(t){
            var body = null;
            try { body = t ? JSON.parse(t) : null; } catch (_) { body = null; }
            return { status: r.status, ok: r.ok, body: body };
        });
    })
    .then(function(res){
        if (res.ok && res.body && res.body.ok) {
            applyImported(res.body.data, url);
            var warns = (res.body.data && res.body.data.warnings) || [];
            if (warns.length) {
                // Veri DOLDU; uyarı kısmi eksikliği anlatır (hata değil) → amber.
                showImportStatus('✓ Bilgiler dolduruldu. Not: ' + warns.join(' '), 'warn');
            } else {
                showImportStatus('✓ Bilgiler dolduruldu. Lütfen kategoriyi ve alanları kontrol edip kaydedin.', 'success');
            }
            return;
        }
        // Duruma özel Türkçe mesaj
        var msg;
        if (res.body && res.body.message) {
            msg = res.body.message;
        } else if (res.body && res.body.errors && res.body.errors.url) {
            msg = res.body.errors.url[0];
        } else if (res.status === 429) {
            msg = 'Çok sık denediniz. Lütfen bir dakika sonra tekrar deneyin.';
        } else if (res.status === 419) {
            msg = 'Oturumunuz yenilendi. Sayfayı yenileyip tekrar deneyin.';
        } else if (res.status === 504 || res.status === 502) {
            msg = 'Sayfa çok uzun sürdü ve zaman aşımına uğradı. Lütfen biraz sonra tekrar deneyin.';
        } else if (res.status >= 500) {
            msg = 'Sunucuda bir sorun oluştu. Lütfen tekrar deneyin.';
        } else {
            msg = 'İçe aktarma başarısız. Lütfen URL\'yi kontrol edip tekrar deneyin.';
        }
        showImportStatus(msg, 'error');
    })
    .catch(function(e){
        if (e && e.name === 'AbortError') {
            showImportStatus('İşlem çok uzun sürdü ve iptal edildi. Lütfen tekrar deneyin.', 'error');
        } else {
            showImportStatus('Bağlantı hatası — internet bağlantınızı kontrol edip tekrar deneyin.', 'error');
        }
    })
    .finally(function(){
        clearTimeout(timer);
        _importInFlight = false;
        btn.disabled = false;
        btn.textContent = oldLabel;
    });
}
</script>
