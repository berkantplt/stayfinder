@extends('layouts.app')
@section('title', 'Banner Yönetimi — Admin')

@section('content')
{{-- Perde degradesi PHP tarafıyla AYNI duraklardan üretilir (HeroVeil::STOPS).
     Formülü buraya elle kopyalarsak admin'de görülenle sitede çıkan görüntü
     sessizce ayrışır. --}}
<script>
    const PERDE_DURAKLARI = @json(\App\Support\HeroVeil::STOPS);
    function perdeCss(deger) {
        const k = Math.max(0, Math.min(100, Number(deger))) / 100;
        const parcalar = PERDE_DURAKLARI.map(
            ([alfa, yuzde]) => `rgba(255,255,255,${+(alfa * k).toFixed(3)}) ${yuzde}%`
        );
        return `linear-gradient(97deg, ${parcalar.join(', ')})`;
    }
</script>
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:30px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;background:var(--accent-bg);color:var(--accent-dark);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;">🖼️</div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Banner Yönetimi</h1>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
            @endif

            {{-- Hero beyaz perdesi: TEK ayar, tüm banner'lara uygulanır --}}
            @php $perde = \App\Support\HeroVeil::strength(); @endphp
            <div class="stat-card" style="padding:26px 30px;margin-bottom:32px;">
                <form method="POST" action="{{ route('admin.banners.veil') }}">
                    @csrf @method('PUT')
                    <div style="display:flex;align-items:flex-end;gap:24px;flex-wrap:wrap;">
                        <div class="form-group" style="margin-bottom:0;flex:1;min-width:280px;">
                            <label style="font-size:13px;color:#475569;font-weight:700;">
                                Beyaz Perde (tüm görseller): <span id="veilVal" style="font-weight:800;color:var(--accent);">%{{ $perde }}</span>
                            </label>
                            <input type="range" name="white_veil" min="0" max="100" value="{{ $perde }}"
                                oninput="document.getElementById('veilVal').textContent='%'+this.value;
                                         document.querySelectorAll('.js-veil').forEach(e => e.style.background = perdeCss(this.value))"
                                style="width:100%;accent-color:var(--accent);">
                            <div style="font-size:11px;color:#64748b;margin-top:6px;">
                                Ana sayfada başlığın arkasındaki beyaz geçiş. Tek ayar, tüm banner'lara birden uygulanır —
                                aşağıdaki önizlemelerde anında görürsün. Başlık okunmuyorsa artır, fotoğraf fazla soluyorsa azalt.
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding:12px 28px;">Perdeyi Kaydet</button>
                    </div>
                </form>
            </div>

            {{-- Mobil hero desen katmanı: şeffaflık + koyuluk (TEK ayar, HeroDeco) --}}
            @php
                $mdSeffaflik = \App\Support\HeroDeco::opacity();
                $mdKoyuluk = \App\Support\HeroDeco::darkness();
            @endphp
            <div class="stat-card" style="padding:26px 30px;margin-bottom:32px;">
                <form method="POST" action="{{ route('admin.banners.deco') }}">
                    @csrf @method('PUT')
                    <div style="display:flex;align-items:flex-end;gap:24px;flex-wrap:wrap;">
                        <div class="form-group" style="margin-bottom:0;flex:1;min-width:220px;">
                            <label style="font-size:13px;color:#475569;font-weight:700;">
                                Mobil Desen Şeffaflığı: <span id="decoOpVal" style="font-weight:800;color:var(--accent);">%{{ $mdSeffaflik }}</span>
                            </label>
                            <input type="range" name="deco_opacity" min="0" max="100" value="{{ $mdSeffaflik }}"
                                oninput="document.getElementById('decoOpVal').textContent='%'+this.value; decoOnizle()"
                                style="width:100%;accent-color:var(--accent);">
                        </div>
                        <div class="form-group" style="margin-bottom:0;flex:1;min-width:220px;">
                            <label style="font-size:13px;color:#475569;font-weight:700;">
                                Mobil Desen Koyuluğu: <span id="decoDkVal" style="font-weight:800;color:var(--accent);">%{{ $mdKoyuluk }}</span>
                            </label>
                            <input type="range" name="deco_darkness" min="0" max="100" value="{{ $mdKoyuluk }}"
                                oninput="document.getElementById('decoDkVal').textContent='%'+this.value; decoOnizle()"
                                style="width:100%;accent-color:var(--accent);">
                        </div>
                        {{-- Canlı mini önizleme: sitedekiyle AYNI şekiller (küçük kopya) --}}
                        <div style="flex:none;">
                            <svg id="decoOnizleme" viewBox="0 0 375 430" width="86" height="99" style="border-radius:10px;background:#eef7f8;{{ \App\Support\HeroDeco::css() }}" aria-hidden="true">
                                @include('partials.hero-deco-shapes')
                            </svg>
                        </div>
                        <button type="submit" class="btn btn-primary" style="padding:12px 28px;">Deseni Kaydet</button>
                    </div>
                    <div style="font-size:11px;color:#64748b;margin-top:10px;">
                        Mobil ana sayfa hero'sundaki turkuaz desen katmanı. Şeffaflık %0 = desen tamamen gizli;
                        koyuluk arttıkça desen kararır (fotoğraf/yazı kontrastına göre ayarla).
                    </div>
                </form>
            </div>
            <script>
                function decoOnizle() {
                    var o = document.querySelector('[name="deco_opacity"]').value / 100;
                    var k = document.querySelector('[name="deco_darkness"]').value / 100;
                    var el = document.getElementById('decoOnizleme');
                    el.style.opacity = o;
                    el.style.filter = 'brightness(' + (1 - (1 - @json(\App\Support\HeroDeco::MIN_BRIGHTNESS)) * k).toFixed(3) + ')';
                }
            </script>

            {{-- Add New Banner --}}
            <div class="stat-card" style="padding:30px;margin-bottom:32px;border-left:4px solid var(--accent) !important;">
                <h3 style="font-size:18px;font-weight:800;letter-spacing:-0.3px;color:#0f172a;margin-bottom:24px;">Yeni Banner Ekle</h3>
                <form method="POST" action="{{ route('admin.banners.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Banner Başlığı <span style="color:#ef4444">*</span></label>
                            <input type="text" name="title" required placeholder="Ör: Kapadokya" value="{{ old('title') }}" style="padding:14px;background:#f8fafc;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Görsel <span style="color:#ef4444">*</span></label>
                            <input type="file" name="image" accept="image/*" required style="padding:11px;background:#f8fafc;">
                            <div style="font-size:11px;color:#64748b;margin-top:6px;">Önerilen boyut: En az 1920x1080 piksel (Full HD)</div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Bulanıklık (Blur): <span id="newBlurVal" style="font-weight:700;color:var(--accent);">0px</span></label>
                            <input type="range" name="blur" min="0" max="20" value="0" oninput="document.getElementById('newBlurVal').textContent=this.value+'px'" style="width:100%;accent-color:var(--accent);">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Karanlık (Overlay): <span id="newDarkVal" style="font-weight:700;color:var(--accent);">40%</span></label>
                            <input type="range" name="darkness" min="0" max="100" value="40" oninput="document.getElementById('newDarkVal').textContent=this.value+'%'" style="width:100%;accent-color:var(--accent);">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Sıralama</label>
                            <input type="number" name="sort_order" value="0" style="padding:14px;background:#f8fafc;">
                        </div>
                    </div>
                    <div style="margin-top:24px;display:flex;justify-content:flex-end;">
                        <button type="submit" class="btn btn-primary" style="padding:14px 32px;font-size:15px;">Banner Ekle</button>
                    </div>
                </form>
            </div>

            {{-- Existing Banners --}}
            <div class="stat-card" style="padding:30px;">
                <h3 style="font-size:18px;font-weight:800;margin-bottom:24px;color:#0f172a;letter-spacing:-0.3px;">Mevcut Bannerlar ({{ $banners->count() }})</h3>

                @forelse($banners as $banner)
                <div style="border:1px solid #e2e8f0;border-radius:16px;padding:0;margin-bottom:20px;overflow:hidden;{{ !$banner->is_active ? 'opacity:0.5;' : '' }} transition:all 0.2s;">
                    {{-- Preview --}}
                    <div id="preview-{{ $banner->id }}" style="position:relative;height:200px;overflow:hidden;">
                        <img src="{{ $banner->image_url }}" alt="{{ $banner->title }}" style="width:100%;height:100%;object-fit:cover;filter:blur({{ $banner->blur }}px);">
                        <div id="overlay-{{ $banner->id }}" style="position:absolute;inset:0;background:rgba(0,0,0,{{ $banner->darkness / 100 }});"></div>
                        <div class="js-veil" style="position:absolute;inset:0;background:{{ \App\Support\HeroVeil::css() }};"></div>
                        {{-- Önizleme ana sayfanın kendisiyle aynı: koyu başlık + beyaz perde.
                             Beyaz metinle önizlersek admin, perde ayarının okunurluğa
                             ne yaptığını göremez. --}}
                        <div style="position:absolute;inset:0;display:flex;align-items:center;padding:0 32px;">
                            <div>
                                <div style="font-size:20px;font-weight:800;color:#0f172a;letter-spacing:-0.4px;">Hayalindeki turu <span style="color:#0d9488;">en uygun fiyatla</span> bul</div>
                                <div style="font-size:12px;color:#475569;margin-top:4px;font-weight:600;">
                                    {{ $banner->title }} · {{ $banner->is_active ? '✅ Aktif' : '❌ Pasif' }} · Sıra: {{ $banner->sort_order }} · Blur: {{ $banner->blur }}px · Karanlık: {{ $banner->darkness }}%
                                </div>
                            </div>
                        </div>
                    </div>
                    {{-- Edit Form --}}
                    <form method="POST" action="{{ route('admin.banners.update', $banner) }}" enctype="multipart/form-data" style="padding:24px;">
                        @csrf @method('PUT')
                        <div style="display:grid;grid-template-columns:1.5fr 1fr 1fr;gap:20px;align-items:end;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Başlık</label>
                                <input type="text" name="title" value="{{ $banner->title }}" required style="padding:12px;background:#f8fafc;">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Görsel Değiştir</label>
                                <input type="file" name="image" accept="image/*" style="padding:9px;background:#f8fafc;">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Sıralama</label>
                                <input type="number" name="sort_order" value="{{ $banner->sort_order }}" style="padding:12px;background:#f8fafc;">
                            </div>
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-top:16px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Bulanıklık: <span id="blurVal{{ $banner->id }}" style="font-weight:700;color:var(--accent);">{{ $banner->blur }}px</span></label>
                                <input type="range" name="blur" min="0" max="20" value="{{ $banner->blur }}"
                                    oninput="document.getElementById('blurVal{{ $banner->id }}').textContent=this.value+'px';document.querySelector('#preview-{{ $banner->id }} img').style.filter='blur('+this.value+'px)'"
                                    style="width:100%;accent-color:var(--accent);">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;color:#475569;">Karanlık: <span id="darkVal{{ $banner->id }}" style="font-weight:700;color:var(--accent);">{{ $banner->darkness }}%</span></label>
                                <input type="range" name="darkness" min="0" max="100" value="{{ $banner->darkness }}"
                                    oninput="document.getElementById('darkVal{{ $banner->id }}').textContent=this.value+'%';document.getElementById('overlay-{{ $banner->id }}').style.background='rgba(0,0,0,'+(this.value/100)+')'"
                                    style="width:100%;accent-color:var(--accent);">
                            </div>
                        </div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:12px;flex-wrap:wrap;">
                            <div style="display:flex;gap:8px;">
                                <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <a href="{{ route('admin.banners.toggle', $banner) }}" class="btn btn-outline btn-sm" onclick="event.preventDefault();document.getElementById('toggleForm{{ $banner->id }}').submit();">
                                    {{ $banner->is_active ? '⏸️ Pasifleştir' : '▶️ Aktifleştir' }}
                                </a>
                                <button type="button" class="btn btn-danger btn-sm" onclick="if(confirm('Bu banner silinsin mi?'))document.getElementById('deleteForm{{ $banner->id }}').submit();">
                                    🗑️ Sil
                                </button>
                            </div>
                        </div>
                    </form>
                    {{-- Hidden forms --}}
                    <form id="toggleForm{{ $banner->id }}" method="POST" action="{{ route('admin.banners.toggle', $banner) }}" style="display:none;">@csrf @method('PATCH')</form>
                    <form id="deleteForm{{ $banner->id }}" method="POST" action="{{ route('admin.banners.destroy', $banner) }}" style="display:none;">@csrf @method('DELETE')</form>
                </div>
                @empty
                <div style="text-align:center;color:#64748b;font-size:14px;padding:48px;background:#f8fafc;border-radius:16px;border:1px dashed #cbd5e1;">
                    <div style="font-size:32px;margin-bottom:12px;">🖼️</div>
                    <div style="font-weight:600;color:#0f172a;margin-bottom:4px;">Henüz banner yok</div>
                    <div>Yukarıdaki formdan yeni bir banner ekleyebilirsiniz.</div>
                </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
