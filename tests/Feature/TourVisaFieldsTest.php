<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

class TourVisaFieldsTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $agency = Agency::create([
            'name' => 'Vize Acenta',
            'slug' => 'vize-acenta',
            'email' => 'vize@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        $this->agencyUser = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $this->category = Category::create([
            'name' => 'Yurt Dışı Turlar',
            'slug' => 'yurt-disi-turlar',
            'is_active' => true,
        ]);
    }

    /** @return array<string, mixed> */
    private function basePayload(): array
    {
        return [
            'category_id' => $this->category->id,
            'title' => 'Klasik İtalya Turu',
            'destination' => 'İtalya',
            'departure_city' => 'İstanbul',
            'duration_days' => 8,
            'currency' => 'EUR',
            'pricing_options' => [
                [
                    'price' => '899',
                    'departure_dates' => [today()->addDays(30)->toDateString()],
                ],
            ],
        ];
    }

    public function test_store_saves_visa_fields_and_marks_international_when_vizeli(): void
    {
        $payload = $this->basePayload() + [
            'requires_visa' => '1',
            'visa_general' => "Pasaport en az 6 ay geçerli olmalı.\nSchengen vizesi gerekir.",
            'visa_documents' => "Standart Evraklar\nPasaport fotokopisi\nBiyometrik fotoğraf",
            'visa_fees' => 'İstanbul - 12 yaş ve üzeri: 370 €',
            'visa_notes' => 'Vize reddi durumunda ücret iade edilmez.',
        ];

        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $payload)
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Klasik İtalya Turu');
        $this->assertNotNull($tour);
        $this->assertTrue($tour->requires_visa);
        // Vizeli tur tanım gereği yurt dışı → is_international otomatik işaretlenir
        $this->assertTrue($tour->is_international);
        $this->assertStringContainsString('Schengen', $tour->visa_general);
        $this->assertStringContainsString('Biyometrik', $tour->visa_documents);
        $this->assertStringContainsString('370', $tour->visa_fees);
        $this->assertStringContainsString('iade edilmez', $tour->visa_notes);
    }

    public function test_store_clears_visa_fields_when_vizesiz(): void
    {
        // Vizesiz seçiliyken (gizli kutularda kalmış) vize metinleri KAYDEDİLMEZ
        $payload = $this->basePayload() + [
            'requires_visa' => '0',
            'visa_general' => 'Bu metin kaydedilmemeli',
            'visa_documents' => 'Bu da kaydedilmemeli',
        ];

        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $payload)
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Klasik İtalya Turu');
        $this->assertNotNull($tour);
        $this->assertFalse($tour->requires_visa);
        $this->assertNull($tour->visa_general);
        $this->assertNull($tour->visa_documents);
        $this->assertNull($tour->visa_fees);
        $this->assertNull($tour->visa_notes);
    }

    public function test_update_clears_visa_fields_when_switched_to_vizesiz(): void
    {
        $tour = Tour::create([
            'agency_id' => $this->agencyUser->agency_id,
            'category_id' => $this->category->id,
            'title' => 'Vizeli Eski Tur',
            'destination' => 'İtalya',
            'departure_city' => 'İstanbul',
            'duration_days' => 8,
            'currency' => 'EUR',
            'price' => 899,
            'requires_visa' => true,
            'is_international' => true,
            'visa_general' => 'Schengen vizesi gerekir.',
            'visa_documents' => 'Pasaport fotokopisi',
            'visa_fees' => 'İstanbul: 370 €',
            'visa_notes' => 'Ücret iade edilmez.',
        ]);

        $payload = $this->basePayload() + ['requires_visa' => '0'];
        $payload['title'] = 'Vizeli Eski Tur';

        $this->actingAs($this->agencyUser)
            ->put(route('agency.tours.update', $tour), $payload)
            ->assertRedirect(route('agency.tours.index'));

        $tour->refresh();
        $this->assertFalse($tour->requires_visa);
        // Vizeli işaretin yazdığı is_international da geri alınır (yanlış "Vizeli"
        // kaydı yurt içi turu kalıcı olarak yurt dışında bırakmasın)
        $this->assertFalse($tour->is_international);
        $this->assertNull($tour->visa_general);
        $this->assertNull($tour->visa_documents);
        $this->assertNull($tour->visa_fees);
        $this->assertNull($tour->visa_notes);
    }

    public function test_update_preserves_independent_is_international_when_vizesiz(): void
    {
        // Tur hiç vizeli olmadı ama is_international bağımsız (ör. AI) set edilmiş:
        // vizesiz güncelleme bu işarete DOKUNMAMALI (vizesiz yurt dışı turlar var)
        $tour = Tour::create([
            'agency_id' => $this->agencyUser->agency_id,
            'category_id' => $this->category->id,
            'title' => 'Balkan Turu',
            'destination' => 'Balkanlar',
            'departure_city' => 'İstanbul',
            'duration_days' => 6,
            'currency' => 'EUR',
            'price' => 499,
            'requires_visa' => false,
            'is_international' => true,
        ]);

        $payload = $this->basePayload() + ['requires_visa' => '0'];
        $payload['title'] = 'Balkan Turu';

        $this->actingAs($this->agencyUser)
            ->put(route('agency.tours.update', $tour), $payload)
            ->assertRedirect(route('agency.tours.index'));

        $tour->refresh();
        $this->assertFalse($tour->requires_visa);
        $this->assertTrue($tour->is_international);
    }

    public function test_import_with_visa_flag_returns_visa_sections(): void
    {
        Http::fake(['*' => Http::response(
            '<html><body><h1>Klasik İtalya Turu</h1><p>Harika bir gezi.</p>'
            .'<h2>Vize Bilgileri</h2><p>Pasaportunuz en az 6 ay geçerli olmalı. Schengen vizesi gerekir. '
            .'Gerekli evraklar: pasaport, biyometrik fotoğraf. Vize ücreti İstanbul 370 Euro.</p></body></html>',
            200,
            ['Content-Type' => 'text/html']
        )]);

        // Sıra: 1. genel çıkarım, 2. vize çıkarımı (fiyat çapası yok → fiyat LLM çağrısı atlanır)
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => json_encode([
                        'title' => 'Klasik İtalya Turu',
                        'destination' => 'İtalya',
                        'duration_days' => 8,
                        'departure_dates' => [],
                    ])],
                ]],
            ]),
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => json_encode([
                        'visa_general' => "Pasaport en az 6 ay geçerli olmalı.\nSchengen vizesi gerekir.",
                        'visa_documents' => "Pasaport\nBiyometrik fotoğraf",
                        'visa_fees' => 'İstanbul - 12 yaş ve üzeri: 370 €',
                        'visa_notes' => null,
                    ])],
                ]],
            ]),
        ]);

        $response = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/italya-turu', 'visa' => 1]);

        $response->assertOk()->assertJson(['ok' => true]);

        $data = $response->json('data');
        $this->assertStringContainsString('Schengen', $data['visa_general']);
        $this->assertStringContainsString('Biyometrik', $data['visa_documents']);
        $this->assertStringContainsString('370', $data['visa_fees']);
        $this->assertNull($data['visa_notes']);
    }

    public function test_import_without_visa_flag_omits_visa_sections(): void
    {
        Http::fake(['*' => Http::response(
            '<html><body><h1>Klasik İtalya Turu</h1><p>Vize gerekir ama istek vizesiz.</p></body></html>',
            200,
            ['Content-Type' => 'text/html']
        )]);

        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => json_encode([
                        'title' => 'Klasik İtalya Turu',
                        'departure_dates' => [],
                    ])],
                ]],
            ]),
        ]);

        $response = $this->actingAs($this->agencyUser)
            ->postJson(route('agency.tours.import'), ['url' => 'https://1.1.1.1/italya-turu']);

        $response->assertOk()->assertJson(['ok' => true]);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('visa_general', $data);
        $this->assertArrayNotHasKey('visa_documents', $data);
    }
}
