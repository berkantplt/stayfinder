{{--
    Kategori banner formu + canlı önizleme (tek kategori / genel varsayılan).

    @var \App\Models\Category|null        $kategori  null = genel varsayılan
    @var \App\Models\CategoryBanner|null  $banner    yoksa "ekle" formu
    @var string                           $key       akordeon/hata hedefi: kb-{id} | kb-genel
    @var array{rgb:string,left:float,right:float} $overlay  CategoryHero sabitleri (JS önizleme)
--}}
@php
    use App\Support\CategoryHero;
    use App\Support\LandingSlug;

    $vars = CategoryHero::defaults($kategori?->name ?? 'Turlar');
    $koyuluk = (int) old('darkness', $banner?->darkness ?? \App\Models\CategoryBanner::DEFAULT_DARKNESS);
    $hataliForm = old('form_key') === $key;
    $deger = fn (string $alan) => $hataliForm ? old($alan, $banner?->{$alan}) : $banner?->{$alan};
    $gorsel = $banner?->image_url;
    $onizlemeId = 'kb-prev-'.$key;
@endphp

<div class="kb-form-wrap">
    {{-- Canlı önizleme: sitedeki hero'nun küçük kopyası; aynı degrade formülü --}}
    <div class="kb-prev {{ $gorsel ? '' : 'kb-prev-bos' }}" id="{{ $onizlemeId }}">
        <img src="{{ $gorsel ?? '' }}" alt="" data-kb-img @if(!$gorsel) hidden @endif>
        <div class="kb-prev-ov" data-kb-ov style="background:{{ CategoryHero::overlayCss($koyuluk) }};"></div>
        <div class="kb-prev-body">
            <div class="kb-prev-eye" data-kb-text="eyebrow" data-kb-default="{{ $vars['eyebrow'] }}">{{ $deger('eyebrow') ?: $vars['eyebrow'] }}</div>
            <div class="kb-prev-title" data-kb-text="title" data-kb-default="{{ $vars['title'] }}">{{ $deger('title') ?: $vars['title'] }}</div>
            <div class="kb-prev-sub" data-kb-text="subtitle" data-kb-default="{{ $vars['subtitle'] }}">{{ $deger('subtitle') ?: $vars['subtitle'] }}</div>
        </div>
        <div class="kb-prev-cap" data-kb-text="caption" data-kb-default="">{{ $deger('caption') }}</div>
        @if(!$gorsel)
            <div class="kb-prev-hint" data-kb-hint>Görsel seçince burada görünür</div>
        @endif
    </div>

    <form method="POST"
          action="{{ $banner ? route('admin.category-banners.update', $banner) : route('admin.category-banners.store') }}"
          enctype="multipart/form-data" class="kb-form" data-kb-form data-kb-target="{{ $onizlemeId }}">
        @csrf
        @if($banner) @method('PUT') @endif
        <input type="hidden" name="form_key" value="{{ $key }}">
        @unless($banner)
            <input type="hidden" name="category_id" value="{{ $kategori?->id }}">
        @endunless

        @if($hataliForm)
            @include('partials.form-errors')
        @endif

        <div class="kb-grid">
            <div class="form-group kb-span">
                <label>Banner görseli {!! $banner ? '<span class="kb-muted">(değiştirmek için seç)</span>' : '<span style="color:#ef4444">*</span>' !!}</label>
                <input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-kb-file @unless($banner) required @endunless>
                <div class="kb-help">JPG/PNG/WebP, en çok 20 MB. Önerilen 1600×600 px, yatay — metin solda durur, ana motifi sağa koyun.</div>
            </div>
            <div class="form-group">
                <label>Üst başlık</label>
                <input type="text" name="eyebrow" maxlength="80" value="{{ $deger('eyebrow') }}" placeholder="{{ $vars['eyebrow'] }}" data-kb-field="eyebrow">
            </div>
            <div class="form-group">
                <label>Konum notu <span class="kb-muted">(sağ alt)</span></label>
                <input type="text" name="caption" maxlength="120" value="{{ $deger('caption') }}" placeholder="Ör: Mostar, Bosna-Hersek" data-kb-field="caption">
            </div>
            <div class="form-group kb-span">
                <label>Başlık</label>
                <input type="text" name="title" maxlength="120" value="{{ $deger('title') }}" placeholder="{{ $vars['title'] }}" data-kb-field="title">
            </div>
            <div class="form-group kb-span">
                <label>Alt başlık</label>
                <input type="text" name="subtitle" maxlength="220" value="{{ $deger('subtitle') }}" placeholder="{{ $vars['subtitle'] }}" data-kb-field="subtitle">
            </div>
            <div class="form-group kb-span">
                <label>Karartma: <strong data-kb-dark-out>%{{ $koyuluk }}</strong></label>
                <input type="range" name="darkness" min="0" max="100" value="{{ $koyuluk }}" data-kb-dark style="width:100%;accent-color:var(--accent);">
                <div class="kb-help">Soldaki metin zemini her zaman biraz daha koyu; sağ taraf fotoğrafı gösterir. Yazı okunmuyorsa artır.</div>
            </div>
        </div>

        <div class="kb-actions">
            <button type="submit" class="btn btn-primary">{{ $banner ? 'Kaydet' : 'Banner\'ı ekle' }}</button>
            @if($kategori)
                <a href="{{ LandingSlug::urlForCategory($kategori) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Sayfayı gör ↗</a>
            @endif
            <span class="kb-help" style="margin-left:auto;">Boş bırakılan metin alanları yerine soluk görünen varsayılan basılır.</span>
        </div>
    </form>

    @if($banner)
        <div class="kb-actions kb-actions-alt">
            <form method="POST" action="{{ route('admin.category-banners.toggle', $banner) }}">
                @csrf @method('PATCH')
                <button type="submit" class="btn btn-outline btn-sm">{{ $banner->is_active ? 'Yayından kaldır' : 'Yayına al' }}</button>
            </form>
            <form method="POST" action="{{ route('admin.category-banners.destroy', $banner) }}"
                  onsubmit="return confirm('Bu banner silinsin mi? Görsel de diskten kaldırılır.');">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline btn-sm" style="color:#dc2626;border-color:#fecaca;">Sil</button>
            </form>
            <span class="kb-help">Son güncelleme: {{ $banner->updated_at?->format('d.m.Y H:i') }}</span>
        </div>
    @endif
</div>
