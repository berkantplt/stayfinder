@extends('layouts.app')
@section('title', 'Kategori Banner Yönetimi — Admin')

@php
    use App\Support\LandingSlug;

    // Her kategori için durum: kendi / üstten miras / genel / yok (CategoryHero sırası)
    $durum = function ($kategori, $ust = null) use ($banners, $genel) {
        $kendi = $banners->get($kategori->id);
        if ($kendi) {
            return ['tip' => 'kendi', 'etiket' => $kendi->is_active ? 'Kendi banner\'ı' : 'Kendi banner\'ı (yayında değil)', 'renk' => $kendi->is_active ? 'yesil' : 'gri', 'gorsel' => $kendi->image_url];
        }
        $ustBanner = $ust ? $banners->get($ust->id) : null;
        if ($ustBanner && $ustBanner->is_active) {
            return ['tip' => 'miras', 'etiket' => 'Üst kategoriden miras', 'renk' => 'mavi', 'gorsel' => $ustBanner->image_url];
        }
        if ($genel && $genel->is_active) {
            return ['tip' => 'genel', 'etiket' => 'Genel varsayılan', 'renk' => 'mavi', 'gorsel' => $genel->image_url];
        }

        return ['tip' => 'yok', 'etiket' => 'Banner yok (tur görseli)', 'renk' => 'gri', 'gorsel' => null];
    };
    $acikAnahtar = old('form_key') ?: session('kb_open');
@endphp

@push('head')
<style>
    /* ── Kategori banner yönetimi (kb-) ── */
    .kb-intro { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:10px; }
    .kb-step { display:flex; gap:10px; align-items:flex-start; padding:12px 14px; border:1px solid var(--border); border-radius:12px; background:#f8fafc; font-size:13px; color:#475569; line-height:1.45; }
    .kb-step b { display:grid; place-items:center; width:22px; height:22px; border-radius:50%; background:var(--accent-ink); color:#fff; font-size:12px; flex:none; }
    .kb-acc { display:flex; flex-direction:column; gap:12px; }
    .kb-item { background:var(--white); border:1px solid var(--border); border-radius:14px; overflow:hidden; }
    .kb-item > summary { list-style:none; display:flex; align-items:center; gap:14px; padding:16px 20px; cursor:pointer; user-select:none; }
    .kb-item > summary::-webkit-details-marker { display:none; }
    .kb-item[open] > summary { border-bottom:1px solid var(--border-light); background:#f8fafc; }
    .kb-ic { width:40px; height:40px; border-radius:11px; display:grid; place-items:center; background:var(--accent-bg); font-size:19px; flex:none; }
    .kb-ad { font-size:16px; font-weight:800; color:#0f172a; letter-spacing:-.2px; }
    .kb-alt { font-size:12.5px; color:#64748b; margin-top:2px; }
    .kb-chev { margin-left:auto; width:28px; height:28px; border-radius:50%; border:1px solid var(--border); display:grid; place-items:center; color:#64748b; transition:transform .2s; flex:none; }
    .kb-item[open] > summary .kb-chev { transform:rotate(180deg); }
    .kb-rozet { display:inline-flex; align-items:center; padding:3px 9px; border-radius:999px; font-size:11.5px; font-weight:700; white-space:nowrap; }
    .kb-rozet.yesil { background:var(--green-bg); color:var(--green-text); }
    .kb-rozet.mavi { background:#e0f2fe; color:#075985; }
    .kb-rozet.gri { background:#f1f5f9; color:#64748b; }
    .kb-panel { padding:14px 16px 18px; display:flex; flex-direction:column; gap:10px; }
    .kb-sub { border:1px solid var(--border); border-radius:12px; background:var(--white); overflow:hidden; }
    .kb-sub > summary { list-style:none; display:flex; align-items:center; gap:12px; padding:11px 14px; cursor:pointer; user-select:none; }
    .kb-sub > summary::-webkit-details-marker { display:none; }
    .kb-sub[open] > summary { background:#f8fafc; border-bottom:1px solid var(--border-light); }
    .kb-sub[open] > summary .kb-chev { transform:rotate(180deg); }
    .kb-thumb { width:64px; height:40px; border-radius:8px; object-fit:cover; background:linear-gradient(135deg,#e0f2fe,#ccfbf1); flex:none; display:grid; place-items:center; font-size:16px; color:#94a3b8; overflow:hidden; }
    .kb-thumb img { width:100%; height:100%; object-fit:cover; display:block; }
    .kb-sub-ad { font-size:14.5px; font-weight:700; color:#0f172a; }
    .kb-sub-url { font-size:12px; color:#64748b; margin-top:1px; }
    .kb-sub-body { padding:16px 14px 18px; }
    .kb-ust-not { font-size:12.5px; color:#64748b; padding:2px 2px 6px; }

    .kb-form-wrap { display:grid; grid-template-columns:minmax(0,5fr) minmax(0,7fr); gap:18px; align-items:start; }
    .kb-prev { position:relative; aspect-ratio:16 / 7; min-height:170px; border-radius:14px; overflow:hidden; background:#0f2421; color:#fff; isolation:isolate; }
    .kb-prev img { position:absolute; inset:0; width:100%; height:100%; object-fit:cover; z-index:0; }
    .kb-prev-ov { position:absolute; inset:0; z-index:1; }
    .kb-prev-body { position:relative; z-index:2; padding:18px 20px; max-width:78%; }
    .kb-prev-eye { font-size:9.5px; font-weight:800; letter-spacing:.14em; text-transform:uppercase; color:#5eead4; margin-bottom:5px; }
    .kb-prev-title { font-family:'Manrope',var(--font); font-size:21px; font-weight:800; line-height:1.08; letter-spacing:-.6px; margin-bottom:6px; }
    .kb-prev-sub { font-size:11.5px; line-height:1.4; color:rgba(255,255,255,.85); }
    .kb-prev-cap { position:absolute; right:12px; bottom:9px; z-index:2; font-size:9.5px; font-weight:600; color:rgba(255,255,255,.92); text-shadow:0 1px 2px rgba(0,0,0,.5); }
    .kb-prev-cap:empty { display:none; }
    .kb-prev-hint { position:absolute; left:0; right:0; bottom:10px; z-index:2; text-align:center; font-size:11px; color:rgba(255,255,255,.55); }
    .kb-grid { display:grid; grid-template-columns:1fr 1fr; gap:12px 14px; }
    .kb-grid .form-group { margin:0; }
    .kb-grid label { font-size:12.5px; color:#475569; font-weight:700; display:block; margin-bottom:5px; }
    .kb-grid input[type=text], .kb-grid input[type=file] { width:100%; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; font:inherit; font-size:14px; background:#f8fafc; }
    .kb-span { grid-column:1 / -1; }
    .kb-help { font-size:11.5px; color:#64748b; margin-top:5px; line-height:1.4; }
    .kb-muted { font-weight:500; color:#94a3b8; }
    .kb-actions { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin-top:14px; }
    .kb-actions-alt { grid-column:1 / -1; padding-top:12px; border-top:1px dashed var(--border); margin-top:0; }
    .kb-actions-alt form { margin:0; }
    @media (max-width: 960px) { .kb-form-wrap { grid-template-columns:1fr; } .kb-grid { grid-template-columns:1fr; } }
</style>
@endpush

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:22px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;background:var(--accent-bg);color:var(--accent-dark);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;">🏞️</div>
                    <div>
                        <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Kategori Banner Yönetimi</h1>
                        <div style="font-size:13px;color:#64748b;margin-top:2px;">Kategori sayfalarının (/balkan-turlari gibi) üstündeki hero alanı. Ana sayfa karuseli <a href="{{ route('admin.banners.index') }}" style="color:var(--accent-ink);font-weight:600;">Banner Yönetimi</a>'nde, burada değil.</div>
                    </div>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            {{-- Çözümleme sırası: admin "neden bu görsel çıktı" diye şaşırmasın --}}
            <div class="stat-card" style="padding:18px 20px;margin-bottom:22px;">
                <div style="font-size:13px;font-weight:800;color:#0f172a;margin-bottom:10px;">Sayfada hangi banner çıkar? (sırayla ilk bulunan)</div>
                <div class="kb-intro">
                    <div class="kb-step"><b>1</b><span>Kategorinin <strong>kendi</strong> banner'ı (yayındaysa)</span></div>
                    <div class="kb-step"><b>2</b><span>Alt kategoriyse <strong>üst kategorisinin</strong> banner'ı</span></div>
                    <div class="kb-step"><b>3</b><span>Aşağıdaki <strong>genel varsayılan</strong></span></div>
                    <div class="kb-step"><b>4</b><span>Hiçbiri yoksa listedeki <strong>ilk turun görseli</strong>; metinler kategori adından türer</span></div>
                </div>
            </div>

            {{-- GENEL VARSAYILAN --}}
            <div class="kb-acc" style="margin-bottom:22px;">
                <details class="kb-item" id="kb-genel" name="kb-ust" data-kb-acc="ust" @if($acikAnahtar === 'kb-genel') open @endif>
                    <summary>
                        <div class="kb-ic">🌐</div>
                        <div>
                            <div class="kb-ad">Genel varsayılan banner</div>
                            <div class="kb-alt">Kendi ya da üst kategorisinin banner'ı olmayan her kategori sayfasında bu çıkar.</div>
                        </div>
                        <span class="kb-rozet {{ $genel ? ($genel->is_active ? 'yesil' : 'gri') : 'gri' }}" style="margin-left:8px;">
                            {{ $genel ? ($genel->is_active ? 'Yayında' : 'Yayında değil') : 'Tanımlı değil' }}
                        </span>
                        <span class="kb-chev" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                    </summary>
                    <div class="kb-panel">
                        @include('admin.partials.category-banner-form', ['kategori' => null, 'banner' => $genel, 'key' => 'kb-genel', 'overlay' => $overlay])
                    </div>
                </details>
            </div>

            {{-- ÜST KATEGORİ AKORDEONU: tek seferde biri açık; içinde alt kategoriler --}}
            <div style="display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:10px;">
                <h2 style="font-size:17px;font-weight:800;color:#0f172a;">Kategoriler</h2>
                <span style="font-size:12.5px;color:#64748b;">{{ $parents->count() }} üst kategori · {{ $parents->sum(fn ($p) => $p->children->count()) }} alt kategori</span>
            </div>

            @if($parents->isEmpty())
                <div class="stat-card" style="padding:28px;text-align:center;color:#64748b;">Henüz kategori yok. Önce <a href="{{ route('admin.categories.parents') }}" style="color:var(--accent-ink);font-weight:600;">Kategori Yönetimi</a>'nden ekleyin.</div>
            @endif

            <div class="kb-acc">
                @foreach($parents as $parent)
                    @php
                        $ustDurum = $durum($parent);
                        $bannerli = $parent->children->filter(fn ($c) => $banners->has($c->id))->count();
                        $cocukAnahtarlar = $parent->children->map(fn ($c) => 'kb-'.$c->id)->push('kb-'.$parent->id)->all();
                        $ustAcik = in_array($acikAnahtar, $cocukAnahtarlar, true);
                    @endphp
                    <details class="kb-item" id="kb-p{{ $parent->id }}" name="kb-ust" data-kb-acc="ust" @if($ustAcik) open @endif>
                        <summary>
                            <div class="kb-ic">{{ $parent->icon ?: '📁' }}</div>
                            <div style="min-width:0;">
                                <div class="kb-ad">{{ $parent->name }}</div>
                                <div class="kb-alt">{{ $parent->children->count() }} alt kategori · {{ $bannerli }} tanesinin kendi banner'ı var · üst sayfa: {{ $ustDurum['etiket'] }}</div>
                            </div>
                            <span class="kb-rozet {{ $ustDurum['renk'] }}" style="margin-left:8px;">{{ $ustDurum['tip'] === 'kendi' ? 'Üst banner var' : 'Üst banner yok' }}</span>
                            <span class="kb-chev" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                        </summary>
                        <div class="kb-panel">
                            {{-- Üst kategorinin kendi sayfası: alt kategorilere de miras kalır --}}
                            <details class="kb-sub" id="kb-{{ $parent->id }}" name="kb-alt-{{ $parent->id }}" data-kb-acc="alt-{{ $parent->id }}" @if($acikAnahtar === 'kb-'.$parent->id) open @endif>
                                <summary>
                                    <div class="kb-thumb">@if($ustDurum['gorsel'])<img src="{{ $ustDurum['gorsel'] }}" alt="" loading="lazy">@else ⛰️ @endif</div>
                                    <div style="min-width:0;">
                                        <div class="kb-sub-ad">{{ $parent->name }} <span class="kb-muted">— üst kategori sayfası</span></div>
                                        <div class="kb-sub-url">/{{ LandingSlug::forCategory($parent) }} · buraya yüklenen banner, kendi banner'ı olmayan alt kategorilere de uygulanır</div>
                                    </div>
                                    <span class="kb-rozet {{ $ustDurum['renk'] }}" style="margin-left:auto;">{{ $ustDurum['etiket'] }}</span>
                                    <span class="kb-chev" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                                </summary>
                                <div class="kb-sub-body">
                                    @include('admin.partials.category-banner-form', ['kategori' => $parent, 'banner' => $banners->get($parent->id), 'key' => 'kb-'.$parent->id, 'overlay' => $overlay])
                                </div>
                            </details>

                            @forelse($parent->children as $child)
                                @php $altDurum = $durum($child, $parent); @endphp
                                <details class="kb-sub" id="kb-{{ $child->id }}" name="kb-alt-{{ $parent->id }}" data-kb-acc="alt-{{ $parent->id }}" @if($acikAnahtar === 'kb-'.$child->id) open @endif>
                                    <summary>
                                        <div class="kb-thumb">@if($altDurum['gorsel'])<img src="{{ $altDurum['gorsel'] }}" alt="" loading="lazy">@else 🏷️ @endif</div>
                                        <div style="min-width:0;">
                                            <div class="kb-sub-ad">{{ $child->icon ? $child->icon.' ' : '' }}{{ $child->name }}@unless($child->is_active) <span class="kb-muted">(pasif kategori)</span>@endunless</div>
                                            <div class="kb-sub-url">/{{ LandingSlug::forCategory($child) }}</div>
                                        </div>
                                        <span class="kb-rozet {{ $altDurum['renk'] }}" style="margin-left:auto;">{{ $altDurum['etiket'] }}</span>
                                        <span class="kb-chev" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg></span>
                                    </summary>
                                    <div class="kb-sub-body">
                                        @include('admin.partials.category-banner-form', ['kategori' => $child, 'banner' => $banners->get($child->id), 'key' => 'kb-'.$child->id, 'overlay' => $overlay])
                                    </div>
                                </details>
                            @empty
                                <div class="kb-ust-not">Bu üst kategorinin alt kategorisi yok.</div>
                            @endforelse
                        </div>
                    </details>
                @endforeach
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Degrade formülü PHP ile AYNI sabitlerden (CategoryHero): sol uç = koyuluk +
    // LEFT_BOOST, sağ uç = koyuluk × RIGHT_FACTOR. İki yere ayrı yazılırsa
    // admin'de görülenle sitede çıkan görüntü sessizce ayrışır.
    var OV = @json($overlay);
    function overlayCss(d) {
        var a = Math.max(0, Math.min(100, Number(d))) / 100;
        var sol = Math.min(1, a + OV.left), sag = a * OV.right;
        return 'linear-gradient(90deg, rgba(' + OV.rgb + ',' + sol.toFixed(3) + ') 0%, rgba(' + OV.rgb + ',' + a.toFixed(3) + ') 48%, rgba(' + OV.rgb + ',' + sag.toFixed(3) + ') 100%)';
    }

    // Canlı önizleme: metin alanları, karartma ve seçilen dosya
    document.querySelectorAll('[data-kb-form]').forEach(function (form) {
        var prev = document.getElementById(form.getAttribute('data-kb-target'));
        if (!prev) return;
        form.querySelectorAll('[data-kb-field]').forEach(function (inp) {
            var hedef = prev.querySelector('[data-kb-text="' + inp.getAttribute('data-kb-field') + '"]');
            if (!hedef) return;
            inp.addEventListener('input', function () {
                hedef.textContent = inp.value.trim() !== '' ? inp.value : (hedef.getAttribute('data-kb-default') || '');
            });
        });
        var dark = form.querySelector('[data-kb-dark]');
        var darkOut = form.querySelector('[data-kb-dark-out]');
        var ov = prev.querySelector('[data-kb-ov]');
        if (dark && ov) dark.addEventListener('input', function () {
            ov.style.background = overlayCss(dark.value);
            if (darkOut) darkOut.textContent = '%' + dark.value;
        });
        var file = form.querySelector('[data-kb-file]');
        var img = prev.querySelector('[data-kb-img]');
        if (file && img) file.addEventListener('change', function () {
            var f = file.files && file.files[0];
            if (!f || !f.type.match(/^image\//)) return;
            var r = new FileReader();
            r.onload = function (e) {
                img.src = e.target.result; img.hidden = false;
                prev.classList.remove('kb-prev-bos');
                var hint = prev.querySelector('[data-kb-hint]'); if (hint) hint.remove();
            };
            r.readAsDataURL(f);
        });
    });

    // Akordeon: aynı gruptan biri açılınca diğerleri kapanır (üst kategoriler
    // kendi aralarında, bir üstün altındakiler kendi aralarında). <details name>
    // bunu yeni tarayıcılarda zaten yapar; eski tarayıcılar için JS.
    document.querySelectorAll('details[data-kb-acc]').forEach(function (d) {
        d.addEventListener('toggle', function () {
            if (!d.open) return;
            var grup = d.getAttribute('data-kb-acc');
            document.querySelectorAll('details[data-kb-acc="' + grup + '"]').forEach(function (o) {
                if (o !== d && o.open) o.open = false;
            });
        });
    });

    // Kayıt/hata sonrası ilgili panel açık geldi: görünür alana kaydır
    var hedef = @json($acikAnahtar);
    if (hedef) {
        var el = document.getElementById(hedef);
        if (el) setTimeout(function () { el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 50);
    }
})();
</script>
@endpush
