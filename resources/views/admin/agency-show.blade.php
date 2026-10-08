@extends('layouts.app')
@section('title', $agency->name . ' — Acenta Detayı')

@section('styles')
/* ── Acenta detayı (admin) — çıplak CSS, layout <style> içine basılır ──
   Önceki sürüm 6 sütunlu <table> + her satırda iptal formunu 1.45fr'lik sol
   sütuna sıkıştırıyordu; panelin `.table td { padding:20px 24px }` kuralı sayfa
   kuralını ezdiği için "Toplam 0" harf harf kırılıyor, 62px İşlem hücresindeki
   180px form sağdaki kartlara taşıyordu. Şimdi: kategoriler TAM genişlik, CSS grid
   satır listesi (.table sınıfı YOK), iptal gerekçesi satır altında açılan panel. */
.ak-icerik { max-width:94%; margin:0 auto; }
.p-kart.ak-kart { padding:0; overflow:hidden; }
.ak-kimlik { display:flex; align-items:flex-start; justify-content:space-between; gap:20px; flex-wrap:wrap; padding:22px 24px; margin-bottom:16px; }
.ak-kimlik-sol { display:flex; gap:16px; align-items:flex-start; min-width:0; }
.ak-avatar { width:56px; height:56px; flex:none; border-radius:16px; background:var(--accent-light); color:var(--accent-deep); display:flex; align-items:center; justify-content:center; font-size:24px; font-weight:800; overflow:hidden; }
.ak-avatar img { width:100%; height:100%; object-fit:cover; display:block; }
.ak-ad { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.ak-ad h1 { margin:0; }
.ak-meta { display:flex; gap:6px 18px; flex-wrap:wrap; margin-top:10px; font-size:13px; color:var(--p-metin-3); }
.ak-meta span { display:inline-flex; align-items:center; gap:6px; min-width:0; }
.ak-meta svg { width:14px; height:14px; flex:none; color:var(--p-metin-4); }
.ak-meta a { color:var(--accent-ink); font-weight:600; }
.ak-islemler { display:flex; gap:10px; flex-wrap:wrap; align-items:center; }
.ak-islemler form { margin:0; }
.ak-bant { padding:14px 16px; margin-bottom:16px; font-size:13px; line-height:1.6; border-radius:16px; }
.ak-bant-notr { background:#f1f5f9; border:1px solid #cbd5e1; color:#334155; }
.ak-bant-uyari { background:#fff7ed; border:1px solid #fed7aa; color:#9a3412; }
.ak-bant-tehlike { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; }
.ak-bant-baslik { font-size:14px; font-weight:700; margin-bottom:14px; }
.ak-onay-izgara { display:grid; grid-template-columns:repeat(2, minmax(0,1fr)); gap:12px; }
.ak-onay-izgara form { display:flex; flex-direction:column; gap:10px; margin:0; }
.ak-onay-izgara textarea { width:100%; padding:12px 14px; border-radius:12px; border:1px solid #fed7aa; background:#fff; font-size:14px; resize:vertical; font-family:inherit; }
.ak-onay-izgara .ak-red-alan { border-color:#fecaca; }
.ak-statlar { display:grid; grid-template-columns:repeat(auto-fit, minmax(150px,1fr)); gap:12px; margin-bottom:16px; }
.stat-card.ak-stat { padding:16px 18px; }
.ak-stat-etiket { font-size:11px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:var(--p-metin-3); }
.ak-stat-deger { font-size:24px; font-weight:800; letter-spacing:-.5px; margin-top:6px; color:var(--p-metin); font-variant-numeric:tabular-nums; }
.ak-stat-alt { font-size:12px; color:var(--p-metin-4); margin-top:4px; }
.ak-kart-bas { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; padding:22px 24px 18px; }
.ak-kart-bas h2 { font-size:17px; font-weight:700; letter-spacing:-.2px; color:var(--p-metin); margin:0; }
.ak-kart-bas p { font-size:13px; color:var(--p-metin-3); margin:4px 0 0; }
.ak-arac { display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap; padding:12px 14px; background:var(--p-zemin); border:1px solid var(--p-cizgi); border-radius:14px; margin:0; }
.ak-alan { display:flex; flex-direction:column; gap:5px; min-width:0; }
.ak-alan label { font-size:11.5px; font-weight:700; color:var(--p-metin-2); letter-spacing:.2px; }
.ak-alan select, .ak-alan input[type="text"] { height:38px; padding:0 12px; border:1.5px solid var(--p-cizgi); border-radius:10px; background:#fff; font-size:13.5px; color:var(--p-metin); font-family:inherit; max-width:100%; }
.ak-alan select:focus, .ak-alan input[type="text"]:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-bg); }
.ak-sec-kategori { width:300px; }
.ak-arac .btn { height:38px; padding:0 16px; }
.ak-arac-not { padding:0 24px 14px; font-size:12.5px; color:var(--p-metin-3); }
.ak-arac-not.uyari { color:#9a3412; }
.ak-kaydir { overflow-x:auto; -webkit-overflow-scrolling:touch; }
.ak-kat-ic { min-width:900px; }
.ak-kat-bas, .ak-satir { display:grid; grid-template-columns:minmax(0,2.2fr) 140px 120px 120px 210px 110px; gap:16px; align-items:center; padding:14px 24px; }
.ak-kat-bas { font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:var(--p-metin-3); border-top:1px solid var(--p-cizgi-acik); border-bottom:1px solid var(--p-cizgi-acik); padding-top:12px; padding-bottom:12px; background:#fafbfc; }
.ak-satir { border-bottom:1px solid var(--p-cizgi-acik); font-size:14px; transition:background .15s; }
.ak-satir:hover { background:var(--p-zemin); }
.ak-satir.acik { background:#fff7f7; }
.ak-kat-ad { display:flex; align-items:center; gap:12px; min-width:0; }
.ak-kat-ikon { width:38px; height:38px; flex:none; border-radius:11px; background:var(--p-cizgi-acik); display:flex; align-items:center; justify-content:center; font-size:18px; }
.ak-kat-ad b { display:block; font-weight:700; color:var(--p-metin); overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.ak-kat-ad small { display:block; font-size:12px; color:var(--p-metin-4); margin-top:2px; }
.ak-sayi b { font-weight:700; color:var(--p-metin); font-variant-numeric:tabular-nums; }
.ak-sayi small { display:block; font-size:12px; color:var(--p-metin-4); margin-top:2px; }
.ak-bedel { font-weight:600; font-variant-numeric:tabular-nums; }
.ak-sure { font-size:12.5px; color:var(--p-metin-2); font-variant-numeric:tabular-nums; white-space:nowrap; }
.ak-sure-bar { height:4px; border-radius:999px; background:var(--p-cizgi); margin-top:7px; overflow:hidden; }
.ak-sure-bar > div { height:100%; border-radius:999px; background:var(--accent); }
.ak-sure small { display:block; font-size:11.5px; color:var(--p-metin-4); margin-top:5px; font-weight:500; }
.ak-sag { justify-self:end; }
.ak-yok { font-size:12px; color:var(--p-metin-4); }
.ak-etiket-mor { background:#f5f3ff; color:#5b21b6; }
.ak-etiket-cam { background:#ecfeff; color:#155e75; }
.btn.ak-btn-iptal { padding:6px 12px; font-size:12.5px; border-radius:8px; background:var(--p-tehlike-zemin); color:var(--p-tehlike-metin); border:1.5px solid var(--p-tehlike-cizgi); box-shadow:none; }
.btn.ak-btn-iptal:hover { background:#fee2e2; transform:none; }
.btn.ak-btn-iptal.acik { background:#fff; color:var(--p-metin-2); border-color:var(--p-cizgi); }
.ak-iptal { display:flex; align-items:flex-end; justify-content:space-between; gap:16px; flex-wrap:wrap; padding:14px 24px 18px; background:#fff7f7; border-bottom:1px solid #fee2e2; }
.ak-iptal[hidden] { display:none; }
.ak-iptal-metin { font-size:13px; color:#7f1d1d; line-height:1.55; flex:1 1 320px; margin:0; }
.ak-iptal form { display:flex; align-items:flex-end; gap:10px; flex-wrap:wrap; margin:0; }
.ak-iptal .ak-alan label { color:#7f1d1d; }
.ak-iptal input[type="text"] { width:340px; border-color:#fecaca; }
.ak-iptal input[type="text"]:focus { border-color:#dc2626; box-shadow:0 0 0 3px rgba(220,38,38,.12); }
.ak-iptal .btn { height:38px; padding:0 16px; }
.ak-dipnot { padding:12px 24px 18px; font-size:12.5px; color:var(--p-metin-3); line-height:1.8; }
.ak-dipnot .p-etiket { font-size:11px; padding:1px 8px; margin:0 2px; }
.ak-bos { margin:0 24px 24px; padding:26px 18px; border:1px dashed #cbd5e1; border-radius:14px; background:var(--p-zemin); color:var(--p-metin-2); font-size:13.5px; text-align:center; line-height:1.5; }
.ak-bos small { display:block; color:var(--p-metin-4); font-size:12px; margin-top:4px; }
.ak-alt-izgara { display:grid; grid-template-columns:minmax(0,1.5fr) minmax(300px,1fr); gap:16px; margin-top:16px; }
.ak-sag-sutun { display:flex; flex-direction:column; gap:16px; }
.ak-tablo { width:100%; border-collapse:collapse; font-size:13.5px; min-width:620px; }
.ak-tablo th { font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:var(--p-metin-3); text-align:left; padding:12px 16px; border-top:1px solid var(--p-cizgi-acik); border-bottom:1px solid var(--p-cizgi-acik); background:#fafbfc; white-space:nowrap; }
.ak-tablo td { padding:13px 16px; border-bottom:1px solid var(--p-cizgi-acik); vertical-align:middle; }
.ak-tablo tbody tr:hover td { background:var(--p-zemin); }
.ak-tablo th:first-child, .ak-tablo td:first-child { padding-left:24px; }
.ak-tablo th:last-child, .ak-tablo td:last-child { padding-right:24px; text-align:right; }
.ak-mono { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12.5px; font-weight:600; color:var(--p-metin); }
.ak-mono a { color:inherit; }
.ak-mono a:hover { color:var(--accent-ink); }
.ak-tarih { font-size:12.5px; color:var(--p-metin-3); font-variant-numeric:tabular-nums; white-space:nowrap; }
.ak-tutar { font-weight:700; font-variant-numeric:tabular-nums; white-space:nowrap; }
.ak-kaynak { display:flex; gap:6px; flex-wrap:wrap; }
.ak-liste { display:flex; flex-direction:column; padding-bottom:8px; }
.ak-tur { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:12px 24px; border-top:1px solid var(--p-cizgi-acik); }
.ak-tur b { display:block; font-weight:700; color:var(--p-metin); font-size:13.5px; line-height:1.35; }
.ak-tur small { display:block; font-size:12px; color:var(--p-metin-4); margin-top:3px; line-height:1.4; }
.ak-dag { padding:4px 24px 22px; display:flex; flex-direction:column; gap:14px; }
.ak-dag-bas { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.ak-dag-bas b { display:block; font-weight:700; color:var(--p-metin); font-size:13.5px; }
.ak-dag-bas small { display:block; font-size:12px; color:var(--p-metin-4); margin-top:3px; }
.ak-dag-sayi { font-size:18px; font-weight:800; color:var(--p-metin); font-variant-numeric:tabular-nums; }
.ak-cubuk { height:8px; border-radius:999px; background:var(--p-cizgi); margin-top:8px; overflow:hidden; }
.ak-cubuk > div { height:100%; border-radius:999px; background:var(--accent); }
@media (max-width:1100px) {
    .ak-alt-izgara { grid-template-columns:minmax(0,1fr); }
}
@media (max-width:768px) {
    .ak-icerik { max-width:none; }
    .ak-kimlik { padding:18px; }
    .ak-islemler { width:100%; }
    .ak-islemler .btn, .ak-islemler form { flex:1 1 auto; }
    .ak-islemler form .btn { width:100%; }
    .ak-arac { width:100%; }
    .ak-sec-kategori { width:100%; }
    .ak-iptal input[type="text"] { width:100%; }
    .ak-onay-izgara { grid-template-columns:1fr; }
}
@endsection

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div class="ak-icerik">
                @include('partials.breadcrumb', ['schema' => false, 'root' => ['name' => 'Admin', 'url' => route('admin.dashboard')], 'items' => [['name' => 'Acentalar', 'url' => route('admin.agencies')], ['name' => $agency->name]]])

                @if(session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @include('partials.form-errors')

                @php
                    $onayDurumu = $agency->approval_status ?? 'approved';
                    $webHost = $agency->website_url ? (parse_url($agency->website_url, PHP_URL_HOST) ?: $agency->website_url) : null;
                @endphp

                {{-- Kimlik kartı: avatar + rozetler + iletişim + işlemler --}}
                <section class="p-kart ak-kimlik" aria-label="Acenta kimliği">
                    <div class="ak-kimlik-sol">
                        <div class="ak-avatar" aria-hidden="true">
                            @if($agency->logo)
                                <img src="{{ $agency->logo }}" alt="">
                            @else
                                {{ mb_strtoupper(mb_substr($agency->name, 0, 1)) }}
                            @endif
                        </div>
                        <div style="min-width:0;">
                            <div class="ak-ad">
                                <h1 class="p-sayfa-baslik">{{ $agency->name }}</h1>
                                @if($onayDurumu === 'pending')
                                    <span class="p-etiket p-etiket-uyari">Onay bekliyor</span>
                                @elseif($onayDurumu === 'rejected')
                                    <span class="p-etiket p-etiket-tehlike">Reddedildi</span>
                                @else
                                    <span class="p-etiket p-etiket-basari">Onaylı</span>
                                @endif
                                @if($agency->trashed())
                                    <span class="p-etiket p-etiket-notr">Arşivde</span>
                                @elseif($agency->is_active)
                                    <span class="p-etiket p-etiket-basari">Aktif</span>
                                @else
                                    <span class="p-etiket p-etiket-tehlike">Pasif</span>
                                @endif
                                @if($agency->legacy_category_access)
                                    <span class="p-etiket p-etiket-uyari">Geçiş erişimi</span>
                                @endif
                            </div>
                            <div class="ak-meta">
                                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"></rect><path d="M3 7l9 6 9-6"></path></svg>{{ $agency->email ?? 'E-posta yok' }}</span>
                                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.6a2 2 0 0 1-.5 2.1L8.1 9.7a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.8.3 1.7.6 2.6.7a2 2 0 0 1 1.9 2z"></path></svg>{{ $agency->phone ?? 'Telefon yok' }}</span>
                                @if($webHost)
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"></path></svg><a href="{{ $agency->website_url }}" target="_blank" rel="noopener">{{ $webHost }} ↗</a></span>
                                @endif
                                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7 0l3-3a5 5 0 0 0-7-7l-1 1"></path><path d="M14 11a5 5 0 0 0-7 0l-3 3a5 5 0 0 0 7 7l1-1"></path></svg>
                                    @if($agency->trashed())
                                        /acentalar/{{ $agency->slug }}
                                    @else
                                        <a href="{{ route('agencies.show', $agency) }}" target="_blank" rel="noopener">/acentalar/{{ $agency->slug }}</a>
                                    @endif
                                </span>
                                <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"></path></svg>{{ $agency->users_count ?? 0 }} kullanıcı</span>
                                @if($agency->created_at)
                                    <span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"></rect><path d="M16 2v4M8 2v4M3 10h18"></path></svg>Kayıt {{ $agency->created_at->format('d.m.Y') }}</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="ak-islemler">
                        <a href="{{ route('admin.category-licenses.index') }}" class="btn btn-outline">Kategori Yetkilendirme</a>
                        @if($agency->website_url)
                            <a href="{{ $agency->website_url }}" class="btn btn-primary" target="_blank" rel="noopener">Siteyi Aç ↗</a>
                        @endif
                        @if($agency->trashed())
                            <form method="POST" action="{{ route('admin.agencies.restore', $agency) }}">
                                @csrf
                                <button type="submit" class="btn btn-primary">↩ Arşivden Geri Al</button>
                            </form>
                        @else
                            {{-- Silme = arşivleme (A10); onay metni etkiyi sayılarla söyler --}}
                            <form method="POST" action="{{ route('admin.agencies.archive', $agency) }}" onsubmit="return confirm({{ \Illuminate\Support\Js::from($archiveConfirmText) }})">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn ak-btn-iptal" title="Arşive taşı — geri alınabilir" style="padding:11px 18px;font-size:14px;border-radius:12px;">Arşivle</button>
                            </form>
                        @endif
                    </div>
                </section>

                {{-- Durum bantları: içerik ve işleyiş değişmedi --}}
                @if($agency->trashed())
                    <div class="ak-bant ak-bant-notr">
                        Bu acenta {{ $agency->deleted_at->format('d.m.Y H:i') }} tarihinde arşivlendi: turları yayında değil, kullanıcıları panele giremiyor.
                        Abonelik ve sipariş kayıtları duruyor. Birlikte arşivlenen turlar 30 gün sonra kalıcı silinir; "Arşivden Geri Al" ile acenta ve turları geri döner.
                    </div>
                @endif

                @if(!$hasCategoryLicensing)
                    <div class="alert alert-error">
                        Kategori yetkilendirme tabloları bu ortamda hazır değil. Detay ekranı yalnızca temel acenta bilgilerini gösteriyor.
                    </div>
                @elseif($onayDurumu === 'pending')
                    <div class="ak-bant ak-bant-uyari" style="padding:18px;">
                        <div class="ak-bant-baslik">Bu başvuru henüz admin onayı bekliyor. Buradan doğrudan onaylayabilir veya reddedebilirsiniz.</div>
                        <div class="ak-onay-izgara">
                            <form method="POST" action="{{ route('admin.agency-applications.approve', $agency) }}">
                                @csrf
                                <textarea name="approval_notes" rows="3" placeholder="Onay notu (opsiyonel)"></textarea>
@error('approval_notes')<p class="p-hata">{{ $message }}</p>@enderror
                                <button type="submit" class="btn btn-primary">Onayla</button>
                            </form>
                            <form method="POST" action="{{ route('admin.agency-applications.reject', $agency) }}">
                                @csrf
                                <textarea name="approval_notes" rows="3" class="ak-red-alan" placeholder="Red nedeni (opsiyonel)"></textarea>
@error('approval_notes')<p class="p-hata">{{ $message }}</p>@enderror
                                <button type="submit" class="btn btn-outline" style="color:#991b1b;border-color:#fecaca;background:#fff5f5;">Reddet</button>
                            </form>
                        </div>
                    </div>
                @elseif($onayDurumu === 'rejected')
                    <div class="ak-bant ak-bant-tehlike">
                        Bu başvuru reddedildi.
                        @if($agency->approval_notes)
                            Not: {{ $agency->approval_notes }}
                        @endif
                    </div>
                @elseif($agency->legacy_category_access)
                    <div class="ak-bant ak-bant-uyari">
                        Bu acenta geçiş erişiminde. Açık kategori listesi tüm aktif kategorilerden oluşur; aylık değer alanı ise aktif tur kullandığı kategorilerin bugünkü fiyatına göre tahmini hesaplanır.
                    </div>
                @endif

                {{-- Sayaçlar --}}
                @php
                    $kaynakSayilari = $ownedCategories->countBy('source');
                    $kaynakOzeti = collect([
                        'purchase' => 'satın alma',
                        'manual' => 'manuel',
                        'legacy' => 'geçiş',
                    ])->filter(fn ($etiket, $kaynak) => ($kaynakSayilari[$kaynak] ?? 0) > 0)
                      ->map(fn ($etiket, $kaynak) => $kaynakSayilari[$kaynak].' '.$etiket)
                      ->implode(' · ');
                    $abonelikSayisi = $ownedCategories->filter(fn ($o) => $o->subscription)->count();
                    $sonSiparis = $recentOrders->first()?->purchased_at;
                @endphp
                <div class="ak-statlar">
                    <div class="stat-card ak-stat">
                        <div class="ak-stat-etiket">Aktif Tur</div>
                        <div class="ak-stat-deger">{{ $stats['active_tours'] }}</div>
                        <div class="ak-stat-alt">toplam {{ $agency->tours_count ?? 0 }} tur</div>
                    </div>
                    <div class="stat-card ak-stat">
                        <div class="ak-stat-etiket">Açık Kategori</div>
                        <div class="ak-stat-deger">{{ $stats['open_categories'] }}</div>
                        <div class="ak-stat-alt">{{ $kaynakOzeti !== '' ? $kaynakOzeti : 'açık abonelik yok' }}</div>
                    </div>
                    <div class="stat-card ak-stat">
                        <div class="ak-stat-etiket">Kullanılan Kategori</div>
                        <div class="ak-stat-deger">{{ $stats['used_categories'] }}</div>
                        <div class="ak-stat-alt">tur girilen kategori</div>
                    </div>
                    <div class="stat-card ak-stat">
                        <div class="ak-stat-etiket">Aylık Kategori Değeri</div>
                        <div class="ak-stat-deger">{{ number_format($stats['monthly_value'], 0, ',', '.') }} TL</div>
                        <div class="ak-stat-alt">{{ $agency->legacy_category_access ? 'tahmini, kullanılan kategorilere göre' : $abonelikSayisi.' aktif abonelik' }}</div>
                    </div>
                    <div class="stat-card ak-stat">
                        <div class="ak-stat-etiket">Kategori Siparişi</div>
                        <div class="ak-stat-deger">{{ $stats['total_orders'] }}</div>
                        <div class="ak-stat-alt">{{ $sonSiparis ? 'son: '.$sonSiparis->format('d.m.Y') : 'sipariş yok' }}</div>
                    </div>
                    <div class="stat-card ak-stat">
                        <div class="ak-stat-etiket">Toplam Sipariş Tutarı</div>
                        <div class="ak-stat-deger">{{ number_format($stats['lifetime_order_value'], 0, ',', '.') }} TL</div>
                        <div class="ak-stat-alt">tüm sipariş kayıtları</div>
                    </div>
                </div>

                {{-- Açık kategoriler: tam genişlik satır listesi --}}
                <section class="p-kart ak-kart" aria-labelledby="ak-kat-baslik">
                    <div class="ak-kart-bas">
                        <div>
                            <h2 id="ak-kat-baslik">Açık Kategoriler</h2>
                            <p>Acentanın şu an paylaşım yapabildiği {{ $ownedCategories->count() }} kategori · aylık toplam {{ number_format($stats['monthly_value'], 0, ',', '.') }} TL</p>
                        </div>
                        {{-- Manuel kategori ekleme: yanlış satın alma telafisi / iyi niyet --}}
                        <form method="POST" action="{{ route('admin.agencies.categories.grant', $agency) }}" class="ak-arac" aria-label="Manuel kategori ekle">
                            @csrf
                            <div class="ak-alan">
                                <label for="ak-kategori">➕ Manuel kategori ekle</label>
                                <select id="ak-kategori" name="category_id" class="ak-sec-kategori" required>
                                    <option value="">Kategori seçin...</option>
                                    @foreach($grantableCategories as $grantable)
                                        <option value="{{ $grantable->id }}" @selected((string) old('category_id') === (string) $grantable->id)>
                                            {{ $grantable->icon }} {{ $grantable->name }}{{ $grantable->parent ? ' — '.$grantable->parent->name : '' }} ({{ number_format((float) $grantable->monthly_price, 0, ',', '.') }} TL/ay)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="ak-alan">
                                <label for="ak-sure">Süre</label>
                                <select id="ak-sure" name="months" required>
                                    @foreach([1 => '1 Ay', 3 => '3 Ay', 6 => '6 Ay', 12 => '12 Ay'] as $ay => $ayEtiket)
                                        <option value="{{ $ay }}" @selected((int) old('months', 12) === $ay)>{{ $ayEtiket }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" {{ $grantableCategories->isEmpty() ? 'disabled' : '' }}>Ekle</button>
                        </form>
                    </div>
                    @error('category_id')<p class="p-hata" style="padding:0 24px 10px;">{{ $message }}</p>@enderror
                    @error('months')<p class="p-hata" style="padding:0 24px 10px;">{{ $message }}</p>@enderror
                    @if($agency->legacy_category_access)
                        <div class="ak-arac-not uyari">Bu acentada geçiş erişimi açık — zaten tüm aktif kategorilere erişebiliyor. Manuel ekleme yine de kayıt altına alınır.</div>
                    @elseif($grantableCategories->isEmpty())
                        <div class="ak-arac-not">Eklenebilecek kategori kalmadı — tüm aktif alt kategorilerde açık aboneliği var.</div>
                    @endif

                    @if($ownedCategories->isEmpty())
                        <div class="ak-bos">Bu acenta için açık kategori bulunmuyor.<small>Yukarıdaki kutudan manuel ekleyebilir ya da acentanın satın almasını bekleyebilirsiniz.</small></div>
                    @else
                        <div class="ak-kaydir">
                            <div class="ak-kat-ic">
                                <div class="ak-kat-bas" aria-hidden="true">
                                    <div>Kategori</div><div>Kaynak</div><div>Turlar</div><div>Aylık bedel</div><div>Süre</div><div class="ak-sag">İşlem</div>
                                </div>
                                @foreach($ownedCategories as $ownership)
                                    @php
                                        $sub = $ownership->subscription;
                                        // Doğrulama hatasıyla dönüldüyse ilgili satırın paneli açık gelir (subscription_id hidden alanı flash'tan)
                                        $panelAcik = $sub && $errors->has('reason') && (string) old('subscription_id') === (string) $sub->id;
                                        $yuzde = null;
                                        $kalanMetin = null;
                                        if ($ownership->source !== 'legacy' && $ownership->started_at && $ownership->expires_at) {
                                            $baslangic = $ownership->started_at->copy()->startOfDay();
                                            $bitis = $ownership->expires_at->copy()->startOfDay();
                                            $bugun = now()->startOfDay();
                                            $toplamGun = max(1.0, (float) $baslangic->diffInDays($bitis, true));
                                            $gecenGun = max(0.0, (float) $baslangic->diffInDays($bugun, false));
                                            $yuzde = (int) min(100, max(0, round($gecenGun / $toplamGun * 100)));
                                            $kalanGun = (int) ceil((float) $bugun->diffInDays($bitis, false));
                                            $kalanMetin = $kalanGun <= 0 ? 'süresi doldu' : ($kalanGun >= 60 ? intdiv($kalanGun, 30).' ay kaldı' : $kalanGun.' gün kaldı');
                                        }
                                        $kaynakSinifi = ['legacy' => 'p-etiket-uyari', 'manual' => 'ak-etiket-mor', 'purchase' => 'ak-etiket-cam'][$ownership->source] ?? 'p-etiket-notr';
                                    @endphp
                                    <div class="ak-grup">
                                        <div class="ak-satir {{ $panelAcik ? 'acik' : '' }}">
                                            <div class="ak-kat-ad">
                                                <div class="ak-kat-ikon" aria-hidden="true">{{ $ownership->category->icon ?: '📁' }}</div>
                                                <div style="min-width:0;">
                                                    <b>{{ $ownership->category->name }}</b>
                                                    @if($ownership->category->parent)
                                                        <small>{{ $ownership->category->parent->name }}</small>
                                                    @endif
                                                </div>
                                            </div>
                                            <div><span class="p-etiket {{ $kaynakSinifi }}">{{ $ownership->source_label }}</span></div>
                                            <div class="ak-sayi"><b>{{ $ownership->active_tours_count }} aktif</b><small>{{ $ownership->tours_count }} toplam</small></div>
                                            <div class="ak-bedel">{{ number_format((float) $ownership->monthly_price, 0, ',', '.') }} TL</div>
                                            <div class="ak-sure">
                                                @if($ownership->source === 'legacy')
                                                    Süresiz geçiş
                                                @else
                                                    {{ $ownership->started_at?->format('d.m.Y') ?? '—' }} → {{ $ownership->expires_at?->format('d.m.Y') ?? '—' }}
                                                    @if($yuzde !== null)
                                                        <div class="ak-sure-bar"><div style="width:{{ $yuzde }}%;"></div></div>
                                                        <small>{{ $kalanMetin }}</small>
                                                    @endif
                                                @endif
                                            </div>
                                            <div class="ak-sag">
                                                @if($sub)
                                                    <button type="button" class="btn ak-btn-iptal js-iptal-ac {{ $panelAcik ? 'acik' : '' }}" aria-expanded="{{ $panelAcik ? 'true' : 'false' }}" aria-controls="ak-iptal-{{ $sub->id }}">{{ $panelAcik ? 'Vazgeç' : 'İptal et' }}</button>
                                                @else
                                                    <span class="ak-yok">—</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if($sub)
                                            {{-- B12: gerekçe zorunlu — acentaya bildirilir. Panel, eski confirm() adımının yerini alır. --}}
                                            <div class="ak-iptal" id="ak-iptal-{{ $sub->id }}" role="region" aria-label="{{ $ownership->category->name }} iptal onayı" {{ $panelAcik ? '' : 'hidden' }}>
                                                <p class="ak-iptal-metin"><b>{{ $ownership->category->name }}</b> aboneliği iptal edilecek; bu kategorideki turlar yayından kalkar. Gerekçe acentaya bildirim olarak gider.</p>
                                                <form method="POST" action="{{ route('admin.agencies.categories.revoke', [$agency, $sub]) }}">
                                                    @csrf
                                                    <input type="hidden" name="subscription_id" value="{{ $sub->id }}">
                                                    <div class="ak-alan">
                                                        <label for="ak-gerekce-{{ $sub->id }}">İptal gerekçesi (zorunlu, en az 5 karakter)</label>
                                                        <input type="text" id="ak-gerekce-{{ $sub->id }}" name="reason" required minlength="5" maxlength="300" value="{{ $panelAcik ? old('reason') : '' }}" placeholder="Örn. yanlış kategoriye eklendi, acenta talebiyle kapatıldı">
                                                        @if($panelAcik)
                                                            @error('reason')<p class="p-hata">{{ $message }}</p>@enderror
                                                        @endif
                                                    </div>
                                                    <button type="submit" class="btn btn-danger">İptali onayla</button>
                                                </form>
                                            </div>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                        <div class="ak-dipnot">
                            Kaynak: <span class="p-etiket ak-etiket-cam">Satın alındı</span> gerçek sipariş ·
                            <span class="p-etiket ak-etiket-mor">Manuel eklendi</span> admin telafisi ·
                            <span class="p-etiket p-etiket-uyari">Geçiş erişimi</span> süresiz legacy. Satırlar aktif tur sayısına göre sıralı.
                        </div>
                    @endif
                </section>

                <div class="ak-alt-izgara">
                    {{-- Sipariş geçmişi: geniş tablo --}}
                    <section class="p-kart ak-kart" aria-labelledby="ak-sip-baslik">
                        <div class="ak-kart-bas" style="padding-bottom:14px;">
                            <div>
                                <h2 id="ak-sip-baslik">Kategori Sipariş Geçmişi</h2>
                                <p>Gerçek satın alma ve manuel ekleme kayıtları · son {{ $recentOrders->count() }} sipariş</p>
                            </div>
                            <a href="{{ route('admin.category-licenses.orders') }}" class="btn btn-outline btn-sm">Tüm siparişler</a>
                        </div>
                        @if($recentOrders->isEmpty())
                            <div class="ak-bos">Bu acenta için kategori siparişi bulunmuyor.</div>
                        @else
                            <div class="ak-kaydir">
                                <table class="ak-tablo">
                                    <thead>
                                        <tr><th>Sipariş No</th><th>Tarih</th><th>Kategoriler</th><th>Kaynak</th><th>Tutar</th></tr>
                                    </thead>
                                    <tbody>
                                        @foreach($recentOrders as $order)
                                            @php
                                                $durum = ['paid' => ['Ödendi', 'p-etiket-basari'], 'pending' => ['Ödeme bekliyor', 'p-etiket-uyari'], 'failed' => ['Başarısız', 'p-etiket-tehlike'], 'cancelled' => ['İptal', 'p-etiket-notr']][$order->status] ?? [$order->status, 'p-etiket-notr'];
                                                $manuel = $order->payment_provider === \App\Models\AgencyCategoryOrder::PROVIDER_MANUAL;
                                            @endphp
                                            <tr>
                                                <td><span class="ak-mono"><a href="{{ route('admin.category-licenses.orders.show', $order) }}">{{ $order->order_number }}</a></span></td>
                                                <td class="ak-tarih">{{ $order->purchased_at?->format('d.m.Y H:i') ?? '—' }}</td>
                                                <td>{{ $order->items->pluck('category_name')->implode(', ') }}</td>
                                                <td>
                                                    <div class="ak-kaynak">
                                                        @if($manuel)<span class="p-etiket ak-etiket-mor">Manuel</span>@endif
                                                        @if(!$manuel || $order->status !== 'paid')<span class="p-etiket {{ $durum[1] }}">{{ $durum[0] }}</span>@endif
                                                    </div>
                                                </td>
                                                <td class="ak-tutar">{{ number_format((float) $order->subtotal, 0, ',', '.') }} TL</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </section>

                    <div class="ak-sag-sutun">
                        {{-- Son turlar --}}
                        <section class="p-kart ak-kart" aria-labelledby="ak-tur-baslik">
                            <div class="ak-kart-bas" style="padding-bottom:14px;">
                                <div>
                                    <h2 id="ak-tur-baslik">Son Turlar</h2>
                                    <p>En yeni tur kayıtları ve kategori eşleşmeleri</p>
                                </div>
                            </div>
                            @if($recentTours->isEmpty())
                                <div class="ak-bos">Bu acenta henüz tur paylaşmamış.<small>Turlar geldikçe başlık · kategori · çıkış tarihi · durum olarak listelenir.</small></div>
                            @else
                                <div class="ak-liste">
                                    @foreach($recentTours as $tour)
                                        <div class="ak-tur">
                                            <div style="min-width:0;">
                                                <b>{{ $tour->title }}</b>
                                                <small>{{ $tour->category?->name ?? 'Kategorisiz' }} · {{ $tour->destination ?? 'Destinasyon yok' }} · Çıkış: {{ $tour->departure_date?->format('d.m.Y') ?? 'Belirtilmedi' }}</small>
                                            </div>
                                            <span class="p-etiket {{ $tour->is_active ? 'p-etiket-basari' : 'p-etiket-tehlike' }}">{{ $tour->is_active ? 'Aktif' : 'Pasif' }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </section>

                        {{-- Kategori bazlı tur dağılımı --}}
                        <section class="p-kart ak-kart" aria-labelledby="ak-dag-baslik">
                            <div class="ak-kart-bas" style="padding-bottom:14px;">
                                <div>
                                    <h2 id="ak-dag-baslik">Kategori Bazlı Tur Dağılımı</h2>
                                    <p>Acentanın hangi kategorilerde gerçekten içerik ürettiğini gösterir</p>
                                </div>
                            </div>
                            @if($usedCategories->isEmpty())
                                <div class="ak-bos">Kategorili tur kaydı bulunmuyor.</div>
                            @else
                                @php($maxUsed = max(1, (int) $usedCategories->max('active_tours_count')))
                                <div class="ak-dag">
                                    @foreach($usedCategories as $category)
                                        <div>
                                            <div class="ak-dag-bas">
                                                <div><b>{{ $category->icon }} {{ $category->name }}</b><small>Toplam {{ $category->tours_count }} tur</small></div>
                                                <span class="ak-dag-sayi">{{ $category->active_tours_count }}</span>
                                            </div>
                                            <div class="ak-cubuk"><div style="width:{{ min(100, round(($category->active_tours_count / $maxUsed) * 100)) }}%;"></div></div>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<noscript><style>.ak-iptal[hidden] { display:flex; }</style></noscript>
@endsection

@push('scripts')
<script>
    // İptal paneli: "İptal et" satırın altındaki gerekçe panelini açar/kapatır.
    // Panel onay adımının kendisidir; eski confirm() diyaloğu kaldırıldı.
    document.addEventListener('click', function (e) {
        const dugme = e.target.closest('.js-iptal-ac');
        if (!dugme) return;
        const panel = document.getElementById(dugme.getAttribute('aria-controls'));
        if (!panel) return;
        const acilacak = panel.hidden;
        panel.hidden = !acilacak;
        dugme.setAttribute('aria-expanded', acilacak ? 'true' : 'false');
        dugme.textContent = acilacak ? 'Vazgeç' : 'İptal et';
        dugme.classList.toggle('acik', acilacak);
        const satir = dugme.closest('.ak-satir');
        if (satir) satir.classList.toggle('acik', acilacak);
        if (acilacak) {
            const alan = panel.querySelector('input[name="reason"]');
            if (alan) alan.focus();
        }
    });
</script>
@endpush
