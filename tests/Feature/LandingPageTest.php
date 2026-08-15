<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Tour;
use App\Support\LandingSlug;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 3 — düz landing adresleri (/kapadokya-turlari, /kultur-turlari).
 *
 * Rakip taramasının en net bulgusu: incelenen 9 sitenin HİÇBİRİ kategori veya
 * destinasyon sayfasını query string ile sunmuyor; hepsi tek düz yol segmenti
 * kullanıyor. Bu testler o kalıbı ve eski adreslerin 301'lerini kilitler.
 */
class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    private function tur(array $attributes = []): Tour
    {
        $agency = Agency::create([
            'name' => 'Acenta '.uniqid(),
            'slug' => 'acenta-'.uniqid(),
            'email' => uniqid().'@ornek.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        return Tour::create(array_merge([
            'agency_id' => $agency->id,
            'title' => 'Kapadokya Balon Turu',
            'destination' => 'Kapadokya',
            'description' => 'Peri bacaları turu.',
            'price' => 8000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(15),
            'is_active' => true,
        ], $attributes));
    }

    // ── Slug normalizasyonu ─────────────────────────────────────────────────

    public function test_slug_govdesi_tur_ekinden_arindirilir(): void
    {
        $this->assertSame('kultur', LandingSlug::stem('kultur-turlari'));
        $this->assertSame('gunubirlik', LandingSlug::stem('gunubirlik-turlar'));
        $this->assertSame('deniz-tekne', LandingSlug::stem('deniz-tekne'));
        $this->assertSame('kapadokya', LandingSlug::stem('kapadokya'));
    }

    public function test_tum_landing_adresleri_ayni_ekle_biter(): void
    {
        // Tek biçim, rotanın tek düzenli ifadeyle sınırlanabilmesini sağlıyor.
        $this->assertSame('kultur-turlari', LandingSlug::canonicalize('kultur-turlari'));
        $this->assertSame('deniz-tekne-turlari', LandingSlug::canonicalize('deniz-tekne'));
        $this->assertSame('kapadokya-turlari', LandingSlug::canonicalize('kapadokya'));
    }

    // ── Sayfa render ────────────────────────────────────────────────────────

    public function test_destinasyon_landing_sayfasi_acilir(): void
    {
        $this->tur(['destination' => 'Kapadokya']);
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertSee('Kapadokya Turları', false)
            ->assertSee('Kapadokya Balon Turu');
    }

    public function test_kategori_landing_sayfasi_acilir(): void
    {
        $category = Category::create(['name' => 'Kültür Turları', 'slug' => 'kultur-turlari']);
        $this->tur(['category_id' => $category->id, 'title' => 'Efes Antik Kent Turu']);

        $this->get('/kultur-turlari')
            ->assertOk()
            ->assertSee('Kültür Turları', false)
            ->assertSee('Efes Antik Kent Turu');
    }

    public function test_ust_kategori_alt_kategorinin_turlarini_da_listeler(): void
    {
        $ust = Category::create(['name' => 'Kültür Turları', 'slug' => 'kultur-turlari']);
        $alt = Category::create(['name' => 'Müze & Sanat', 'slug' => 'muze-sanat', 'parent_id' => $ust->id]);
        $this->tur(['category_id' => $alt->id, 'title' => 'Louvre Turu']);

        $this->get('/kultur-turlari')->assertOk()->assertSee('Louvre Turu');
    }

    public function test_eksiz_slug_kanonik_adrese_301_doner(): void
    {
        Category::create(['name' => 'Deniz & Tekne', 'slug' => 'deniz-tekne']);

        // Aynı içerik iki adreste yaşamamalı.
        $this->get('/deniz-tekne-turlari')->assertOk();
    }

    public function test_bilinmeyen_landing_adresi_404_doner(): void
    {
        $this->get('/uydurma-bir-yer-turlari')->assertNotFound();
    }

    public function test_landing_rotasi_mevcut_sayfalari_yutmaz(): void
    {
        // Kök seviyede rota var; /turlar, /blog gibi açık adresleri
        // gölgelememeli (rota dosyanın en sonunda ve ek kısıtlı).
        $this->get('/turlar')->assertOk();
        $this->get('/blog')->assertOk();
    }

    // ── SEO çıktısı ─────────────────────────────────────────────────────────

    public function test_landing_sayfasi_itemlist_ve_breadcrumb_basar(): void
    {
        $this->tur(['destination' => 'Kapadokya']);
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        $html = $this->get('/kapadokya-turlari')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"ItemList"', $html);
        $this->assertStringContainsString('"@type":"BreadcrumbList"', $html);
    }

    public function test_landing_sayfasi_kendine_kanonik_verir(): void
    {
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/kapadokya-turlari').'">', false);
    }

    public function test_landing_sayfasi_yil_damgali_baslik_kullanir(): void
    {
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertSee('Kapadokya Turu Fiyatları '.\App\Support\Seo::year(), false);
    }

    // ── Envanteri biten sayfa ───────────────────────────────────────────────

    public function test_turu_olmayan_sayfa_404_vermez(): void
    {
        // Gruppal kalıbı: /kayak-turlari Ağustos'ta 0 ürün listeliyor ama
        // sayfa canlı duruyor. 404'e düşürmek adresin biriktirdiği değeri
        // çöpe atardı; envanter mevsimlik geri geliyor.
        Category::create(['name' => 'Kayak Turları', 'slug' => 'kayak-turlari']);

        $this->get('/kayak-turlari')
            ->assertOk()
            ->assertSee('Kayak Turları', false)
            ->assertSee('Şu anda bu başlıkta yayında tur yok.');
    }

    // ── Veri-tabanlı içerik blokları ────────────────────────────────────────

    private function kapadokyaTurlari(): void
    {
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        foreach ([['A Acenta', 6000, 2], ['B Acenta', 9000, 3], ['C Acenta', 15000, 3]] as [$ad, $fiyat, $gun]) {
            $agency = Agency::create([
                'name' => $ad,
                'slug' => \Illuminate\Support\Str::slug($ad).'-'.uniqid(),
                'email' => uniqid().'@ornek.com',
                'is_active' => true,
                'legacy_category_access' => true,
            ]);

            Tour::create([
                'agency_id' => $agency->id,
                'title' => $ad.' Kapadokya Turu',
                'destination' => 'Kapadokya',
                'description' => 'Tur.',
                'price' => $fiyat,
                'currency' => 'TRY',
                'duration_days' => $gun,
                'departure_date' => today()->addDays(20),
                'is_active' => true,
            ]);
        }
    }

    public function test_fiyat_ozeti_gercek_veriden_gelir(): void
    {
        $this->kapadokyaTurlari();

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertSee('Kapadokya Tur Fiyatları', false)
            ->assertSee('6.000 ₺', false)   // min
            ->assertSee('15.000 ₺', false)  // max
            ->assertSee('9.000 ₺', false);  // medyan
    }

    public function test_acenta_fiyat_karsilastirma_tablosu_basar(): void
    {
        // Sayfanın rakipte olmayan kısmı: tek acentalı bir site bu tabloyu
        // yapısal olarak üretemez (ölçüm: MNG kendi sayfasında rakip adını
        // 0 kez, Jolly 0 kez yazıyor).
        $this->kapadokyaTurlari();

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertSee('Acenta Fiyat Karşılaştırması', false)
            ->assertSee('A Acenta')
            ->assertSee('B Acenta')
            ->assertSee('C Acenta')
            ->assertSee('EN UYGUN');
    }

    public function test_tek_acenta_varsa_karsilastirma_tablosu_basilmaz(): void
    {
        // Tek satırlık "karşılaştırma" tablosu değersiz; hem kullanıcıya hem
        // Google'a zayıf görünür.
        $this->tur(['destination' => 'Kapadokya']);
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertDontSee('Acenta Fiyat Karşılaştırması', false);
    }

    public function test_landing_faq_semasi_ve_gorunur_blok_eslesir(): void
    {
        $this->kapadokyaTurlari();

        $html = $this->get('/kapadokya-turlari')->assertOk()->getContent();

        $this->assertStringContainsString('Sıkça Sorulan Sorular', $html);
        $this->assertStringContainsString('"@type":"FAQPage"', $html);

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $faq = null;
        foreach ($m[1] as $json) {
            $d = json_decode($json, true);
            if (($d['@type'] ?? null) === 'FAQPage') {
                $faq = $d;
            }
        }

        $this->assertNotNull($faq, 'FAQPage şeması yok.');
        foreach ($faq['mainEntity'] as $soru) {
            $this->assertStringContainsString(e($soru['name']), $html);
        }
    }

    public function test_istatistikler_sayfalamadan_etkilenmez(): void
    {
        // 2. sayfada da aynı rakamlar görünmeli — istatistik sayfalanmamış
        // kümeden hesaplanıyor.
        $this->kapadokyaTurlari();

        $this->get('/kapadokya-turlari?page=1')
            ->assertOk()
            ->assertSee('3 acentanın fiyatı karşılaştırmalı', false);
    }

    public function test_turu_olmayan_sayfada_istatistik_basilmaz(): void
    {
        Category::create(['name' => 'Kayak Turları', 'slug' => 'kayak-turlari']);

        $this->get('/kayak-turlari')
            ->assertOk()
            ->assertDontSee('Tur Fiyatları', false)
            ->assertDontSee('"@type":"FAQPage"', false);
    }

    // ── Şehir profili blokları ──────────────────────────────────────────────

    private function kapadokyaProfili(array $ek = []): void
    {
        // DestinationObserver, Destination oluşturulunca boş bir profil açıyor;
        // bu yüzden create değil updateOrCreate.
        \App\Models\DestinationProfile::updateOrCreate([
            'normalized_city' => \App\Models\DestinationProfile::normalize('Kapadokya'),
        ], array_merge([
            'city' => 'Kapadokya',
            'country' => 'Türkiye',
            'summary' => 'Kapadokya, peri bacaları ve yeraltı şehirleriyle bilinen eşsiz bir bölgedir.',
            'best_months' => [4, 5, 9, 10],
            'crowded_months' => [7, 8],
            'climate_by_month' => [
                '4' => ['temp_c' => 14, 'condition' => 'ılıman'],
                '5' => ['temp_c' => 19, 'condition' => 'ılıman'],
                '9' => ['temp_c' => 20, 'condition' => 'ılıman'],
                '10' => ['temp_c' => 13, 'condition' => 'serin'],
            ],
            'vibe_tags' => ['nature', 'historical', 'photography'],
            'crowd_score' => 0.8,
            'liveliness_score' => 0.5,
            'source' => \App\Models\DestinationProfile::SOURCE_LLM,
            'enrichment_version' => \App\Models\DestinationProfile::CURRENT_ENRICHMENT_VERSION,
        ], $ek));
    }

    public function test_sehir_profili_bolumleri_basar(): void
    {
        // Kaynak: mevcut DestinationProfile tablosu — 55 şehir için zaten
        // üretilmiş içerik. YENİ LLM çağrısı yapılmaz.
        $this->tur(['destination' => 'Kapadokya']);
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);
        $this->kapadokyaProfili();

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertSee('Kapadokya Nasıl Bir Yer?', false)
            ->assertSee('peri bacaları', false)
            ->assertSee('Kapadokya Turlarına Ne Zaman Gidilir?', false)
            ->assertSee('Kapadokya Turları Kimler İçin Uygun?', false);
    }

    public function test_ne_zaman_bolumu_iklim_verisini_kullanir(): void
    {
        $this->tur(['destination' => 'Kapadokya']);
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);
        $this->kapadokyaProfili();

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertSee('Nisan, Mayıs, Eylül ve Ekim', false)
            ->assertSee('13–20°C', false)
            ->assertSee('Temmuz ve Ağustos', false);
    }

    public function test_profil_yoksa_bolumler_basilmaz(): void
    {
        $this->tur(['destination' => 'Kapadokya']);
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        $this->get('/kapadokya-turlari')
            ->assertOk()
            ->assertDontSee('Nasıl Bir Yer?', false);
    }

    public function test_kategori_sayfasinda_sehir_profili_aranmaz(): void
    {
        // "Kültür Turları" bir şehir değil; profil eşleşmesi anlamsız.
        $category = Category::create(['name' => 'Kültür Turları', 'slug' => 'kultur-turlari']);
        $this->tur(['category_id' => $category->id]);

        $this->get('/kultur-turlari')
            ->assertOk()
            ->assertDontSee('Nasıl Bir Yer?', false);
    }

    // ── Sitemap ─────────────────────────────────────────────────────────────

    public function test_sitemap_landing_adreslerini_listeler(): void
    {
        Category::create(['name' => 'Kültür Turları', 'slug' => 'kultur-turlari']);
        Destination::create(['name' => 'Kapadokya', 'slug' => 'kapadokya']);

        $this->get('/sitemap-kategoriler.xml')
            ->assertOk()
            ->assertSee(url('/kultur-turlari'), false);

        $this->get('/sitemap-destinasyonlar.xml')
            ->assertOk()
            // Eski /destinasyonlar/... değil, kanonik düz adres olmalı.
            ->assertSee(url('/kapadokya-turlari'), false)
            ->assertDontSee(url('/destinasyonlar/kapadokya'), false);
    }
}
