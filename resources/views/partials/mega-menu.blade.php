{{--
    Ana sayfa kategori ağacı (mega menü), İKİ KATMAN — yönetim panelindeki
    ağacın kendisi (2026-09-25 tasarımı):

        kapalı: beyaz kart şerit, her üst kategori = ikon kutusu + ad + ok
        açık:   şeridin altında panel = başlık + açıklama + "Tümünü gör · N tur",
                alt kategori listesi (sayaç rozetli) ve sağda üst kategorinin
                kart görseli ("Rota ilhamın").

    Fareyle üzerine gelince açılır (pointerType=mouse), tıklama aç/kapa yapar
    (dokunmatik + klavye), Escape ve dışarı tıklama kapatır. Tek panel açık kalır.
    JS yoksa .no-js ile hover üzerinden açılır. İçerik App\Support\MegaMenu'den.

    Mobilde gizli: ≤768px'de sayfanın kendi m-home bloğu devrede.
--}}
@php $megaAgac = \App\Support\MegaMenu::build(); @endphp

@if(! empty($megaAgac))
<nav class="mega no-js" aria-label="Tur kategorileri">
    <ul class="mega-row">
        @foreach($megaAgac as $ust)
            @php $altSayisi = count($ust['children']); @endphp
            <li class="mega-item">
                {{-- Şeritteki başlık paneli açar; kategorinin sayfasına panel içindeki
                     "Tümünü gör" ve görsel kart götürür. --}}
                <button type="button" class="mega-trigger" aria-haspopup="true" aria-expanded="false" aria-controls="mega-panel-{{ $ust['key'] }}">
                    <span class="mega-tile" aria-hidden="true">{{ $ust['icon'] ?: '🧭' }}</span>
                    <span class="mega-trigger-name">{{ $ust['name'] }}</span>
                    <svg class="mega-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>

                <div class="mega-panel" id="mega-panel-{{ $ust['key'] }}">
                    <div class="mega-head">
                        <div class="mega-head-text">
                            <h3>{{ $ust['name'] }}</h3>
                            <p>
                                @if($ust['description'])
                                    {{ $ust['description'] }}
                                @elseif($altSayisi > 0)
                                    {{ $altSayisi }} alt kategori{{ $ust['count'] > 0 ? ' · '.$ust['count'].' tur' : '' }}
                                @else
                                    Bu başlıktaki tüm turlar.
                                @endif
                            </p>
                        </div>
                        {{-- Sayaç tek echo ifadesinde: kelimeye bitişik direktif (gör + if) derlenmez, sayfaya ham basılır. --}}
                        <a class="mega-all" href="{{ $ust['url'] }}">
                            Tümünü gör{{ $ust['count'] > 0 ? ' · '.$ust['count'].' tur' : '' }}
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg>
                        </a>
                    </div>

                    <div class="mega-body">
                        <div class="mega-list">
                            @if($altSayisi === 0)
                                <p class="mega-empty">Bu başlıkta henüz alt kırılım yok. <a href="{{ $ust['url'] }}">Tüm turlara göz at →</a></p>
                            @else
                                <span class="mega-eyebrow">Alt kategoriler</span>
                                <ul class="mega-links {{ $altSayisi >= 14 ? 'mega-links-3' : ($altSayisi > 5 ? 'mega-links-2' : '') }}">
                                    @foreach($ust['children'] as $alt)
                                        <li>
                                            <a href="{{ $alt['url'] }}">
                                                <span class="mega-link-name">{{ $alt['name'] }}</span>
                                                @if($alt['count'] > 0)<em class="mega-count">{{ $alt['count'] }}</em>@endif
                                                <svg class="mega-link-caret" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 6 15 12 9 18"></polyline></svg>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </div>

                        {{-- Üst kategorinin kart görseli (admin › Üst Kategori › "Kart görseli").
                             Görsel yoksa turkuaz zemin + ikon. --}}
                        <a class="mega-card {{ $ust['image'] ? '' : 'mega-card-bos' }}" href="{{ $ust['url'] }}" @if($ust['image']) style="background-image:url('{{ $ust['image'] }}');" @endif>
                            <span class="mega-card-tag">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                                Rota ilhamın
                            </span>
                            @unless($ust['image'])<span class="mega-card-ikon" aria-hidden="true">{{ $ust['icon'] }}</span>@endunless
                            <span class="mega-card-body">
                                <strong>{{ $ust['name'] }}</strong>
                                <span>Turları incele <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"></line><polyline points="12 5 19 12 12 19"></polyline></svg></span>
                            </span>
                        </a>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</nav>

<style>
/* ── Kapalı görünüm: beyaz kart şerit ── */
.mega { position:relative; }
.mega-row {
    list-style:none; margin:0; padding:6px; display:flex; flex-wrap:wrap;
    background:#fff; border:1px solid var(--border-light); border-radius:18px;
    box-shadow:0 14px 36px -20px rgba(15,23,42,.28), 0 1px 2px rgba(15,23,42,.04);
}
/* Panel .mega'ya göre konumlanır: öğe static kalmalı. */
.mega-item { position:static; flex:1 1 220px; min-width:0; display:flex; }
.mega-item + .mega-item { border-left:1px solid var(--border-light); }

.mega-trigger {
    flex:1; min-width:0; display:flex; align-items:center; gap:13px; margin:2px; padding:10px 14px;
    border:none; border-radius:14px; background:transparent; font-family:inherit; font-size:16.5px;
    font-weight:700; color:#0f172a; cursor:pointer; text-align:left; transition:background .15s, color .15s;
    -webkit-appearance:none; appearance:none;
}
.mega-trigger:hover, .mega-item.open .mega-trigger { background:rgba(13,148,136,.09); color:var(--accent-ink); }
.mega-trigger:focus-visible { outline:2px solid var(--accent); outline-offset:-2px; }
.mega-tile {
    width:46px; height:46px; flex:none; display:flex; align-items:center; justify-content:center;
    border-radius:13px; background:#e3f5f1; font-size:22px; line-height:1;
}
.mega-trigger-name { flex:1; min-width:0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.mega-caret { flex:none; color:#64748b; transition:transform .18s, color .15s; }
.mega-item.open .mega-caret { transform:rotate(180deg); color:var(--accent-ink); }

/* ── Açık görünüm: şeridin altındaki panel ── */
.mega-panel {
    position:absolute; top:calc(100% + 12px); left:0; right:0; z-index:60; display:none;
    background:#fff; border:1px solid var(--border-light); border-radius:18px; padding:18px 24px 22px;
    /* Emniyet: çok alt kategorili başlıkta panel ekrana sığmazsa kendi içinde kayar,
       sayfadan taşmaz (300px ≈ üst menü + şerit + boşluk). Kompaktlaştırma 2026-09-26:
       canlıda 22 alt kategorili panel ~715px'ti, dizüstü ekranına sığmıyordu. */
    max-height:max(280px, calc(100vh - 300px)); overflow-y:auto;   /* 280px taban: yatay telefon / yarım pencerede sıfıra inmesin */
    box-shadow:0 34px 64px -26px rgba(15,23,42,.32), 0 2px 6px rgba(15,23,42,.05);
}
.mega-item.open .mega-panel { display:block; }
.mega.no-js .mega-item:hover .mega-panel { display:block; }

.mega-head { display:flex; align-items:flex-start; justify-content:space-between; gap:24px; padding-bottom:12px; border-bottom:1px solid var(--border-light); }
.mega-head-text { min-width:0; }
.mega-head h3 { margin:0; font-size:21px; font-weight:800; letter-spacing:-.5px; color:#0f172a; line-height:1.15; }
.mega-head p { margin:3px 0 0; font-size:13.5px; color:var(--text-meta); }
.mega-all { display:inline-flex; align-items:center; gap:8px; margin-top:4px; font-size:14px; font-weight:700; color:var(--accent-ink); text-decoration:none; white-space:nowrap; transition:color .15s; }
.mega-all:hover { color:var(--accent-deep); }

.mega-body { display:grid; grid-template-columns:minmax(0,1fr) 26%; gap:24px; padding-top:14px; }
.mega-eyebrow { display:block; margin:0 0 6px 8px; font-size:11.5px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:var(--text-meta); }
.mega-links { list-style:none; margin:0; padding:0; }
.mega-links-2 { columns:2; column-gap:32px; }
/* 14+ alt kategoride 3 sütun: 22 kategori 11 yerine 8 satıra iner (kaydırmasız görünüm). */
.mega-links-3 { columns:3; column-gap:28px; }
.mega-links li { break-inside:avoid; border-bottom:1px solid var(--border-light); }
.mega-links a {
    display:flex; align-items:center; gap:8px; padding:7px 10px; border-radius:10px;
    font-size:14.5px; font-weight:500; color:#1e293b; text-decoration:none; transition:background .12s, color .12s;
}
.mega-links a:hover { background:rgba(13,148,136,.09); color:var(--accent-ink); font-weight:700; }
.mega-link-name { flex:1; min-width:0; }
.mega-count {
    font-style:normal; font-size:12px; font-weight:800; color:var(--accent-ink); background:var(--accent-light);
    border-radius:100px; min-width:24px; text-align:center; padding:2px 7px; font-variant-numeric:tabular-nums;
}
.mega-link-caret { flex:none; color:#94a3b8; transition:color .12s; }
.mega-links a:hover .mega-link-caret { color:var(--accent-ink); }
.mega-empty { margin:0; font-size:14px; color:var(--text-meta); }
.mega-empty a { color:var(--accent-ink); font-weight:700; text-decoration:none; }

/* Sağdaki görsel kart */
.mega-card {
    position:relative; display:flex; flex-direction:column; justify-content:flex-end; min-height:240px;
    border-radius:16px; overflow:hidden; background:#0f766e center/cover no-repeat; color:#fff; text-decoration:none;
    transition:transform .2s;
}
.mega-card:hover { transform:translateY(-2px); }
.mega-card::after { content:""; position:absolute; inset:0; background:linear-gradient(180deg, rgba(15,23,42,0) 38%, rgba(15,23,42,.74) 100%); }
.mega-card-bos { background-image:linear-gradient(135deg, #0d9488 0%, #115e59 100%); }
.mega-card-ikon { position:absolute; inset:0; display:flex; align-items:center; justify-content:center; font-size:110px; line-height:1; opacity:.32; }
.mega-card-tag {
    position:absolute; top:16px; left:16px; z-index:2; display:inline-flex; align-items:center; gap:6px; padding:7px 13px;
    border-radius:100px; background:rgba(255,255,255,.93); color:var(--accent-ink); font-size:11.5px; font-weight:800;
    letter-spacing:.08em; text-transform:uppercase;
}
.mega-card-body { position:relative; z-index:2; padding:18px; }
.mega-card-body strong { display:block; font-size:20px; font-weight:800; letter-spacing:-.4px; line-height:1.15; }
.mega-card-body > span { display:inline-flex; align-items:center; gap:6px; margin-top:7px; font-size:14.5px; font-weight:600; }

@media (max-width:1100px) {
    .mega-body { grid-template-columns:1fr; }
    .mega-links-2, .mega-links-3 { columns:1; }
    .mega-card { min-height:220px; }
}
@media (max-width:768px) { .mega { display:none; } }
</style>

<script>
(function () {
    const kok = document.querySelector('.mega');
    if (!kok) return;
    kok.classList.remove('no-js');

    const ogeler = Array.from(kok.querySelectorAll('.mega-item'));
    let kapatZamani = null;

    function ac(secili) {
        ogeler.forEach(oge => {
            const acik = oge === secili;
            oge.classList.toggle('open', acik);
            oge.querySelector('.mega-trigger').setAttribute('aria-expanded', acik ? 'true' : 'false');
        });
    }
    function kapat() { ac(null); }

    ogeler.forEach(oge => {
        const tetik = oge.querySelector('.mega-trigger');

        // Fare: üzerine gelince açılır, ayrılınca kısa gecikmeyle kapanır (şerit →
        // panel arasındaki boşluğu geçerken titremesin). Dokunmatikte pointerenter
        // da tetiklenir ve ardından gelen click hemen kapatırdı: yalnız fare.
        oge.addEventListener('pointerenter', e => {
            if (e.pointerType !== 'mouse') return;
            clearTimeout(kapatZamani);
            ac(oge);
        });
        oge.addEventListener('pointerleave', e => {
            if (e.pointerType !== 'mouse') return;
            kapatZamani = setTimeout(kapat, 140);
        });

        // Dokunmatik + klavye: tıklama aç/kapa.
        tetik.addEventListener('click', () => {
            clearTimeout(kapatZamani);
            oge.classList.contains('open') ? kapat() : ac(oge);
        });
    });

    document.addEventListener('keydown', e => { if (e.key === 'Escape') kapat(); });
    document.addEventListener('click', e => { if (!kok.contains(e.target)) kapat(); });
})();
</script>
@endif
