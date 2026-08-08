@extends('legal._layout')

@section('legal_title', 'Çerez Politikası')
@section('legal_lede', 'Hangi çerezleri kullandığımız, ne işe yaradıkları ve tercihlerinizi nasıl değiştirebileceğiniz.')

@section('legal_body')

<h2>Çerez nedir?</h2>

<p>
    Çerezler, bir siteyi ziyaret ettiğinizde tarayıcınıza kaydedilen küçük metin dosyalarıdır.
    Oturumunuzun açık kalması, tercihlerinizin hatırlanması ve sitenin nasıl kullanıldığının
    ölçülmesi gibi işler için kullanılırlar.
</p>

<h2>Kullandığımız çerezler</h2>

<h3>Zorunlu çerezler</h3>

<p>
    Sitenin çalışması için gereklidir, kapatılamaz ve <strong>rıza gerektirmez</strong>.
</p>

<table class="legal-table">
    <tbody>
        <tr><th>Oturum çerezi</th><td>Giriş yaptığınızda hesabınızın açık kalmasını sağlar. Tarayıcı kapandığında veya oturum süresi dolduğunda silinir.</td></tr>
        <tr><th>CSRF belirteci</th><td>Form gönderimlerinin sizin tarafınızdan yapıldığını doğrular; sahte istek saldırılarını engeller.</td></tr>
        <tr><th>Çerez tercihiniz</th><td>Bu sayfadaki seçiminizi hatırlar ki banner her sayfada tekrar çıkmasın. 12 ay saklanır.</td></tr>
    </tbody>
</table>

<h3>İşlevsel depolama</h3>

<p>
    Teknik olarak çerez değil, tarayıcınızın <em>yerel depolama</em> alanında tutulan
    tercihlerdir. Sunucuya gönderilmezler, cihazınızdan çıkmazlar:
</p>

<ul>
    <li><strong>Karşılaştırma listesi</strong> — karşılaştırmak üzere seçtiğiniz turların numaraları</li>
    <li><strong>Son görüntülenen turlar</strong> — ana sayfada size tekrar gösterebilmek için</li>
</ul>

<h3>Analitik ve pazarlama çerezleri</h3>

<p>
    Bu kategorideki çerezler <strong>yalnızca açık rızanız varsa</strong> yüklenir.
    Banner'da "Yalnızca zorunlu" derseniz hiçbiri çalıştırılmaz. Şu an kullanılan
    üçüncü taraf analitik veya reklam çerezi <strong>bulunmamaktadır</strong>; ileride
    eklenirse bu tablo güncellenecek ve rızanız yeniden alınacaktır.
</p>

<h2>Tercihinizi değiştirme</h2>

<p>
    Aşağıdaki düğmeyle çerez tercihinizi istediğiniz zaman yeniden belirleyebilirsiniz.
</p>

<p>
    <button type="button" class="btn btn-outline js-cookie-reopen">Çerez tercihlerimi değiştir</button>
</p>

<p>
    Ayrıca tarayıcınızın ayarlarından tüm çerezleri silebilir veya engelleyebilirsiniz.
    Zorunlu çerezleri engellerseniz giriş yapma ve form gönderme gibi işlevler çalışmayabilir.
</p>

<p>
    Çerezlerle işlenen kişisel verilere ilişkin haklarınız için
    <a href="{{ route('legal.kvkk') }}">KVKK Aydınlatma Metni</a> sayfasına bakabilirsiniz.
</p>
@endsection
