{{--
    Ana sayfa mega menüsü (malitur kalıbı), ÜÇ KATMAN:

        üst şerit (kova) → sol ray (ana kategori) → orta sütun (alt kategoriler)

    Panel hover ile açılır; sol raydaki seçim de hover ile değişir ve menüden
    çıkınca ilk raya sıfırlanır. İçerik App\Support\MegaMenu'den gelir.

    Mobilde gizli: ≤768px'de sayfanın kendi m-home bloğu devrede.
--}}
@php $megaKovalar = \App\Support\MegaMenu::build(); @endphp

@if(! empty($megaKovalar))
<nav class="mega" aria-label="Tur kategorileri">
    <ul class="mega-row">
        @foreach($megaKovalar as $kova)
            <li class="mega-item">
                {{-- Kovanın kendi sayfası yok (kategori değil, gruplama):
                     tetikleyici link değil düğme. --}}
                <button type="button" class="mega-trigger" aria-haspopup="true" aria-expanded="false">
                    @if($kova['icon'])<span class="mega-ico" aria-hidden="true">{{ $kova['icon'] }}</span>@endif
                    {{ $kova['label'] }}
                    <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><polyline points="6 9 12 15 18 9"></polyline></svg>
                </button>

                <div class="mega-panel">
                    {{-- Sol ray: ana kategoriler. Tek dallı kovada (kovaya
                         yazılmamış, kendi başlığıyla açılan ana kategori) ray
                         tek kartlık bir tekrar olurdu: atlanır, ağaç panelin
                         tamamını kullanır. --}}
                    @if(count($kova['rail']) > 1)
                    <div class="mega-rail">
                        @foreach($kova['rail'] as $i => $dal)
                            <a class="mega-card {{ $i === 0 ? 'on' : '' }}"
                               href="{{ $dal['url'] }}"
                               data-show="mega-{{ $kova['key'] }}-{{ $dal['key'] }}">
                                @if($dal['icon'])<span class="mega-card-ico" aria-hidden="true">{{ $dal['icon'] }}</span>@endif
                                <span class="mega-card-name">{{ $dal['name'] }}</span>
                                @if($dal['count'] > 0)<em class="mega-card-count">{{ $dal['count'] }}</em>@endif
                                <svg class="mega-card-caret" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" aria-hidden="true"><polyline points="9 6 15 12 9 18"></polyline></svg>
                            </a>
                        @endforeach
                    </div>
                    @endif

                    {{-- Orta sütun: seçili dalın alt kategorileri --}}
                    <div class="mega-content">
                        @foreach($kova['rail'] as $i => $dal)
                            <div class="mega-pane {{ $i === 0 ? 'on' : '' }}" id="mega-{{ $kova['key'] }}-{{ $dal['key'] }}">
                                <h4>{{ $dal['name'] }}</h4>
                                @if(empty($dal['links']))
                                    <p class="mega-empty">Bu başlıkta henüz alt kırılım yok.</p>
                                @else
                                    <ul>
                                        @foreach($dal['links'] as $link)
                                            <li>
                                                <a href="{{ $link['url'] }}">
                                                    @if($link['icon'])<i class="mega-ico" aria-hidden="true">{{ $link['icon'] }}</i>@endif
                                                    <span>{{ $link['label'] }}</span>
                                                    @if($link['count'] > 0)<em>{{ $link['count'] }}</em>@endif
                                                </a>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
</nav>

<style>
.mega { display:block; }
.mega-row { list-style:none; margin:0; padding:0; display:flex; flex-wrap:wrap; justify-content:center; gap:4px; }
.mega-item { position:static; }

.mega-trigger {
    display:inline-flex; align-items:center; gap:8px; border:none; background:transparent;
    font-family:inherit; font-size:15px; font-weight:700; color:#0f172a; cursor:pointer;
    padding:12px 18px; border-radius:12px 12px 0 0; text-decoration:none; transition:background .15s, color .15s;
    -webkit-appearance:none; appearance:none;
}
.mega-trigger svg { transition:transform .15s; opacity:.55; }
.mega-item:hover .mega-trigger { background:#fff; color:var(--accent-dark); box-shadow:0 -6px 20px -12px rgba(15,23,42,.35); }
.mega-item:hover .mega-trigger svg { transform:rotate(180deg); }
.mega-trigger:focus-visible { outline:2px solid var(--accent); outline-offset:-2px; }
.mega-ico { font-style:normal; line-height:1; }

/* Panel container genişliğinde: .mega-item position:static olduğu için
   konumlanma referansı .mega (position:relative). */
.mega { position:relative; }
.mega-panel {
    position:absolute; top:100%; left:0; right:0; z-index:60;
    display:none; gap:0;
    background:#f8fbff; border:1px solid var(--border); border-radius:0 0 18px 18px;
    box-shadow:0 28px 56px -22px rgba(15,23,42,.28); overflow:hidden;
}
.mega-item:hover .mega-panel { display:flex; }

/* ── Sol ray ── */
.mega-rail { width:32%; min-width:240px; padding:18px 14px 22px 18px; display:flex; flex-direction:column; gap:2px; }
.mega-card {
    display:flex; align-items:center; gap:11px; padding:11px 14px; border-radius:12px;
    text-decoration:none; color:#334155; font-size:14.5px; font-weight:600; transition:all .15s;
}
.mega-card:hover { background:rgba(255,255,255,.6); }
.mega-card.on { background:#fff; color:var(--accent-dark); font-weight:800; box-shadow:0 6px 18px -10px rgba(15,23,42,.35); }
.mega-card-ico { font-style:normal; line-height:1; flex-shrink:0; }
.mega-card-name { flex:1; min-width:0; }
.mega-card-count { font-style:normal; font-size:11px; font-weight:800; color:var(--accent-dark); background:var(--accent-light); border-radius:100px; padding:1px 7px; }
.mega-card-caret { opacity:0; flex-shrink:0; transition:opacity .15s; }
.mega-card.on .mega-card-caret { opacity:1; }

/* ── Orta sütun ── */
.mega-content { flex:1; min-width:0; background:#fff; padding:22px 26px 26px; }
.mega-pane { display:none; }
.mega-pane.on { display:block; }
.mega-pane h4 { margin:0 0 14px; font-size:16px; font-weight:800; color:#0f172a; letter-spacing:-.3px; }
.mega-pane ul { list-style:none; margin:0; padding:0; columns:2; column-gap:28px; }
.mega-pane li { break-inside:avoid; }
.mega-pane li + li { margin-top:2px; }
.mega-pane a {
    display:flex; align-items:center; gap:8px; padding:7px 10px; border-radius:8px;
    font-size:14px; color:#334155; text-decoration:none; transition:background .12s;
}
.mega-pane a:hover { background:#f1f5f9; color:var(--accent-dark); }
.mega-pane a span { flex:1; min-width:0; }
.mega-pane a em { font-style:normal; font-size:11.5px; color:#94a3b8; font-variant-numeric:tabular-nums; }
.mega-pane .mega-ico { flex-shrink:0; }
.mega-empty { margin:0; font-size:13px; color:#94a3b8; }

@media (max-width:1100px) { .mega-pane ul { columns:1; } }
@media (max-width:768px) { .mega { display:none; } }
</style>

<script>
(function () {
    const kok = document.querySelector('.mega');
    if (!kok) return;

    function goster(kart) {
        const panel = kart.closest('.mega-panel');
        panel.querySelectorAll('.mega-card').forEach(k => k.classList.toggle('on', k === kart));
        panel.querySelectorAll('.mega-pane').forEach(p => p.classList.toggle('on', p.id === kart.dataset.show));
    }

    // Sol raydaki seçim hover ile değişir; tıklamak kategoriye gider (link).
    kok.querySelectorAll('.mega-card').forEach(kart => {
        kart.addEventListener('mouseenter', () => goster(kart));
        // Klavye kullanıcısı fareyi kullanamıyor: odak da aynı işi görsün.
        kart.addEventListener('focus', () => goster(kart));
    });

    // Menüden çıkınca panel ilk dala sıfırlansın ki bir dahaki açılışta
    // rastgele bir yerde kalmasın.
    kok.querySelectorAll('.mega-item').forEach(item => {
        item.addEventListener('mouseleave', () => {
            const ilk = item.querySelector('.mega-card');
            if (ilk) goster(ilk);
        });
    });
})();
</script>
@endif
