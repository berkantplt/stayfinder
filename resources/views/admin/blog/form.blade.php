@extends('layouts.app')
@section('title', isset($post) ? 'Yazıyı Düzenle — Admin' : 'Yeni Blog Yazısı — Admin')

@section('styles')
/* ── Blog yazısı formu (admin) — çıplak CSS, layout <style> içine basılır ── */
.bf-icerik { max-width:94%; margin:0 auto; }
.bf-bas { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
.bf-grid { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:24px; align-items:start; }
.bf-ana > .p-kart + .p-kart { margin-top:16px; }
.bf-yan { position:sticky; top:90px; display:flex; flex-direction:column; gap:16px; }
.bf-etiket-satir { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }
.bf-etiket-satir label { margin:0; }
.bf-sayac { font-size:12px; font-weight:600; color:var(--p-metin-4); font-variant-numeric:tabular-nums; }
.bf-sayac.dolu { color:var(--p-uyari-metin); }
.bf-sayac.asti { color:var(--p-tehlike-metin); }
.bf-ipucu { font-size:12px; color:var(--p-metin-4); margin-top:6px; line-height:1.5; }
.bf-zorunlu { color:var(--p-tehlike); font-weight:700; }
.form-group textarea#content { min-height:360px; line-height:1.7; }
/* Yayın anahtarı: gerçek checkbox görünmez ama odaklanabilir kalır */
.bf-anahtar { position:relative; display:flex; align-items:center; gap:12px; padding:12px 14px; border:1px solid var(--p-cizgi); border-radius:12px; cursor:pointer; user-select:none; margin:0; }
.bf-anahtar input { position:absolute; opacity:0; width:0; height:0; margin:0; }
.bf-anahtar-ray { width:42px; height:24px; flex:none; border-radius:999px; background:#cbd5e1; position:relative; transition:background .2s; }
.bf-anahtar-ray::after { content:""; position:absolute; top:3px; left:3px; width:18px; height:18px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(0,0,0,.25); transition:transform .2s; }
.bf-anahtar input:checked + .bf-anahtar-ray { background:var(--p-vurgu); }
.bf-anahtar input:checked + .bf-anahtar-ray::after { transform:translateX(18px); }
.bf-anahtar input:focus-visible + .bf-anahtar-ray { box-shadow:0 0 0 3px var(--accent-bg); }
.bf-anahtar-metin { display:block; font-size:14px; font-weight:700; color:var(--p-metin); }
.bf-anahtar-alt { display:block; font-size:12px; color:var(--p-metin-3); font-weight:500; margin-top:1px; }
.bf-islem { display:flex; flex-direction:column; gap:8px; margin-top:16px; }
.bf-islem .btn { justify-content:center; width:100%; }
.bf-onizleme { width:100%; aspect-ratio:16/9; border-radius:10px; object-fit:cover; background:var(--p-zemin); display:none; margin-top:10px; }
.bf-onizleme.goster { display:block; }
.bf-bilgi { display:grid; grid-template-columns:auto 1fr; gap:6px 12px; font-size:13px; margin:0; }
.bf-bilgi dt { color:var(--p-metin-3); font-weight:600; white-space:nowrap; }
.bf-bilgi dd { color:var(--p-metin); margin:0; word-break:break-all; font-variant-numeric:tabular-nums; }
.bf-sil { width:100%; justify-content:center; margin-top:14px; }
/* Google sonuç önizlemesi: sadece his verir, birebir değil */
.bf-serp { border:1px solid var(--p-cizgi-acik); border-radius:10px; padding:12px 14px; background:var(--p-zemin); margin-top:14px; font-family:Arial, Helvetica, sans-serif; }
.bf-serp-url { font-size:12px; color:#4d5156; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.bf-serp-baslik { font-size:17px; color:#1a0dab; line-height:1.3; margin:2px 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.bf-serp-aciklama { font-size:13px; color:#4d5156; line-height:1.5; }
@media (max-width:1024px) { .bf-grid { grid-template-columns:minmax(0,1fr); } .bf-yan { position:static; } }
@media (max-width:768px) { .bf-icerik { max-width:none; } .bf-bas .btn { width:100%; justify-content:center; } }
@endsection

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div class="bf-icerik">
                @include('partials.breadcrumb', ['schema' => false, 'root' => ['name' => 'Admin', 'url' => route('admin.dashboard')], 'items' => [['name' => 'Blog Yönetimi', 'url' => route('admin.blog.index')], ['name' => isset($post) ? 'Düzenle' : 'Yeni Yazı']]])

                <div class="bf-bas">
                    <div>
                        <h1 class="p-sayfa-baslik">{{ isset($post) ? 'Yazıyı Düzenle' : 'Yeni Blog Yazısı' }}</h1>
                        <p class="p-alt">{{ isset($post) ? 'Kaydedilen değişiklik sitede anında görünür.' : 'Taslak olarak kaydedip hazır olunca yayınlayabilirsiniz.' }}</p>
                    </div>
                    @if(isset($post) && $post->is_published)
                        <a href="{{ route('blog.show', $post) }}" target="_blank" rel="noopener" class="btn btn-outline btn-sm">Sitede görüntüle ↗</a>
                    @endif
                </div>

                @include('partials.form-errors')

                <form method="POST" action="{{ isset($post) ? route('admin.blog.update', $post) : route('admin.blog.store') }}" id="blogForm">
                    @csrf
                    @if(isset($post)) @method('PUT') @endif

                    <div class="bf-grid">
                        {{-- Ana sütun: metin --}}
                        <div class="bf-ana">
                            <div class="p-kart">
                                <div class="form-group">
                                    <label for="title">Başlık <span class="bf-zorunlu" aria-hidden="true">*</span></label>
                                    <input type="text" id="title" name="title" value="{{ old('title', $post->title ?? '') }}" placeholder="Örn. Kapadokya'da 3 Gün: Balon, Vadi ve Yeraltı Şehirleri" required maxlength="255" autocomplete="off">
                                    @error('title')<p class="p-hata">{{ $message }}</p>@enderror
                                </div>

                                <div class="form-group">
                                    <div class="bf-etiket-satir">
                                        <label for="excerpt">Kısa Özet</label>
                                        <span class="bf-sayac" id="excerpt-sayac" aria-live="polite"></span>
                                    </div>
                                    <textarea id="excerpt" name="excerpt" rows="3" maxlength="500" data-sayac="excerpt-sayac" placeholder="Liste sayfasında ve kartlarda görünen 1-2 cümle…">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
                                    @error('excerpt')<p class="p-hata">{{ $message }}</p>@enderror
                                </div>

                                <div class="form-group" style="margin-bottom:0;">
                                    <label for="content">İçerik <span class="bf-zorunlu" aria-hidden="true">*</span></label>
                                    <textarea id="content" name="content" rows="16" placeholder="Yazı metni… Paragraflar için boş satır bırakın." required>{{ old('content', $post->content ?? '') }}</textarea>
                                    <p class="bf-ipucu">Düz metin olarak kaydedilir: satır sonları sitede korunur, HTML etiketleri işlenmez.</p>
                                    @error('content')<p class="p-hata">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="p-kart">
                                <div class="p-kart-baslik">Arama Motoru (SEO)</div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <div class="bf-etiket-satir">
                                        <label for="meta_description">Meta Açıklama</label>
                                        <span class="bf-sayac" id="meta-sayac" aria-live="polite"></span>
                                    </div>
                                    <input type="text" id="meta_description" name="meta_description" value="{{ old('meta_description', $post->meta_description ?? '') }}" maxlength="160" data-sayac="meta-sayac" placeholder="Google sonuçlarında başlığın altında görünen açıklama">
                                    <p class="bf-ipucu">Boş bırakılırsa kısa özet kullanılır.</p>
                                    @error('meta_description')<p class="p-hata">{{ $message }}</p>@enderror
                                </div>
                                <div class="bf-serp" aria-hidden="true">
                                    <div class="bf-serp-url">{{ request()->getHost() }} › blog › {{ isset($post) ? $post->slug : 'yazi-adresi' }}</div>
                                    <div class="bf-serp-baslik" id="serp-baslik">Yazı başlığı</div>
                                    <div class="bf-serp-aciklama" id="serp-aciklama">Meta açıklama veya kısa özet burada görünür.</div>
                                </div>
                            </div>
                        </div>

                        {{-- Yan sütun: yayın, sınıflandırma, görsel, kayıt bilgisi --}}
                        <aside class="bf-yan">
                            <div class="p-kart">
                                <div class="p-kart-baslik">Yayın</div>
                                {{-- hidden 0 ŞART: işaret kaldırılınca alan hiç POST edilmez; old() de
                                     boş kalıp doğrulama hatasında eski işaret geri gelirdi. --}}
                                <input type="hidden" name="is_published" value="0">
                                <label class="bf-anahtar" for="is_published">
                                    <input type="checkbox" name="is_published" id="is_published" value="1" @checked(old('is_published', ($post->is_published ?? false) ? '1' : '0') === '1')>
                                    <span class="bf-anahtar-ray" aria-hidden="true"></span>
                                    <span>
                                        <span class="bf-anahtar-metin" id="yayin-metin">Yayında</span>
                                        <span class="bf-anahtar-alt" id="yayin-alt">Kaydedince sitede herkese görünür.</span>
                                    </span>
                                </label>
                                <div class="bf-islem">
                                    <button type="submit" class="btn btn-primary">{{ isset($post) ? 'Değişiklikleri Kaydet' : 'Yazıyı Kaydet' }}</button>
                                    <a href="{{ route('admin.blog.index') }}" class="btn btn-outline">İptal</a>
                                </div>
                            </div>

                            <div class="p-kart">
                                <div class="p-kart-baslik">Sınıflandırma</div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label for="category">Kategori</label>
                                    <select id="category" name="category" required>
                                        @foreach($kategoriler as $kat)
                                            <option value="{{ $kat }}" @selected(old('category', $post->category ?? 'Rehber') === $kat)>{{ $kat }}</option>
                                        @endforeach
                                    </select>
                                    @error('category')<p class="p-hata">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="p-kart">
                                <div class="p-kart-baslik">Kapak Görseli</div>
                                <div class="form-group" style="margin-bottom:0;">
                                    <label for="image">Görsel adresi (URL)</label>
                                    <input type="url" id="image" name="image" value="{{ old('image', $post->image ?? '') }}" placeholder="https://…" maxlength="255" inputmode="url" autocomplete="off">
                                    <p class="bf-ipucu">Yatay (16:9) bir görsel en iyi sonucu verir. Boş bırakılırsa sitede renkli yer tutucu çıkar.</p>
                                    @error('image')<p class="p-hata">{{ $message }}</p>@enderror
                                    @php $kapak = old('image', $post->image ?? ''); @endphp
                                    {{-- data-yedek-atla: layout'un genel kırık-görsel yedeği devreye girmesin, hata olunca gizlensin --}}
                                    <img id="image-onizleme" class="bf-onizleme {{ $kapak ? 'goster' : '' }}" alt="Kapak görseli önizlemesi" data-yedek-atla @if($kapak) src="{{ $kapak }}" @endif>
                                </div>
                            </div>

                            @isset($post)
                            <div class="p-kart">
                                <div class="p-kart-baslik">Kayıt Bilgisi</div>
                                <dl class="bf-bilgi">
                                    <dt>Adres</dt><dd>/blog/{{ $post->slug }}</dd>
                                    <dt>Oluşturma</dt><dd>{{ $post->created_at->format('d-m-Y H:i') }}</dd>
                                    <dt>Son düzenleme</dt><dd>{{ $post->updated_at->format('d-m-Y H:i') }}</dd>
                                    @if($post->published_at)
                                        <dt>İlk yayın</dt><dd>{{ $post->published_at->format('d-m-Y H:i') }}</dd>
                                    @endif
                                </dl>
                                <p class="bf-ipucu">Adres, başlık değişse de sabit kalır; paylaşılmış bağlantılar kırılmaz.</p>
                                <button type="submit" form="yaziSilForm" class="p-btn p-btn-tehlike bf-sil">Yazıyı Sil</button>
                            </div>
                            @endisset
                        </aside>
                    </div>
                </form>

                @isset($post)
                {{-- Ana formun DIŞINDA (iç içe form geçersiz); kart içindeki düğme form="" ile buraya bağlı --}}
                <form id="yaziSilForm" method="POST" action="{{ route('admin.blog.destroy', $post) }}" onsubmit="return confirm(@js('“' . $post->title . '” kalıcı olarak silinsin mi? Bu işlem geri alınamaz.'))">
                    @csrf @method('DELETE')
                </form>
                @endisset
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    // Karakter sayaçları (maxlength JS .length ile aynı birimi sayar; Türkçe harfler tek birim)
    document.querySelectorAll('[data-sayac]').forEach(function (alan) {
        var hedef = document.getElementById(alan.dataset.sayac);
        var max = parseInt(alan.getAttribute('maxlength'), 10) || 0;
        if (!hedef) return;
        function guncelle() {
            var n = alan.value.length;
            hedef.textContent = max ? n + ' / ' + max : String(n);
            hedef.classList.toggle('dolu', max > 0 && n >= max * 0.9 && n < max);
            hedef.classList.toggle('asti', max > 0 && n >= max);
        }
        alan.addEventListener('input', guncelle);
        guncelle();
    });

    // Yayın anahtarı metni
    var kutu = document.getElementById('is_published');
    var yayinMetin = document.getElementById('yayin-metin');
    var yayinAlt = document.getElementById('yayin-alt');
    function yayinGuncelle() {
        yayinMetin.textContent = kutu.checked ? 'Yayında' : 'Taslak';
        yayinAlt.textContent = kutu.checked ? 'Kaydedince sitede herkese görünür.' : 'Yalnızca bu panelde görünür, sitede 404 verir.';
    }
    if (kutu && yayinMetin && yayinAlt) { kutu.addEventListener('change', yayinGuncelle); yayinGuncelle(); }

    // Kapak görseli canlı önizleme
    var adres = document.getElementById('image');
    var onizleme = document.getElementById('image-onizleme');
    function kapakGuncelle() {
        var v = adres.value.trim();
        if (/^https?:\/\/\S+$/i.test(v)) { onizleme.src = v; onizleme.classList.add('goster'); }
        else { onizleme.removeAttribute('src'); onizleme.classList.remove('goster'); }
    }
    if (adres && onizleme) {
        onizleme.addEventListener('error', function () { onizleme.classList.remove('goster'); });
        adres.addEventListener('input', kapakGuncelle);
        adres.addEventListener('change', kapakGuncelle);
    }

    // Google sonuç önizlemesi
    var baslik = document.getElementById('title');
    var meta = document.getElementById('meta_description');
    var ozet = document.getElementById('excerpt');
    var serpBaslik = document.getElementById('serp-baslik');
    var serpAciklama = document.getElementById('serp-aciklama');
    function serpGuncelle() {
        serpBaslik.textContent = baslik.value.trim() || 'Yazı başlığı';
        var a = meta.value.trim() || ozet.value.trim();
        serpAciklama.textContent = a ? (a.length > 160 ? a.slice(0, 157) + '…' : a) : 'Meta açıklama veya kısa özet burada görünür.';
    }
    if (baslik && meta && ozet && serpBaslik && serpAciklama) {
        [baslik, meta, ozet].forEach(function (x) { x.addEventListener('input', serpGuncelle); });
        serpGuncelle();
    }
})();
</script>
@endpush
