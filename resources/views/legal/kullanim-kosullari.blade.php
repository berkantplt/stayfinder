@extends('legal._layout')

@section('legal_title', 'Kullanım Koşulları')
@section('legal_lede', 'turXtur’u kullanarak kabul ettiğiniz kurallar ve tarafların sorumlulukları.')

@section('legal_body')
@php $c = config('company'); @endphp

<h2>1. Platformun konumu</h2>

<p>
    turXtur bir <strong>aracı hizmet sağlayıcıdır</strong>. Sitede yer alan turlar, T.C. Kültür
    ve Turizm Bakanlığı’ndan belgeli seyahat acentaları tarafından yayımlanır. turXtur:
</p>

<ul>
    <li>tur düzenlemez, satmaz ve tur bedelini tahsil etmez;</li>
    <li>rezervasyon işlemi yapmaz — "Tura Git" bağlantısı sizi ilgili acentanın kendi sitesine yönlendirir;</li>
    <li>tur sözleşmesinin tarafı değildir. Sözleşme <strong>sizinle acenta arasında</strong> kurulur.</li>
</ul>

<p>
    Turun içeriği, fiyatı, kontenjanı, kalkışının gerçekleşmesi, iptal ve iade koşulları
    ilgili acentanın sorumluluğundadır. Bu konulardaki talep ve şikâyetlerinizi öncelikle
    acentaya iletmeniz gerekir.
</p>

<h2>2. Bilgilerin doğruluğu</h2>

<p>
    Tur bilgileri acentalar tarafından girilir veya acentanın kendi sayfasından otomatik
    olarak aktarılır. Doğruluğunu artırmak için çaba gösteririz, ancak <strong>fiyatlar ve
    müsaitlik acentanın sitesinde değişmiş olabilir.</strong> Bağlayıcı olan, acentanın kendi
    sayfasındaki güncel bilgidir. Rezervasyondan önce mutlaka orada teyit edin.
</p>

<p>
    Gerçeğe aykırı olduğunu düşündüğünüz bir ilanı bize bildirebilirsiniz; inceler,
    gerekirse yayından kaldırırız.
</p>

<h2>3. Üyelik</h2>

<ul>
    <li>Üyelik ücretsizdir ve 18 yaşını doldurmuş olmanız gerekir.</li>
    <li>Hesap bilgilerinizin gizliliğinden siz sorumlusunuz; şifrenizi kimseyle paylaşmayın.</li>
    <li>Hesabınızın izinsiz kullanıldığını fark ederseniz derhal bize bildirin.</li>
    <li>Üyeliğinizi dilediğiniz zaman sonlandırabilirsiniz.</li>
</ul>

<h2>4. Yorum ve içerik kuralları</h2>

<p>Yorum yazarken şunlara uymanız gerekir. Aksi hâlde içerik kaldırılabilir, hesap askıya alınabilir:</p>

<ul>
    <li>Gerçek deneyime dayanmayan, yanıltıcı veya ücret karşılığı yazılmış yorum paylaşmayın.</li>
    <li>Hakaret, nefret söylemi, ayrımcılık ve tehdit içeren ifadeler kullanmayın.</li>
    <li>Başkalarının kişisel verilerini (telefon, adres, kimlik bilgisi) paylaşmayın.</li>
    <li>Reklam, spam ve alakasız bağlantı eklemeyin.</li>
    <li>Telif hakkı size ait olmayan metin ve görselleri yüklemeyin.</li>
</ul>

<h2>5. Acentaların yükümlülükleri</h2>

<p>Platformda tur yayımlayan acentalar:</p>

<ul>
    <li>geçerli bir seyahat acentası işletme belgesine sahip olmalı ve belge bilgilerini doğrulatmalıdır;</li>
    <li>yalnızca gerçekten sundukları turları, doğru fiyat ve koşullarla yayımlayabilir;</li>
    <li>fiyata dâhil olan ve olmayan hizmetleri açıkça belirtmekle yükümlüdür;</li>
    <li>yayımladıkları içeriğin hukuka uygunluğundan kendileri sorumludur;</li>
    <li>yalnızca lisans aldıkları kategorilerde tur yayımlayabilir.</li>
</ul>

<p>
    Bu kurallara aykırılık hâlinde ilan yayından kaldırılabilir, tekrarı hâlinde acentanın
    platform erişimi sonlandırılabilir.
</p>

<h2>6. Fikri mülkiyet</h2>

<p>
    Sitenin tasarımı, yazılımı, markası ve turXtur tarafından üretilen içerikler
    {{ $c['legal_name'] ?: $c['brand'] }}’a aittir. Acentaların yüklediği metin ve görsellerin
    hakları ilgili acentaya aittir. İzinsiz kopyalama, toplu veri çekme (scraping) ve
    ticari amaçla yeniden yayımlama yasaktır.
</p>

<h2>7. Sorumluluğun sınırı</h2>

<p>
    turXtur, hizmeti "olduğu gibi" sunar. Kesintisiz veya hatasız çalışacağını taahhüt etmez.
    Acentayla aranızdaki sözleşmeden doğan uyuşmazlıklardan, turun gerçekleşmemesinden veya
    acentanın sitesindeki işlemlerden turXtur sorumlu tutulamaz. Bu hüküm, mevzuattan doğan
    ve sınırlandırılamayan sorumluluklarımızı ortadan kaldırmaz.
</p>

<h2>8. Sıralama ve öne çıkarma</h2>

<p>
    Arama sonuçlarının hangi ölçütlere göre sıralandığını
    <a href="{{ route('legal.siralama') }}">Sıralama Kriterleri</a> sayfasında açıkladık.
</p>

<h2>9. Değişiklikler ve uygulanacak hukuk</h2>

<p>
    Bu koşullarda değişiklik yapabiliriz; güncel metin bu sayfada yayımlanır ve yayımlandığı
    anda yürürlüğe girer. Uyuşmazlıklarda Türk hukuku uygulanır. Tüketici işlemleri
    bakımından Tüketici Hakem Heyetleri ve Tüketici Mahkemeleri yetkilidir.
</p>

<p>
    Sorularınız için: <a href="{{ route('legal.iletisim') }}">İletişim</a>
</p>

<div class="legal-note">
    <p>
        Bu metin platformun bugünkü işleyişine göre hazırlanmış bir şablondur; yayına almadan
        önce hukuk danışmanınıza gözden geçirtin. Siteye rezervasyon veya ödeme eklendiği gün
        bu koşulların ve yasal yükümlülüklerin yeniden değerlendirilmesi gerekir.
    </p>
</div>
@endsection
