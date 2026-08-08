@extends('legal._layout')

@section('legal_title', 'Nasıl Çalışır?')
@section('legal_lede', 'turXtur turları nereden alır, neyi karşılaştırır ve rezervasyon nasıl yapılır?')

@section('legal_body')

<h2>Kullanıcılar için</h2>

<h3>1. Turları farklı acentalardan tek yerde görürsünüz</h3>
<p>
    Sitedeki turlar, belgeli seyahat acentaları tarafından yayımlanır. Aynı rotayı birden
    fazla acenta düzenliyorsa fiyatlarını yan yana görebilirsiniz — tur detay sayfasındaki
    <strong>"Diğer Acentalar"</strong> bölümü bunun içindir.
</p>

<h3>2. Arayın, filtreleyin, karşılaştırın</h3>
<p>
    Kategori, destinasyon, ay, özel dönem, süre, kalkış şehri ve bütçeye göre filtreleyebilir;
    üç tura kadar seçip <strong>Karşılaştır</strong> ile yan yana inceleyebilirsiniz.
    Ne aradığınızdan emin değilseniz <strong>tur eşleştirme testi</strong> size uygun turları
    gerekçesiyle sıralar.
</p>

<h3>3. Fiyat değişimini takip edin</h3>
<p>
    Tur detayında son 30 günün fiyat grafiği bulunur. Bir turu favorilerinize eklerseniz
    fiyatı düştüğünde haberdar oluruz.
</p>

<h3>4. Rezervasyonu acenta üzerinden yaparsınız</h3>
<p>
    <strong>turXtur üzerinden rezervasyon yapılmaz ve ödeme alınmaz.</strong>
    "Tura Git" bağlantısı sizi turu düzenleyen acentanın kendi sitesine yönlendirir.
    Sözleşme sizinle acenta arasında kurulur; ödeme, iptal ve iade işlemleri acentanın
    koşullarına tabidir.
</p>

<div class="legal-note">
    <p>
        Fiyatlar ve müsaitlik acentanın sitesinde değişmiş olabilir. Bağlayıcı olan,
        acentanın kendi sayfasındaki güncel bilgidir — rezervasyondan önce orada teyit edin.
    </p>
</div>

<h2>Acentalar için</h2>

<h3>1. Başvuru ve onay</h3>
<p>
    <a href="{{ route('agency.register') }}">Acenta kaydı</a> oluşturursunuz. Başvurunuz,
    belge bilgileriniz doğrulandıktan sonra ekibimizce onaylanır. Onay öncesinde turlarınız
    yayımlanmaz.
</p>

<h3>2. Kategori lisansı</h3>
<p>
    Tur yayımlamak istediğiniz kategoriler için aylık lisans alırsınız. Lisans, o kategoride
    <strong>tur yayımlama hakkı</strong> verir; arama sonuçlarında üst sıraya çıkarmaz
    (bkz. <a href="{{ route('legal.siralama') }}">Sıralama Kriterleri</a>).
    Lisans sona erdiğinde o kategorideki turlarınız listeden çıkar, silinmez — yenilediğinizde
    geri döner.
</p>

<h3>3. Tur ekleme</h3>
<p>
    Turları elle girebilir veya kendi sitenizdeki tur sayfasının bağlantısını yapıştırarak
    <strong>otomatik içe aktarabilirsiniz</strong>: başlık, program, fiyat tablosu, kalkış
    tarihleri ve görseller çıkarılıp forma doldurulur. Yayımlamadan önce kontrol etmeniz
    beklenir — otomatik çıkarım her sayfada eksiksiz çalışmayabilir.
</p>

<h3>4. Performansı görün</h3>
<p>
    Panelinizde turlarınızın kaç kez görüntülendiğini ve kaç kez sitenize yönlendirme
    aldığını gün, hafta ve ay bazında izleyebilirsiniz.
</p>

<h2>turXtur ne yapmaz?</h2>

<ul>
    <li>Tur düzenlemez, satmaz, tur bedeli tahsil etmez.</li>
    <li>Rezervasyon işlemi yapmaz, bilet veya voucher düzenlemez.</li>
    <li>Sıralamada ücretli öne çıkarma satmaz.</li>
    <li>Acentalarla aranızdaki sözleşmenin tarafı olmaz.</li>
</ul>

<p>
    Sorularınız için <a href="{{ route('legal.iletisim') }}">İletişim</a> sayfasına bakın.
</p>
@endsection
