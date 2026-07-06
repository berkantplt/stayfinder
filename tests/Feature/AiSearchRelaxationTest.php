<?php

namespace Tests\Feature;

use App\Http\Controllers\AiSearchController;
use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use OpenAI\Responses\Embeddings\CreateResponse as EmbeddingResponse;
use Tests\TestCase;

/**
 * 0-sonuç gevşetme merdiveninin SON BASAMAĞI: hiçbir filtre kombinasyonu sonuç
 * vermese bile aktif tur varsa kullanıcı asla çıplak "bulunamadı" görmez.
 */
class AiSearchRelaxationTest extends TestCase
{
    use RefreshDatabase;

    private const VECTOR = [0.1, 0.2, 0.3];

    private function makeActiveTour(): Tour
    {
        $agency = Agency::create([
            'name' => 'Relax Acenta',
            'slug' => 'relax-acenta',
            'email' => 'relax@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        return Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Yüzme Molalı Ege Turu',
            'destination' => 'Ege',
            'departure_city' => 'İstanbul',
            'duration_days' => 5,
            'currency' => 'TRY',
            'price' => 9000,
            'is_active' => true,
            'is_international' => false,
            'departure_date' => today()->addDays(20),
            'embedding' => self::VECTOR,
        ]);
    }

    public function test_miras_filtreler_sifir_sonuc_uretse_bile_en_yakin_turlar_gosterilir(): void
    {
        $this->makeActiveTour();

        OpenAI::fake([
            // intent: boş JSON (heuristikler de sinyal bulamaz)
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => '{}']]]]),
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'En yakın seçenekler bunlar.']]]]),
        ]);

        // Önceki turdan miras kalan is_international=true, DB'deki tek (yurt içi)
        // turu sıfırlıyor — son basamak tüm filtreleri bırakıp turu göstermeli
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));

        $result = app(AiSearchController::class)->performAiSearch(
            $request,
            'yazlık bir tur istiyorum denize gireyim',
            ['is_international' => true]
        );

        $this->assertIsArray($result);
        if (isset($result['error'])) {
            $this->fail('performAiSearch hata döndü: '.$result['error']);
        }
        $this->assertGreaterThan(0, count($result['results']), 'Gevşetme merdiveni sonuç üretmeliydi');
        $this->assertNotNull($result['relaxation_note'], 'Kullanıcıya gevşetme nedeni söylenmeli');
        $this->assertSame('Yüzme Molalı Ege Turu', $result['results'][0]['title']);
    }

    public function test_yazlik_kelimesi_temmuza_eslenir(): void
    {
        $controller = app(AiSearchController::class);
        $reflection = new \ReflectionMethod($controller, 'extractPreferredMonth');

        $this->assertSame(7, $reflection->invoke($controller, [], 'yazlık bir tur istiyorum'));
    }
}
