@extends('layouts.app')
@section('title', 'Tur Puanlama — Admin')
@section('styles')
/* ── Tur Puanlama (admin) — rubrik kapsamı + editör incelemesi ── */
.rp-icerik { max-width:94%; margin:0 auto; }
.rp-bas { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
.rp-bas h1 { margin:0; text-wrap:balance; }
.rp-bas .p-alt { margin:4px 0 0; max-width:70ch; }
.rp-serit { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px, 1fr)); gap:12px; margin-bottom:22px; }
.rp-kutu { background:var(--p-yuzey, #fff); border:1px solid var(--p-cizgi, #e2e8f0); border-radius:12px; padding:14px 16px; }
.rp-kutu b { display:block; font-size:24px; font-weight:800; line-height:1.1; font-variant-numeric:tabular-nums; color:var(--p-metin, #0f172a); }
.rp-kutu span { display:block; font-size:12.5px; font-weight:600; color:var(--p-metin-2, #334155); margin-top:4px; }
.rp-kutu small { display:block; font-size:11.5px; color:var(--p-metin-4, #94a3b8); margin-top:2px; line-height:1.4; }
.rp-kutu.tehlike b { color:var(--p-tehlike-metin, #b91c1c); }
.rp-kutu.basari b { color:var(--p-basari-metin, #15803d); }
.rp-kutu.uyari b { color:var(--p-uyari-metin, #b45309); }
.rp-sekmeler { display:flex; gap:4px; flex-wrap:wrap; border-bottom:1px solid var(--p-cizgi, #e2e8f0); margin-bottom:16px; }
.rp-sekme { padding:10px 14px; font-size:13.5px; font-weight:600; color:var(--p-metin-3, #64748b); text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-1px; white-space:nowrap; }
.rp-sekme:hover { color:var(--p-metin, #0f172a); }
.rp-sekme.aktif { color:var(--p-vurgu, #0d9488); border-bottom-color:currentColor; }
.rp-sekme i { font-style:normal; font-size:12px; opacity:.75; margin-left:4px; font-variant-numeric:tabular-nums; }
.rp-arac { display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
.rp-arac .sol { font-size:13px; color:var(--p-metin-3, #64748b); }
.rp-arac .sag { display:flex; gap:8px; flex-wrap:wrap; }
.rp-mesaj { padding:10px 14px; border-radius:10px; margin-bottom:16px; font-size:13px; }
.rp-mesaj.basari { background:var(--p-basari-zemin, #dcfce7); color:var(--p-basari-metin, #166534); }
.rp-mesaj.uyari { background:var(--p-uyari-zemin, #fef3c7); color:var(--p-uyari-metin, #92400e); }
.rp-tur a { font-weight:600; color:inherit; text-decoration:none; }
.rp-tur a:hover { text-decoration:underline; }
.rp-tur small { display:block; color:var(--p-metin-4, #94a3b8); font-size:12px; margin-top:2px; }
.rp-cipler { display:flex; gap:4px; flex-wrap:wrap; }
.rp-cip { font-size:11px; font-weight:700; padding:2px 8px; border-radius:999px; background:var(--p-tehlike-zemin, #fef2f2); color:var(--p-tehlike-metin, #b91c1c); white-space:nowrap; }
.rp-hata { display:block; font-size:11.5px; color:var(--p-tehlike-metin, #b91c1c); margin-top:4px; max-width:360px; line-height:1.4; }
.rp-bos { padding:40px 20px; text-align:center; color:var(--p-metin-3, #64748b); font-size:14px; }
.rp-deger { text-align:center; font-variant-numeric:tabular-nums; }
.rp-null { color:var(--p-tehlike-metin, #b91c1c); font-weight:800; }
.rp-sec { width:16px; height:16px; cursor:pointer; }
.rp-eksik { padding:10px 14px; border:1px solid var(--p-tehlike-cizgi, #fecaca); background:var(--p-tehlike-zemin, #fef2f2); border-radius:10px; margin-bottom:8px; font-size:13px; }
body.panel-layout-active .rp-tablo td { padding:14px 16px; }
body.panel-layout-active .rp-tablo th { padding:14px 16px; }
/* Boyut tablosu: 10 puan sütunu dar, tur sütunu geniş kalsın */
.rp-tablo .rp-tur { min-width:240px; }
body.panel-layout-active .rp-tablo .rp-boyut { padding:12px 6px; }
.rp-tablo th.rp-boyut { font-size:10px; letter-spacing:.2px; }
@media (max-width:768px) { .rp-arac .sag { width:100%; } .rp-arac .sag .p-btn { flex:1; justify-content:center; } }
@endsection

@section('content')
@php
    $etiket = \App\Services\Matching\RubricCoverage::ETIKET;
    $sekmeler = [
        'puansiz' => 'Puansız', 'bayat' => 'Bayat', 'incelemede' => 'İncelemede',
        'puanli' => 'Puanlı', 'eksik-veri' => 'Eksik veri',
    ];
    $kuyrugaAlinabilir = in_array($sekme, ['puansiz', 'bayat'], true);
@endphp
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div class="rp-icerik">
                @include('partials.breadcrumb', ['schema' => false, 'root' => ['name' => 'Admin', 'url' => route('admin.dashboard')], 'items' => [['name' => 'Tur Puanlama']]])
                <div class="rp-bas">
                    <div>
                        <h1 class="p-sayfa-baslik">Tur Puanlama</h1>
                        <p class="p-alt">Sohbet yalnız rubrik puanı olan turları kart olarak gösterebilir. Puanlama tur eklenince ve program değişince otomatik kuyruğa girer; bu sayfa hangi turun puansız, bayat ya da editör onayında kaldığını gösterir.</p>
                    </div>
                </div>

                @if(session('success'))
                    <div class="rp-mesaj basari">{{ session('success') }}</div>
                @endif
                @if(session('warning'))
                    <div class="rp-mesaj uyari">{{ session('warning') }}</div>
                @endif
                @include('partials.form-errors')

                {{-- Durum şeridi: app:score-tours-rubric --dry ile aynı kaynaktan (RubricCoverage) --}}
                <div class="rp-serit" data-rp-ozet>
                    <div class="rp-kutu"><b>{{ $ozet['tur'] }}</b><span>Aktif tur</span><small>Pasif turlar sayılmaz</small></div>
                    <div class="rp-kutu basari"><b>{{ $ozet['yayinlanabilir'] }}</b><span>Yayınlanabilir</span><small>Sohbette kart olabilir</small></div>
                    <div class="rp-kutu tehlike"><b>{{ $ozet['puansiz'] }}</b><span>Puansız</span><small>Hiç puanlanmamış</small></div>
                    <div class="rp-kutu"><b>{{ $ozet['bayat'] }}</b><span>Bayat</span><small>Program değişmiş, eski puan kullanılıyor</small></div>
                    <div class="rp-kutu uyari"><b>{{ $ozet['incelemede'] }}</b><span>İncelemede</span><small>İki geçiş uyuşmadı, onay bekliyor</small></div>
                    <div class="rp-kutu {{ $ozet['basarisiz'] > 0 ? 'tehlike' : '' }}"><b>{{ $ozet['kuyrukta'] }}</b><span>Kuyrukta</span><small>{{ $ozet['basarisiz'] > 0 ? $ozet['basarisiz'].' başarısız job' : 'Başarısız job yok' }}</small></div>
                </div>

                <nav class="rp-sekmeler" aria-label="Puanlama sekmeleri">
                    @foreach($sekmeler as $anahtar => $ad)
                        <a href="{{ route('admin.rubric.index', ['sekme' => $anahtar]) }}" class="rp-sekme {{ $sekme === $anahtar ? 'aktif' : '' }}" @if($sekme === $anahtar) aria-current="page" @endif>{{ $ad }}<i>{{ $sekmeSayilari[$anahtar] }}</i></a>
                    @endforeach
                </nav>

                @if($kuyrugaAlinabilir)
                    {{-- Satır başı "Puanla" düğmeleri bu forma bağlanır (form attribute); seçim formuyla iç içe olmaz --}}
                    <form method="POST" action="{{ route('admin.rubric.queue') }}" id="rpTek">
                        @csrf
                        <input type="hidden" name="sekme" value="{{ $sekme }}">
                    </form>

                    <form method="POST" action="{{ route('admin.rubric.queue') }}" id="rpForm">
                        @csrf
                        <input type="hidden" name="sekme" value="{{ $sekme }}">
                        <div class="rp-arac">
                            <div class="sol"><b id="rpSecili">0</b> seçili · bu sekmede {{ $satirlar->total() }} tur</div>
                            <div class="sag">
                                <button type="submit" class="p-btn p-btn-ikincil p-btn-kucuk" id="rpSeciliBtn" disabled>Seçilenleri kuyruğa al</button>
                                @if($satirlar->total() > 0)
                                    <button type="submit" name="kapsam" value="{{ $sekme }}" class="p-btn p-btn-birincil p-btn-kucuk"
                                        onclick="return confirm('Bu sekmedeki {{ $satirlar->total() }} turun tamamı kuyruğa alınacak. Tur başına iki LLM geçişi yapılır. Devam edilsin mi?')">
                                        Listenin tamamını kuyruğa al ({{ $satirlar->total() }})
                                    </button>
                                @endif
                            </div>
                        </div>

                        @if($satirlar->total() === 0)
                            <div class="card rp-bos">{{ $sekme === 'puansiz' ? 'Puansız aktif tur yok — katalogun tamamı puanlanmış. 🎉' : 'Bayat puan yok — bütün puanlar güncel programa göre.' }}</div>
                        @else
                            <div class="card" style="padding:0;overflow:hidden;margin:0;">
                                <div class="table-wrap"><table class="table rp-tablo" style="margin:0;border:none;">
                                    <thead><tr>
                                        <th style="width:36px;"><input type="checkbox" id="rpTumu" class="rp-sec" aria-label="Sayfadaki tüm turları seç"></th>
                                        <th>Tur</th>
                                        <th>Acenta</th>
                                        <th>Eklendi</th>
                                        <th>Kalkış</th>
                                        <th>İçerik</th>
                                        @if($sekme === 'bayat')<th>Eski puan</th>@endif
                                        <th>Kuyruk</th>
                                        <th></th>
                                    </tr></thead>
                                    <tbody>
                                    @foreach($satirlar as $satir)
                                        @php($tur = $satir['tour'])
                                        <tr>
                                            <td><input type="checkbox" name="tour_ids[]" value="{{ $tur->id }}" class="rp-sec rp-satir" aria-label="{{ $tur->title }} seç"></td>
                                            <td class="rp-tur">
                                                <a href="{{ route('tours.show', $tur) }}" target="_blank" rel="noopener">{{ $tur->title }}</a>
                                                <small>#{{ $tur->id }} · {{ $tur->destination }}</small>
                                            </td>
                                            <td>{{ $tur->agency?->name ?? '—' }}</td>
                                            <td style="white-space:nowrap;">{{ $tur->created_at?->format('d.m.Y') ?? '—' }}</td>
                                            <td>
                                                @if($satir['gelecek_kalkis'])
                                                    <span class="p-etiket p-etiket-basari">Gelecek tarih var</span>
                                                @else
                                                    <span class="p-etiket p-etiket-notr" title="Sohbet geçmiş tarihli turu zaten listelemez">Gelecek tarih yok</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if($satir['eksikler'] === [])
                                                    <span class="p-etiket p-etiket-basari">Tam</span>
                                                @else
                                                    <div class="rp-cipler" title="Boş alan LLM'e kanıt vermez; o boyut null kalır">
                                                        @foreach($satir['eksikler'] as $eksik)<span class="rp-cip">{{ $eksik }} yok</span>@endforeach
                                                    </div>
                                                @endif
                                            </td>
                                            @if($sekme === 'bayat')
                                                <td style="white-space:nowrap;">
                                                    {{ $satir['score']?->scored_at?->format('d.m.Y') ?? '—' }}
                                                    @if($satir['score']?->review_status === \App\Models\TourRubricScore::STATUS_NEEDS_REVIEW)
                                                        <span class="p-etiket p-etiket-uyari" style="margin-left:4px;">incelemedeydi</span>
                                                    @endif
                                                </td>
                                            @endif
                                            <td>
                                                @if($satir['kuyruk'] === 'kuyrukta')
                                                    <span class="p-etiket p-etiket-bilgi">Kuyrukta</span>
                                                @elseif($satir['kuyruk'] === 'basarisiz')
                                                    <span class="p-etiket p-etiket-tehlike">Başarısız</span>
                                                    @if($satir['hata'])<span class="rp-hata">{{ $satir['hata'] }}</span>@endif
                                                @else
                                                    <span style="color:var(--p-metin-4, #94a3b8);">—</span>
                                                @endif
                                            </td>
                                            <td style="text-align:right;white-space:nowrap;">
                                                <button type="submit" form="rpTek" name="tour_ids[]" value="{{ $tur->id }}" class="p-btn p-btn-ikincil p-btn-kucuk">Puanla</button>
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table></div>
                            </div>
                            <div style="margin-top:16px;">{{ $satirlar->links() }}</div>
                        @endif
                    </form>

                @elseif($sekme === 'eksik-veri')
                    <p class="p-alt" style="margin:0 0 12px;">Kanıt bulunamayan boyutlar null kalır ve eşleştirmede devre dışı sayılır. Genelde tur programı ya da dahil hizmetler boştur; turu zenginleştirince gözlemci yeniden puanlatır.</p>
                    @forelse($satirlar as $satir)
                        <div class="rp-eksik">
                            <b>{{ $satir['tour']->title }}</b> <span style="color:var(--p-metin-4, #94a3b8);">#{{ $satir['tour']->id }}</span>
                            — kanıt bulunamayan boyutlar: {{ implode(', ', $satir['score']->nullDimensions()) }}
                            @if($satir['eksikler'] !== [])
                                <div class="rp-cipler" style="margin-top:6px;">@foreach($satir['eksikler'] as $eksik)<span class="rp-cip">{{ $eksik }} yok</span>@endforeach</div>
                            @endif
                        </div>
                    @empty
                        <div class="card rp-bos">Null boyut yok — tüm puanlı turlarda her boyut kanıtlı. 🎉</div>
                    @endforelse
                    @if($satirlar->total() > 0)<div style="margin-top:16px;">{{ $satirlar->links() }}</div>@endif

                @else
                    {{-- incelemede / puanlı: boyut tablosu --}}
                    @if($sekme === 'incelemede')
                        <p class="p-alt" style="margin:0 0 12px;">İki geçiş arasında bir boyutta 1'den büyük fark çıkan puanlar otomatik yayınlanmaz. Değerler makulse onaylayın; değilse turu zenginleştirip yeniden puanlatın.</p>
                    @endif
                    @if($satirlar->total() === 0)
                        <div class="card rp-bos">{{ $sekme === 'incelemede' ? 'Onay bekleyen puan yok.' : 'Henüz puanlı aktif tur yok.' }}</div>
                    @else
                        <div class="card" style="padding:0;overflow:hidden;margin:0;">
                            <div class="table-wrap"><table class="table rp-tablo" style="margin:0;border:none;font-size:12.5px;">
                                <thead><tr>
                                    <th>Tur</th>
                                    @foreach($dimensions as $d)
                                        <th class="rp-deger rp-boyut" title="{{ \App\Services\Matching\Rubric::label($d) }}">{{ $d }}</th>
                                    @endforeach
                                    <th>Durum</th>
                                    <th>Puanlandı</th>
                                    <th></th>
                                </tr></thead>
                                <tbody>
                                @foreach($satirlar as $satir)
                                    @php($score = $satir['score'])
                                    <tr>
                                        <td class="rp-tur">
                                            <a href="{{ route('tours.show', $satir['tour']) }}" target="_blank" rel="noopener">{{ $satir['tour']->title }}</a>
                                            <small>#{{ $satir['tour']->id }} · {{ $satir['tour']->destination }}</small>
                                        </td>
                                        @foreach($dimensions as $d)
                                            @php($v = $score->scores[$d]['value'] ?? null)
                                            <td class="rp-deger rp-boyut {{ $v === null ? 'rp-null' : '' }}">{{ $v ?? '∅' }}</td>
                                        @endforeach
                                        <td><span class="p-etiket {{ $etiket[$satir['durum']][1] }}">{{ $score->review_status === \App\Models\TourRubricScore::STATUS_APPROVED ? 'Onaylı' : $etiket[$satir['durum']][0] }}</span></td>
                                        <td style="white-space:nowrap;">{{ $score->scored_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                        <td style="text-align:right;">
                                            @if($score->review_status === \App\Models\TourRubricScore::STATUS_NEEDS_REVIEW)
                                                <form method="POST" action="{{ route('admin.rubric.approve', $score) }}">
                                                    @csrf
                                                    <button type="submit" class="p-btn p-btn-birincil p-btn-kucuk">Onayla</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table></div>
                        </div>
                        <div style="margin-top:16px;">{{ $satirlar->links() }}</div>
                    @endif
                @endif
            </div>
        </div>
    </div>
</div>

@if($kuyrugaAlinabilir)
<script>
(function () {
    var tumu = document.getElementById('rpTumu');
    var satirlar = Array.prototype.slice.call(document.querySelectorAll('.rp-satir'));
    var sayac = document.getElementById('rpSecili');
    var btn = document.getElementById('rpSeciliBtn');
    if (!sayac || !btn) return;

    function guncelle() {
        var n = satirlar.filter(function (c) { return c.checked; }).length;
        sayac.textContent = n;
        btn.disabled = n === 0;
        if (tumu) {
            tumu.checked = satirlar.length > 0 && n === satirlar.length;
            tumu.indeterminate = n > 0 && n < satirlar.length;
        }
    }
    satirlar.forEach(function (c) { c.addEventListener('change', guncelle); });
    if (tumu) {
        tumu.addEventListener('change', function () {
            satirlar.forEach(function (c) { c.checked = tumu.checked; });
            guncelle();
        });
    }
    guncelle();
})();
</script>
@endif
@endsection
