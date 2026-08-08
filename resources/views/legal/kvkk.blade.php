@extends('legal._layout')

@section('legal_title', 'KVKK Aydınlatma Metni')
@section('legal_lede', '6698 sayılı Kişisel Verilerin Korunması Kanunu kapsamında, kişisel verilerinizi hangi amaçla işlediğimize ve haklarınıza dair bilgilendirme.')

@section('legal_body')
@php $c = config('company'); @endphp

<h2>1. Veri sorumlusu</h2>

<p>
    Kişisel verileriniz, veri sorumlusu sıfatıyla
    <strong>{{ $c['legal_name'] ?: $c['brand'] }}</strong> tarafından aşağıda açıklanan
    kapsamda işlenmektedir. İletişim bilgilerimize
    <a href="{{ route('legal.iletisim') }}">İletişim sayfasından</a> ulaşabilirsiniz.
</p>

<h2>2. İşlenen kişisel veriler</h2>

<table class="legal-table">
    <tbody>
        <tr><th>Kimlik ve iletişim</th><td>Ad soyad, e-posta adresi, telefon numarası (üyelik ve iletişim formlarında verdiğiniz kadarıyla)</td></tr>
        <tr><th>İşlem güvenliği</th><td>IP adresi, oturum bilgisi, giriş kayıtları, tarayıcı ve cihaz bilgisi</td></tr>
        <tr><th>Kullanım verileri</th><td>Görüntülediğiniz turlar, arama ve filtre tercihleri, favorilere eklediğiniz turlar, tıklama kayıtları</td></tr>
        <tr><th>Tercih verileri</th><td>Tur eşleştirme testinde verdiğiniz cevaplar (kişiye bağlanmadan, bantlanmış biçimde)</td></tr>
        <tr><th>Müşteri işlem</th><td>Yorum ve puanlarınız, kupon talepleriniz, destek yazışmaları</td></tr>
    </tbody>
</table>

<p>
    Ödeme kartı bilgisi <strong>tarafımızca işlenmez</strong>. Acentaların kategori aboneliği
    ödemeleri lisanslı ödeme kuruluşu iyzico üzerinden alınır; kart verisi sistemlerimize
    hiçbir aşamada girmez.
</p>

<h2>3. İşleme amaçları</h2>

<ul>
    <li>Üyelik oluşturma, kimlik doğrulama ve hesap güvenliğinin sağlanması</li>
    <li>Tur arama, filtreleme, karşılaştırma ve öneri hizmetlerinin sunulması</li>
    <li>Favori, yorum ve kupon özelliklerinin çalıştırılması</li>
    <li>Platform performansının ölçülmesi, hata ayıklama ve kötüye kullanımın önlenmesi</li>
    <li>Açık rızanız varsa ticari elektronik ileti gönderilmesi</li>
    <li>Mevzuattan doğan yükümlülüklerin yerine getirilmesi ve yetkili kurum taleplerine yanıt verilmesi</li>
</ul>

<h2>4. Hukuki sebepler</h2>

<p>Verileriniz KVKK md. 5 uyarınca şu hukuki sebeplere dayanarak işlenir:</p>

<ul>
    <li><strong>Sözleşmenin kurulması veya ifası:</strong> üyelik işlemleri, favori ve yorum özellikleri</li>
    <li><strong>Meşru menfaat:</strong> platform güvenliği, kötüye kullanımın önlenmesi, hizmet kalitesinin ölçülmesi</li>
    <li><strong>Hukuki yükümlülük:</strong> mevzuatın öngördüğü saklama ve bildirim yükümlülükleri</li>
    <li><strong>Açık rıza:</strong> zorunlu olmayan çerezler ve ticari elektronik iletiler</li>
</ul>

<h2>5. Aktarım</h2>

<p>Kişisel verileriniz yalnızca hizmetin gerektirdiği ölçüde şu taraflara aktarılabilir:</p>

<ul>
    <li><strong>Barındırma ve altyapı sağlayıcıları</strong> — verilerin saklanması amacıyla</li>
    <li><strong>Ödeme kuruluşu (iyzico)</strong> — yalnızca acenta abonelik ödemeleri için, yalnızca ilgili acentaya ait fatura bilgileri</li>
    <li><strong>Yapay zekâ hizmet sağlayıcısı</strong> — sohbet ve öneri özelliklerinde, mesaj metniniz işlenmek üzere. Kart numarası ve T.C. kimlik numarası gibi veriler gönderilmeden önce otomatik olarak maskelenir.</li>
    <li><strong>Yetkili kamu kurum ve kuruluşları</strong> — mevzuattan doğan talepler kapsamında</li>
</ul>

<p>
    Turları yayımlayan seyahat acentalarına, siz kendiniz iletişime geçmediğiniz sürece kimlik
    veya iletişim bilginiz aktarılmaz. Bir turun bağlantısına tıkladığınızda acentanın kendi
    sitesine yönlendirilirsiniz; o noktadan sonra acentanın kendi gizlilik politikası geçerlidir.
</p>

<h2>6. Saklama süreleri</h2>

<table class="legal-table">
    <tbody>
        <tr><th>Üyelik verileri</th><td>Hesabınız açık olduğu sürece; hesap silindikten sonra mevzuattaki zamanaşımı süreleri boyunca</td></tr>
        <tr><th>Görüntüleme ve tıklama kayıtları</th><td>180 gün</td></tr>
        <tr><th>Arama kayıtları</th><td>90 gün</td></tr>
        <tr><th>Fiyat geçmişi</th><td>365 gün (tura ait, kişiye bağlı değil)</td></tr>
        <tr><th>Ödeme fatura bilgileri</th><td>Mevzuatın öngördüğü süre; iptal edilen işlemlerde 7 gün içinde silinir</td></tr>
    </tbody>
</table>

<h2>7. Haklarınız</h2>

<p>KVKK md. 11 uyarınca veri sorumlusuna başvurarak:</p>

<ul>
    <li>Kişisel verinizin işlenip işlenmediğini öğrenme, işlenmişse bilgi talep etme</li>
    <li>İşlenme amacını ve amacına uygun kullanılıp kullanılmadığını öğrenme</li>
    <li>Yurt içinde veya yurt dışında aktarıldığı üçüncü kişileri bilme</li>
    <li>Eksik veya yanlış işlenmişse düzeltilmesini isteme</li>
    <li>Silinmesini veya yok edilmesini isteme</li>
    <li>Düzeltme, silme ve yok etme işlemlerinin aktarıldığı üçüncü kişilere bildirilmesini isteme</li>
    <li>Münhasıran otomatik sistemlerle analiz edilmesi suretiyle aleyhinize bir sonuç doğmasına itiraz etme</li>
    <li>Kanuna aykırı işleme sebebiyle zarara uğramanız hâlinde zararın giderilmesini talep etme</li>
</ul>

<p>
    Başvurularınızı <a href="mailto:{{ $c['kvkk_email'] }}">{{ $c['kvkk_email'] }}</a> adresine
    @if($c['kep']) veya {{ $c['kep'] }} KEP adresine @endif
    iletebilirsiniz. Talebiniz en geç <strong>30 gün</strong> içinde sonuçlandırılır.
</p>

<div class="legal-note">
    <p>
        Bu metin, platformun bugünkü işleyişine göre hazırlanmış bir şablondur.
        Yayına almadan önce bir hukuk danışmanına gözden geçirtin; özellikle veri envanteri,
        saklama süreleri ve aktarım listesi şirketinizin gerçek süreçleriyle birebir uyumlu olmalıdır.
    </p>
</div>
@endsection
