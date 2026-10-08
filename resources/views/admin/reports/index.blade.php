@extends('layouts.app')
@section('title', 'Raporlar — Admin')

@php
    // B13 + 2026-10 yeniden tasarım — dönem çubuğu, bölüm çipleri, 4 bölüm
    // (Gelir · Trafik · Ürün/AI · Operasyon), tek dışa aktar menüsü.
    // Para yalnız ödenmiş siparişlerden (paid_at); ücretli / manuel hatları ayrı.
    $g = $rapor['gelir']; $t = $rapor['trafik']; $u = $rapor['urun']; $o = $rapor['operasyon'];
    $tl = fn ($v) => number_format((float) $v, 0, ',', '.').' ₺';
    $sayi = fn ($v) => number_format((int) $v, 0, ',', '.');
    $yuzde = fn ($v) => $v === null ? '—' : '%'.number_format((float) $v, 1, ',', '.');
    $donemEtiketi = $start->format('d.m.Y').' – '.$end->format('d.m.Y');
    $donemParam = array_filter(['donem' => $donem, 'from' => $donem === 'ozel' ? $start->toDateString() : null, 'to' => $donem === 'ozel' ? $end->toDateString() : null]);
    $csv = fn ($tablo) => route('admin.reports.export', ['tablo' => $tablo] + $donemParam);
    $yenileUrl = route('admin.reports.index', $donemParam + ['yenile' => 1]);
    $mrrTutarlar = array_column($g['mrrEgri'], 'tutar');
    $mrrMax = max(1, max($mrrTutarlar));
    $mrrBos = max($mrrTutarlar) <= 0;
    $pay = fn ($v, $mx) => $mx > 0 ? (int) round($v / $mx * 100) : 0;
    $destMax = max(1, (int) ($t['destinasyon']->max('adet') ?? 0));
    $katMax = max(1, (int) ($t['kategori']->max('adet') ?? 0));
    $kesifAltSatir = $u['kesif']['tamamlanan'].' tamamlandı · '.$u['kesif']['basarisiz'].' başarısız · '.$u['kesif']['bekleyen'].' bekliyor/işleniyor';
@endphp

@section('styles')
/* ── Raporlar (admin) — çıplak CSS, layout <style> içine basılır. .table sınıfı
   BİLEREK kullanılmadı: panel kuralı (td 20px 24px) ve .table-wrap min-width 640px
   yarım genişlik tablolarda 3. sütunu ekran dışına itiyordu. ── */
.rp-icerik { max-width:94%; margin:0 auto; display:flex; flex-direction:column; gap:20px; }
.rp-ust { background:var(--p-yuzey); border:1px solid var(--p-cizgi); border-radius:var(--p-radius); padding:16px 20px; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:14px; }
.rp-ust h1 { margin:0; }
.rp-ust-alt { font-size:13px; color:var(--p-metin-3); margin-top:4px; display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
.rp-ust-alt a { font-weight:600; display:inline-flex; align-items:center; gap:4px; }
.rp-form { display:flex; flex-wrap:wrap; align-items:center; gap:10px; margin:0; }
.rp-seg { display:inline-flex; background:var(--p-zemin); border:1px solid var(--p-cizgi); border-radius:10px; padding:3px; }
.rp-seg-sec { position:relative; }
.rp-seg-sec input { position:absolute; inset:0; opacity:0; margin:0; cursor:pointer; }
.rp-seg-sec span { display:inline-flex; align-items:center; min-height:36px; padding:0 14px; border-radius:8px; font-size:13px; font-weight:600; color:var(--p-metin-2); }
.rp-seg-sec input:checked + span { background:var(--p-yuzey); color:var(--p-metin); box-shadow:0 1px 2px rgba(15,23,42,.08); }
.rp-seg-sec input:focus-visible + span { outline:2px solid var(--p-vurgu); outline-offset:1px; }
.rp-tarihler { display:flex; flex-wrap:wrap; gap:8px; }
.rp-tarihler label { display:inline-flex; align-items:center; gap:6px; font-size:12px; color:var(--p-metin-4); }
.rp-tarihler input { font:inherit; font-size:13px; min-height:36px; padding:0 10px; border:1px solid var(--p-cizgi); border-radius:8px; background:var(--p-yuzey); color:var(--p-metin); }
.rp-tarihler input:disabled { color:var(--p-metin-4); background:var(--p-zemin); }
.rp-cipler { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; }
.rp-cip-liste { display:flex; flex-wrap:wrap; gap:8px; }
.rp-cip { display:inline-flex; align-items:center; min-height:34px; padding:0 14px; border-radius:999px; border:1px solid var(--p-cizgi); background:var(--p-yuzey); font-size:13px; font-weight:600; color:var(--p-metin-2); text-decoration:none; }
.rp-cip:hover { border-color:var(--p-vurgu); color:var(--p-vurgu-koyu); }
.rp-menu { position:relative; }
.rp-menu summary { list-style:none; }
.rp-menu summary::-webkit-details-marker { display:none; }
.rp-menu-liste { position:absolute; right:0; top:calc(100% + 6px); z-index:20; min-width:240px; background:var(--p-yuzey); border:1px solid var(--p-cizgi); border-radius:12px; padding:6px; box-shadow:var(--p-golge); display:flex; flex-direction:column; }
.rp-menu-liste a { display:flex; align-items:center; gap:8px; padding:10px 12px; border-radius:8px; font-size:13.5px; font-weight:600; color:var(--p-metin-2); text-decoration:none; }
.rp-menu-liste a:hover { background:var(--p-zemin); color:var(--p-metin); }
.rp-bolum { display:flex; flex-direction:column; gap:18px; scroll-margin-top:16px; }
.rp-bolum-bas { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; }
.rp-bolum-bas-sol { display:flex; flex-wrap:wrap; align-items:center; gap:10px; }
.rp-bolum-bas h2 { margin:0; font-size:16px; font-weight:800; letter-spacing:-.3px; }
.rp-bolum-bas a { font-size:13px; font-weight:600; text-decoration:none; }
.rp-kural { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; font-size:11.5px; font-weight:600; background:var(--p-cizgi-acik); color:var(--p-metin-3); }
.rp-rz { display:inline-flex; align-items:center; padding:2px 8px; border-radius:999px; font-size:10.5px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; white-space:nowrap; }
.rp-rz-donem { background:#ccfbf1; color:var(--p-vurgu-koyu); }
.rp-rz-an { background:var(--p-cizgi-acik); color:var(--p-metin-2); }
.rp-rz-canli { background:var(--p-uyari-zemin); color:var(--p-uyari-metin); }
.rp-g4 { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:12px; }
.rp-g2 { display:grid; grid-template-columns:minmax(0,1.3fr) minmax(0,1fr); gap:20px; }
.rp-g2-esit { grid-template-columns:repeat(2, minmax(0,1fr)); }
.rp-kpi { background:var(--p-yuzey); border:1px solid var(--p-cizgi); border-radius:14px; padding:16px 18px; display:flex; flex-direction:column; gap:6px; min-width:0; }
.rp-kpi-disi { border-style:dashed; background:#fafbfc; }
.rp-kpi-tehlike { border-color:var(--p-tehlike-cizgi); }
.rp-kpi-l { display:flex; justify-content:space-between; align-items:center; gap:8px; font-size:12px; font-weight:600; color:var(--p-metin-3); }
.rp-kpi-v { font-size:28px; font-weight:800; letter-spacing:-.6px; font-variant-numeric:tabular-nums; line-height:1.1; color:var(--p-metin); }
.rp-kpi-v small { font-size:13px; font-weight:600; color:var(--p-metin-3); letter-spacing:0; }
.rp-kpi-v.rp-kpi-v-kucuk { font-size:18px; letter-spacing:-.3px; line-height:1.3; }
.rp-kpi-s { font-size:12.5px; color:var(--p-metin-3); line-height:1.45; }
.rp-kpi-s strong { color:var(--p-metin); }
.rp-hareket { display:grid; grid-template-columns:repeat(4, minmax(0,1fr)); gap:6px; margin-top:2px; }
.rp-hareket b { display:block; font-size:20px; font-weight:800; font-variant-numeric:tabular-nums; line-height:1.1; }
.rp-hareket small { display:block; font-size:11px; color:var(--p-metin-3); margin-top:2px; }
.rp-serit { display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:10px; background:var(--p-zemin); border:1px solid var(--p-cizgi); border-radius:12px; padding:10px 14px; }
.rp-serit-sol { display:flex; flex-wrap:wrap; align-items:center; gap:14px; font-size:13px; color:var(--p-metin-2); }
.rp-serit-sol strong { color:var(--p-metin); }
.rp-serit a { font-size:13px; font-weight:600; text-decoration:none; }
.rp-blok { display:flex; flex-direction:column; gap:10px; min-width:0; }
.rp-blok-bas { display:flex; justify-content:space-between; align-items:center; gap:8px; }
.rp-blok-bas p { margin:0; font-size:13px; font-weight:700; color:var(--p-metin); }
.rp-not { font-size:12px; color:var(--p-metin-3); }
.rp-kaydir { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.rp-tablo { width:100%; border-collapse:collapse; font-size:13.5px; }
.rp-tablo th { text-align:left; font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:var(--p-metin-3); padding:6px 10px; border-bottom:1px solid var(--p-cizgi); white-space:nowrap; }
.rp-tablo td { padding:9px 10px; border-bottom:1px solid var(--p-cizgi-acik); vertical-align:middle; }
.rp-tablo tr:hover td { background:var(--p-zemin); }
.rp-tablo .n { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
.rp-tablo .n b { font-weight:700; }
.rp-tablo .sonuk { color:var(--p-metin-4); }
.rp-tablo .t { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:420px; font-weight:600; }
.rp-tablo .t a { color:inherit; text-decoration:none; }
.rp-tablo .bos td { text-align:center; color:var(--p-metin-3); padding:18px 10px; }
.rp-bar { height:6px; border-radius:99px; background:var(--p-cizgi-acik); overflow:hidden; min-width:60px; }
.rp-bar i { display:block; height:100%; border-radius:99px; background:var(--p-vurgu); }
.rp-kirilim { display:grid; grid-template-columns:minmax(0,1fr) 48px 56px; column-gap:10px; row-gap:7px; align-items:center; font-size:13px; }
.rp-kirilim .ad { margin-bottom:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.rp-kirilim .n { text-align:right; font-variant-numeric:tabular-nums; }
.rp-bos { border:1px dashed #cbd5e1; border-radius:12px; padding:14px; text-align:center; font-size:13px; color:var(--p-metin-3); background:#fafbfc; }
.rp-bos-dolu { flex:1; display:flex; align-items:center; justify-content:center; min-height:120px; }
.rp-mrr { position:relative; height:150px; border-bottom:1px solid var(--p-cizgi); display:flex; align-items:flex-end; gap:8px; padding:0 4px 24px; }
.rp-mrr-cubuk { flex:1; display:flex; flex-direction:column; justify-content:flex-end; height:100%; }
.rp-mrr-cubuk i { display:block; width:100%; background:var(--p-vurgu); border-radius:4px 4px 0 0; min-height:3px; }
.rp-mrr-bos .rp-mrr-cubuk i { background:var(--p-cizgi); }
.rp-mrr-etiketler { position:absolute; left:0; right:0; bottom:0; display:flex; gap:8px; padding:0 4px; font-size:10.5px; color:var(--p-metin-4); font-variant-numeric:tabular-nums; }
.rp-mrr-etiketler span { flex:1; text-align:center; }
.rp-mrr-mesaj { position:absolute; left:50%; top:38%; transform:translate(-50%,-50%); background:var(--p-yuzey); border:1px solid var(--p-cizgi); border-radius:10px; padding:8px 14px; font-size:13px; color:var(--p-metin-3); white-space:nowrap; }
.rp-liste { display:flex; flex-direction:column; }
.rp-liste-satir { display:flex; justify-content:space-between; gap:8px; padding:7px 0; border-bottom:1px solid var(--p-cizgi-acik); font-size:13px; }
.rp-liste-satir .rp-not { white-space:nowrap; }
@media(max-width:1200px){ .rp-g4 { grid-template-columns:repeat(2, minmax(0,1fr)); } }
@media(max-width:1100px){ .rp-g2 { grid-template-columns:minmax(0,1fr); } .rp-tablo .t { max-width:260px; } }
@media(max-width:600px){
    .rp-g4 { grid-template-columns:minmax(0,1fr); }
    .rp-mrr-mesaj { white-space:normal; width:80%; text-align:center; }
    /* Telefon: segment 2×2 ızgara (yan yana dört etiket "Bu / ay" diye kırılıyordu), tarihler tam satır */
    .rp-form, .rp-tarihler, .rp-tarihler label { width:100%; }
    .rp-seg { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); width:100%; }
    .rp-seg-sec span { width:100%; justify-content:center; white-space:nowrap; }
    .rp-tarihler input { flex:1; min-width:0; }
    .rp-form .p-btn { width:100%; justify-content:center; }
}
@endsection

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div class="rp-icerik">

            {{-- ═══════════ DÖNEM ÇUBUĞU ═══════════ --}}
            <div class="rp-ust">
                <div>
                    <h1 class="p-sayfa-baslik">Raporlar</h1>
                    <div class="rp-ust-alt">
                        <span>Seçili dönem <strong style="color:var(--p-metin);">{{ $donemEtiketi }}</strong></span>
                        <span>· hesaplandı {{ $hesaplandi->format('H:i') }} · 5 dk önbellek</span>
                        <a href="{{ $yenileUrl }}" title="Önbelleği tazele">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12a9 9 0 1 1-3-6.7"></path><path d="M21 3v6h-6"></path></svg>yenile
                        </a>
                    </div>
                </div>
                <form method="GET" action="{{ route('admin.reports.index') }}" class="rp-form" id="rp-form">
                    <div class="rp-seg" role="radiogroup" aria-label="Dönem">
                        @foreach($donemler as $k => $ad)
                            <label class="rp-seg-sec"><input type="radio" name="donem" value="{{ $k }}" @checked($donem === $k)><span>{{ $ad }}</span></label>
                        @endforeach
                    </div>
                    <div class="rp-tarihler" id="rp-tarihler">
                        <label>Başlangıç <input type="date" name="from" value="{{ $donem === 'ozel' ? $start->toDateString() : '' }}" max="{{ now()->toDateString() }}" @disabled($donem !== 'ozel')></label>
                        <label>Bitiş <input type="date" name="to" value="{{ $donem === 'ozel' ? $end->toDateString() : '' }}" max="{{ now()->toDateString() }}" @disabled($donem !== 'ozel')></label>
                    </div>
                    <button type="submit" class="p-btn p-btn-birincil">Göster</button>
                </form>
            </div>

            {{-- ═══════════ BÖLÜM ÇİPLERİ + DIŞA AKTAR ═══════════ --}}
            <div class="rp-cipler">
                <nav class="rp-cip-liste" aria-label="Rapor bölümleri">
                    <a href="#gelir" class="rp-cip">Gelir</a>
                    <a href="#trafik" class="rp-cip">Trafik</a>
                    <a href="#urun" class="rp-cip">Ürün &amp; AI</a>
                    <a href="#operasyon" class="rp-cip">Operasyon</a>
                </nav>
                <details class="rp-menu">
                    <summary class="p-btn p-btn-ikincil" role="button" aria-haspopup="menu">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12"></path><path d="M7 10l5 5 5-5"></path><path d="M4 21h16"></path></svg>
                        Dışa aktar (CSV)
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"></path></svg>
                    </summary>
                    <div class="rp-menu-liste" role="menu">
                        <a href="{{ $csv('siparisler') }}" role="menuitem">Siparişler <span class="rp-not">(ödenmiş, dönem)</span></a>
                        <a href="{{ $csv('acenta-gelir') }}" role="menuitem">Acenta başına gelir</a>
                        <a href="{{ $csv('kategori-gelir') }}" role="menuitem">Kategori başına gelir</a>
                    </div>
                </details>
            </div>

            {{-- ═══════════ GELİR ═══════════ --}}
            <section class="p-kart rp-bolum" id="gelir">
                <div class="rp-bolum-bas">
                    <div class="rp-bolum-bas-sol">
                        <h2>Gelir</h2>
                        <span class="rp-kural">yalnız ödenmiş siparişler · ödeme tarihine göre · tutar = sipariş ara toplamı</span>
                    </div>
                    <span class="rp-rz rp-rz-donem">Ücretli hat</span>
                </div>

                <div class="rp-g4">
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>MRR</span><span class="rp-rz rp-rz-an">Şu an</span></div>
                        <div class="rp-kpi-v">{{ $tl($g['mrr']) }}</div>
                        <div class="rp-kpi-s">{{ $g['ucretliAbonelik'] }} ücretli abonelik · ekstra tur hakkı: <strong>+{{ $tl($g['mrrEkstra']) }}</strong>/ay</div>
                    </div>
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Dönem tahsilatı</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-kpi-v">{{ $tl($g['tahsilat']['tutar']) }}</div>
                        <div class="rp-kpi-s">{{ $g['tahsilat']['adet'] }} ücretli sipariş · {{ $g['otomatikYenileme'] }} otomatik yenileme</div>
                    </div>
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Abonelik hareketi</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-hareket">
                            <div><b style="color:var(--p-basari-metin);">+{{ $g['hareket']['yeni'] }}</b><small>yeni</small></div>
                            <div><b style="color:var(--p-vurgu-koyu);">{{ $g['hareket']['yenilenen'] }}</b><small>yenilenen</small></div>
                            <div><b style="color:var(--p-uyari-metin);">{{ $g['hareket']['iptal'] }}</b><small>iptal</small></div>
                            <div><b style="color:var(--p-tehlike-metin);">{{ $g['hareket']['dolan'] }}</b><small>dolan</small></div>
                        </div>
                        <div class="rp-kpi-s">yeni: {{ $g['hareket']['yeniManuel'] }}'i manuel/bedava · yenilenen = ücretli uzatma · dolan = iptal edilmeden süresi bitti</div>
                    </div>
                    <div class="rp-kpi {{ $g['basarisizCekimSayisi'] ? 'rp-kpi-tehlike' : '' }}">
                        <div class="rp-kpi-l"><span>Başarısız otomatik çekim</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-kpi-v" style="{{ $g['basarisizCekimSayisi'] ? 'color:var(--p-tehlike-metin);' : '' }}">{{ $g['basarisizCekimSayisi'] }}</div>
                        <div class="rp-kpi-s">abonelik · gün bazında tekil</div>
                    </div>
                </div>

                <div class="rp-serit">
                    <div class="rp-serit-sol">
                        <span class="rp-rz rp-rz-an">Manuel / bedava · gelir dışı</span>
                        <span><strong>{{ $g['manuelAbonelik'] }}</strong> aktif abonelik</span>
                        <span><strong>{{ $g['manuelSiparis'] }}</strong> manuel sipariş bu dönem</span>
                        <span><strong>{{ $g['legacyAcenta'] }}</strong> geçiş erişimli acenta</span>
                    </div>
                    <a href="{{ route('admin.category-licenses.access') }}">Acenta Erişimleri'nde gör →</a>
                </div>

                <div class="rp-g2">
                    <div class="rp-blok">
                        <div class="rp-blok-bas"><p>MRR eğrisi · son 12 ay, ay sonu</p><span class="rp-not">yaklaşık — abonelik satırı geçmiş tutmaz</span></div>
                        <div class="rp-mrr {{ $mrrBos ? 'rp-mrr-bos' : '' }}">
                            @foreach($g['mrrEgri'] as $n)
                                <div class="rp-mrr-cubuk" title="{{ $n['etiket'] }}: {{ $tl($n['tutar']) }}"><i style="height:{{ $mrrBos ? 3 : max(3, $pay($n['tutar'], $mrrMax)) }}%;"></i></div>
                            @endforeach
                            <div class="rp-mrr-etiketler">
                                @foreach($g['mrrEgri'] as $n)
                                    <span>{{ (int) substr($n['etiket'], 0, 2) === 1 || $loop->first ? substr($n['etiket'], 0, 2).'/'.substr($n['etiket'], 5, 2) : substr($n['etiket'], 0, 2) }}</span>
                                @endforeach
                            </div>
                            @if($mrrBos)
                                <div class="rp-mrr-mesaj">Henüz ücretli abonelik yok — eğri ilk ödemeyle dolar</div>
                            @endif
                        </div>
                    </div>
                    <div class="rp-blok">
                        <div class="rp-blok-bas"><p>Başarısız çekimler</p><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        @if(count($g['basarisizCekim']) === 0)
                            <div class="rp-bos rp-bos-dolu">Bu dönemde başarısız çekim yok.</div>
                        @else
                            <div class="rp-liste">
                                @foreach($g['basarisizCekim'] as $b)
                                    <div class="rp-liste-satir">
                                        <span><strong>{{ $b['acenta'] }}</strong> · {{ $b['kategori'] }} <span class="rp-not">({{ $tl($b['tutar']) }}/ay, bitiş {{ $b['bitis'] ?? '—' }})</span></span>
                                        <span class="rp-not">{{ \Illuminate\Support\Carbon::parse($b['zaman'])->format('d.m.Y') }}</span>
                                    </div>
                                @endforeach
                            </div>
                            @if($g['basarisizCekimSayisi'] > count($g['basarisizCekim']))
                                <div class="rp-not">İlk {{ count($g['basarisizCekim']) }} gösteriliyor; toplam {{ $g['basarisizCekimSayisi'] }}.</div>
                            @endif
                        @endif
                    </div>
                </div>

                <div class="rp-g2 rp-g2-esit">
                    <div class="rp-blok">
                        <div class="rp-blok-bas"><p>Kategori başına gelir</p><span class="rp-not">kalem = sipariş satırı · manuel = 0 ₺ verilen</span></div>
                        <div class="rp-kaydir">
                            <table class="rp-tablo">
                                <thead><tr><th>Kategori</th><th class="n">Kalem</th><th class="n">Manuel</th><th class="n">Tutar</th><th style="width:26%;">Pay</th></tr></thead>
                                <tbody>
                                @forelse($g['kategoriGelir'] as $r)
                                    <tr>
                                        <td>{{ $r->kategori }}</td>
                                        <td class="n">{{ $r->kalem }}</td>
                                        <td class="n sonuk">{{ $r->manuel }}</td>
                                        <td class="n"><b>{{ $tl($r->tutar) }}</b></td>
                                        <td><div class="rp-bar"><i style="width:{{ $pay($r->tutar, $g['tahsilat']['tutar']) }}%;"></i></div></td>
                                    </tr>
                                @empty
                                    <tr class="bos"><td colspan="5">Dönemde ödenmiş kalem yok.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="rp-blok">
                        <div class="rp-blok-bas"><p>Acenta başına gelir</p><span class="rp-not">ilk 20 · tutara, sonra sipariş sayısına göre</span></div>
                        <div class="rp-kaydir">
                            <table class="rp-tablo">
                                <thead><tr><th>Acenta</th><th class="n">Sipariş</th><th class="n">Manuel</th><th class="n">Tutar</th><th style="width:26%;">Pay</th></tr></thead>
                                <tbody>
                                @forelse($g['acentaGelir'] as $r)
                                    <tr>
                                        <td class="t">@if($r->silinme){{ $r->acenta }} <span class="p-etiket p-etiket-notr">silinmiş</span>@else<a href="{{ route('admin.agencies.show', $r->agency_id) }}">{{ $r->acenta }}</a>@endif</td>
                                        <td class="n">{{ $r->siparis }}</td>
                                        <td class="n sonuk">{{ $r->manuel }}</td>
                                        <td class="n"><b>{{ $tl($r->tutar) }}</b></td>
                                        <td><div class="rp-bar"><i style="width:{{ $pay($r->tutar, $g['tahsilat']['tutar']) }}%;"></i></div></td>
                                    </tr>
                                @empty
                                    <tr class="bos"><td colspan="5">Dönemde ödenmiş sipariş yok.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                        @if($g['tahsilat']['adet'] === 0 && $g['manuelSiparis'] > 0)
                            <div class="rp-bos" style="padding:10px;">Ücretli sipariş yok — tablolar yalnız manuel verilenleri gösteriyor.</div>
                        @endif
                    </div>
                </div>
            </section>

            {{-- ═══════════ TRAFİK ═══════════ --}}
            <section class="p-kart rp-bolum" id="trafik">
                <div class="rp-bolum-bas">
                    <div class="rp-bolum-bas-sol">
                        <h2>Trafik</h2>
                        <span class="rp-kural">ham kayıtlar 180 gün tutulur · görüntülenme oturum+saat tekil · tıklama ham</span>
                    </div>
                    <a href="{{ route('admin.traffic') }}">Tur bazlı ayrıntı →</a>
                </div>
                @if($t['retentionUyari'])
                    <div class="alert alert-warning" style="margin:0;">Seçili dönem 180 günden eski günler içeriyor; ham görüntülenme/tıklama kayıtları o günler için budanmış olabilir. Yaşam boyu sayaçlar etkilenmez.</div>
                @endif

                <div class="rp-g4">
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Görüntülenme</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-kpi-v">{{ $sayi($t['views']) }}</div>
                        <div class="rp-kpi-s">tur sayfası açılışı</div>
                    </div>
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Acentaya tıklama</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-kpi-v">{{ $sayi($t['clicks']) }}</div>
                        <div class="rp-kpi-s">“Acentaya git” yönlendirmesi</div>
                    </div>
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Dönüşüm</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-kpi-v">{{ $yuzde($t['donusum']) }}</div>
                        <div class="rp-kpi-s">tıklama ÷ görüntülenme · tıklama tekilleştirilmiyor, oran üst sınırdır</div>
                    </div>
                    <div class="rp-kpi rp-kpi-disi">
                        <div class="rp-kpi-l"><span>Yaşam boyu sayaç</span><span class="rp-rz rp-rz-an">Dönem dışı</span></div>
                        <div class="rp-kpi-v rp-kpi-v-kucuk">{{ $sayi($t['yasamBoyu']['views']) }} gör. · {{ $sayi($t['yasamBoyu']['clicks']) }} tık.</div>
                        <div class="rp-kpi-s">Dashboard ve Trafik ile aynı kaynak · arşivdeki turlar hariç</div>
                    </div>
                </div>

                <div class="rp-g2">
                    <div class="rp-blok">
                        <div class="rp-blok-bas"><p>En çok görüntülenen turlar</p><span class="rp-not">ilk 10 · dönem</span></div>
                        <div class="rp-kaydir">
                            <table class="rp-tablo">
                                <thead><tr><th>Tur</th><th class="n">Gör.</th><th class="n">Tık.</th><th class="n">Dönüşüm</th></tr></thead>
                                <tbody>
                                @forelse($t['enCok'] as $r)
                                    <tr>
                                        <td>
                                            <div class="t">@if($r['tour']->trashed()){{ $r['tour']->title }} <span class="p-etiket p-etiket-notr">arşivde</span>@else<a href="{{ route('admin.traffic.show', $r['tour']) }}">{{ $r['tour']->title }}</a>@endif</div>
                                            <div class="rp-not">{{ $r['tour']->destination }}</div>
                                        </td>
                                        <td class="n">{{ $sayi($r['views']) }}</td>
                                        <td class="n">{{ $sayi($r['clicks']) }}</td>
                                        <td class="n sonuk">{{ $r['views'] > 0 ? $yuzde(round($r['clicks'] / $r['views'] * 100, 1)) : '—' }}</td>
                                    </tr>
                                @empty
                                    <tr class="bos"><td colspan="4">Dönemde görüntülenme kaydı yok.</td></tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="rp-blok" style="gap:18px;">
                        @foreach([['Destinasyon kırılımı', $t['destinasyon'], $destMax], ['Kategori kırılımı', $t['kategori'], $katMax]] as [$baslik, $liste, $mx])
                            <div class="rp-blok" style="gap:8px;">
                                <div class="rp-blok-bas"><p>{{ $baslik }}</p><span class="rp-not">görüntülenme · pay</span></div>
                                @if($liste->isEmpty())
                                    <div class="rp-bos">Veri yok.</div>
                                @else
                                    <div class="rp-kirilim">
                                        @foreach($liste as $r)
                                            <div><div class="ad">{{ $r->ad ?: '—' }}</div><div class="rp-bar"><i style="width:{{ $pay($r->adet, $mx) }}%;"></i></div></div>
                                            <div class="n">{{ $sayi($r->adet) }}</div>
                                            <div class="n rp-not">{{ $t['views'] > 0 ? $yuzde(round($r->adet / $t['views'] * 100, 1)) : '—' }}</div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ═══════════ ÜRÜN / AI ═══════════ --}}
            <section class="p-kart rp-bolum" id="urun">
                <div class="rp-bolum-bas">
                    <div class="rp-bolum-bas-sol">
                        <h2>Ürün &amp; AI</h2>
                        <span class="rp-kural">AI arama kayıtları 90 gün tutulur</span>
                    </div>
                </div>
                @if($u['aiRetentionUyari'])
                    <div class="alert alert-warning" style="margin:0;">Seçili dönem 90 günden eski günler içeriyor; AI arama kayıtları o günler için budanmış olabilir.</div>
                @endif
                <div class="rp-g4">
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>AI arama</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-kpi-v">{{ $sayi($u['ai']['toplam']) }}</div>
                        <div class="rp-kpi-s">{{ $sayi($u['ai']['sonucsuz']) }} sonuçsuz · oran {{ $yuzde($u['ai']['sonucsuzOran']) }}</div>
                    </div>
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Keşif rehberi</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-kpi-v">{{ $u['kesif']['toplam'] }}</div>
                        <div class="rp-kpi-s">{{ $kesifAltSatir }}</div>
                    </div>
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Kupon</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-kpi-v">{{ $u['kupon']['alinan'] }} <small>alım</small></div>
                        <div class="rp-kpi-s">dönemde {{ $u['kupon']['tanimlanan'] }} tanımlandı<br>şu an <strong>{{ $u['kupon']['aktif'] }}</strong> aktif · <strong>{{ $u['kupon']['tukenen'] }}</strong> tükendi</div>
                    </div>
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Yeni müşteri</span><span class="rp-rz rp-rz-donem">Dönem</span></div>
                        <div class="rp-kpi-v">{{ $u['kullanici']['kayit'] }}</div>
                        <div class="rp-kpi-s">
                            @if($u['kullanici']['yediGunUygun'] > 0)
                                7 gün içinde favori ekleyen: <strong>{{ $u['kullanici']['yediGunFavori'] }} / {{ $u['kullanici']['yediGunUygun'] }}</strong> ({{ $yuzde($u['kullanici']['yediGunOran']) }}) · penceresi kapanan kayıtlar
                            @elseif($u['kullanici']['kayit'] > 0)
                                7 gün içinde favori: — · kayıtların 7 günlük penceresi henüz kapanmadı
                            @else
                                7 gün içinde favori: —
                            @endif
                        </div>
                    </div>
                </div>
                <div class="rp-blok">
                    <div class="rp-blok-bas"><p>Sonuçsuz kalan aramalar</p><span class="rp-not">ilk 10 · arz açığı sinyali</span></div>
                    @if(count($u['ai']['sonucsuzSorgular']) === 0)
                        <div class="rp-bos">Dönemde sonuçsuz arama yok.</div>
                    @else
                        <div class="rp-liste">
                            @foreach($u['ai']['sonucsuzSorgular'] as $sorgu => $adet)
                                <div class="rp-liste-satir"><span>“{{ $sorgu }}”</span><span class="p-etiket p-etiket-notr">{{ $adet }}</span></div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            {{-- ═══════════ OPERASYON ═══════════ --}}
            <section class="p-kart rp-bolum" id="operasyon">
                <div class="rp-bolum-bas">
                    <div class="rp-bolum-bas-sol">
                        <h2>Operasyon</h2>
                        <span class="rp-kural">bekleyenler anlık · karar sayıları döneme göre</span>
                    </div>
                </div>
                <div class="rp-g4">
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Acenta başvurusu</span><span class="rp-rz rp-rz-an">Şu an</span></div>
                        <div class="rp-kpi-v">{{ $o['bekleyenBasvuru'] }} <small>bekliyor</small></div>
                        <div class="rp-kpi-s">dönemde {{ $o['donemdeOnaylanan'] }} onaylandı · ort. süre {{ $o['ortalamaOnaySaati'] !== null ? number_format($o['ortalamaOnaySaati'], 1, ',', '.').' saat' : '—' }}</div>
                    </div>
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Kategori talebi</span><span class="rp-rz rp-rz-an">Şu an</span></div>
                        <div class="rp-kpi-v">{{ $o['bekleyenTalep'] }} <small>bekliyor</small></div>
                        <div class="rp-kpi-s">dönemde {{ $o['donemdeKarar'] }} karara bağlandı</div>
                    </div>
                    <div class="rp-kpi">
                        <div class="rp-kpi-l"><span>Rubrik puanı olmayan tur</span><span class="rp-rz rp-rz-an">Şu an</span></div>
                        <div class="rp-kpi-v">{{ $o['puansizTur'] }} <small>/ {{ $o['aktifTur'] }} aktif</small></div>
                        <div class="rp-kpi-s">{{ $o['quizAcik'] ? 'tatil karakteri testi açık' : 'tatil karakteri testi kapalı — bilgi amaçlı' }}</div>
                    </div>
                    <div class="rp-kpi {{ $kuyruk['basarisiz'] ? 'rp-kpi-tehlike' : '' }}">
                        <div class="rp-kpi-l"><span>Kuyruk</span><span class="rp-rz rp-rz-canli">Canlı</span></div>
                        <div class="rp-kpi-v">{{ $kuyruk['bekleyen'] }} <small>bekleyen iş</small></div>
                        <div class="rp-kpi-s">{{ $kuyruk['enEskiDakika'] !== null ? 'en eski '.$kuyruk['enEskiDakika'].' dk · ' : '' }}{{ $kuyruk['basarisiz'] }} başarısız{{ $kuyruk['sonBasarisiz'] ? ' (son: '.\Illuminate\Support\Carbon::parse($kuyruk['sonBasarisiz'])->format('d.m H:i').')' : '' }} · önbelleğe girmez</div>
                    </div>
                </div>
            </section>

            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Özel aralık seçilince tarih alanları açılır; diğer dönemlerde devre dışı
    // (disabled alan gönderilmez → from/to doğrulaması yalnız "ozel"de çalışır).
    var form = document.getElementById('rp-form');
    if (!form) return;
    var tarihler = form.querySelectorAll('#rp-tarihler input');
    function uygula() {
        var secili = form.querySelector('input[name="donem"]:checked');
        var ozel = secili && secili.value === 'ozel';
        tarihler.forEach(function (i) { i.disabled = !ozel; i.required = ozel; });
    }
    form.querySelectorAll('input[name="donem"]').forEach(function (r) { r.addEventListener('change', uygula); });
    uygula();
})();
</script>
@endpush
