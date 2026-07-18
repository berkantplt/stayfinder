<?php

namespace Tests\Unit;

use App\Services\TourImport\TourUrlImporter;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * harvestImages doğruluk testleri: galeri görsellerinin ham HTML'den doğru,
 * tekrarsız ve tema çöpünden arınmış toplanması. lastHtml reflection ile
 * set edilir; ağ isteği atılmaz.
 */
class TourImageHarvestTest extends TestCase
{
    private TourUrlImporter $importer;

    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importer = new TourUrlImporter;
        $this->ref = new ReflectionClass($this->importer);
    }

    public function test_base64_proxy_gallery_images_are_harvested(): void
    {
        // etstur "Fotoğraflar ve Videolar" galerisi (viya.plus/kplus motoru):
        // görseller div.lazy data-src'ta base64 proxy URL'i olarak gömülü —
        // uzantı base64 İÇİNDE olduğundan eski toplayıcılar ıskalıyordu.
        $b64 = base64_encode('agency.viya.plus//AlbumMedia/Tour/24547/fa29e82e-16b0.jpg');
        $b64b = base64_encode('agency.viya.plus//AlbumMedia/Tour/24547/79fb8dd1-a3e0.jpg');
        $html = '<html><body>'
            .'<div class="content-box lazy" data-src="https://cdn.kplus.com.tr/?url='.$b64.'"></div>'
            .'<div class="content-box lazy" data-src="https://cdn.kplus.com.tr/?url='.$b64b.'"></div>'
            .'<div class="lazy" data-src="https://cdn.kplus.com.tr/?url='.base64_encode('x/site.pdf').'"></div>'
            .'</body></html>';
        $prop = $this->ref->getProperty('lastHtml');
        $prop->setValue($this->importer, null);

        // contentFilter ağa çıkmasın diye doğrudan looksLike + aday toplamayı test ediyoruz
        $this->assertTrue($this->looksLike('https://cdn.kplus.com.tr/?url='.$b64), 'base64 jpg proxy kabul');
        $this->assertFalse($this->looksLike('https://cdn.kplus.com.tr/?url='.base64_encode('x/site.pdf')), 'base64 pdf proxy ret');

        $imgs = $this->harvest($html, 'https://www.etstur.com/tur-x');
        $this->assertContains('https://cdn.kplus.com.tr/?url='.$b64, $imgs, 'proxy galeri adayı toplanmalı');
        $this->assertContains('https://cdn.kplus.com.tr/?url='.$b64b, $imgs);
        $this->assertCount(2, $imgs, 'pdf proxy elenmiş olmalı');
    }

    private function looksLike(string $url): bool
    {
        $m = $this->ref->getMethod('looksLikeTourImage');

        return (bool) $m->invoke($this->importer, $url);
    }

    /** @return array<int, string> */
    private function harvest(string $html, string $pageUrl): array
    {
        $prop = $this->ref->getProperty('lastHtml');
        $prop->setValue($this->importer, $html);

        $method = $this->ref->getMethod('harvestImages');

        return $method->invoke($this->importer, $pageUrl);
    }

    public function test_blacklist_words_are_word_bounded(): void
    {
        // etstur Fas vakası: 'blank' kara-liste kelimesi "KazaBLANKa" şehir adının
        // içinde eşleşip GERÇEK tur fotoğraflarını kapıda reddediyordu (import 0
        // fotoğrafla döndü). Belirsiz kısa kelimeler kelime-sınırlı olmalı.
        $yes = [
            'https://images.etstur.com/imgproxy/files/images/site/images/cmsRoot/tourMedia/etstur-kazablanka-genel-1-155.jpg',
            'https://x.com/tour/flagship-hotel.jpg',   // flag ≠ flagship
            'https://x.com/media/rubicon-tour.jpg',    // icon ≠ rubicon
        ];
        $no = [
            'https://x.com/assets/site-logo.png',
            'https://x.com/img/blank.gif',
            'https://x.com/icons/icon-32.png',
            'https://x.com/logo@2x.png',
        ];
        foreach ($yes as $u) {
            $this->assertTrue($this->looksLike($u), "kabul edilmeli: $u");
        }
        foreach ($no as $u) {
            $this->assertFalse($this->looksLike($u), "reddedilmeli: $u");
        }
    }

    public function test_tema_copu_elenir_boyut_varyanti_tekillesir_ve_kapak_basa_gelir(): void
    {
        $html = <<<'HTML'
        <html><head>
        <meta property="og:image" content="https://cdng.example.com/files/packagephoto/aaa-1024.jpg">
        </head><body>
        <img src="https://cdng.example.com/files/packagephoto/aaa-1024.jpg">
        <img src="https://cdng.example.com/files/packagephoto/aaa.jpg">
        <img src="https://cdng.example.com/files/packagephoto/bbb-1024.jpg">
        <img src="https://cdng.example.com/files/packagephoto/ccc-1024.jpg">
        <img src="https://cdn2.example.com/theme/no-images/flight/path.png">
        <img src="https://cdn2.example.com/theme/470x338.jpg">
        <img src="https://cdn2.example.com/theme/default-slider-image.jpg">
        </body></html>
        HTML;

        $result = $this->harvest($html, 'https://www.example.com/tur/amalfi');

        // Tema çöpleri girmedi
        foreach ($result as $u) {
            $this->assertStringNotContainsString('/theme/', $u);
        }
        // aaa'nın iki boyut varyantı tek görsele indi (eksiz orijinal kazandı)
        $this->assertContains('https://cdng.example.com/files/packagephoto/aaa.jpg', $result);
        $this->assertNotContains('https://cdng.example.com/files/packagephoto/aaa-1024.jpg', $result);
        // Kapak (og:image grubunun temsilcisi) ilk sırada
        $this->assertSame('https://cdng.example.com/files/packagephoto/aaa.jpg', $result[0]);
        // Gerçek galeri tam
        $this->assertContains('https://cdng.example.com/files/packagephoto/bbb-1024.jpg', $result);
        $this->assertContains('https://cdng.example.com/files/packagephoto/ccc-1024.jpg', $result);
        $this->assertCount(3, $result);
    }

    public function test_bir_iki_basamakli_galeri_numaralari_birlesmez(): void
    {
        $html = '<img src="https://s.com/uploads/foto-1.jpg"><img src="https://s.com/uploads/foto-2.jpg"><img src="https://s.com/uploads/foto-3.jpg">';

        $result = $this->harvest($html, 'https://s.com/tur/x');

        $this->assertCount(3, $result);
    }

    public function test_wordpress_boyut_varyantlari_orijinale_iner(): void
    {
        $html = '<img src="https://s.com/uploads/photo-150x150.jpg"><img src="https://s.com/uploads/photo-768x512.jpg"><img src="https://s.com/uploads/photo.jpg">';

        $result = $this->harvest($html, 'https://s.com/tur/x');

        $this->assertSame(['https://s.com/uploads/photo.jpg'], $result);
    }

    public function test_orijinal_yoksa_en_buyuk_varyant_kalir(): void
    {
        $html = '<img src="https://s.com/uploads/photo-150x150.jpg"><img src="https://s.com/uploads/photo-768x512.jpg">';

        $result = $this->harvest($html, 'https://s.com/tur/x');

        $this->assertSame(['https://s.com/uploads/photo-768x512.jpg'], $result);
    }

    public function test_srcset_ve_belge_goreli_yollar_yakalanir(): void
    {
        $html = <<<'HTML'
        <img srcset="/media/gal/a-480.jpg 480w, /media/gal/a-1024.jpg 1024w">
        <img src="img/b.jpg">
        <img data-src="../foto/c.jpg">
        HTML;

        $result = $this->harvest($html, 'https://site.com/turlar/paris-turu');

        $this->assertContains('https://site.com/media/gal/a-1024.jpg', $result);
        $this->assertNotContains('https://site.com/media/gal/a-480.jpg', $result);
        $this->assertContains('https://site.com/turlar/img/b.jpg', $result);
        $this->assertContains('https://site.com/foto/c.jpg', $result);
    }

    public function test_uzantisiz_cdn_gorseli_img_etiketinden_gelir(): void
    {
        $html = '<img src="https://res.cloudinary.com/demo/image/upload/v123/abc">';

        $result = $this->harvest($html, 'https://site.com/tur/x');

        $this->assertSame(['https://res.cloudinary.com/demo/image/upload/v123/abc'], $result);
    }

    public function test_kotu_adaylarin_tamami_elenir(): void
    {
        $html = <<<'HTML'
        <img src="https://s.com/img/no-image.jpg">
        <img src="https://s.com/img/default-tour.jpg">
        <img src="https://s.com/img/logo.png">
        <img src="https://s.com/theme/hero.jpg">
        <img src="https://s.com/img/470x338.jpg">
        <img src="https://s.com/img/lazyload.gif">
        HTML;

        $this->assertSame([], $this->harvest($html, 'https://s.com/tur/x'));
    }

    public function test_baskin_kume_yokken_skorla_siralanir(): void
    {
        $html = <<<'HTML'
        <meta property="og:image" content="https://s.com/uploads/tours/x.jpg">
        <img src="https://baska-cdn.com/random/dis.jpg">
        <img src="https://s.com/uploads/tours/y.jpg">
        HTML;

        $result = $this->harvest($html, 'https://s.com/tur/x');

        // Kapak ilk, aynı klasördeki ikinci, yabancı host son
        $this->assertSame([
            'https://s.com/uploads/tours/x.jpg',
            'https://s.com/uploads/tours/y.jpg',
            'https://baska-cdn.com/random/dis.jpg',
        ], $result);
    }

    public function test_koke_goreli_query_stringli_gorsel_korunur(): void
    {
        $html = '<img src="/uploads/x.jpg?v=123&sig=abc">';

        $result = $this->harvest($html, 'https://s.com/tur/x');

        $this->assertSame(['https://s.com/uploads/x.jpg?v=123&sig=abc'], $result);
    }

    public function test_javascript_ve_data_adaylari_reddedilir(): void
    {
        $html = '<img src="javascript:void(0)"><img src="data:image/gif;base64,R0lGOD"><img data-src="{{ lazyUrl }}">';

        $this->assertSame([], $this->harvest($html, 'https://s.com/tur/x'));
    }

    public function test_sifir_dolgulu_numaralar_ve_yillar_farkli_fotograftir(): void
    {
        $html = '<img src="https://s.com/uploads/kapadokya-001.jpg"><img src="https://s.com/uploads/kapadokya-002.jpg">'
            .'<img src="https://s.com/uploads/kapadokya-003.jpg"><img src="https://s.com/uploads/festival-2023.jpg">'
            .'<img src="https://s.com/uploads/festival-2024.jpg"><img src="https://s.com/uploads/IMG-1001.jpg">'
            .'<img src="https://s.com/uploads/IMG-1002.jpg">';

        $result = $this->harvest($html, 'https://s.com/tur/x');

        // Hiçbiri SIZE_SUFFIXES'te değil → 7 ayrı fotoğraf korunur
        $this->assertCount(7, $result);
    }

    public function test_wp_aylik_klasore_bolunmus_galeri_tam_gelir(): void
    {
        $html = <<<'HTML'
        <meta property="og:image" content="https://s.com/uploads/2025/05/kapak.jpg">
        <img src="https://s.com/uploads/2025/05/foto-a.jpg">
        <img src="https://s.com/uploads/2025/06/foto-b.jpg">
        <img src="https://s.com/uploads/2025/06/foto-c.jpg">
        <img src="https://s.com/uploads/2025/06/foto-d.jpg">
        HTML;

        $result = $this->harvest($html, 'https://s.com/tur/x');

        // Tarih klasörü soyulduğu için 05 ve 06 aynı küme: hepsi gelir, kapak ilk
        $this->assertCount(5, $result);
        $this->assertSame('https://s.com/uploads/2025/05/kapak.jpg', $result[0]);
        $this->assertContains('https://s.com/uploads/2025/06/foto-d.jpg', $result);
    }

    public function test_sinyalsiz_yabanci_cop_kota_bos_olsa_da_girmez(): void
    {
        $html = <<<'HTML'
        <meta property="og:image" content="https://s.com/packagephoto/a.jpg">
        <img src="https://s.com/packagephoto/b.jpg">
        <img src="https://s.com/packagephoto/c.jpg">
        <img src="https://tracker.example.net/x/y/rand.jpg">
        HTML;

        $result = $this->harvest($html, 'https://s.com/tur/x');

        $this->assertCount(3, $result);
        $this->assertNotContains('https://tracker.example.net/x/y/rand.jpg', $result);
    }

    public function test_video_source_mp4_girmez(): void
    {
        $html = '<video><source src="https://s.com/media/tanitim.mp4" type="video/mp4"></video>';

        $this->assertSame([], $this->harvest($html, 'https://s.com/tur/x'));
    }

    public function test_imzali_cdn_urlde_queryli_kopya_kazanir(): void
    {
        // Desen-1 (tüm HTML regex'i) query'siz kırpık kopyayı, etiket taraması tam
        // kopyayı üretir; aynı gruba düşer ve query'li (imzalı) kopya seçilir.
        $html = '<img src="https://cdn.s.com/gal/a.jpg?sig=abc123&w=1200">';

        $result = $this->harvest($html, 'https://s.com/tur/x');

        $this->assertSame(['https://cdn.s.com/gal/a.jpg?sig=abc123&w=1200'], $result);
    }

    public function test_cloudinary_transformlu_srcset_parcalanmaz(): void
    {
        $html = '<img srcset="https://res.cloudinary.com/d/image/upload/w_300,c_fill/t1.jpg 300w, https://res.cloudinary.com/d/image/upload/w_800,c_fill/t1.jpg 800w">';

        $result = $this->harvest($html, 'https://s.com/tur/x');

        // Transform virgülünden bölünüp "…/upload/w_300" gibi kırpık çöp üretilmez
        foreach ($result as $u) {
            $this->assertStringEndsWith('t1.jpg', $u);
        }
        $this->assertNotEmpty($result);
    }

    public function test_torino_image_gibi_masum_adlar_engellenmez(): void
    {
        $html = '<img src="https://s.com/uploads/torino-image.jpg">';

        $this->assertSame(['https://s.com/uploads/torino-image.jpg'], $this->harvest($html, 'https://s.com/tur/x'));
    }

    public function test_sondaki_slashli_sayfada_belge_goreli_dogru_cozulur(): void
    {
        $html = '<img src="galeri/foto1.jpg">';

        $result = $this->harvest($html, 'https://s.com/turlar/kapadokya-turu/');

        $this->assertSame(['https://s.com/turlar/kapadokya-turu/galeri/foto1.jpg'], $result);
    }

    public function test_dinamik_gorsel_scriptinde_query_kimliktir(): void
    {
        $html = '<img src="/getimage.php?img=101"><img src="/getimage.php?img=102">';

        $result = $this->harvest($html, 'https://s.com/tur/x');

        $this->assertCount(2, $result);
    }

    public function test_ipucu_kelimeli_yabanci_banner_kota_bos_olsa_da_girmez(): void
    {
        // Güçlü galeri varken farklı alt-domaindeki site geneli banner'lar
        // (media/img kelime ipucuyla) kota boşluğunu dolduramaz
        $html = <<<'HTML'
        <meta property="og:image" content="https://cdng.s.com/files/packagephoto/a.jpg">
        <img src="https://cdng.s.com/files/packagephoto/b.jpg">
        <img src="https://cdng.s.com/files/packagephoto/c.jpg">
        <img src="https://concore.s.com/concore/media/departman-slider/kampanya/vitrin.png">
        <img src="https://www.s.com/assets/img/security-rozeti.jpg">
        HTML;

        $result = $this->harvest($html, 'https://www.s.com/tur/x');

        $this->assertCount(3, $result);
        foreach ($result as $u) {
            $this->assertStringContainsString('/packagephoto/', $u);
        }
    }
}
