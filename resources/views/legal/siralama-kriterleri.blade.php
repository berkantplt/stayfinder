@extends('legal._layout')

@section('legal_title', 'Sıralama Kriterleri')
@section('legal_lede', 'Turların arama sonuçlarında hangi ölçütlere göre sıralandığını burada açıklıyoruz.')

@section('legal_body')

<p>
    6563 sayılı Kanun’un ek 1. maddesi, aracı hizmet sağlayıcıların sıralama ölçütlerini
    açıklamasını gerektirir. Aşağıdaki bilgi, platformun bugünkü çalışma biçimini yansıtır.
</p>

<h2>Sıralamayı siz seçersiniz</h2>

<p>
    Tur listesinde sıralama ölçütünü kullanıcı belirler. Varsayılan ölçüt
    <strong>artan fiyattır</strong> — yani hiçbir seçim yapmazsanız en ucuz tur en üstte çıkar.
    Seçenekler:
</p>

<table class="legal-table">
    <tbody>
        <tr><th>Fiyat (artan) — varsayılan</th><td>Kişi başı fiyatın Türk lirası karşılığına göre ucuzdan pahalıya</td></tr>
        <tr><th>Fiyat (azalan)</th><td>Pahalıdan ucuza</td></tr>
        <tr><th>Kalkış tarihi</th><td>En yakın kalkış tarihi önce</td></tr>
        <tr><th>Yeni eklenenler</th><td>Platforma en son eklenen turlar önce</td></tr>
        <tr><th>Popüler</th><td>Turun aldığı tıklama ve görüntülenme sayısının toplamına göre; eşitlikte yorum sayısı belirleyici olur</td></tr>
        <tr><th>Yorum sayısı</th><td>En çok yorum alan turlar önce</td></tr>
    </tbody>
</table>

<h2>Ücretli sıralama yoktur</h2>

<p>
    Acentalar sıralamada üst sıraya çıkmak için <strong>ödeme yapamaz</strong>. Sponsorlu
    yerleşim, öne çıkarma paketi veya komisyon artırarak sıra satın alma gibi bir mekanizma
    platformda bulunmamaktadır. Yukarıdaki ölçütler dışında sıralamayı etkileyen gizli bir
    parametre yoktur.
</p>

<h2>Aboneliğin sıralamaya etkisi</h2>

<p>
    Acentalar, tur yayımlamak istedikleri kategoriler için aylık lisans alır. Bu lisans
    <strong>turun listelenip listelenmediğini</strong> belirler — <strong>sıradaki yerini
    değil</strong>. Yani:
</p>

<ul>
    <li>Lisansı geçerli olan acentanın turu listelenir ve yukarıdaki ölçütlere göre sıralanır.</li>
    <li>Lisansı sona eren acentanın turu listeden tamamen çıkar; alt sıraya düşmez.</li>
    <li>Daha fazla kategori lisansı almak, bir turu diğerinin üstüne çıkarmaz.</li>
</ul>

<h2>Yapay zekâ destekli öneriler</h2>

<p>
    Sohbet asistanı ve tur eşleştirme testi, klasik listeden farklı çalışır: sizin
    belirttiğiniz tercihlere (bütçe, süre, tempo, kalabalıklık, doğa/şehir dengesi gibi)
    turların özelliklerini karşılaştırıp bir uyum puanı üretir ve gerekçesini gösterir.
    Bu puanlamada da acentanın ödediği tutarın hiçbir etkisi yoktur; yalnızca turun içeriği
    ile sizin tercihleriniz karşılaştırılır.
</p>

<h2>Filtreler</h2>

<p>
    Filtreler sıralamayı değil, hangi turların listeye gireceğini belirler. Bir filtre
    uyguladığınızda seçtiğiniz sıralama ölçütü korunur.
</p>

<p>
    Sıralamaya dair sorularınız için <a href="{{ route('legal.iletisim') }}">bize yazabilirsiniz</a>.
</p>
@endsection
