@extends('layouts.app')
@section('title', 'Turları Karşılaştır — ' . count($tours) . ' Tur')

@section('content')
@php
    // Dahil/hariç listeleri aynı iskeleti paylaşır, tek fark işaret ve renk.
    $listeler = [
        ['anahtar' => 'dahil', 'etiket' => 'Fiyata dahil', 'isaret' => '✓', 'sinif' => 'kiyas-arti', 'ikon' => 'onay'],
        ['anahtar' => 'haric', 'etiket' => 'Dahil değil', 'isaret' => '✗', 'sinif' => 'kiyas-eksi', 'ikon' => 'carpi'],
    ];

    /* Satır ikonları: tek çizgi setinden, 24x24 viewBox. Emoji yerine SVG —
       etiket kolonu dar ve emoji boyu platformdan platforma değişiyor. */
    $svgYollari = [
        'bina' => '<rect x="4" y="3" width="16" height="18" rx="2"/><path d="M9 8h.5M14.5 8h.5M9 12h.5M14.5 12h.5M10 21v-4h4v4"/>',
        'kategori' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'pin' => '<path d="M12 21s7-5.6 7-11a7 7 0 1 0-14 0c0 5.4 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>',
        'saat' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'otobus' => '<rect x="4" y="4" width="16" height="13" rx="2.5"/><path d="M4 11h16M8 20v-3M16 20v-3"/>',
        'takvim' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'tekrar' => '<path d="M3.5 12a8.5 8.5 0 0 1 14.2-6.3L20.5 8"/><path d="M20.5 4v4h-4"/><path d="M20.5 12a8.5 8.5 0 0 1-14.2 6.3L3.5 16"/><path d="M3.5 20v-4h4"/>',
        'yatak' => '<path d="M3 19v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6"/><path d="M3 15.5h18M7 11V8h10v3"/>',
        'harita' => '<path d="M9 4 3 6.5v13L9 17l6 2.5 6-2.5v-13L15 6.5 9 4z"/><path d="M9 4v13M15 6.5v13"/>',
        'simsek' => '<path d="M13 2.5 4.5 14H11l-1 7.5L19.5 10H13l1-7.5z"/>',
        'pasaport' => '<rect x="4.5" y="3" width="15" height="18" rx="2"/><circle cx="12" cy="10" r="3"/><path d="M8.5 17h7"/>',
        'arti' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v8M8 12h8"/>',
        'kisi' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20a7 7 0 0 1 14 0"/>',
        'geri-saat' => '<path d="M3.5 12a8.5 8.5 0 1 0 2.8-6.3"/><path d="M3.5 4v4h4"/><path d="M12 8v4.5l3 1.5"/>',
        'onay' => '<circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.5 2.5L16 9.5"/>',
        'carpi' => '<circle cx="12" cy="12" r="9"/><path d="m9 9 6 6M15 9l-6 6"/>',
        'nokta' => '<circle cx="12" cy="12" r="3"/>',
        'cop' => '<path d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2M6.5 7l.8 12.1A2 2 0 0 0 9.3 21h5.4a2 2 0 0 0 2-1.9L17.5 7"/>',
        'goz' => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
    ];

    /* Satır etiketi → ikon. TourComparison satır üretiyor; buradaki eşleşme
       yoksa nötr nokta basılır, satır ikonsuz kalmaz. */
    $satirIkonlari = [
        'Acenta' => 'bina', 'Kategori' => 'kategori', 'Destinasyon' => 'pin',
        'Süre' => 'saat', 'Ulaşım' => 'otobus', 'Kalkış yeri' => 'pin',
        'En yakın kalkış' => 'takvim', 'Hareket sıklığı' => 'tekrar',
        'Konaklama' => 'yatak', 'Program' => 'harita', 'Tempo' => 'simsek',
        'Vize' => 'pasaport', 'Ekstra turlar' => 'arti', 'Rehber' => 'kisi',
        'İptal koşulları' => 'geri-saat',
    ];

    $ikon = function (string $ad, string $sinif = 'kiyas-ikon') use ($svgYollari) {
        $yol = $svgYollari[$ad] ?? $svgYollari['nokta'];

        return '<svg class="'.$sinif.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
            .'stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$yol.'</svg>';
    };
@endphp
<style>
    .kiyas-sayfa { padding:32px 20px 56px; --etiket-g:172px; --kolon-g:278px;
        /* Neon şerit: tek yerden ayarlansın diye değişken. Çizgi kolonun
           dış hattı, ışık içeriden sızan parıltı. */
        --neon:#0d9488; --neon-cizgi:rgba(13,148,136,.7); --neon-isik:rgba(13,148,136,.42); }
    .kiyas-ust-satir { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:22px; }
    .kiyas-sayfa h1 { font-size:32px; font-weight:800; letter-spacing:-1px; color:var(--text); margin:12px 0 6px; }
    .kiyas-sayfa .kiyas-alt-baslik { color:var(--text-sec); font-size:15px; }

    /* Üst eylemler: görseldeki gibi iki eşit hap düğme. "Sadece farkları
       göster" checkbox değil basılı-kalır düğme (aria-pressed). */
    .kiyas-eylemler { display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .kiyas-eylem { display:inline-flex; align-items:center; gap:8px; cursor:pointer;
        border:1.5px solid var(--border); background:var(--white); border-radius:12px;
        padding:11px 18px; font-size:14px; font-weight:600; color:var(--text-sec); font-family:inherit; }
    .kiyas-eylem:hover { border-color:var(--neon); color:var(--accent-dark); }
    .kiyas-eylem svg { width:17px; height:17px; flex:0 0 auto; }
    .kiyas-eylem[aria-pressed="true"] { border-color:var(--neon); color:var(--accent-dark);
        background:var(--accent-bg); box-shadow:0 0 0 3px rgba(13,148,136,.12); }
    .kiyas-eylem-sil:hover { border-color:#ef4444; color:#ef4444; }

    /* Yapışkan mini başlık: tablo uzun, kolon kimliği kaybolmasın */
    .kiyas-mini { position:sticky; top:var(--kiyas-ust,70px); z-index:40; background:var(--white);
        border-bottom:1px solid var(--border); box-shadow:0 6px 14px -10px rgba(15,23,42,.4);
        opacity:0; visibility:hidden; transition:opacity .15s ease; }
    .kiyas-mini.gorunur { opacity:1; visibility:visible; }
    .kiyas-mini-ic { display:flex; align-items:stretch; }
    .kiyas-mini-etiket { flex:0 0 var(--etiket-g); }
    .kiyas-mini-kaydir { flex:1; overflow:hidden; }
    .kiyas-mini-sira { display:flex; }
    .kiyas-mini-hucre { flex:0 0 auto; padding:9px 12px; min-width:0; border-left:1px solid var(--neon-cizgi); }
    .kiyas-mini-baslik { display:block; font-size:13px; font-weight:700; color:var(--text); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .kiyas-mini-fiyat { display:block; font-size:12.5px; font-weight:700; color:var(--accent-dark); }

    /* Tablo: satır hizalaması kıyasın tamamı — kart yığını bunu veremiyordu */
    .kiyas-sarmal { overflow-x:auto; padding-bottom:8px; }
    .kiyas { border-collapse:separate; border-spacing:0; table-layout:fixed; width:100%;
        min-width:calc(var(--etiket-g) + {{ count($tours) }} * var(--kolon-g)); }
    .kiyas th, .kiyas td { vertical-align:top; text-align:left; padding:15px 18px; border-bottom:1px solid var(--border-light); }
    .kiyas tbody tr:hover td:not(.kiyas-ayni) { background:var(--accent-bg); }

    /* ——— NEON ŞERİT ———
       Her tur kolonu baştan sona ince turkuaz bir çerçeveyle sarılır. Sol/sağ
       kenarlık kolonun dış hattı; içe doğru sızan iki gölge (negatif spread,
       yatay offset) parıltıyı SADECE yanlardan verir — satır aralarında
       yatay çizgi oluşmaz, yani şerit kolon boyunca kesintisiz okunur.
       Çerçeve tabloyla aynı hücrelerde durduğu için satır hizası bozulmaz. */
    .kiyas thead th.kiyas-kolon,
    .kiyas tbody td {
        border-left:1px solid var(--neon-cizgi);
        border-right:1px solid var(--neon-cizgi);
        box-shadow:inset 6px 0 14px -8px var(--neon-isik), inset -6px 0 14px -8px var(--neon-isik);
    }
    .kiyas thead th.kiyas-kolon { border-top:1px solid var(--neon-cizgi); border-radius:18px 18px 0 0; }
    .kiyas tbody tr:last-child td { border-bottom:1px solid var(--neon-cizgi); border-radius:0 0 18px 18px; }

    /* Etiket kolonu neon dışında: kıyasın kendisi değil, okuma kılavuzu. */
    .kiyas-etiket { position:sticky; left:0; z-index:2; background:var(--white);
        font-size:13px; font-weight:600; color:var(--text-sec); border-right:1px solid var(--border); }
    .kiyas thead .kiyas-etiket { z-index:3; }
    .kiyas-etiket-ic { display:flex; align-items:center; gap:9px; }
    .kiyas-ikon { width:17px; height:17px; flex:0 0 auto; color:var(--accent-dark); opacity:.85; }

    /* "Tura ekle" yuvası: görselde kartların solundaki boş kutu. Ayrı bir
       kolon açmıyoruz — etiket kolonunun başlık hücresinde duruyor, böylece
       tablo genişliği ve mini başlık hizası aynen korunuyor. */
    .kiyas-slot-hucre { padding:14px !important; }
    .kiyas-slot { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:4px;
        width:100%; min-height:200px; padding:16px 10px; text-align:center; cursor:pointer; font-family:inherit;
        border:1.5px dashed var(--neon-cizgi); border-radius:16px; background:var(--accent-bg); }
    .kiyas-slot:hover { border-color:var(--neon); background:rgba(13,148,136,.1); }
    .kiyas-slot-arti { width:38px; height:38px; border-radius:999px; border:1.5px solid var(--neon-cizgi);
        display:flex; align-items:center; justify-content:center; font-size:22px; line-height:1;
        color:var(--accent-dark); background:var(--white); margin-bottom:4px; }
    .kiyas-slot-baslik { font-size:14px; font-weight:700; color:var(--text); }
    .kiyas-slot-alt { font-size:12px; color:var(--text-muted); line-height:1.4; }
    .kiyas-slot-dolu .kiyas-slot-alt.uyari { color:#b45309; font-weight:600; }
    .kiyas-slot.sarsil { animation:kiyasSarsil .4s ease; }
    @keyframes kiyasSarsil { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }

    /* Başlık hücresi = tur kimliği */
    .kiyas-kolon { background:var(--white); padding:0 !important; position:relative; z-index:0; }
    .kiyas-gorsel-sarmal { position:relative; }
    .kiyas-gorsel { width:100%; height:150px; object-fit:cover; display:block; border-radius:17px 17px 0 0; }
    .kiyas-gorsel-bos { width:100%; height:150px; background:linear-gradient(135deg,#e0f2fe,#f0fdf4);
        display:flex; align-items:center; justify-content:center; font-size:40px; border-radius:17px 17px 0 0; }
    .kiyas-kolon-govde { padding:16px 18px 4px; }
    .kiyas-kolon-govde h2 { font-size:16px; font-weight:800; line-height:1.4; margin:0 0 8px;
        display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .kiyas-kolon-govde h2 a { color:var(--text); }
    .kiyas-kolon-govde h2 a:hover { color:var(--accent-dark); }

    .kiyas-kart-satir { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:10px; }
    .kiyas-acenta { font-size:12.5px; color:var(--text-muted); min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .kiyas-puan { display:inline-flex; align-items:center; gap:4px; flex:0 0 auto; font-size:12.5px; font-weight:800; color:var(--text); }
    .kiyas-puan svg { width:13px; height:13px; color:#f5b301; }
    .kiyas-puan i { font-style:normal; font-weight:600; color:var(--text-muted); }

    .kiyas-chipler { display:flex; flex-wrap:wrap; gap:6px 12px; margin-bottom:12px; }
    .kiyas-chip { display:inline-flex; align-items:center; gap:5px; font-size:12.5px; color:var(--text-sec); }
    .kiyas-chip svg { width:15px; height:15px; color:var(--text-muted); }

    /* Görseldeki köşe rozeti: kampanya varsa indirim oranı, yoksa en ucuz. */
    .kiyas-kose-rozet { position:absolute; top:12px; left:0; z-index:1; padding:6px 11px 6px 12px;
        border-radius:0 10px 10px 0; background:var(--green); color:#fff;
        font-size:11px; font-weight:800; letter-spacing:.3px; box-shadow:0 4px 10px -4px rgba(5,150,105,.6); }
    .kiyas-cikar { position:absolute; top:10px; right:10px; z-index:1; width:32px; height:32px; border:none; border-radius:999px;
        background:rgba(255,255,255,.92); color:var(--text-sec); font-size:18px; line-height:1; cursor:pointer;
        display:flex; align-items:center; justify-content:center; box-shadow:0 4px 10px -3px rgba(15,23,42,.35); }
    .kiyas-cikar:hover { background:#b91c1c; color:#fff; }

    /* Fiyat kutusu */
    .kiyas-fiyat-blok { border-top:1px solid var(--border-light); padding-top:12px; }
    .kiyas-fiyat { font-size:25px; font-weight:800; letter-spacing:-.6px; color:var(--accent-dark); line-height:1.2; }
    .kiyas-fiyat small { font-size:12.5px; font-weight:600; color:var(--text-muted); letter-spacing:0; margin-left:4px; }
    .kiyas-eski-satir { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:5px; }
    .kiyas-eski { text-decoration:line-through; color:var(--text-muted); font-size:13px; }
    .kiyas-indirim-chip { font-size:11.5px; font-weight:700; padding:3px 8px; border-radius:7px;
        background:var(--green-bg); color:var(--green-text); }
    .kiyas-try { font-size:12px; color:var(--text-muted); margin-top:4px; }
    .kiyas-gunluk { font-size:12.5px; color:var(--text-sec); margin-top:6px; }
    .kiyas-fark { font-size:12.5px; font-weight:700; color:#b45309; margin-top:6px; }

    .kiyas-rozet { display:inline-block; font-size:10.5px; font-weight:800; letter-spacing:.3px;
        padding:4px 9px; border-radius:8px; margin-top:8px; margin-right:5px; }
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

    .kiyas-cta { padding:18px !important; }
    .kiyas-cta .btn { width:100%; }
    .kiyas-bos-uyari { background:var(--accent-bg); border:1px solid var(--accent-light); border-radius:12px;
        padding:14px 16px; font-size:14px; color:var(--text-sec); margin-top:18px; display:none; }
    .kiyas-bos-uyari.aktif { display:block; }

    .kiyas-guven { display:flex; align-items:center; justify-content:center; gap:7px;
        margin-top:22px; font-size:13px; color:var(--text-muted); }

    @media(max-width:768px) {
        .kiyas-sayfa { padding:20px 16px 40px; --etiket-g:120px; --kolon-g:232px; }
        .kiyas-sayfa h1 { font-size:23px; }
        .kiyas-sayfa .kiyas-alt-baslik { font-size:13.5px; }
        .kiyas-eylem { padding:9px 13px; font-size:13px; }
        .kiyas th, .kiyas td { padding:12px 13px; }
        .kiyas-etiket { font-size:12px; padding:12px 11px; }
        .kiyas-ikon { width:15px; height:15px; }
        .kiyas-slot-hucre { padding:10px !important; }
        .kiyas-slot { min-height:150px; }
        .kiyas-gorsel, .kiyas-gorsel-bos { height:118px; }
        .kiyas-kolon-govde { padding:12px 13px 4px; }
        .kiyas-fiyat { font-size:20px; }
        .kiyas-deger, .kiyas-maddeler { font-size:13px; }
    }
</style>

<div class="container kiyas-sayfa">
    <div class="kiyas-ust-satir">
        <div>
            <a href="{{ route('tours.index') }}" class="btn btn-outline btn-sm">← Turlara dön</a>
            <h1>Turları Karşılaştır</h1>
            <p class="kiyas-alt-baslik">Beğendiğin turları yan yana karşılaştır ve sana en uygun olanı seç.</p>
        </div>
        <div class="kiyas-eylemler">
            <button type="button" class="kiyas-eylem kiyas-eylem-sil" onclick="clearAndReturn()">
                {!! $ikon('cop', 'kiyas-eylem-ikon') !!} Temizle
            </button>
            <button type="button" class="kiyas-eylem" id="kiyas-farklar" aria-pressed="false">
                {!! $ikon('goz', 'kiyas-eylem-ikon') !!} Sadece farkları göster
            </button>
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
                    <th class="kiyas-etiket kiyas-slot-hucre">
                        @if(count($tours) < 3)
                            <a href="{{ route('home') }}" class="kiyas-slot">
                                <span class="kiyas-slot-arti">+</span>
                                <span class="kiyas-slot-baslik">Tura ekle</span>
                                <span class="kiyas-slot-alt">Maksimum 3 tur</span>
                            </a>
                        @else
                            {{-- Kolon sınırı dolu: kutu kalıyor ama tıklayınca ekleme
                                 yerine sınırı söylüyor (bkz. TourController limiti). --}}
                            <button type="button" class="kiyas-slot kiyas-slot-dolu" id="kiyas-slot-dolu" onclick="kiyasSlotUyar()">
                                <span class="kiyas-slot-arti">+</span>
                                <span class="kiyas-slot-baslik">Tura ekle</span>
                                <span class="kiyas-slot-alt" id="kiyas-slot-alt">Maksimum 3 tur</span>
                            </button>
                        @endif
                    </th>
                    @foreach($tours as $tour)
                        @php $f = $karsilastirma['fiyatlar'][$tour->id]; @endphp
                        <th class="kiyas-kolon" data-kiyas-tur="{{ $tour->id }}">
                            <div class="kiyas-gorsel-sarmal">
                                @if($f['indirimYuzde'])
                                    <span class="kiyas-kose-rozet">%{{ $f['indirimYuzde'] }} İNDİRİM</span>
                                @elseif($f['enUcuz'])
                                    <span class="kiyas-kose-rozet">EN UCUZ</span>
                                @endif

                                <button type="button" class="kiyas-cikar" title="Bu turu karşılaştırmadan çıkar"
                                        onclick="removeComparedTour({{ $tour->id }})">×</button>

                                @if($tour->image)
                                    <img class="kiyas-gorsel" src="{{ $tour->image }}" alt="{{ $tour->title }}" loading="lazy">
                                @else
                                    <div class="kiyas-gorsel-bos">🏖️</div>
                                @endif
                            </div>

                            <div class="kiyas-kolon-govde">
                                <h2><a href="{{ route('tours.show', $tour) }}">{{ $tour->title }}</a></h2>

                                <div class="kiyas-kart-satir">
                                    <span class="kiyas-acenta">{{ optional($tour->agency)->name }}</span>
                                    {{-- Yorum yoksa boş yıldız basılmaz: sıfır puan, puansızlıkla aynı şey değil. --}}
                                    @if(($tour->reviews_count ?? 0) > 0)
                                        <span class="kiyas-puan">
                                            <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="m12 3.6 2.6 5.4 5.9.8-4.3 4.2 1 5.9-5.2-2.8-5.2 2.8 1-5.9L3.5 9.8l5.9-.8z"/></svg>
                                            {{ number_format((float) $tour->reviews_avg_rating, 1, ',', '') }}
                                            <i>({{ $tour->reviews_count }})</i>
                                        </span>
                                    @endif
                                </div>

                                <div class="kiyas-chipler">
                                    @if($tour->transport_label)
                                        <span class="kiyas-chip">{!! $ikon('otobus', 'kiyas-chip-ikon') !!} {{ $tour->transport_label }}</span>
                                    @endif
                                    @if($tour->duration_label)
                                        <span class="kiyas-chip">{!! $ikon('saat', 'kiyas-chip-ikon') !!} {{ $tour->duration_label }}</span>
                                    @endif
                                </div>

                                <div class="kiyas-fiyat-blok">
                                    <div class="kiyas-fiyat">{{ $f['etiket'] }}<small>/ kişi başı</small></div>

                                    @if($f['eskiEtiket'])
                                        <div class="kiyas-eski-satir">
                                            <span class="kiyas-eski">{{ $f['eskiEtiket'] }}</span>
                                            @if($f['indirimYuzde'])
                                                <span class="kiyas-indirim-chip">%{{ $f['indirimYuzde'] }} indirim</span>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Kur normalizasyonu görünür olmalı: rozet EUR/TRY karışık
                                         listede hangi sayıya bakılarak verildiği anlaşılsın. --}}
                                    @if($f['tryEtiket'])
                                        <div class="kiyas-try">{{ $f['tryEtiket'] }}</div>
                                    @endif

                                    @if($f['gunlukEtiket'])
                                        <div class="kiyas-gunluk">{{ $f['gunlukEtiket'] }}</div>
                                    @endif

                                    @if($f['enUcuz'])
                                        <span class="kiyas-rozet kiyas-rozet-yesil">EN UYGUN FİYAT</span>
                                    @elseif($f['farkYuzde'])
                                        <div class="kiyas-fark">En ucuza göre %{{ $f['farkYuzde'] }} fazla</div>
                                    @endif
                                    @if($f['enAvantajli'])
                                        <span class="kiyas-rozet kiyas-rozet-turkuaz">GÜNÜ EN UYGUN</span>
                                    @endif
                                </div>
                            </div>
                        </th>
                    @endforeach
                </tr>
            </thead>

            <tbody>
                @foreach($karsilastirma['satirlar'] as $satir)
                    <tr data-ayni="{{ $satir['ayni'] ? 1 : 0 }}">
                        <th class="kiyas-etiket">
                            <span class="kiyas-etiket-ic">
                                {!! $ikon($satirIkonlari[$satir['etiket']] ?? 'nokta') !!}
                                <span>{{ $satir['etiket'] }}</span>
                            </span>
                        </th>
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
                        <th class="kiyas-etiket">
                            <span class="kiyas-etiket-ic">
                                {!! $ikon($liste['ikon']) !!}
                                <span>{{ $liste['etiket'] }}</span>
                            </span>
                        </th>
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
        Bu turlar karşılaştırılan tüm alanlarda aynı. Farkı görmek için düğmeyi kapatıp tüm satırları inceleyebilirsin.
    </div>

    <div class="kiyas-guven">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
        256-bit SSL ile güvenli bağlantı
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

    /* Farkları göster: basılı-kalır düğme (aria-pressed). Tercih hatırlanır,
       aynı kullanıcı her karşılaştırmada aynı düğmeye basmasın. */
    var anahtar = document.getElementById('kiyas-farklar');
    var uyari = document.getElementById('kiyas-bos-uyari');

    function acikMi() {
        return anahtar.getAttribute('aria-pressed') === 'true';
    }

    function farklariUygula() {
        var acik = acikMi();
        tablo.classList.toggle('farklar', acik);
        try { localStorage.setItem('kiyas_sadece_farklar', acik ? '1' : '0'); } catch (e) {}

        // Düğme açıkken tek bir özellik satırı bile kalmadıysa boş ekran
        // yerine açıklama. Liste ve CTA satırları sayıma girmez (data-ayni yok).
        var ozellikSatirlari = tablo.querySelectorAll('tbody tr[data-ayni]');
        var gorunen = Array.prototype.filter.call(ozellikSatirlari, function (satir) {
            return satir.dataset.ayni !== '1';
        });
        uyari.classList.toggle('aktif', acik && ozellikSatirlari.length > 0 && gorunen.length === 0);
        miniGuncelle();
    }

    anahtar.addEventListener('click', function () {
        anahtar.setAttribute('aria-pressed', acikMi() ? 'false' : 'true');
        farklariUygula();
    });

    try {
        if (localStorage.getItem('kiyas_sadece_farklar') === '1') anahtar.setAttribute('aria-pressed', 'true');
    } catch (e) {}

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

/* Kolon sınırı dolu. Alert yerine kutunun kendi içinde uyarı: kullanıcı
   zaten oraya bakıyor, modal kapatma adımı eklemek gereksiz. */
function kiyasSlotUyar() {
    var kutu = document.getElementById('kiyas-slot-dolu');
    var alt = document.getElementById('kiyas-slot-alt');
    if (!kutu || !alt) return;

    alt.textContent = '3 turdan fazlası karşılaştırılamaz';
    alt.classList.add('uyari');
    kutu.classList.remove('sarsil');
    void kutu.offsetWidth; // animasyonu yeniden tetiklemek için reflow
    kutu.classList.add('sarsil');
}

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
