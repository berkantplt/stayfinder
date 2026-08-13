{{--
    Yapısal veri (JSON-LD) basımı — TEK GÜVENLİ YOL.

    Blade interpolasyonuyla ("name": "{{ $tour->title }}") elle JSON yazmak
    kırılgandı: başlıkta bir tek tırnak veya çift tırnak geçtiğinde JSON parse
    edilemiyor ve Google o sayfanın TÜM yapısal verisini sessizce atıyordu.

    Burada dizi json_encode edilir:
      JSON_HEX_TAG          → içerikteki "</script>" script'i erken kapatamaz (XSS)
      JSON_UNESCAPED_UNICODE→ Türkçe karakterler \u kaçışına dönüşmez, okunur kalır
      JSON_UNESCAPED_SLASHES→ URL'lerdeki / gereksiz kaçışlanmaz

    Kullanım:
      @include('partials.json-ld', ['data' => [ '@context' => 'https://schema.org', ... ]])

    @var array $data
--}}
@if (!empty($data))
<script type="application/ld+json">{!! json_encode($data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}</script>
@endif
