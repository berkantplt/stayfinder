<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Support\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 1 teknik SEO kilidi.
 *
 * Buradaki her şey sessizce bozulabilen türden: kimse bir hata görmez, sayfa
 * açılmaya devam eder, sadece Google sayfayı indexlemeyi bırakır. Bu yüzden
 * teste bağlandı.
 */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private function tur(array $attributes = []): Tour
    {
        $agency = Agency::create([
            'name' => 'Test Acenta '.uniqid(),
            'slug' => 'test-acenta-'.uniqid(),
            'email' => uniqid().'@ornek.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        return Tour::create(array_merge([
            'agency_id' => $agency->id,
            'title' => 'Kapadokya Balon Turu',
            'destination' => 'Kapadokya',
            'description' => 'Açıklama metni.',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(13),
            'is_active' => true,
        ], $attributes));
    }

    // ── Slug URL'leri ───────────────────────────────────────────────────────

    public function test_tur_adresi_slug_kullanir(): void
    {
        $tour = $this->tur();

        $this->assertSame('kapadokya-balon-turu', $tour->slug);
        $this->assertStringEndsWith('/turlar/kapadokya-balon-turu', route('tours.show', $tour));
    }

    public function test_ayni_baslik_rastgele_degil_sayisal_son_ek_alir(): void
    {
        $this->tur();
        $ikinci = $this->tur();

        // Eski davranış "kapadokya-balon-turu-a3f9x" üretiyordu; rastgele son ek
        // anahtar kelimeyi seyreltiyor.
        $this->assertSame('kapadokya-balon-turu-2', $ikinci->slug);
    }

    public function test_eski_id_adresi_slug_adresine_301_doner(): void
    {
        $tour = $this->tur();

        $this->get('/turlar/'.$tour->id)
            ->assertStatus(301)
            ->assertRedirect(route('tours.show', $tour));
    }

    public function test_id_yonlendirmesi_sorgu_dizesini_korur(): void
    {
        $tour = $this->tur();

        // utm etiketleri kampanya ölçümünde kritik; yönlendirmede düşerse
        // trafiğin kaynağı kaybolur.
        $this->get('/turlar/'.$tour->id.'?utm_source=instagram')
            ->assertStatus(301)
            ->assertRedirect(route('tours.show', $tour).'?utm_source=instagram');
    }

    public function test_eski_rastgele_son_ekli_slug_301_ile_kurtarilir(): void
    {
        // Slug üretimi bir dönem "kapadokya-balon-turu-wDBRf" biçimindeydi ve
        // bu adresler canlıda sitemap'te yayınlandı. backfill onları
        // yeniden adlandırınca 404'e düştüler — indekslenmiş adres kaybı.
        $tour = $this->tur(['title' => 'Kapadokya Balon Turu']);

        $this->get('/turlar/kapadokya-balon-turu-wDBRf')
            ->assertStatus(301)
            ->assertRedirect(route('tours.show', $tour));
    }

    public function test_turkce_kelimeyle_biten_slug_kirpilmaz(): void
    {
        // "...-dahil", "...-gidis", "...-donus" gerçek slug parçaları; 5 harfli
        // diye kırpılıp başka bir tura yönlendirilmemeli.
        $this->tur(['title' => 'Mısır Turu Ekstra Turlar Dahil']);

        $this->get('/turlar/misir-turu-ekstra-turlar-dahil')->assertOk();
        $this->get('/turlar/misir-turu-ekstra-turlar-dahil-bilet')->assertNotFound();
    }

    public function test_acenta_adresi_slug_kullanir_ve_id_301_doner(): void
    {
        $tour = $this->tur();
        $agency = $tour->agency;

        $this->get('/acentalar/'.$agency->id)
            ->assertStatus(301)
            ->assertRedirect(route('agencies.show', $agency));
    }

    // ── JSON-LD ─────────────────────────────────────────────────────────────

    public function test_baslikta_tirnak_varken_json_ld_gecerli_kalir(): void
    {
        // ESKİ HATA: JSON, Blade interpolasyonuyla elle yazılıyordu. Başlıktaki
        // tek bir çift tırnak JSON'ı bozuyor ve Google sayfanın TÜM yapısal
        // verisini atıyordu — hiçbir yerde hata görünmeden.
        $tour = $this->tur(['title' => 'İstanbul "Boğaz" Turu & O\'Neill']);

        $html = $this->get(route('tours.show', $tour))->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches[1], 'Sayfada hiç JSON-LD yok.');

        foreach ($matches[1] as $json) {
            $this->assertNotNull(
                json_decode($json, true),
                'Geçersiz JSON-LD: '.json_last_error_msg()
            );
        }
    }

    public function test_json_ld_script_etiketiyle_kapatilamaz(): void
    {
        $tour = $this->tur(['title' => 'Tur </script><script>alert(1)</script>']);

        $html = $this->get(route('tours.show', $tour))->assertOk()->getContent();

        // JSON_HEX_TAG sayesinde "<" karakteri < olarak kaçışlanır.
        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertStringContainsString('<', $html);
    }

    // ── Breadcrumb ──────────────────────────────────────────────────────────

    public function test_tur_sayfasi_breadcrumb_semasi_basar(): void
    {
        $category = Category::create(['name' => 'Kültür Turları', 'slug' => 'kultur-turlari']);
        $tour = $this->tur(['category_id' => $category->id]);

        $html = $this->get(route('tours.show', $tour))->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
        $this->assertStringContainsString('"name":"Kültür Turları"', $html);
        // Son eleman (bulunulan sayfa) "item" almamalı — Google'ın istediği biçim.
        $this->assertStringContainsString('"position":4', $html);
    }

    // ── Canonical ve indexleme ──────────────────────────────────────────────

    public function test_sayfalama_kendine_kanonik_verir(): void
    {
        // ESKİ HATA: url()->current() sorgu dizesini atıyordu, ?page=2 de 1.
        // sayfaya kanonik veriyordu — Google 2. sayfadaki turları hiç görmüyordu.
        $this->get('/turlar?page=2')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/turlar').'?page=2">', false);
    }

    public function test_ilk_sayfa_kanonikte_page_parametresi_tasimaz(): void
    {
        $this->get('/turlar?page=1')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/turlar').'">', false);
    }

    public function test_izleme_parametreleri_kanonikten_dusulur(): void
    {
        $this->get('/turlar?utm_source=instagram&fbclid=abc')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/turlar').'">', false);
    }

    public function test_tekil_facet_duz_landing_adresine_301_doner(): void
    {
        // Rakip taramasında incelenen 9 sitenin hiçbiri kategori sayfasını
        // query string ile sunmuyor. Tek facet artık düz adreste yaşıyor.
        $category = Category::create(['name' => 'Kültür Turları', 'slug' => 'kultur-turlari']);

        $this->get('/turlar?category=kultur-turlari')
            ->assertStatus(301)
            ->assertRedirect(\App\Support\LandingSlug::urlForCategory($category));
    }

    public function test_coklu_filtre_yonlendirilmez(): void
    {
        // "?category=x&min_price=y" bir landing sayfası değil, kullanıcının
        // kurduğu filtre. Yönlendirmek seçimini silerdi.
        Category::create(['name' => 'Kültür Turları', 'slug' => 'kultur-turlari']);

        $this->get('/turlar?category=kultur-turlari&min_price=1000')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_noindex_sayfa_kendine_kanonik_verir(): void
    {
        // noindex + başka adrese canonical çelişkili sinyaldir; Google ikisini
        // birden yok sayabilir.
        $html = $this->get('/turlar?sort=price_desc')->assertOk()->getContent();

        $this->assertStringContainsString('name="robots" content="noindex,follow"', $html);
        $this->assertStringContainsString(
            '<link rel="canonical" href="'.url('/turlar').'?sort=price_desc">',
            $html
        );
    }

    public function test_filtre_kombinasyonu_noindex_alir(): void
    {
        // Kombinasyonlar kombinatoryal çoğalır; hepsi indexlenirse tarama
        // bütçesi yanar ve yinelenen içerik sinyali doğar.
        $this->get('/turlar?category=kultur-turlari&min_price=1000&sort=price_desc')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_siralama_tek_basina_da_noindex_alir(): void
    {
        $this->get('/turlar?sort=price_desc')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false);
    }

    public function test_tur_detay_sayfasi_indexlenir(): void
    {
        $tour = $this->tur();

        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertDontSee('name="robots"', false);
    }

    public function test_page_bir_adresi_temiz_adrese_indirgenir(): void
    {
        $this->assertSame('https://ornek.com/turlar', Seo::withoutFirstPage('https://ornek.com/turlar?page=1'));
        $this->assertSame('https://ornek.com/turlar?category=x', Seo::withoutFirstPage('https://ornek.com/turlar?category=x&page=1'));
        $this->assertSame('https://ornek.com/turlar?page=2', Seo::withoutFirstPage('https://ornek.com/turlar?page=2'));
    }

    // ── Başlık formülleri ───────────────────────────────────────────────────

    public function test_yil_damgasi_degiskenden_gelir(): void
    {
        // SSC Tur'un sayfası hâlâ elle yazılmış "2025" taşıyor. Bizde yıl elle
        // yazılmaz; Kasım'dan itibaren bir sonraki yıla geçer (tur satışı öne çalışır).
        $this->travelTo(now()->setDate(2027, 6, 15));
        $this->assertSame(2027, Seo::year());

        $this->travelTo(now()->setDate(2027, 11, 3));
        $this->assertSame(2028, Seo::year());

        $this->travelBack();
    }

    public function test_liste_basligi_kalibi(): void
    {
        $this->travelTo(now()->setDate(2027, 3, 1));

        $this->assertSame(
            'Kapadokya Turları | Kapadokya Turu Fiyatları 2027 — turXtur',
            Seo::listingTitle('Kapadokya')
        );

        $this->travelBack();
    }

    public function test_adda_zaten_tur_eki_varsa_tekrarlanmaz(): void
    {
        // Kategori adları "Kültür Turları" biçiminde geliyor; naif ekleme
        // "Kültür Turları Turları" üretiyordu.
        $this->travelTo(now()->setDate(2027, 3, 1));

        $this->assertSame('Kültür Turları', Seo::listingHeading('Kültür Turları'));
        $this->assertSame('Kapadokya Turları', Seo::listingHeading('Kapadokya'));
        $this->assertSame('Günübirlik Turları', Seo::listingHeading('Günübirlik Turlar'));

        $this->assertSame(
            'Kültür Turları | Kültür Turu Fiyatları 2027 — turXtur',
            Seo::listingTitle('Kültür Turları')
        );

        $this->travelBack();
    }

    public function test_uzun_adda_baslik_62_karakteri_asmaz(): void
    {
        // Prontotour'un 85 karakterlik başlığı SERP'te kesiliyor ve markayı iki
        // kez tekrar ediyor — o hataya düşmemeli.
        foreach (['Kapadokya', 'Kuzey Avrupa ve Fiyortlar', 'Güneydoğu Anadolu Gezisi'] as $ad) {
            $this->assertLessThanOrEqual(
                62,
                mb_strlen(Seo::listingTitle($ad)),
                "Başlık çok uzun: {$ad}"
            );
        }
    }

    public function test_eski_destinasyon_adresi_landing_sayfasina_301_doner(): void
    {
        $destination = \App\Models\Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        $this->get(route('destinations.show', $destination))
            ->assertStatus(301)
            ->assertRedirect(url('/kapadokya-turlari'));
    }

    public function test_landing_sayfasi_h1_envanter_sayisi_gosterir(): void
    {
        $this->tur(['destination' => 'Kapadokya']);
        \App\Models\Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertSee('Kapadokya Turları', false)
            ->assertSee('(1)', false);
    }

    // ── Sitemap ─────────────────────────────────────────────────────────────

    public function test_sitemap_index_bolumleri_listeler(): void
    {
        $this->tur();

        $response = $this->get('/sitemap.xml')->assertOk();

        $this->assertNotFalse(simplexml_load_string($response->getContent()), 'sitemap.xml geçerli XML değil.');
        $response->assertSee('sitemap-turlar.xml', false);
        $response->assertSee('sitemap-acentalar.xml', false);
        $response->assertSee('sitemap-sayfalar.xml', false);
    }

    public function test_sitemap_turlari_slug_adresiyle_listeler(): void
    {
        $tour = $this->tur();

        $this->get('/sitemap-turlar.xml')
            ->assertOk()
            ->assertSee(route('tours.show', $tour), false);
    }

    public function test_sitemap_pasif_acentanin_turunu_listelemez(): void
    {
        // Pasif acentanın turu sitede 404 veriyor; sitemap'e konursa Google'a
        // kırık adres bildirilmiş olur.
        $tour = $this->tur();
        $tour->agency->update(['is_active' => false]);

        $this->get('/sitemap-turlar.xml')
            ->assertOk()
            ->assertDontSee(route('tours.show', $tour), false);
    }

    public function test_sitemap_acenta_sayfalarini_icerir(): void
    {
        // Eski sitemap'te acenta sayfaları HİÇ yoktu ve onlara link veren
        // public bir liste sayfası da yok — sitemap tek keşif yolu.
        $tour = $this->tur();

        $this->get('/sitemap-acentalar.xml')
            ->assertOk()
            ->assertSee(route('agencies.show', $tour->agency), false);
    }

    public function test_bilinmeyen_sitemap_bolumu_404_doner(): void
    {
        $this->get('/sitemap-uydurma.xml')->assertNotFound();
    }

    // ── robots.txt ──────────────────────────────────────────────────────────

    public function test_robots_uretimde_mutlak_sitemap_adresi_verir(): void
    {
        // Spec mutlak URL şart koşar; eski dosyadaki "Sitemap: /sitemap.xml"
        // satırını Google sessizce yok sayıyordu.
        app()['env'] = 'production';

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Sitemap: '.route('sitemap'), false);
    }

    public function test_robots_acenta_sayfalarini_engellemez(): void
    {
        // ESKİ HATA: "Disallow: /acenta" ön-ek eşleşmesi yüzünden herkese açık
        // /acentalar/... sayfalarını da kapatıyordu.
        app()['env'] = 'production';

        $content = $this->get('/robots.txt')->assertOk()->getContent();

        $this->assertStringNotContainsString("Disallow: /acenta\n", $content);
        $this->assertStringContainsString('Disallow: /acenta$', $content);
        $this->assertStringContainsString('Disallow: /acenta/', $content);
    }

    public function test_robots_uretim_disinda_taramayi_kapatir(): void
    {
        // Prova ortamı indexlenirse canlı siteyle birebir kopya içerik olur.
        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /', false);
    }
}
