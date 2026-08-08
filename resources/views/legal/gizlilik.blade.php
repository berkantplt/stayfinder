@extends('legal._layout')

@section('legal_title', 'Gizlilik Politikası')
@section('legal_lede', 'Verilerinizi nasıl koruduğumuza dair kısa ve açık bir özet.')

@section('legal_body')
@php $c = config('company'); @endphp

<p>
    Bu sayfa günlük dilde bir özettir. Kişisel verilerinizin hangi hukuki sebeple işlendiği,
    kimlere aktarıldığı ve haklarınızın tam listesi için
    <a href="{{ route('legal.kvkk') }}">KVKK Aydınlatma Metni</a>’ni okuyun.
</p>

<h2>Kısaca</h2>

<ul>
    <li><strong>Kart bilgilerinizi hiç görmüyoruz.</strong> Sitede tur satışı yapılmıyor; acentaların abonelik ödemeleri lisanslı ödeme kuruluşu iyzico üzerinden alınıyor ve kart verisi sistemimize hiç girmiyor.</li>
    <li><strong>Verinizi satmıyoruz.</strong> Kişisel verilerinizi reklam amacıyla üçüncü taraflara satmıyor veya kiralamıyoruz.</li>
    <li><strong>Acentalara kimliğinizi vermiyoruz.</strong> Siz kendiniz iletişime geçmediğiniz sürece acenta sizin kim olduğunuzu bilmez.</li>
    <li><strong>Gereğinden uzun saklamıyoruz.</strong> Görüntüleme kayıtları 180, arama kayıtları 90 gün sonra otomatik siliniyor.</li>
</ul>

<h2>Hassas veriler</h2>

<p>
    Yapay zekâ destekli arama ve sohbet özelliklerinde yazdığınız metin işlenmek üzere hizmet
    sağlayıcıya gönderilir. Gönderilmeden önce <strong>kart numarası ve T.C. kimlik numarası
    gibi diziler otomatik olarak maskelenir.</strong> Yine de bu alanlara kimlik, kart veya
    şifre bilgisi yazmamanızı öneririz — sohbet asistanının bu bilgilere ihtiyacı yoktur.
</p>

<h2>Güvenlik</h2>

<ul>
    <li>Bağlantılar HTTPS ile şifrelenir.</li>
    <li>Şifreler geri döndürülemez biçimde (hash) saklanır; düz metin şifre tutulmaz.</li>
    <li>Ödeme fatura bilgileri veritabanında şifrelenmiş olarak saklanır.</li>
    <li>Giriş denemeleri hız sınırına tabidir; kaba kuvvet saldırıları engellenir.</li>
</ul>

<p>
    Hiçbir sistem %100 güvenli değildir. Bir güvenlik açığı fark ederseniz lütfen
    <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a> adresinden bize bildirin;
    açığı kamuya duyurmadan önce düzeltmemiz için makul süre tanımanızı rica ederiz.
</p>

<h2>Çocukların gizliliği</h2>

<p>
    Platform 18 yaş altındakilere yönelik değildir ve bilerek onlardan kişisel veri toplamayız.
</p>

<h2>Haklarınız</h2>

<p>
    Verilerinize erişmek, düzeltmek veya sildirmek için
    <a href="mailto:{{ $c['kvkk_email'] }}">{{ $c['kvkk_email'] }}</a> adresine yazın.
    Talebiniz en geç 30 gün içinde sonuçlandırılır.
</p>
@endsection
