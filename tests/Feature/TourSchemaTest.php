<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Support\TourSchema;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 2 — tur detay sayfası yapısal verisi.
 *
 * Testlerin çoğu, rakip taramasında ölçülen SOMUT hataların bizde
 * tekrarlanmadığını kilitliyor (setur'un "TL"si, jolly'nin bozuk JSON'ı,
 * tatilbudur'un boş description'ı gibi).
 */
class TourSchemaTest extends TestCase
{
    use RefreshDatabase;

    private function tur(array $attributes = []): Tour
    {
        $agency = Agency::create([
            'name' => 'Örnek Acenta',
            'slug' => 'ornek-acenta-'.uniqid(),
            'email' => uniqid().'@ornek.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        return Tour::create(array_merge([
            'agency_id' => $agency->id,
            'title' => 'Kapadokya Balon Turu',
            'destination' => 'Kapadokya',
            'description' => 'Peri bacaları ve balon turu içeren üç günlük program.',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'duration_nights' => 2,
            'departure_date' => today()->addDays(20),
            'return_date' => today()->addDays(23),
            'is_active' => true,
        ], $attributes));
    }

    /** @return array<string, mixed> */
    private function nodeOfType(Tour $tour, string $type): array
    {
        foreach (TourSchema::graph($tour)['@graph'] as $node) {
            if (($node['@type'] ?? null) === $type) {
                return $node;
            }
        }

        return [];
    }

    // ── Yapı ────────────────────────────────────────────────────────────────

    public function test_graph_touristtrip_ve_product_birlikte_basar(): void
    {
        $tour = $this->tur();
        $types = array_column(TourSchema::graph($tour)['@graph'], '@type');

        // tatilsepeti ve jolly bir TURA LodgingBusiness basıyor — turizme özgü
        // doğru tip TouristTrip.
        $this->assertContains('TouristTrip', $types);
        $this->assertContains('Product', $types);
        $this->assertNotContains('LodgingBusiness', $types);
    }

    public function test_dugumler_ayri_id_tasir(): void
    {
        $tour = $this->tur();

        $this->assertStringEndsWith('#trip', $this->nodeOfType($tour, 'TouristTrip')['@id']);
        $this->assertStringEndsWith('#product', $this->nodeOfType($tour, 'Product')['@id']);
    }

    public function test_uretilen_json_gecerli(): void
    {
        // jolly'nin JSON-LD'si fazladan ";" yüzünden parse edilmiyor. Bizimki
        // diziden json_encode edildiği için sözdizimi hatası imkânsız.
        $tour = $this->tur(['title' => 'Kapadokya "Balon" Turu & O\'Neill']);

        $json = json_encode(TourSchema::graph($tour), JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);

        $this->assertNotFalse($json);
        $this->assertIsArray(json_decode($json, true));
    }

    // ── Fiyat / teklif ──────────────────────────────────────────────────────

    public function test_gecersiz_para_birimi_teklifi_dusurur(): void
    {
        // setur'un hatası: priceCurrency="TL". ISO 4217'de böyle bir kod yok.
        $tour = $this->tur(['currency' => 'TL']);

        $this->assertArrayNotHasKey('offers', $this->nodeOfType($tour, 'Product'));
    }

    public function test_tek_fiyatta_offer_basar(): void
    {
        $offer = $this->nodeOfType($this->tur(), 'Product')['offers'];

        $this->assertSame('Offer', $offer['@type']);
        $this->assertSame('10000.00', $offer['price']);
        $this->assertSame('TRY', $offer['priceCurrency']);
        $this->assertSame('https://schema.org/InStock', $offer['availability']);
    }

    public function test_farkli_tarih_fiyatlarinda_aggregateoffer_basar(): void
    {
        $tour = $this->tur();
        $tour->dates()->create([
            'departure_date' => today()->addDays(30),
            'return_date' => today()->addDays(33),
            'price' => 12000,
        ]);
        $tour->dates()->create([
            'departure_date' => today()->addDays(60),
            'return_date' => today()->addDays(63),
            'price' => 15000,
        ]);
        $tour->load('dates');

        $offer = $this->nodeOfType($tour, 'Product')['offers'];

        $this->assertSame('AggregateOffer', $offer['@type']);
        $this->assertSame('10000.00', $offer['lowPrice']);
        $this->assertSame('15000.00', $offer['highPrice']);
        $this->assertSame(3, $offer['offerCount']);
    }

    public function test_teklifte_satici_acenta_yazar(): void
    {
        $offer = $this->nodeOfType($this->tur(), 'Product')['offers'];

        $this->assertSame('TravelAgency', $offer['seller']['@type']);
        $this->assertSame('Örnek Acenta', $offer['seller']['name']);
    }

    public function test_gecmis_tarihli_pricevaliduntil_basilmaz(): void
    {
        // Google, priceValidUntil'i geçmiş olan teklifi zengin sonuçtan düşürür.
        $tour = $this->tur([
            'departure_date' => today()->subDays(60),
            'return_date' => today()->subDays(55),
        ]);

        $this->assertArrayNotHasKey('priceValidUntil', $this->nodeOfType($tour, 'Product')['offers']);
    }

    public function test_gelecek_tarihli_pricevaliduntil_basilir(): void
    {
        $offer = $this->nodeOfType($this->tur(), 'Product')['offers'];

        $this->assertSame(today()->addDays(23)->toDateString(), $offer['priceValidUntil']);
    }

    // ── Günlük program ──────────────────────────────────────────────────────

    public function test_itinerary_subtrip_olarak_basar(): void
    {
        // İncelenen 6 rakip sitenin 4'ü günlük programı schema'ya HİÇ taşımıyor.
        $tour = $this->tur(['itinerary' => [
            ['title' => '1. Gün: İstanbul – Kapadokya', 'content' => 'Hareket ve Göreme gezisi.'],
            ['title' => '2. Gün: Balon Turu', 'content' => 'Gün doğumunda balon uçuşu.'],
        ]]);

        $subTrips = $this->nodeOfType($tour, 'TouristTrip')['subTrip'];

        $this->assertCount(2, $subTrips);
        $this->assertSame('Trip', $subTrips[0]['@type']);
        $this->assertSame('1. Gün: İstanbul – Kapadokya', $subTrips[0]['name']);
        $this->assertSame('Gün doğumunda balon uçuşu.', $subTrips[1]['description']);
    }

    public function test_bos_itinerary_subtrip_uretmez(): void
    {
        // Şu an 92 turun tamamında itinerary boş; alan hiç basılmamalı.
        $this->assertArrayNotHasKey('subTrip', $this->nodeOfType($this->tur(), 'TouristTrip'));
    }

    public function test_bos_gun_atlanir(): void
    {
        $tour = $this->tur(['itinerary' => [
            ['title' => '', 'content' => ''],
            ['title' => '2. Gün', 'content' => 'Dolu gün.'],
        ]]);

        $this->assertCount(1, $this->nodeOfType($tour, 'TouristTrip')['subTrip']);
    }

    // ── Boş alan basılmaması ────────────────────────────────────────────────

    public function test_bos_aciklama_ve_gorsel_alani_hic_basilmaz(): void
    {
        // tatilbudur'un hatası: description:"" ve image:[""] boş basıyor.
        $tour = $this->tur(['description' => '', 'image' => null]);
        $product = $this->nodeOfType($tour, 'Product');

        $this->assertArrayNotHasKey('description', $product);
        $this->assertArrayNotHasKey('image', $product);
    }

    public function test_puan_ve_yorum_basilmaz(): void
    {
        // Gerçek yorum olmadan aggregateRating basmak manuel işlem sebebi.
        $product = $this->nodeOfType($this->tur(), 'Product');

        $this->assertArrayNotHasKey('aggregateRating', $product);
        $this->assertArrayNotHasKey('review', $product);
    }

    // ── SSS ─────────────────────────────────────────────────────────────────

    public function test_faq_gercek_veriden_uretilir(): void
    {
        $tour = $this->tur([
            'included' => "Ulaşım\nKahvaltı\nRehberlik",
            'excluded' => "Öğle yemeği\nMüze girişleri",
        ]);

        $faq = $this->nodeOfType($tour, 'FAQPage');
        $sorular = array_column($faq['mainEntity'], 'name');

        $this->assertContains('Tur fiyatına neler dahil?', $sorular);
        $this->assertContains('Tur fiyatına neler dahil değil?', $sorular);
        $this->assertStringContainsString('Kahvaltı', $faq['mainEntity'][1]['acceptedAnswer']['text']);
    }

    public function test_veri_yoksa_soru_uydurulmaz(): void
    {
        // Yalnız süre sorusu üretilebilir → tek soruluk SSS basılmaz.
        $tour = $this->tur(['included' => null, 'excluded' => null, 'cancellation_policy' => null]);

        $this->assertSame([], $this->nodeOfType($tour, 'FAQPage'));
    }

    public function test_yurtdisi_turda_vize_sorusu_cikar(): void
    {
        $tour = $this->tur([
            'is_international' => true,
            'requires_visa' => true,
            'included' => "Ulaşım\nKahvaltı",
        ]);

        $faq = $this->nodeOfType($tour, 'FAQPage');
        $sorular = array_column($faq['mainEntity'], 'name');

        $this->assertContains('Bu tur için vize gerekiyor mu?', $sorular);
    }

    // ── Sayfa çıktısı ───────────────────────────────────────────────────────

    public function test_tur_sayfasinda_schema_gecerli_basar(): void
    {
        $tour = $this->tur(['included' => "Ulaşım\nKahvaltı", 'excluded' => 'Öğle yemeği']);

        $html = $this->get(route('tours.show', $tour))->assertOk()->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);
        $this->assertNotEmpty($matches[1]);

        $types = [];
        foreach ($matches[1] as $json) {
            $data = json_decode($json, true);
            $this->assertNotNull($data, 'Geçersiz JSON-LD: '.json_last_error_msg());
            foreach ($data['@graph'] ?? [$data] as $node) {
                $types[] = $node['@type'] ?? null;
            }
        }

        $this->assertContains('TouristTrip', $types);
        $this->assertContains('Product', $types);
        $this->assertContains('FAQPage', $types);
        $this->assertContains('BreadcrumbList', $types);
    }

    public function test_faq_semasi_gorunur_blokla_eslesir(): void
    {
        // Google, FAQPage şemasının sayfada GÖRÜNÜR karşılığı olmasını şart
        // koşar; görünmeyen SSS şeması ihlaldir.
        $tour = $this->tur(['included' => "Ulaşım\nKahvaltı", 'excluded' => 'Öğle yemeği']);

        $html = $this->get(route('tours.show', $tour))->assertOk()->getContent();

        $this->assertStringContainsString('Sıkça Sorulan Sorular', $html);

        foreach (TourSchema::faq($tour)['mainEntity'] as $soru) {
            $this->assertStringContainsString(
                e($soru['name']),
                $html,
                'Şemadaki soru sayfada görünmüyor: '.$soru['name']
            );
        }
    }

    // ── Liste sayfaları (ItemList) ──────────────────────────────────────────

    public function test_liste_sayfasi_itemlist_basar(): void
    {
        // jollytur ve Prontotour'un liste sayfalarında ürün schema'sı HİÇ yok.
        $this->tur();
        $this->tur(['title' => 'Bodrum Mavi Yolculuk', 'destination' => 'Bodrum']);

        $html = $this->get(route('tours.index'))->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"ItemList"', $html);
        $this->assertStringContainsString('"numberOfItems":2', $html);
        $this->assertStringContainsString('"@type":"TouristTrip"', $html);
    }

    public function test_itemlist_her_ture_kendi_aciklamasini_yazar(): void
    {
        // tatilbudur'un hatası: 20 ürünün hepsine kategori meta metnini kopyalamış.
        $this->tur(['title' => 'A Turu', 'description' => 'Ege kıyılarında yedi gün.']);
        $this->tur(['title' => 'B Turu', 'description' => 'Karadeniz yaylalarında beş gün.']);

        $list = \App\Support\TourSchema::itemList(Tour::orderBy('id')->get(), url('/turlar'));
        $aciklamalar = array_column(array_column($list['itemListElement'], 'item'), 'description');

        $this->assertCount(2, array_unique($aciklamalar));
    }

    public function test_itemliste_satici_acenta_yazilir(): void
    {
        $this->tur();

        $list = \App\Support\TourSchema::itemList(Tour::all(), url('/turlar'));

        $this->assertSame('Örnek Acenta', $list['itemListElement'][0]['item']['provider']['name']);
    }

    public function test_bos_listede_itemlist_basilmaz(): void
    {
        $this->assertNull(\App\Support\TourSchema::itemList([], url('/turlar')));

        $this->get(route('tours.index'))
            ->assertOk()
            ->assertDontSee('"@type":"ItemList"', false);
    }

    public function test_sss_verisi_yoksa_gorunur_blok_da_basilmaz(): void
    {
        $tour = $this->tur(['included' => null, 'excluded' => null, 'cancellation_policy' => null]);

        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertDontSee('Sıkça Sorulan Sorular');
    }
}
