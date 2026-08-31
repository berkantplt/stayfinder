<?php

/*
|--------------------------------------------------------------------------
| Şirket / Yasal Kimlik Bilgileri
|--------------------------------------------------------------------------
| 6563 sayılı Elektronik Ticaretin Düzenlenmesi Hakkında Kanun md. 3, hizmet
| sağlayıcının tanıtıcı bilgilerini alıcıların KOLAYCA ulaşabileceği şekilde
| sunmasını zorunlu kılar. Bu değerler /iletisim sayfasında ve yasal
| metinlerin künyesinde kullanılır.
|
| ⚠️ BOŞ BIRAKILAN ALANLAR SAYFADA GÖSTERİLMEZ. Yayına almadan önce en az
| şunları doldurun: ticaret unvanı, MERSİS no, adres, e-posta, telefon.
| KEP adresi tüzel kişiler için zorunludur.
*/

return [

    // Görünen marka adı
    'brand' => env('COMPANY_BRAND', 'turXtur'),

    // Ticaret sicilindeki tam unvan — ör. "Örnek Turizm Teknolojileri A.Ş."
    'legal_name' => env('COMPANY_LEGAL_NAME'),

    'mersis_no' => env('COMPANY_MERSIS_NO'),
    'tax_office' => env('COMPANY_TAX_OFFICE'),   // Vergi dairesi
    'tax_number' => env('COMPANY_TAX_NUMBER'),   // VKN
    'trade_registry_no' => env('COMPANY_TRADE_REGISTRY_NO'),

    'address' => env('COMPANY_ADDRESS'),
    'phone' => env('COMPANY_PHONE'),
    'email' => env('COMPANY_EMAIL', 'info@turxtur.com'),

    // KEP: tüzel kişiler için zorunlu elektronik tebligat adresi
    'kep' => env('COMPANY_KEP'),

    // KVKK veri sorumlusu başvuru kanalı (ayrı bir adres olabilir)
    'kvkk_email' => env('COMPANY_KVKK_EMAIL', env('COMPANY_EMAIL', 'kvkk@turxtur.com')),

    // ETBİS kayıt tarihi/no — Ticaret Bakanlığı e-ticaret bilgi sistemi
    'etbis_registered' => (bool) env('COMPANY_ETBIS_REGISTERED', false),

    /*
    | turXtur bir ARACI HİZMET SAĞLAYICIdır: turları belgeli seyahat
    | acentaları yayımlar, satış ve sözleşme acenta ile kullanıcı arasında
    | kurulur, bedel turXtur üzerinden tahsil edilmez. Bu ayrım yasal
    | metinlerin tamamının dayanağıdır — model değişirse metinler de
    | (ve muhtemelen belge yükümlülüğü de) değişir.
    */
    'role' => 'aracı hizmet sağlayıcı',

    // Sözleşmenin/metinlerin son güncellenme tarihi (sayfa altında gösterilir)
    'legal_updated_at' => env('COMPANY_LEGAL_UPDATED_AT', '2026-08-08'),

    // Sosyal medya hesapları (footer'da gösterilir; boş kalan ikon '#' yer tutucuya gider)
    'social' => [
        'facebook' => env('COMPANY_FACEBOOK'),
        'instagram' => env('COMPANY_INSTAGRAM'),
        'youtube' => env('COMPANY_YOUTUBE'),
        'linkedin' => env('COMPANY_LINKEDIN'),
    ],
];
