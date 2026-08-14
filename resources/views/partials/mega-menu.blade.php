{{--
    Ana sayfa mega menüsü (malitur kalıbı). Filtre barının YERİNE değil ALTINA
    gelir — hero'nun dalgasından sonra, güven şeridinin üstünde (bkz. home.blade
    içindeki .mega-wrap). Menü gezinme (ne aradığını bilmeyen kullanıcı + SEO),
    filtre daraltma (ne aradığını bilen kullanıcı) işi görür; ikisi de kalıyor.

    İçerik App\Support\MegaMenu'den, envanterden türer — turu olmayan başlık
    menüye hiç girmez. Boş kova varsa kova da basılmaz.

    Mobilde gizli: ≤768px'de sayfanın kendi m-home bloğu devrede.
--}}
@php $megaKovalar = \App\Support\MegaMenu::build(); @endphp

@if(! empty($megaKovalar))
<nav class="mega" aria-label="Tur kategorileri">
    <ul class="mega-row">
        @foreach($megaKovalar as $kova)
            <li class="mega-item">
                <button type="button" class="mega-trigger" aria-expanded="false" aria-controls="mega-{{ $kova['key'] }}">
                    {{ $kova['label'] }}
                    <span class="mega-count">{{ $kova['count'] }}</span>
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>

                <div class="mega-panel" id="mega-{{ $kova['key'] }}" hidden>
                    <div class="mega-cols">
                        @foreach($kova['columns'] as $sutun)
                            <div class="mega-col">
                                <h4>{{ $sutun['title'] }}</h4>
                                <ul>
                                    @foreach($sutun['links'] as $link)
                                        <li>
                                            <a href="{{ $link['url'] }}">
                                                <span>{{ $link['label'] }}</span>
                                                <em>{{ $link['count'] }}</em>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                    <a class="mega-all" href="{{ route('tours.index') }}">Tüm turları gör →</a>
                </div>
            </li>
        @endforeach
    </ul>
</nav>

<style>
.mega { display:block; margin-bottom:10px; }
.mega-row { list-style:none; margin:0; padding:0 4px; display:flex; flex-wrap:wrap; gap:4px; }
/* Ana sayfada (.mega-wrap içinde) şerit ortalanır ve filtre haplarıyla aynı
   dili konuşsun diye düğmeler yuvarlak + biraz iri olur. */
.mega-wrap .mega { margin-bottom:0; }
.mega-wrap .mega-row { justify-content:center; gap:6px; }
.mega-wrap .mega-trigger { font-size:15px; padding:11px 18px; border-radius:100px; }
.mega-item { position:relative; }
.mega-trigger {
    display:inline-flex; align-items:center; gap:7px; border:none; background:transparent;
    font-family:inherit; font-size:14px; font-weight:700; color:#0f172a; cursor:pointer;
    padding:10px 14px; border-radius:10px; transition:background .15s;
}
.mega-trigger:hover, .mega-item.open .mega-trigger { background:var(--accent-bg); color:var(--accent-dark); }
.mega-trigger:focus-visible { outline:2px solid var(--accent); outline-offset:-2px; }
.mega-item.open .mega-trigger svg { transform:rotate(180deg); }
.mega-trigger svg { transition:transform .15s; opacity:.55; }
.mega-count {
    font-size:11px; font-weight:800; color:var(--accent-dark); background:var(--accent-light);
    border-radius:100px; padding:1px 7px; font-variant-numeric:tabular-nums;
}
.mega-panel {
    position:absolute; top:calc(100% + 6px); left:0; z-index:60; min-width:520px;
    background:#fff; border:1px solid var(--border); border-radius:16px;
    box-shadow:0 24px 48px -18px rgba(15,23,42,.22); padding:20px 22px 16px;
}
.mega-cols { display:flex; gap:32px; }
.mega-col { min-width:210px; }
.mega-col h4 {
    margin:0 0 10px; font-size:11px; font-weight:800; letter-spacing:.08em;
    text-transform:uppercase; color:#94a3b8;
}
.mega-col ul { list-style:none; margin:0; padding:0; }
.mega-col li + li { margin-top:2px; }
.mega-col a {
    display:flex; align-items:center; justify-content:space-between; gap:14px;
    padding:7px 10px; border-radius:8px; font-size:14px; color:#334155;
    text-decoration:none; transition:background .12s;
}
.mega-col a:hover { background:#f8fafc; color:var(--accent-dark); }
.mega-col a em { font-style:normal; font-size:12px; color:#94a3b8; font-variant-numeric:tabular-nums; }
.mega-all {
    display:inline-block; margin-top:14px; padding-top:12px; border-top:1px solid var(--border-light);
    width:100%; font-size:13px; font-weight:700; color:var(--accent); text-decoration:none;
}
@media (max-width:768px) { .mega { display:none; } }
</style>

<script>
(function () {
    const kok = document.querySelector('.mega');
    if (!kok) return;

    function kapat(item) {
        item.classList.remove('open');
        item.querySelector('.mega-trigger').setAttribute('aria-expanded', 'false');
        item.querySelector('.mega-panel').hidden = true;
    }

    function hepsiniKapat(haric) {
        kok.querySelectorAll('.mega-item.open').forEach(function (i) { if (i !== haric) kapat(i); });
    }

    kok.addEventListener('click', function (e) {
        const trigger = e.target.closest('.mega-trigger');
        if (!trigger) return;
        const item = trigger.closest('.mega-item');
        const acik = item.classList.contains('open');
        hepsiniKapat(item);
        if (acik) { kapat(item); return; }
        item.classList.add('open');
        trigger.setAttribute('aria-expanded', 'true');
        item.querySelector('.mega-panel').hidden = false;
    });

    document.addEventListener('click', function (e) { if (!e.target.closest('.mega')) hepsiniKapat(null); });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        const acik = kok.querySelector('.mega-item.open');
        if (acik) { kapat(acik); acik.querySelector('.mega-trigger').focus(); }
    });
})();
</script>
@endif
