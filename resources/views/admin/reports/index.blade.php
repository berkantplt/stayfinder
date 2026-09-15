@extends('layouts.app')
@section('title', 'Raporlar — Admin')

@php
    // B13 — dönem seçici + 4 bölüm (Gelir · Trafik · Ürün/AI · Operasyon) + CSV.
    // Para yalnız ödenmiş siparişlerden (paid_at); ham trafik 180 gün budanır.
    $g = $rapor['gelir']; $t = $rapor['trafik']; $u = $rapor['urun']; $o = $rapor['operasyon'];
    $tl = fn ($v) => number_format((float) $v, 0, ',', '.').' ₺';
    $donemEtiketi = $start->format('d.m.Y').' – '.$end->format('d.m.Y');
    $csv = fn ($tablo) => route('admin.reports.export', array_filter(['tablo' => $tablo, 'donem' => $donem, 'from' => $donem === 'ozel' ? $start->toDateString() : null, 'to' => $donem === 'ozel' ? $end->toDateString() : null]));
@endphp

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="max-width:94%;margin:0 auto 20px;display:flex;align-items:flex-end;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                <div>
                    <h1 class="p-sayfa-baslik">Raporlar</h1>
                    <p class="p-alt" style="margin-top:4px;">Gelir, trafik, ürün ve operasyon — seçili dönem: <strong>{{ $donemEtiketi }}</strong>. Para rakamları yalnız <em>ödenmiş</em> siparişlerden, ödeme tarihine göre.</p>
                </div>
                <form method="GET" action="{{ route('admin.reports.index') }}" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;">
                    <label class="p-alt" style="display:flex;flex-direction:column;gap:4px;">Dönem
                        <select name="donem" onchange="document.getElementById('ozel-aralik').style.display = this.value === 'ozel' ? 'flex' : 'none'" style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;background:#fff;">
                            @foreach($donemler as $k => $ad)<option value="{{ $k }}" @selected($donem === $k)>{{ $ad }}</option>@endforeach
                        </select>
                    </label>
                    <div id="ozel-aralik" style="display:{{ $donem === 'ozel' ? 'flex' : 'none' }};gap:8px;">
                        <input type="date" name="from" value="{{ $donem === 'ozel' ? $start->toDateString() : '' }}" style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;">
                        <input type="date" name="to" value="{{ $donem === 'ozel' ? $end->toDateString() : '' }}" style="padding:9px 12px;border:1px solid var(--p-cizgi);border-radius:10px;">
                    </div>
                    <button type="submit" class="p-btn p-btn-birincil">Göster</button>
                </form>
            </div>

            <div style="max-width:94%;margin:0 auto;display:flex;flex-direction:column;gap:24px;">

            {{-- ═══════════ GELİR ═══════════ --}}
            <section class="p-kart" id="gelir">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:16px;">
                    <h2 class="p-kart-baslik" style="margin:0;">💳 Gelir</h2>
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        <a href="{{ $csv('siparisler') }}" class="p-btn p-btn-ikincil p-btn-kucuk">⬇ Siparişler CSV</a>
                        <a href="{{ $csv('acenta-gelir') }}" class="p-btn p-btn-ikincil p-btn-kucuk">⬇ Acenta CSV</a>
                        <a href="{{ $csv('kategori-gelir') }}" class="p-btn p-btn-ikincil p-btn-kucuk">⬇ Kategori CSV</a>
                    </div>
                </div>
                <div class="panel-grid-4" style="margin-bottom:20px;">
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">MRR (bugün aktif abonelikler)</div><div style="font-size:26px;font-weight:800;">{{ $tl($g['mrr']) }}</div><div class="p-alt">{{ $g['aktifAbonelik'] }} aktif abonelik ({{ $g['manuelAbonelik'] }} manuel/0 ₺, MRR dışı) · {{ $g['legacyAcenta'] }} geçiş erişimli</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Dönem tahsilatı</div><div style="font-size:26px;font-weight:800;">{{ $tl($g['tahsilat']['tutar']) }}</div><div class="p-alt">{{ $g['tahsilat']['adet'] }} ödenmiş sipariş · {{ $g['otomatikYenileme'] }} otomatik yenileme · {{ $g['manuelSiparis'] }} manuel/0 ₺</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Abonelik hareketi</div><div style="font-size:20px;font-weight:800;"><span style="color:var(--p-basari-metin);">+{{ $g['hareket']['yeni'] }}</span> yeni · <span style="color:var(--p-uyari-metin);">{{ $g['hareket']['iptal'] }}</span> iptal · <span style="color:var(--p-tehlike-metin);">{{ $g['hareket']['dolan'] }}</span> dolan</div><div class="p-alt">iptal = yenileme kapatıldı; dolan = süresi bitti</div></div>
                    <div class="p-kart" style="padding:16px;border-color:{{ $g['basarisizCekimSayisi'] ? 'var(--p-tehlike-cizgi)' : 'var(--p-cizgi)' }};"><div class="p-alt">Başarısız otomatik çekim</div><div style="font-size:26px;font-weight:800;color:{{ $g['basarisizCekimSayisi'] ? 'var(--p-tehlike-metin)' : 'inherit' }};">{{ $g['basarisizCekimSayisi'] }}</div><div class="p-alt">abonelik·gün bazında; liste aşağıda</div></div>
                </div>

                <div class="panel-grid-2">
                    <div>
                        <div class="p-alt" style="font-weight:700;margin-bottom:8px;">MRR eğrisi (12 ay, ay sonu; yaklaşık — abonelik satırı geçmiş tutmaz)</div>
                        @php($mrrMax = max(1, max(array_column($g['mrrEgri'], 'tutar'))))
                        <div style="display:flex;align-items:flex-end;gap:6px;height:120px;border-bottom:1px solid var(--p-cizgi);padding-bottom:4px;">
                            @foreach($g['mrrEgri'] as $n)
                                <div title="{{ $n['etiket'] }}: {{ $tl($n['tutar']) }}" style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;align-items:center;gap:4px;height:100%;">
                                    <div style="width:100%;background:var(--p-vurgu);border-radius:4px 4px 0 0;height:{{ round($n['tutar'] / $mrrMax * 100) }}%;min-height:2px;"></div>
                                    <div style="font-size:10px;color:var(--p-metin-4);">{{ substr($n['etiket'], 0, 2) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <div class="p-alt" style="font-weight:700;margin-bottom:8px;">Başarısız çekimler (dönem)</div>
                        @forelse($g['basarisizCekim'] as $b)
                            <div style="display:flex;justify-content:space-between;gap:8px;padding:6px 0;border-bottom:1px solid var(--p-cizgi-acik);font-size:13px;">
                                <span><strong>{{ $b['acenta'] }}</strong> · {{ $b['kategori'] }} <span class="p-alt">({{ $tl($b['tutar']) }}/ay, bitiş {{ $b['bitis'] ?? '—' }})</span></span>
                                <span class="p-alt" style="white-space:nowrap;">{{ \Illuminate\Support\Carbon::parse($b['zaman'])->format('d.m.Y') }}</span>
                            </div>
                        @empty
                            <div class="p-alt">Bu dönemde başarısız çekim yok.</div>
                        @endforelse
                    </div>
                </div>

                <div class="panel-grid-2" style="margin-top:20px;">
                    <div>
                        <div class="p-alt" style="font-weight:700;margin-bottom:8px;">Kategori başına gelir (dönem)</div>
                        <div class="table-wrap"><table class="table" style="width:100%;">
                            <thead><tr><th>Kategori</th><th style="text-align:right;">Kalem</th><th style="text-align:right;">Tutar</th></tr></thead>
                            <tbody>
                            @forelse($g['kategoriGelir'] as $r)
                                <tr><td>{{ $r->kategori }}</td><td style="text-align:right;">{{ $r->kalem }}</td><td style="text-align:right;font-weight:700;">{{ $tl($r->tutar) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="p-alt" style="text-align:center;">Dönemde ödenmiş kalem yok.</td></tr>
                            @endforelse
                            </tbody>
                        </table></div>
                    </div>
                    <div>
                        <div class="p-alt" style="font-weight:700;margin-bottom:8px;">Acenta başına gelir (dönem, ilk 20)</div>
                        <div class="table-wrap"><table class="table" style="width:100%;">
                            <thead><tr><th>Acenta</th><th style="text-align:right;">Sipariş</th><th style="text-align:right;">Tutar</th></tr></thead>
                            <tbody>
                            @forelse($g['acentaGelir'] as $r)
                                <tr><td>@if($r->silinme){{ $r->acenta }} <span class="p-etiket p-etiket-notr">silinmiş</span>@else<a href="{{ route('admin.agencies.show', $r->agency_id) }}" style="color:inherit;">{{ $r->acenta }}</a>@endif</td><td style="text-align:right;">{{ $r->siparis }}</td><td style="text-align:right;font-weight:700;">{{ $tl($r->tutar) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="p-alt" style="text-align:center;">Dönemde ödenmiş sipariş yok.</td></tr>
                            @endforelse
                            </tbody>
                        </table></div>
                    </div>
                </div>
            </section>

            {{-- ═══════════ TRAFİK ═══════════ --}}
            <section class="p-kart" id="trafik">
                <h2 class="p-kart-baslik">📈 Trafik <a href="{{ route('admin.traffic') }}" class="p-alt" style="font-weight:600;margin-left:8px;">tur bazlı ayrıntı →</a></h2>
                @if($t['retentionUyari'])
                    <div class="alert alert-error" style="margin-bottom:12px;">Seçili dönem 180 günden eski günler içeriyor; ham görüntülenme/tıklama kayıtları o günler için budanmış olabilir. Yaşam boyu sayaçlar etkilenmez.</div>
                @endif
                <div class="panel-grid-4" style="margin-bottom:20px;">
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Görüntülenme (dönem)</div><div style="font-size:26px;font-weight:800;">{{ number_format($t['views']) }}</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Acentaya tıklama (dönem)</div><div style="font-size:26px;font-weight:800;">{{ number_format($t['clicks']) }}</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Dönüşüm (tıklama / görüntülenme)</div><div style="font-size:26px;font-weight:800;">{{ $t['donusum'] === null ? '—' : '%'.number_format($t['donusum'], 1, ',', '.') }}</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Yaşam boyu (sayaç)</div><div style="font-size:18px;font-weight:800;">{{ number_format($t['yasamBoyu']['views']) }} gör. · {{ number_format($t['yasamBoyu']['clicks']) }} tık.</div></div>
                </div>
                <div class="panel-grid-2">
                    <div>
                        <div class="p-alt" style="font-weight:700;margin-bottom:8px;">En çok görüntülenen turlar (dönem)</div>
                        <div class="table-wrap"><table class="table" style="width:100%;">
                            <thead><tr><th>Tur</th><th style="text-align:right;">Gör.</th><th style="text-align:right;">Tık.</th></tr></thead>
                            <tbody>
                            @forelse($t['enCok'] as $r)
                                <tr><td>@if($r['tour']->trashed())<span style="font-weight:600;">{{ $r['tour']->title }}</span> <span class="p-etiket p-etiket-notr">arşivde</span>@else<a href="{{ route('admin.traffic.show', $r['tour']) }}" style="color:inherit;font-weight:600;">{{ $r['tour']->title }}</a>@endif<br><span class="p-alt">{{ $r['tour']->destination }}</span></td><td style="text-align:right;">{{ number_format($r['views']) }}</td><td style="text-align:right;">{{ number_format($r['clicks']) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="p-alt" style="text-align:center;">Dönemde görüntülenme kaydı yok.</td></tr>
                            @endforelse
                            </tbody>
                        </table></div>
                    </div>
                    <div>
                        @foreach([['Destinasyon', $t['destinasyon']], ['Kategori', $t['kategori']]] as [$baslik, $liste])
                            <div class="p-alt" style="font-weight:700;margin:0 0 8px;">{{ $baslik }} kırılımı (görüntülenme)</div>
                            @php($mx = max(1, (int) ($liste->max('adet') ?? 0)))
                            @forelse($liste as $r)
                                <div style="margin-bottom:8px;font-size:13px;">
                                    <div style="display:flex;justify-content:space-between;"><span>{{ $r->ad ?: '—' }}</span><span>{{ number_format($r->adet) }}</span></div>
                                    <div style="background:var(--p-cizgi-acik);border-radius:99px;height:6px;"><div style="background:var(--p-vurgu);height:100%;border-radius:99px;width:{{ round($r->adet / $mx * 100) }}%;"></div></div>
                                </div>
                            @empty
                                <div class="p-alt" style="margin-bottom:12px;">Veri yok.</div>
                            @endforelse
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ═══════════ ÜRÜN / AI ═══════════ --}}
            <section class="p-kart" id="urun">
                <h2 class="p-kart-baslik">🤖 Ürün / AI</h2>
                @if($u['aiRetentionUyari'])
                    <div class="alert alert-error" style="margin-bottom:12px;">Seçili dönem 90 günden eski günler içeriyor; AI arama kayıtları o günler için budanmış olabilir.</div>
                @endif
                <div class="panel-grid-4" style="margin-bottom:20px;">
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">AI arama (dönem)</div><div style="font-size:26px;font-weight:800;">{{ number_format($u['ai']['toplam']) }}</div><div class="p-alt">{{ number_format($u['ai']['sonucsuz']) }} sonuçsuz{{ $u['ai']['sonucsuzOran'] !== null ? ' (%'.number_format($u['ai']['sonucsuzOran'], 1, ',', '.').')' : '' }}</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Keşif rehberi (dönem)</div><div style="font-size:26px;font-weight:800;">{{ $u['kesif']['toplam'] }}</div><div class="p-alt">{{ $u['kesif']['tamamlanan'] }} tamamlandı · {{ $u['kesif']['basarisiz'] }} başarısız</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Kupon</div><div style="font-size:26px;font-weight:800;">{{ $u['kupon']['alinan'] }} <span class="p-alt" style="font-size:13px;">alım (dönem)</span></div><div class="p-alt">{{ $u['kupon']['tanimlanan'] }} tanımlandı · {{ $u['kupon']['aktif'] }} aktif · {{ $u['kupon']['tukenen'] }} tükendi</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Yeni müşteri (dönem)</div><div style="font-size:26px;font-weight:800;">{{ $u['kullanici']['kayit'] }}</div><div class="p-alt">{{ $u['kullanici']['yediGunFavori'] }} kişi 7 gün içinde favori ekledi{{ $u['kullanici']['yediGunOran'] !== null ? ' (%'.number_format($u['kullanici']['yediGunOran'], 1, ',', '.').')' : '' }}</div></div>
                </div>
                <div class="p-alt" style="font-weight:700;margin-bottom:8px;">Sonuçsuz kalan aramalar (dönem, ilk 10) — arz açığı sinyali</div>
                @forelse($u['ai']['sonucsuzSorgular'] as $sorgu => $adet)
                    <div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid var(--p-cizgi-acik);font-size:13px;"><span>"{{ $sorgu }}"</span><span class="p-etiket p-etiket-notr">{{ $adet }}</span></div>
                @empty
                    <div class="p-alt">Dönemde sonuçsuz arama yok.</div>
                @endforelse
            </section>

            {{-- ═══════════ OPERASYON ═══════════ --}}
            <section class="p-kart" id="operasyon">
                <h2 class="p-kart-baslik">🛠️ Operasyon</h2>
                <div class="panel-grid-4">
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Acenta başvurusu</div><div style="font-size:26px;font-weight:800;">{{ $o['bekleyenBasvuru'] }} <span class="p-alt" style="font-size:13px;">bekliyor</span></div><div class="p-alt">dönemde {{ $o['donemdeOnaylanan'] }} onaylandı{{ $o['ortalamaOnaySaati'] !== null ? ' · ort. '.number_format($o['ortalamaOnaySaati'], 1, ',', '.').' saat' : '' }}</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Kategori talebi</div><div style="font-size:26px;font-weight:800;">{{ $o['bekleyenTalep'] }} <span class="p-alt" style="font-size:13px;">bekliyor</span></div><div class="p-alt">dönemde {{ $o['donemdeKarar'] }} karara bağlandı</div></div>
                    <div class="p-kart" style="padding:16px;"><div class="p-alt">Rubrik puanı olmayan aktif tur</div><div style="font-size:26px;font-weight:800;">{{ $o['puansizTur'] }} <span class="p-alt" style="font-size:13px;">/ {{ $o['aktifTur'] }}</span></div><div class="p-alt">{{ $o['quizAcik'] ? 'tatil karakteri testi açık' : 'test kapalı — bilgi amaçlı' }}</div></div>
                    <div class="p-kart" style="padding:16px;border-color:{{ $o['kuyruk']['basarisiz'] ? 'var(--p-tehlike-cizgi)' : 'var(--p-cizgi)' }};"><div class="p-alt">Kuyruk</div><div style="font-size:26px;font-weight:800;">{{ $o['kuyruk']['bekleyen'] }} <span class="p-alt" style="font-size:13px;">bekleyen iş</span></div><div class="p-alt">{{ $o['kuyruk']['enEskiDakika'] !== null ? 'en eski '.$o['kuyruk']['enEskiDakika'].' dk · ' : '' }}{{ $o['kuyruk']['basarisiz'] }} başarısız{{ $o['kuyruk']['sonBasarisiz'] ? ' (son: '.\Illuminate\Support\Carbon::parse($o['kuyruk']['sonBasarisiz'])->format('d.m H:i').')' : '' }}</div></div>
                </div>
            </section>

            </div>
        </div>
    </div>
</div>
@endsection
