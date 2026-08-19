{{-- Vize durumu — ÜÇ DURUMLU.

     Kutucuklar bilerek: tek boolean ile "vizesiz" ile "girilmemiş" ayrılamıyor
     ve kolon yıllarca default false durduğu için HomeController vize filtresi bu
     alanı kullanmayı reddediyordu. Hiçbiri işaretsiz = belirtilmemiş (null).

     "Kapıda vize" ayrı seçenek: yolcu için vizeli turdan bambaşka bir iş
     (konsolosluk randevusu/evrak yok, sınırda ödenip alınıyor). Vizeli'ye
     katlansaydı kullanıcıyı gereksiz yere caydırırdık.

     Değer sözlüğü: '1' (vizeli) | 'kapida' | '0' (vizesiz) | 'unknown'. --}}
@php
    $visaValue = $visaValue ?? 'unknown';
@endphp
<div class="form-group">
    <label>Vize durumu</label>
    <input type="hidden" name="requires_visa" value="{{ $visaValue }}" data-visa-hidden>

    <div class="visa-kutular" data-visa-grup>
        <label class="visa-kutu">
            <input type="checkbox" data-visa-deger="1" @checked($visaValue === '1')>
            <span>Vizeli</span>
        </label>
        <label class="visa-kutu">
            <input type="checkbox" data-visa-deger="kapida" @checked($visaValue === 'kapida')>
            <span>Kapıda vize</span>
        </label>
        <label class="visa-kutu">
            <input type="checkbox" data-visa-deger="0" @checked($visaValue === '0')>
            <span>Vizesiz</span>
        </label>
    </div>

    <div style="font-size:11px;color:#94a3b8;margin-top:6px;">
        Hiçbiri işaretsizse <strong>belirtilmemiş</strong> olarak kaydedilir — turda ve
        karşılaştırmada vize satırı hiç görünmez. Yurt içi turlarda boş bırakabilirsin.
        URL'den içe aktarım bu alana <strong>dokunmaz</strong>; vize durumunu sen işaretlersin.
    </div>
</div>

<style>
    .visa-kutular { display:flex; gap:10px; flex-wrap:wrap; }
    .visa-kutu { display:inline-flex; align-items:center; gap:8px; cursor:pointer; user-select:none;
        border:1.5px solid #e2e8f0; background:#fff; border-radius:10px; padding:10px 16px;
        font-size:14px; font-weight:600; color:#475569; transition:border-color .15s, color .15s; }
    .visa-kutu:hover { border-color:#0d9488; color:#0f766e; }
    .visa-kutu input { accent-color:#0d9488; width:16px; height:16px; cursor:pointer; margin:0; }
    .visa-kutu:has(input:checked) { border-color:#0d9488; color:#0f766e; background:rgba(13,148,136,0.06); }
</style>

<script>
(function () {
    // Sayfada birden çok kez include edilirse dinleyici iki kez bağlanmasın.
    if (window.__visaKutulariBagli) return;
    window.__visaKutulariBagli = true;

    document.addEventListener('change', function (olay) {
        var kutu = olay.target.closest('[data-visa-deger]');
        if (!kutu) return;

        var grup = kutu.closest('[data-visa-grup]');
        var gizli = grup.parentNode.querySelector('[data-visa-hidden]');

        // Karşılıklı dışlama: vizeli / kapıda / vizesiz aynı anda doğru olamaz.
        grup.querySelectorAll('[data-visa-deger]').forEach(function (diger) {
            if (diger !== kutu) diger.checked = false;
        });

        // İşareti kaldırmak geçerli bir eylem: belirtilmemişe döner.
        gizli.value = kutu.checked ? kutu.dataset.visaDeger : 'unknown';
    });
})();
</script>
