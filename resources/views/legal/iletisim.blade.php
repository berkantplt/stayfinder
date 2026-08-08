@extends('legal._layout')

@section('legal_title', 'İletişim ve Şirket Bilgileri')
@section('legal_lede', 'turXtur’u işleten şirketin tanıtıcı bilgileri ve bize ulaşabileceğiniz kanallar.')

@section('legal_body')
@php $c = config('company'); @endphp

<h2>Hizmet sağlayıcı</h2>

<table class="legal-table">
    <tbody>
    @if($c['legal_name'])
        <tr><th>Ticaret unvanı</th><td>{{ $c['legal_name'] }}</td></tr>
    @endif
    @if($c['mersis_no'])
        <tr><th>MERSİS numarası</th><td>{{ $c['mersis_no'] }}</td></tr>
    @endif
    @if($c['trade_registry_no'])
        <tr><th>Ticaret sicil no</th><td>{{ $c['trade_registry_no'] }}</td></tr>
    @endif
    @if($c['tax_office'] || $c['tax_number'])
        <tr>
            <th>Vergi dairesi / no</th>
            <td>{{ trim(($c['tax_office'] ?? '').' '.($c['tax_number'] ?? '')) }}</td>
        </tr>
    @endif
    @if($c['address'])
        <tr><th>Merkez adresi</th><td>{{ $c['address'] }}</td></tr>
    @endif
    @if($c['phone'])
        <tr><th>Telefon</th><td><a href="tel:{{ preg_replace('/\s+/', '', $c['phone']) }}">{{ $c['phone'] }}</a></td></tr>
    @endif
        <tr><th>E-posta</th><td><a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></td></tr>
    @if($c['kep'])
        <tr><th>KEP adresi</th><td>{{ $c['kep'] }}</td></tr>
    @endif
    </tbody>
</table>

@unless($c['legal_name'] && $c['mersis_no'] && $c['address'] && $c['phone'])
    <div class="legal-note">
        <p>
            <strong>Yayına almadan önce doldurulmalı.</strong>
            6563 sayılı Kanun md. 3 gereği ticaret unvanı, MERSİS numarası, merkez adresi ve
            iletişim bilgilerinin bu sayfada bulunması zorunludur. Değerler
            <code>.env</code> dosyasındaki <code>COMPANY_*</code> anahtarlarıyla girilir
            (bkz. <code>config/company.php</code>); boş bırakılan satırlar gösterilmez.
        </p>
    </div>
@endunless

<h2>Bize ulaşın</h2>

<p>
    Aşağıdaki konularda doğrudan bize yazabilirsiniz. Turların içeriği, fiyatı, rezervasyonu
    ve iptali hakkındaki sorular için ilgili <strong>seyahat acentasına</strong> başvurmanız
    gerekir — bu konularda turXtur taraf değildir.
</p>

<ul>
    <li><strong>Genel sorular ve öneriler:</strong> <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></li>
    <li><strong>Kişisel verilerinizle ilgili başvurular:</strong> <a href="mailto:{{ $c['kvkk_email'] }}">{{ $c['kvkk_email'] }}</a> (bkz. <a href="{{ route('legal.kvkk') }}">KVKK Aydınlatma Metni</a>)</li>
    <li><strong>Platformdaki bir ilanla ilgili şikâyet:</strong> <a href="mailto:{{ $c['email'] }}">{{ $c['email'] }}</a></li>
    <li><strong>Acenta olarak yer almak:</strong> <a href="{{ route('agency.register') }}">Acenta başvuru formu</a></li>
</ul>

<h2>Şikâyet ve bildirim süreleri</h2>

<p>
    Elektronik Ticaret Aracı Hizmet Sağlayıcı mevzuatı uyarınca:
</p>

<ul>
    <li>Fikri ve sınai mülkiyet hakkı ihlali bildirimleri <strong>48 saat</strong> içinde değerlendirilir.</li>
    <li>Diğer şikâyet ve talepler <strong>15 gün</strong> içinde sonuçlandırılır.</li>
</ul>

<p>
    Bir turun içeriğinin gerçeğe aykırı olduğunu düşünüyorsanız bize bildirin; ilgili acentadan
    açıklama ister, gerekirse ilanı yayından kaldırırız.
</p>
@endsection
