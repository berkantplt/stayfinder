<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
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

    public function test_guest_cannot_import(): void
    {
        $this->post(route('agency.tours.import'), ['url' => 'https://1.1.1.1/x'])
            ->assertRedirect(route('login'));
    }
}
