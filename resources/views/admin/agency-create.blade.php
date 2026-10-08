@extends('layouts.app')
@section('title', 'Acenta Ekle — Admin')

@section('styles')
/* ── Acenta ekleme formu (admin) — çıplak CSS, layout <style> içine basılır ── */
.af-icerik { max-width:94%; margin:0 auto; }
.af-bas { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:20px; }
.af-bas h1 { margin:0; text-wrap:balance; }
.af-bas .p-alt { margin:4px 0 0; max-width:62ch; }
.af-grid { display:grid; grid-template-columns:minmax(0,1fr) 340px; gap:24px; align-items:start; }
.af-ana > .p-kart + .p-kart { margin-top:16px; }
.af-yan { position:sticky; top:90px; display:flex; flex-direction:column; gap:16px; }
.af-kart-bas { margin-bottom:18px; }
.af-kart-bas .p-kart-baslik { margin:0 0 4px; }
.af-kart-alt { font-size:12.5px; color:var(--p-metin-3); margin:0; }
.af-zorunlu { color:var(--p-tehlike); font-weight:700; }
.af-ipucu { font-size:12px; color:var(--p-metin-4); margin:6px 0 0; line-height:1.5; }
.af-etiket-satir { display:flex; align-items:center; justify-content:space-between; gap:8px; margin-bottom:6px; }
.af-etiket-satir label { margin:0; }
.af-uret { border:none; background:none; padding:2px 0; font:inherit; font-size:12.5px; font-weight:700; color:var(--accent-ink); cursor:pointer; border-radius:6px; }
.af-uret:hover { text-decoration:underline; }
.af-sifre input { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; letter-spacing:.3px; }
/* Giriş akışı kutusu: parola doluysa "parola sizden", boşsa "bağlantı" sürümü görünür */
.af-akis { border:1px solid #bfdbfe; background:var(--p-bilgi-zemin); border-radius:12px; padding:14px 16px; margin-top:16px; }
.af-akis[hidden] { display:none; }
.af-akis-baslik { font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--p-bilgi-metin); margin:0 0 10px; }
.af-akis ol { margin:0; padding:0; list-style:none; display:grid; gap:8px; }
.af-akis li { display:grid; grid-template-columns:22px 1fr; gap:10px; font-size:13px; color:var(--p-metin-2); line-height:1.5; }
.af-akis li b { color:var(--p-metin); font-weight:600; }
.af-no { width:22px; height:22px; border-radius:50%; background:#fff; border:1.5px solid #bfdbfe; color:var(--p-bilgi-metin); font-size:11.5px; font-weight:800; display:grid; place-items:center; font-variant-numeric:tabular-nums; }
.af-hesap { display:flex; align-items:center; gap:10px; margin-top:14px; padding:10px 12px; border:1px dashed var(--p-cizgi); border-radius:10px; background:var(--p-zemin); font-size:13px; color:var(--p-metin-3); flex-wrap:wrap; }
.af-hesap b { color:var(--p-metin); font-weight:700; }
/* Yan sütun */
.af-durum { display:grid; gap:10px; margin:0; }
.af-durum-satir { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; }
.af-durum-satir dt { font-size:13px; color:var(--p-metin-3); font-weight:600; }
.af-durum-satir dd { margin:0; text-align:right; }
.af-durum-not { display:block; font-size:11.5px; color:var(--p-metin-4); margin-top:3px; line-height:1.4; max-width:170px; }
.af-islem { display:flex; flex-direction:column; gap:8px; margin-top:18px; padding-top:18px; border-top:1px solid var(--p-cizgi-acik); }
.af-islem .btn { width:100%; justify-content:center; }
.af-onizleme { display:flex; gap:12px; align-items:center; }
.af-avatar { width:48px; height:48px; border-radius:12px; flex:none; display:grid; place-items:center; font-weight:800; font-size:16px; color:#fff; background:linear-gradient(135deg,#0d9488,#115e59); letter-spacing:.5px; }
.af-on-ad { font-weight:700; font-size:15px; color:var(--p-metin); line-height:1.25; overflow-wrap:anywhere; }
.af-on-meta { font-size:12.5px; color:var(--p-metin-3); margin-top:3px; overflow-wrap:anywhere; }
.af-on-adres { margin-top:12px; font-size:12px; color:var(--p-metin-4); font-family:ui-monospace, SFMono-Regular, Menlo, monospace; background:var(--p-zemin); border:1px solid var(--p-cizgi-acik); border-radius:8px; padding:7px 10px; overflow-wrap:anywhere; }
.af-on-adres b { color:var(--p-metin-2); font-weight:600; }
.af-sonra { margin:0; padding:0; list-style:none; display:grid; gap:10px; }
.af-sonra li { display:grid; grid-template-columns:18px 1fr; gap:10px; font-size:13px; color:var(--p-metin-2); line-height:1.45; }
.af-sonra .af-sira { color:var(--accent-ink); font-weight:800; font-variant-numeric:tabular-nums; }
.af-sonra a { color:var(--accent-ink); font-weight:600; }
@media (max-width:1024px) { .af-grid { grid-template-columns:minmax(0,1fr); } .af-yan { position:static; } }
@media (max-width:768px) { .af-icerik { max-width:none; } .af-bas .btn { width:100%; justify-content:center; } .af-durum-not { max-width:none; } }
@endsection

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div class="af-icerik">
                @include('partials.breadcrumb', ['schema' => false, 'root' => ['name' => 'Admin', 'url' => route('admin.dashboard')], 'items' => [['name' => 'Acentalar', 'url' => route('admin.agencies')], ['name' => 'Yeni Acenta']]])

                <div class="af-bas">
                    <div>
                        <h1 class="p-sayfa-baslik">Yeni Acenta Ekle</h1>
                        <p class="p-alt">Kaydedince acenta onaylı ve aktif olarak açılır. Parolayı siz belirleyebilir ya da acentanın tek kullanımlık bağlantıyla kendisinin seçmesini sağlayabilirsiniz.</p>
                    </div>
                    <a href="{{ route('admin.agencies') }}" class="btn btn-outline btn-sm">← Acentalara dön</a>
                </div>

                @include('partials.form-errors')

                <form method="POST" action="{{ route('admin.agencies.store') }}" id="acentaForm">
                    @csrf
                    <div class="af-grid">
                        {{-- Ana sütun --}}
                        <div class="af-ana">
                            <div class="p-kart">
                                <div class="af-kart-bas">
                                    <h2 class="p-kart-baslik">Acenta Bilgileri</h2>
                                    <p class="af-kart-alt">Sitede, acenta kartlarında ve tur sayfalarında görünen kimlik bilgileri.</p>
                                </div>

                                <div class="form-group">
                                    <label for="name">Acenta Adı <span class="af-zorunlu" aria-hidden="true">*</span></label>
                                    <input type="text" id="name" name="name" value="{{ old('name') }}" placeholder="Örn. Jolly Tur" required maxlength="255" autocomplete="organization">
                                    <p class="af-ipucu">Sayfa adresi (slug) bu addan türetilir ve sonradan değişmez.</p>
                                    @error('name')<p class="p-hata">{{ $message }}</p>@enderror
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label for="phone">Telefon</label>
                                        <input type="text" id="phone" name="phone" value="{{ old('phone') }}" placeholder="0212 000 00 00" inputmode="tel" autocomplete="tel" maxlength="50">
                                        <p class="af-ipucu">Acenta sayfasında ve admin listesinde görünür.</p>
                                        @error('phone')<p class="p-hata">{{ $message }}</p>@enderror
                                    </div>
                                    <div class="form-group">
                                        <label for="website_url">Web Sitesi</label>
                                        <input type="url" id="website_url" name="website_url" value="{{ old('website_url') }}" placeholder="https://www.acenta.com" inputmode="url" autocomplete="url" maxlength="255">
                                        <p class="af-ipucu">"Siteyi Aç" bağlantısı olur; https:// ile başlamalı.</p>
                                        @error('website_url')<p class="p-hata">{{ $message }}</p>@enderror
                                    </div>
                                </div>

                                <div class="form-group" style="margin-bottom:0;">
                                    <label for="description">Açıklama</label>
                                    <textarea id="description" name="description" rows="4" placeholder="Uzmanlık alanı, kuruluş yılı, öne çıkan destinasyonlar…">{{ old('description') }}</textarea>
                                    <p class="af-ipucu">İsteğe bağlı. Acenta profil sayfasında gösterilir; acenta kendi panelinden düzenleyebilir.</p>
                                    @error('description')<p class="p-hata">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div class="p-kart">
                                <div class="af-kart-bas">
                                    <h2 class="p-kart-baslik">Panel Girişi</h2>
                                    <p class="af-kart-alt">Acentanın panele gireceği yönetici hesabı bu adresle açılır.</p>
                                </div>

                                <div class="form-group">
                                    <label for="email">E-posta <span class="af-zorunlu" aria-hidden="true">*</span></label>
                                    <input type="email" id="email" name="email" value="{{ old('email') }}" placeholder="yonetici@acenta.com" required maxlength="255" autocomplete="off">
                                    <p class="af-ipucu">Sistemde başka bir hesapta kayıtlı olmayan, acentanın kendi adresi olmalı.</p>
                                    @error('email')<p class="p-hata">{{ $message }}</p>@enderror
                                </div>

                                {{-- Parola: isteğe bağlı. Doğrulama hatasında Laravel parolayı old()'a
                                     geri basmaz (dontFlash); alan boş döner, akış kutusu da "bağlantı" olur. --}}
                                <div class="form-group" style="margin-bottom:0;">
                                    <div class="af-etiket-satir">
                                        <label for="password">Parola</label>
                                        <button type="button" class="af-uret" id="af-uret">Güçlü parola üret</button>
                                    </div>
                                    <div class="sifre-sar af-sifre">
                                        <input type="password" id="password" name="password" placeholder="En az 8 karakter" minlength="8" maxlength="72" autocomplete="new-password">
                                        <button type="button" class="sifre-goz" data-sifre-hedef="password" aria-label="Parolayı göster" aria-pressed="false">
                                            @include('partials.icon-eye')
                                        </button>
                                    </div>
                                    <p class="af-ipucu">İsteğe bağlı. Doldurursanız acenta bu parolayla hemen girer; boş bırakırsanız parolasını tek kullanımlık bağlantıyla kendisi belirler.</p>
                                    @error('password')<p class="p-hata">{{ $message }}</p>@enderror
                                </div>

                                <div class="af-akis" role="note" id="af-akis-parola" hidden>
                                    <p class="af-akis-baslik">Giriş nasıl kurulur · parola sizden</p>
                                    <ol>
                                        <li><span class="af-no">1</span><span><b>Kaydet.</b> Acenta ve yönetici hesabı tek işlemde oluşur; parola şifrelenerek saklanır, bir daha gösterilmez.</span></li>
                                        <li><span class="af-no">2</span><span><b>Parolayı iletin.</b> E-posta ve parolayı acentaya güvenli bir kanaldan siz verirsiniz.</span></li>
                                        <li><span class="af-no">3</span><span><b>Acenta hemen girer.</b> İsterse panelden ya da "Şifremi unuttum" ile değiştirir.</span></li>
                                    </ol>
                                </div>

                                <div class="af-akis" role="note" id="af-akis-baglanti">
                                    <p class="af-akis-baslik">Giriş nasıl kurulur · parola acentadan</p>
                                    <ol>
                                        <li><span class="af-no">1</span><span><b>Kaydet.</b> Acenta ve yönetici hesabı tek işlemde oluşur.</span></li>
                                        <li><span class="af-no">2</span><span><b>Bağlantı gider.</b> Tek kullanımlık "parola belirle" bağlantısı acentanın e-postasına gönderilir. Posta tanımlı değilse bağlantı size bir kez gösterilir ve {{ (int) config('auth.passwords.users.expire', 60) }} dakika geçerlidir.</span></li>
                                        <li><span class="af-no">3</span><span><b>Acenta parolasını seçer.</b> Süresi dolarsa "Şifremi unuttum" ile yenisini alır.</span></li>
                                    </ol>
                                </div>

                                <div class="af-hesap" aria-live="polite">
                                    <span>Oluşacak hesap:</span>
                                    <b id="af-hesap-ad">Acenta Yönetici</b>
                                    <span aria-hidden="true">·</span>
                                    <span id="af-hesap-eposta">e-posta</span>
                                </div>
                            </div>
                        </div>

                        {{-- Yan sütun --}}
                        <aside class="af-yan">
                            <div class="p-kart">
                                <div class="af-kart-bas">
                                    <h2 class="p-kart-baslik">Kayıt</h2>
                                    <p class="af-kart-alt">Admin eliyle açılan acenta başvuru adımını atlar.</p>
                                </div>
                                <dl class="af-durum">
                                    <div class="af-durum-satir">
                                        <dt>Onay durumu</dt>
                                        <dd><span class="p-etiket p-etiket-basari">Onaylı</span></dd>
                                    </div>
                                    <div class="af-durum-satir">
                                        <dt>Durum</dt>
                                        <dd><span class="p-etiket p-etiket-basari">Aktif</span></dd>
                                    </div>
                                    <div class="af-durum-satir">
                                        <dt>Kategori yetkisi</dt>
                                        <dd><span class="p-etiket p-etiket-notr">Yok</span><span class="af-durum-not">Kaydettikten sonra Kategori Yetkilendirme'den verilir.</span></dd>
                                    </div>
                                </dl>
                                <div class="af-islem">
                                    <button type="submit" class="btn btn-primary">Acenta Oluştur</button>
                                    <a href="{{ route('admin.agencies') }}" class="btn btn-outline">İptal</a>
                                </div>
                            </div>

                            <div class="p-kart">
                                <div class="af-kart-bas">
                                    <h2 class="p-kart-baslik">Önizleme</h2>
                                    <p class="af-kart-alt">Acenta listesinde ve sitede böyle görünür.</p>
                                </div>
                                <div class="af-onizleme">
                                    <div class="af-avatar" id="af-on-avatar" aria-hidden="true">—</div>
                                    <div>
                                        <div class="af-on-ad" id="af-on-ad">Acenta adı</div>
                                        <div class="af-on-meta" id="af-on-meta">E-posta ve telefon burada görünür</div>
                                    </div>
                                </div>
                                <div class="af-on-adres"><b>{{ request()->getHost() }}/acentalar/</b><span id="af-on-slug">acenta-adi</span></div>
                            </div>

                            <div class="p-kart">
                                <div class="af-kart-bas">
                                    <h2 class="p-kart-baslik">Kayıttan sonra</h2>
                                    <p class="af-kart-alt">Acentanın tur yayınlayabilmesi için.</p>
                                </div>
                                <ul class="af-sonra">
                                    <li><span class="af-sira" aria-hidden="true">1</span><span><a href="{{ route('admin.category-licenses.access') }}">Kategori yetkisi</a> verin; yetkisiz acentanın turları sitede görünmez.</span></li>
                                    <li><span class="af-sira" aria-hidden="true">2</span><span>Acenta panele girer; logo ve adresi kendi profilinden ekler.</span></li>
                                    <li><span class="af-sira" aria-hidden="true">3</span><span>Turları elle ya da web sitesinden içe aktararak ekler.</span></li>
                                </ul>
                            </div>
                        </aside>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    var ad = document.getElementById('name'), ep = document.getElementById('email'), tel = document.getElementById('phone');
    var pw = document.getElementById('password'), goz = document.querySelector('[data-sifre-hedef="password"]');
    var akisP = document.getElementById('af-akis-parola'), akisB = document.getElementById('af-akis-baglanti');

    // Sunucudaki Str::slug'ın yaklaşığı (Türkçe harfler ASCII'ye); çakışmada sunucu -2, -3 ekler.
    var tr = { 'ç':'c','ğ':'g','ı':'i','ö':'o','ş':'s','ü':'u','Ç':'c','Ğ':'g','İ':'i','Ö':'o','Ş':'s','Ü':'u' };
    function slug(s) {
        s = s.replace(/[çğıöşüÇĞİÖŞÜ]/g, function (c) { return tr[c]; }).toLowerCase();
        return s.replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '') || 'acenta-adi';
    }
    function basHarf(s) {
        var p = s.trim().split(/\s+/).filter(Boolean);
        if (!p.length) return '—';
        return (p[0][0] + (p[1] ? p[1][0] : '')).toLocaleUpperCase('tr-TR');
    }
    function guncelle() {
        var n = ad.value.trim();
        document.getElementById('af-hesap-ad').textContent = (n || 'Acenta') + ' Yönetici';
        document.getElementById('af-hesap-eposta').textContent = ep.value.trim() || 'e-posta';
        document.getElementById('af-on-ad').textContent = n || 'Acenta adı';
        document.getElementById('af-on-avatar').textContent = basHarf(n);
        document.getElementById('af-on-slug').textContent = slug(n);
        var m = [ep.value.trim(), tel.value.trim()].filter(Boolean);
        document.getElementById('af-on-meta').textContent = m.length ? m.join(' · ') : 'E-posta ve telefon burada görünür';
    }
    [ad, ep, tel].forEach(function (el) { el.addEventListener('input', guncelle); });
    guncelle();

    // Parola doluysa "parola sizden", boşsa "bağlantı" akışı görünür
    function akis() { var dolu = pw.value.length > 0; akisP.hidden = !dolu; akisB.hidden = dolu; }
    pw.addEventListener('input', akis);
    akis();

    // Güçlü parola üret: 14 karakter, karışabilen harfler (0/O, 1/l/I) yok.
    // Göster/gizle düğmesi layout'taki ortak [data-sifre-hedef] dinleyicisinde; üretince
    // gizliyse düğmeye tıklatıp açarız ki admin parolayı okuyup acentaya iletebilsin.
    document.getElementById('af-uret').addEventListener('click', function () {
        var h = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789!?*+-';
        var r = new Uint32Array(14);
        window.crypto.getRandomValues(r);
        pw.value = Array.from(r, function (n) { return h[n % h.length]; }).join('');
        if (pw.type === 'password' && goz) goz.click();
        akis();
        pw.focus();
    });
})();
</script>
@endpush
