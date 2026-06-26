<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use App\Services\TourImport\TourUrlImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class TourUrlImportTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    protected function setUp(): void
    {
        parent::setUp();

        $agency = Agency::create([
            'name' => 'İçe Aktar Acenta',
            'slug' => 'ice-aktar-acenta',
            'email' => 'iceaktar@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        $this->agencyUser = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);
    }

    private function fakeOpenAi(array $payload): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => json_encode($payload)],
                ]],
            ]),
        ]);
    }

    public function test_successful_import_returns_normalized_fields(): void
    {
        Http::fake(['*' => Http::response('<html><body><h1>Tur</h1><p>Harika bir gezi</p></body></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->fakeOpenAi([
            'title' => 'Latin Amerika Turu',
            'destination' => 'Peru',
            'duration_days' => 8,
            'currency' => 'USD',
            'price' => 1500,
            'description' => 'Muhteşem bir Latin Amerika rotası.',
            'included' => "Uçak\nOtel",
            'excluded' => 'Vize ücreti',
            'itinerary' => [
                ['title' => '1. Gün Lima', 'content' => 'Lima şehir turu detayları.'],
                ['title' => '2. Gün Cusco', 'content' => 'Cusco ve çevresi gezisi.'],
            ],
            'departure_points' => "21:00 Yenibosna\n21:30 Mecidiyeköy",
            'hotel_info' => '5★ Suhan Hotel',
            'extras' => 'Balon turu',
            'cancellation_policy' => 'İptal edilemez',
            'guide_info' => 'Profesyonel rehber',
            'frequency' => 'Her Cuma',
            'departure_dates' => ['2030-09-01', '2000-01-01'], // ikincisi geçmiş → elenmeli
        ]);

        $response = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/latin-amerika']);

        $response->assertOk()->assertJson(['ok' => true]);

        $data = $response->json('data');
        $this->assertSame('Latin Amerika Turu', $data['title']);
        $this->assertSame('Peru', $data['destination']);
        $this->assertSame(8, $data['duration_days']);
        $this->assertSame('USD', $data['currency']);
        $this->assertEquals(1500, $data['price']);
        $this->assertStringContainsString('Uçak', $data['included']);
        $this->assertSame(['2030-09-01'], $data['departure_dates']); // geçmiş tarih elendi
        // Yeni detay alanları — itinerary gün gün dizi
        $this->assertCount(2, $data['itinerary']);
        // "N. Gün" ön eki başlıktan ayıklanır (sayfa kendisi ekliyor)
        $this->assertSame('Cusco', $data['itinerary'][1]['title']);
        $this->assertStringContainsString('Cusco', $data['itinerary'][1]['content']);
        $this->assertStringContainsString('Yenibosna', $data['departure_points']);
        $this->assertSame('5★ Suhan Hotel', $data['hotel_info']);
        $this->assertSame('Balon turu', $data['extras']);
        $this->assertSame('İptal edilemez', $data['cancellation_policy']);
        $this->assertSame('Profesyonel rehber', $data['guide_info']);
        $this->assertSame('Her Cuma', $data['frequency']);
    }

    public function test_reader_markdown_is_used_when_available(): void
    {
        $markdown = "# Karadeniz Turu\n\n## Fiyata Dahil Olanlar\n- Uçak\n- Otel\n\n"
            .str_repeat('Detaylı tur açıklaması metni burada. ', 20);

        Http::fake([
            'https://r.jina.ai/*' => Http::response($markdown, 200),
            '*' => Http::response('BU KULLANILMAMALI', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->fakeOpenAi([
            'title' => 'Karadeniz Turu',
            'included' => "Uçak\nOtel",
            'departure_dates' => [],
        ]);

        $response = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/karadeniz'])
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertSame('Karadeniz Turu', $response->json('data.title'));
        $this->assertStringContainsString('Uçak', $response->json('data.included'));
        Http::assertSent(fn ($r) => str_contains($r->url(), 'r.jina.ai'));
    }

    public function test_deep_mode_uses_firecrawl_and_extracts_all_dates(): void
    {
        config([
            'ai.import_firecrawl_url' => 'https://api.firecrawl.dev/v1/scrape',
            'ai.import_firecrawl_key' => 'fc-test-key',
        ]);

        $markdown = "# New York Turu\n\n## Turun Tarihi\n- 25 Eylül 2030\n- 17 Ekim 2030\n- 24 Ekim 2030\n- 7 Kasım 2030\n\n"
            .str_repeat('Tur detayları ve açıklama metni. ', 20);

        Http::fake([
            'https://api.firecrawl.dev/*' => Http::response(['success' => true, 'data' => ['markdown' => $markdown]], 200),
            '*' => Http::response('FALLBACK KULLANILMAMALI', 200, ['Content-Type' => 'text/html']),
        ]);

        $this->fakeOpenAi([
            'title' => 'New York Turu',
            'departure_dates' => ['2030-09-25', '2030-10-17', '2030-10-24', '2030-11-07'],
        ]);

        $data = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/new-york', 'deep' => 1])
            ->assertOk()
            ->json('data');

        $this->assertSame(['2030-09-25', '2030-10-17', '2030-10-24', '2030-11-07'], $data['departure_dates']);
        Http::assertSent(fn ($r) => str_contains($r->url(), 'api.firecrawl.dev'));
    }

    public function test_dates_are_harvested_from_content_even_if_llm_returns_fewer(): void
    {
        $html = '<html><body>Turun Tarihi: 25 Eylül 2030, 17 Ekim 2030, 24 Ekim 2030. '
            .str_repeat('dolgu metni ', 40).'</body></html>';
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);

        // LLM sadece 1 tarih dönse bile, içerikteki diğerleri deterministik yakalanır
        $this->fakeOpenAi(['title' => 'Tur', 'departure_dates' => ['2030-10-17']]);

        $data = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/x'])
            ->assertOk()
            ->json('data');

        $this->assertSame(['2030-09-25', '2030-10-17', '2030-10-24'], $data['departure_dates']);
    }

    public function test_turkish_month_dates_are_parsed_to_iso(): void
    {
        Http::fake(['*' => Http::response('<html><body>İçerik metni burada.</body></html>', 200, ['Content-Type' => 'text/html'])]);

        // Model ISO yerine Türkçe tarih döndürürse de doğru parse edilmeli
        $this->fakeOpenAi([
            'title' => 'Tur',
            'departure_dates' => ['17 Ekim 2030', '7 Kasım 2030'],
        ]);

        $data = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/x'])
            ->assertOk()
            ->json('data');

        $this->assertSame(['2030-10-17', '2030-11-07'], $data['departure_dates']);
    }

    public function test_unrecognized_currency_and_invalid_values_are_normalized(): void
    {
        Http::fake(['*' => Http::response('<html><body>İçerik</body></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->fakeOpenAi([
            'title' => 'Tur',
            'currency' => 'XYZ',          // tanınmıyor → null
            'duration_days' => 0,         // < 1 → null
            'price' => -50,               // negatif → null
            'departure_dates' => ['2000-01-01'], // geçmiş → boş
        ]);

        $data = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/x'])
            ->assertOk()
            ->json('data');

        $this->assertNull($data['currency']);
        $this->assertNull($data['duration_days']);
        $this->assertNull($data['price']);
        $this->assertSame([], $data['departure_dates']);
    }

    public function test_ssrf_private_and_reserved_urls_are_rejected_without_fetching(): void
    {
        Http::fake();

        foreach (['http://localhost/x', 'http://127.0.0.1/x', 'http://169.254.169.254/latest/meta-data'] as $url) {
            $this->actingAs($this->agencyUser)
                ->postJson(route('agency.tours.import'), ['url' => $url])
                ->assertStatus(422)
                ->assertJson(['ok' => false]);
        }

        Http::assertNothingSent();
    }

    public function test_non_html_content_is_rejected(): void
    {
        Http::fake(['*' => Http::response('{"a":1}', 200, ['Content-Type' => 'application/json'])]);

        $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/api'])
            ->assertStatus(422)
            ->assertJson(['ok' => false]);
    }

    public function test_invalid_url_is_rejected_by_validation(): void
    {
        $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'not-a-url'])
            ->assertStatus(422);
    }

    public function test_focus_content_keeps_relevant_sections_in_long_pages(): void
    {
        $importer = new TourUrlImporter;
        $method = new \ReflectionMethod($importer, 'focusContent');
        $method->setAccessible(true);

        // ~70K karakter gürültü, dahil/hariç bölümü en sonda (kör kesme bunu kaçırırdı)
        $noise = str_repeat('Menü bağlantısı ve alakasız içerik. ', 2000);
        $text = "Başlık New York Turu\n".$noise
            ."\n## Dahil Olan Hizmetler\n- Uçak bileti\n- Otel\n"
            ."## Dahil Olmayan Hizmetler\n- Vize bedeli\n".$noise;

        $out = $method->invoke($importer, $text);

        $this->assertLessThanOrEqual(52000, mb_strlen($out));
        $this->assertStringContainsString('Dahil Olan Hizmetler', $out);
        $this->assertStringContainsString('Uçak bileti', $out);
        $this->assertStringContainsString('Dahil Olmayan Hizmetler', $out);
        $this->assertStringContainsString('Vize bedeli', $out);
    }

    public function test_focus_content_prioritizes_price_table_region(): void
    {
        $importer = new TourUrlImporter;
        $method = new \ReflectionMethod($importer, 'focusContent');
        $method->setAccessible(true);

        // Fiyat tablosu sayfanın SONUNDA; baştaki gürültü onu kör kesmede atardı.
        $noise = str_repeat('Alakasız menü ve içerik metni. ', 2000);
        $table = "Tur Hareket Tarihi: 19-06-2026 Cuma\n"
            ."Paket Adı İki Kişilik Oda Kişi Başı Tek Kişilik Oda İlave Yatak\n"
            ."5* Suhan Cappadocia Hotel & Spa 11.498,00 5.749,00 13.998,00 6.999,00\n"
            ."Rezervasyon Yap\n";
        $text = "Başlık Kapadokya Turu\n".$noise.$table.$noise;

        $out = $method->invoke($importer, $text);

        // Sayfanın sonundaki fiyat tablosu, baştaki gürültüye rağmen dahil edilir
        $this->assertStringContainsString('Paket Adı', $out);
        $this->assertStringContainsString('5* Suhan Cappadocia Hotel & Spa', $out);
        $this->assertStringContainsString('11.498,00', $out);
    }

    public function test_focus_content_keeps_sections_after_price_table(): void
    {
        $importer = new TourUrlImporter;
        $method = new \ReflectionMethod($importer, 'focusContent');
        $method->setAccessible(true);

        // Gerçek hayattaki düzen: dahil/dahil-değil/iptal listeleri fiyat tablosunun ARDINDAN gelir
        $noise = str_repeat('Alakasız içerik metni satırı. ', 1500);
        $table = "Paket Adı İki Kişilik Oda Kişi Başı\n5* Otel 10.998,00 5.499,00\nRezervasyon Yap\n";
        $afterTable = "FİYATA DAHİL DEĞİLDİR\n- Müze giriş ücretleri\n- Öğle yemekleri\n"
            ."İPTAL İADE KOŞULLARI\n- 72 saat öncesine kadar ücretsiz iptal\n";
        $text = "Başlık Kapadokya Turu\n".$noise.$table.$afterTable.$noise;

        $out = $method->invoke($importer, $text);

        $this->assertStringContainsString('DAHİL DEĞİLDİR', $out);
        $this->assertStringContainsString('Müze giriş ücretleri', $out);
        $this->assertStringContainsString('İPTAL İADE', $out);
    }

    public function test_numeric_dmy_dates_are_harvested_and_parsed(): void
    {
        $html = '<html><body>Tur Hareket Tarihi: 19-06-2030 Cuma, 26.06.2030, 03/07/2030. '
            .str_repeat('dolgu metni ', 40).'</body></html>';
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);

        // LLM hiç tarih dönmese bile sayısal DD-MM-YYYY tarihleri yakalanır
        $this->fakeOpenAi(['title' => 'Tur', 'departure_dates' => []]);

        $data = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/x'])
            ->assertOk()
            ->json('data');

        $this->assertSame(['2030-06-19', '2030-06-26', '2030-07-03'], $data['departure_dates']);
    }

    public function test_pricing_blocks_are_extracted_and_normalized(): void
    {
        Http::fake(['*' => Http::response('<html><body>Bodrum turu fiyat tablosu içerik metni burada.</body></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->fakeOpenAi([
            'title' => 'Bodrum Turu',
            'departure_dates' => [],
            'pricing_blocks' => [
                [
                    'dates' => ['2030-07-15', '2030-07-22'],
                    'packages' => [
                        [
                            'hotel' => '5★ Suhan Bodrum',
                            'prices' => [
                                'double_pp' => ['old' => '12.500', 'new' => '9.900'],
                                'single' => ['old' => null, 'new' => 14000],
                                'child_3_5' => ['old' => null, 'new' => 0],
                                'child_7_11' => ['old' => null, 'new' => 4500],
                            ],
                        ],
                    ],
                ],
                [
                    // Bayram fiyatı — farklı blok
                    'dates' => ['2030-06-10'],
                    'packages' => [
                        ['hotel' => '5★ Suhan Bodrum', 'prices' => ['double_pp' => ['old' => null, 'new' => 15900]]],
                    ],
                ],
                // tarihsiz blok → elenmeli
                ['dates' => [], 'packages' => [['hotel' => 'X', 'prices' => ['single' => ['old' => null, 'new' => 100]]]]],
            ],
        ]);

        $data = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/bodrum'])
            ->assertOk()
            ->json('data');

        $blocks = $data['pricing_blocks'];
        $this->assertCount(2, $blocks); // tarihsiz blok elendi

        // İlk blok: tarihler sıralı + tireli/binlikli fiyat float'a çevrildi
        $this->assertSame(['2030-07-15', '2030-07-22'], $blocks[0]['dates']);
        $this->assertSame('5★ Suhan Bodrum', $blocks[0]['packages'][0]['hotel']);
        $this->assertEquals(9900, $blocks[0]['packages'][0]['prices']['double_pp']['new']);
        $this->assertEquals(12500, $blocks[0]['packages'][0]['prices']['double_pp']['old']);
        $this->assertEquals(0, $blocks[0]['packages'][0]['prices']['child_3_5']['new']);

        // Blok tarihleri departure_dates ile birleşti (takvimde seçili gelsin)
        $this->assertContains('2030-06-10', $data['departure_dates']);
        $this->assertContains('2030-07-15', $data['departure_dates']);
        $this->assertContains('2030-07-22', $data['departure_dates']);
    }

    public function test_departure_and_stop_cities_are_normalized_to_provinces(): void
    {
        Http::fake(['*' => Http::response('<html><body>İstanbul çıkışlı Kapadokya turu içeriği.</body></html>', 200, ['Content-Type' => 'text/html'])]);

        $this->fakeOpenAi([
            'title' => 'Kapadokya Turu',
            'departure_city' => 'istanbul',
            'stop_cities' => ['Kocaeli', 'Bolu', 'ankara', 'İstanbul'], // kalkış ili tekrar → elenir
            'departure_dates' => [],
        ]);

        $data = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/kapadokya'])
            ->assertOk()
            ->json('data');

        $this->assertSame('İstanbul', $data['departure_city']);
        $this->assertSame(['Kocaeli', 'Bolu', 'Ankara'], $data['stop_cities']);
    }

    public function test_guest_cannot_import(): void
    {
        $this->post(route('agency.tours.import'), ['url' => 'https://1.1.1.1/x'])
            ->assertRedirect(route('login'));
    }
}
