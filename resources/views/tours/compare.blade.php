@extends('layouts.app')
@section('title', 'Turları Karşılaştır — ' . count($tours) . ' Tur')

@section('content')
@php
    // Dahil/hariç listeleri aynı iskeleti paylaşır, tek fark işaret ve renk.
    $listeler = [
        ['anahtar' => 'dahil', 'etiket' => 'Fiyata dahil', 'isaret' => '✓', 'sinif' => 'kiyas-arti'],
        ['anahtar' => 'haric', 'etiket' => 'Dahil değil', 'isaret' => '✗', 'sinif' => 'kiyas-eksi'],
    ];
@endphp
<style>
    .kiyas-sayfa { padding:32px 20px 56px; --etiket-g:150px; --kolon-g:270px; }
    .kiyas-ust-satir { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
    .kiyas-sayfa h1 { font-size:30px; font-weight:800; letter-spacing:-1px; color:var(--text); margin:12px 0 6px; }
    .kiyas-sayfa .kiyas-alt-baslik { color:var(--text-sec); font-size:15px; }

    /* Farkları göster anahtarı */
    .kiyas-anahtar { display:inline-flex; align-items:center; gap:9px; cursor:pointer; user-select:none;
        border:1.5px solid var(--border); background:var(--white); border-radius:999px; padding:9px 16px; font-size:14px; font-weight:600; color:var(--text-sec); }
    .kiyas-anahtar input { accent-color:var(--accent); width:16px; height:16px; cursor:pointer; }
    .kiyas-anahtar:hover { border-color:var(--accent); color:var(--accent-dark); }

    /* Yapışkan mini başlık: tablo uzun, kolon kimliği kaybolmasın */
    .kiyas-mini { position:sticky; top:var(--kiyas-ust,70px); z-index:40; background:var(--white);
        border-bottom:1px solid var(--border); box-shadow:0 6px 14px -10px rgba(15,23,42,.4);
        opacity:0; visibility:hidden; transition:opacity .15s ease; }
    .kiyas-mini.gorunur { opacity:1; visibility:visible; }
    .kiyas-mini-ic { display:flex; align-items:stretch; }
    .kiyas-mini-etiket { flex:0 0 var(--etiket-g); }
    .kiyas-mini-kaydir { flex:1; overflow:hidden; }
    .kiyas-mini-sira { display:flex; }
    .kiyas-mini-hucre { flex:0 0 auto; padding:9px 12px; min-width:0; border-left:1px solid var(--border-light); }
    .kiyas-mini-baslik { display:block; font-size:13px; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .kiyas-mini-fiyat { display:block; font-size:12.5px; font-weight:700; color:var(--accent-dark); }

    /* Tablo: satır hizalaması kıyasın tamamı — kart yığını bunu veremiyordu */
    .kiyas-sarmal { overflow-x:auto; padding-bottom:8px; }
    .kiyas { border-collapse:separate; border-spacing:0; table-layout:fixed; width:100%;
        min-width:calc(var(--etiket-g) + {{ count($tours) }} * var(--kolon-g)); }
    .kiyas th, .kiyas td { vertical-align:top; text-align:left; padding:14px 16px; border-bottom:1px solid var(--border-light); }
    .kiyas tbody tr:hover td:not(.kiyas-ayni) { background:var(--accent-bg); }

    .kiyas-etiket { position:sticky; left:0; z-index:2; background:var(--white);
        font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.4px; color:var(--text-muted);
        border-right:1px solid var(--border); }
    .kiyas thead .kiyas-etiket { z-index:3; }

    /* Başlık hücresi = tur kimliği */
    .kiyas-kolon { background:var(--white); border-left:1px solid var(--border-light); padding:0 !important; position:relative; z-index:0; }
    .kiyas-gorsel { width:100%; height:132px; object-fit:cover; display:block; }
    .kiyas-gorsel-bos { width:100%; height:132px; background:linear-gradient(135deg,#e0f2fe,#f0fdf4); display:flex; align-items:center; justify-content:center; font-size:38px; }
    .kiyas-kolon-govde { padding:14px 16px 0; }
    .kiyas-kolon-govde h2 { font-size:15.5px; font-weight:700; line-height:1.4; margin:0 0 4px;
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .kiyas-kolon-govde h2 a { color:var(--text); }
    .kiyas-kolon-govde h2 a:hover { color:var(--accent-dark); }
    .kiyas-acenta { font-size:12.5px; color:var(--text-muted); margin-bottom:10px; }
    .kiyas-cikar { position:absolute; top:8px; right:8px; z-index:1; width:30px; height:30px; border:none; border-radius:999px;
        background:rgba(15,23,42,.72); color:#fff; font-size:18px; line-height:1; cursor:pointer; display:flex; align-items:center; justify-content:center; }
    .kiyas-cikar:hover { background:#b91c1c; }

    /* Fiyat kutusu */
    .kiyas-eski { text-decoration:line-through; color:var(--text-muted); font-size:13px; }
    .kiyas-fiyat { font-size:23px; font-weight:800; letter-spacing:-.5px; color:var(--text); line-height:1.2; }
    .kiyas-fiyat.indirimli { color:var(--green); }
    .kiyas-try { font-size:12px; color:var(--text-muted); margin-top:2px; }
    .kiyas-gunluk { font-size:12.5px; color:var(--text-sec); margin-top:6px; }
    .kiyas-fark { font-size:12.5px; font-weight:700; color:#b45309; margin-top:4px; }

    .kiyas-rozet { display:inline-block; font-size:10.5px; font-weight:800; letter-spacing:.3px;
        padding:3px 8px; border-radius:999px; margin-top:6px; margin-right:5px; }
    .kiyas-rozet-yesil { background:var(--green-bg); color:var(--green-text); }
    .kiyas-rozet-turkuaz { background:var(--accent-light); color:var(--accent-dark); }
    .kiyas-rozet-notr { background:var(--border-light); color:var(--text-sec); }

    /* Değer hücreleri */
    .kiyas-deger { font-size:14px; color:var(--text); line-height:1.65; }
    .kiyas-ayni .kiyas-deger { color:var(--text-muted); }
    .kiyas-yok { color:var(--text-muted); font-size:14px; }
    .kiyas-metin { max-height:132px; overflow:hidden; position:relative; }
    .kiyas-metin.acik { max-height:none; }
    .kiyas-devam { background:none; border:none; padding:4px 0 0; font-size:12.5px; font-weight:700; color:var(--accent-dark); cursor:pointer; }

    /* Dahil / hariç madde listeleri */
    .kiyas-ozet { font-size:12px; font-weight:700; padding:5px 9px; border-radius:8px; margin-bottom:9px; display:inline-block; }
    .kiyas-arti .kiyas-ozet { background:var(--green-bg); color:var(--green-text); }
    .kiyas-eksi .kiyas-ozet { background:#fee2e2; color:#991b1b; }
    .kiyas-maddeler { list-style:none; padding:0; margin:0; font-size:13.5px; line-height:1.55; }
    .kiyas-maddeler li { display:flex; gap:7px; padding:3px 0; color:var(--text-sec); }
    .kiyas-maddeler li.ozel { color:var(--text); font-weight:600; }
    .kiyas-isaret { flex:0 0 auto; opacity:.55; }
    .kiyas-arti .kiyas-maddeler li.ozel .kiyas-isaret { color:var(--green); opacity:1; }
    .kiyas-eksi .kiyas-maddeler li.ozel .kiyas-isaret { color:#dc2626; opacity:1; }
    .kiyas-gizlendi { font-size:12px; color:var(--text-muted); margin-top:8px; display:none; }

    /* "Sadece farkları göster" açıkken */
    .kiyas.farklar tr[data-ayni="1"] { display:none; }
    .kiyas.farklar .kiyas-maddeler li[data-ortak="1"] { display:none; }
    .kiyas.farklar .kiyas-gizlendi { display:block; }

    /* Zaten karşılaştırma sayfasındayız: "Karşılaştır" çağrısı yapan alt bar
       burada gereksiz. JS ile gizlemek işe yaramıyor (bkz. yukarıdaki not). */
    #compare-bar { display:none !important; }

    .kiyas-cta { padding:16px !important; }
    .kiyas-cta .btn { width:100%; }
    .kiyas-bos-uyari { background:var(--accent-bg); border:1px solid var(--accent-light); border-radius:12px;
        padding:14px 16px; font-size:14px; color:var(--text-sec); margin-top:18px; display:none; }
    .kiyas-bos-uyari.aktif { display:block; }

    @media(max-width:768px) {
        .kiyas-sayfa { padding:20px 16px 40px; --etiket-g:104px; --kolon-g:228px; }
        .kiyas-sayfa h1 { font-size:23px; }
        .kiyas-sayfa .kiyas-alt-baslik { font-size:13.5px; }
        .kiyas th, .kiyas td { padding:11px 12px; }
        .kiyas-etiket { font-size:11px; padding:11px 10px; }
        .kiyas-gorsel, .kiyas-gorsel-bos { height:104px; }
        .kiyas-kolon-govde { padding:11px 12px 0; }
        .kiyas-fiyat { font-size:19px; }
        .kiyas-deger, .kiyas-maddeler { font-size:13px; }
    }
</style>

<div class="container kiyas-sayfa">
    <div class="kiyas-ust-satir">
        <div>
            <a href="{{ route('tours.index') }}" class="btn btn-outline btn-sm">← Turlara dön</a>
            <h1>Turları Karşılaştır</h1>
            <p class="kiyas-alt-baslik">{{ count($tours) }} tur yan yana. Farklı olan satırlar koyu, hepsinde aynı olanlar soluk.</p>
        </div>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <label class="kiyas-anahtar">
                <input type="checkbox" id="kiyas-farklar"> Sadece farkları göster
            </label>
            <button onclick="clearAndReturn()" class="btn btn-outline" style="border-color:#ef4444;color:#ef4444;">Temizle</button>
        </div>
    </div>

    {{-- Gerçek başlık ekrandan çıkınca devreye giren kompakt kopya. Yatay
         kaydırma tabloyla eşitlenir (JS), yoksa kolonlar kayardı. --}}
    <div class="kiyas-mini" id="kiyas-mini" aria-hidden="true">
        <div class="kiyas-mini-ic">
            <div class="kiyas-mini-etiket"></div>
            <div class="kiyas-mini-kaydir">
                <div class="kiyas-mini-sira">
                    @foreach($tours as $tour)
                        @php $f = $karsilastirma['fiyatlar'][$tour->id]; @endphp
                        <div class="kiyas-mini-hucre">
                            <span class="kiyas-mini-baslik">{{ $tour->title }}</span>
                            <span class="kiyas-mini-fiyat">{{ $f['etiket'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="kiyas-sarmal" id="kiyas-sarmal">
        <table class="kiyas" id="kiyas-tablo">
            <colgroup>
                <col style="width:var(--etiket-g)">
                @foreach($tours as $tour)
                    <col style="width:calc((100% - var(--etiket-g)) / {{ count($tours) }})">
                @endforeach
            </colgroup>

            <thead>
                <tr>
                    <th class="kiyas-etiket"></th>
                    @foreach($tours as $tour)
                        @php $f = $karsilastirma['fiyatlar'][$tour->id]; @endphp
                        <th class="kiyas-kolon" data-kiyas-tur="{{ $tour->id }}">
                            <button type="button" class="kiyas-cikar" title="Bu turu karşılaştırmadan çıkar"
                                    onclick="removeComparedTour({{ $tour->id }})">×</button>

                            @if($tour->image)
                                <img class="kiyas-gorsel" src="{{ $tour->image }}" alt="{{ $tour->title }}" loading="lazy">
                            @else
                                <div class="kiyas-gorsel-bos">🏖️</div>
                            @endif

                            <div class="kiyas-kolon-govde">
                                <h2><a href="{{ route('tours.show', $tour) }}">{{ $tour->title }}</a></h2>
                                <div class="kiyas-acenta">{{ optional($tour->agency)->name }}</div>

                                @if($f['eskiEtiket'])
                                    <div class="kiyas-eski">{{ $f['eskiEtiket'] }}</div>
                                @endif
                                <div class="kiyas-fiyat {{ $f['kampanya'] ? 'indirimli' : '' }}">{{ $f['etiket'] }}</div>

                                {{-- Kur normalizasyonu görünür olmalı: rozet EUR/TRY karışık
                                     listede hangi sayıya bakılarak verildiği anlaşılsın. --}}
                                @if($f['tryEtiket'])
                                    <div class="kiyas-try">{{ $f['tryEtiket'] }}</div>
                                @endif

                                @if($f['gunlukEtiket'])
                                    <div class="kiyas-gunluk">{{ $f['gunlukEtiket'] }}</div>
                                @endif

                                @if($f['enUcuz'])
                                    <span class="kiyas-rozet kiyas-rozet-yesil">EN UCUZ</span>
                                @elseif($f['farkYuzde'])
                                    <div class="kiyas-fark">En ucuza göre %{{ $f['farkYuzde'] }} fazla</div>
                                @endif
                                @if($f['enAvantajli'])
                                    <span class="kiyas-rozet kiyas-rozet-turkuaz">GÜNÜ EN UYGUN</span>
                                @endif
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach($karsilastirma['satirlar'] as $satir)
                    <tr data-ayni="{{ $satir['ayni'] ? 1 : 0 }}">
                        <th class="kiyas-etiket">{{ $satir['etiket'] }}</th>
                        @foreach($tours as $tour)
                            @php $deger = $satir['degerler'][$tour->id] ?? null; @endphp
                            <td class="{{ $satir['ayni'] ? 'kiyas-ayni' : '' }}">
                                @if($deger)
                                    <div class="kiyas-deger kiyas-metin">{!! nl2br(e($deger)) !!}</div>
                                    @if($rozet = $satir['rozetler'][$tour->id] ?? null)
                                        <span class="kiyas-rozet kiyas-rozet-notr">{{ $rozet }}</span>
                                    @endif
                                @else
                                    <span class="kiyas-yok">—</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach

                @foreach($listeler as $liste)
                    <tr class="{{ $liste['sinif'] }}">
                        <th class="kiyas-etiket">{{ $liste['etiket'] }}</th>
                        @foreach($tours as $tour)
                            @php $d = $karsilastirma[$liste['anahtar']][$tour->id] ?? null; @endphp
                            <td>
                                @if($d && count($d['maddeler']))
                                    @if($d['ozelSayisi'])
                                        <span class="kiyas-ozet">Sadece bu turda: {{ $d['ozelSayisi'] }} madde</span>
                                    @endif
                                    <ul class="kiyas-maddeler">
                                        @foreach($d['maddeler'] as $madde)
                                            <li data-ortak="{{ $madde['ortak'] ? 1 : 0 }}" class="{{ $madde['ozel'] ? 'ozel' : '' }}">
                                                <span class="kiyas-isaret">{{ $liste['isaret'] }}</span>
                                                <span>{{ $madde['metin'] }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                    @if($d['ortakSayisi'])
                                        <div class="kiyas-gizlendi">{{ $d['ortakSayisi'] }} ortak madde gizlendi</div>
                                    @endif
                                @else
                                    <span class="kiyas-yok">Belirtilmemiş</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach

                {{-- Tablo uzun: kullanıcıyı karar için başa döndürmemek gerekiyor. --}}
                <tr>
                    <th class="kiyas-etiket"></th>
                    @foreach($tours as $tour)
                        <td class="kiyas-cta">
                            <a href="{{ route('tours.show', $tour) }}" class="btn btn-primary">Turu incele</a>
                        </td>
                    @endforeach
                </tr>
            </tbody>
        </table>
    </div>

    <div class="kiyas-bos-uyari" id="kiyas-bos-uyari">
        Bu turlar karşılaştırılan tüm alanlarda aynı. Farkı görmek için anahtarı kapatıp tüm satırları inceleyebilirsin.
    </div>
</div>

<script>
(function () {
    var sarmal = document.getElementById('kiyas-sarmal');
    var tablo = document.getElementById('kiyas-tablo');
    var mini = document.getElementById('kiyas-mini');
    var miniSira = mini.querySelector('.kiyas-mini-sira');
    var miniEtiket = mini.querySelector('.kiyas-mini-etiket');
    var thead = tablo.tHead;

    /* Mini başlığın altına gireceği yükseklik nav'dan ölçülür: masaüstünde
       70px'lik şerit, mobilde farklı yükseklikte bir blok. Sabit yazmak
       iki yüzeyden birinde başlığı nav'ın altına gizlerdi. */
    function ustBosluk() {
        var nav = document.querySelector('.nav');
        if (!nav) return 0;
        var stil = window.getComputedStyle(nav);
        if (stil.position !== 'sticky' && stil.position !== 'fixed') return 0;
        return Math.round(nav.getBoundingClientRect().height);
    }

    function olculeriEsitle() {
        document.querySelector('.kiyas-sayfa').style.setProperty('--kiyas-ust', ustBosluk() + 'px');

        var basliklar = thead.rows[0].cells;
        if (!basliklar.length) return;

        miniEtiket.style.flexBasis = basliklar[0].getBoundingClientRect().width + 'px';
        for (var i = 1; i < basliklar.length; i++) {
            var hucre = miniSira.children[i - 1];
            if (hucre) hucre.style.width = basliklar[i].getBoundingClientRect().width + 'px';
        }
    }

    function miniGuncelle() {
        // Yatay konum tabloyla birebir: mini kaydırılamaz, transform ile taşınır.
        miniSira.style.transform = 'translateX(' + (-sarmal.scrollLeft) + 'px)';
        var gecti = thead.getBoundingClientRect().bottom < ustBosluk() + 8;
        mini.classList.toggle('gorunur', gecti);
    }

    sarmal.addEventListener('scroll', miniGuncelle, { passive: true });
    window.addEventListener('scroll', miniGuncelle, { passive: true });
    window.addEventListener('resize', function () { olculeriEsitle(); miniGuncelle(); });

    /* Uzun serbest metinler (konaklama, iptal koşulları) satırı devleştiriyor.
       Kırpma CSS'te; düğme sadece gerçekten taşan hücrelere basılır. */
    function kirpmalariKur() {
        tablo.querySelectorAll('.kiyas-metin').forEach(function (kutu) {
            if (kutu.scrollHeight <= kutu.clientHeight + 4) return;
            var dugme = document.createElement('button');
            dugme.type = 'button';
            dugme.className = 'kiyas-devam';
            dugme.textContent = 'Tamamını göster';
            dugme.addEventListener('click', function () {
                var acik = kutu.classList.toggle('acik');
                dugme.textContent = acik ? 'Kısalt' : 'Tamamını göster';
                miniGuncelle();
            });
            kutu.parentNode.appendChild(dugme);
        });
    }

    /* Farkları göster: tercih hatırlanır, aynı kullanıcı her karşılaştırmada
       aynı düğmeye basmasın. */
    var anahtar = document.getElementById('kiyas-farklar');
    var uyari = document.getElementById('kiyas-bos-uyari');

    function farklariUygula() {
        var acik = anahtar.checked;
        tablo.classList.toggle('farklar', acik);
        try { localStorage.setItem('kiyas_sadece_farklar', acik ? '1' : '0'); } catch (e) {}

        // Anahtar açıkken tek bir özellik satırı bile kalmadıysa boş ekran
        // yerine açıklama. Liste ve CTA satırları sayıma girmez (data-ayni yok).
        var ozellikSatirlari = tablo.querySelectorAll('tbody tr[data-ayni]');
        var gorunen = Array.prototype.filter.call(ozellikSatirlari, function (satir) {
            return satir.dataset.ayni !== '1';
        });
        uyari.classList.toggle('aktif', acik && ozellikSatirlari.length > 0 && gorunen.length === 0);
        miniGuncelle();
    }

    anahtar.addEventListener('change', farklariUygula);
    try { anahtar.checked = localStorage.getItem('kiyas_sadece_farklar') === '1'; } catch (e) {}

    document.addEventListener('DOMContentLoaded', function () {
        olculeriEsitle();
        kirpmalariKur();
        farklariUygula();
        miniGuncelle();

        // Paylaşılan bağlantıyla gelindiğinde localStorage sayfayla çelişiyordu:
        // alt bardaki sayaç başka, ekrandaki tablo başka turu gösteriyordu.
        try {
            localStorage.setItem('compared_tours', JSON.stringify(getDisplayedCompareIds()));
        } catch (e) {}
        if (typeof window.updateCompareUI === 'function') window.updateCompareUI();
    });

    // Görseller yüklendikçe kolon genişlikleri oturuyor.
    window.addEventListener('load', function () { olculeriEsitle(); miniGuncelle(); });
})();

function normalizeCompareIds(ids) {
    var uniq = new Set();
    return (ids || [])
        .map(function (id) { return parseInt(id, 10); })
        .filter(function (id) {
            if (!Number.isInteger(id) || id <= 0 || uniq.has(id)) return false;
            uniq.add(id);
            return true;
        });
}

function getStoredCompareIds() {
    try {
        return normalizeCompareIds(JSON.parse(localStorage.getItem('compared_tours') || '[]'));
    } catch (e) {
        return [];
    }
}

function getDisplayedCompareIds() {
    var kolonlar = Array.prototype.slice.call(document.querySelectorAll('[data-kiyas-tur]'));
    return normalizeCompareIds(kolonlar.map(function (kolon) {
        return kolon.getAttribute('data-kiyas-tur');
    }));
}

function getCompareIds() {
    var gosterilen = getDisplayedCompareIds();
    return gosterilen.length ? gosterilen : getStoredCompareIds();
}

function removeComparedTour(tourId) {
    var hedef = parseInt(tourId, 10);
    var kalan = getCompareIds().filter(function (id) { return id !== hedef; });

    localStorage.setItem('compared_tours', JSON.stringify(kalan));
    if (typeof window.updateCompareUI === 'function') window.updateCompareUI();

    if (kalan.length < 2) {
        alert('Karşılaştırma için en az 2 tur gerekir. Lütfen yeniden tur seç.');
        window.location.href = "{{ route('tours.index') }}";
        return;
    }

    var sorgu = kalan.map(function (id) { return 'ids[]=' + encodeURIComponent(id); }).join('&');
    window.location.href = "{{ route('tours.compare') }}" + '?' + sorgu;
}

function clearAndReturn() {
    if (!confirm('Karşılaştırma listesini temizlemek istediğinize emin misiniz?')) return;

    if (typeof window.clearCompare === 'function') {
        window.clearCompare();
    } else {
        localStorage.setItem('compared_tours', '[]');
    }
    window.location.href = "{{ route('tours.index') }}";
}
</script>
@endsection
