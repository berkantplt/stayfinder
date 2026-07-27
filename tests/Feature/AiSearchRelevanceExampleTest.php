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
 * ÖRNEK / isabet senaryosu: "tam ihtiyacım olan tur öne çıksın, alakasız elensin".
 *
 * Gerçekçi bir kullanıcı sorgusunu uçtan uca (intent → embedding → hibrit
 * arama → skorlama) koşturur ve iki şeyi birlikte kanıtlar:
 *   1) Yön uyuşmayan tur ASLA önerilmez (sert eleme) — "alakasız elenir"
 *   2) Sorguya birebir uyan tur ilk sırada — "tam ihtiyacım olan tur"
 *
 * LLM yanıtları mock'lu; tamamen deterministik.
 */
class AiSearchRelevanceExampleTest extends TestCase
{
    use RefreshDatabase;

    private const VECTOR = [0.1, 0.2, 0.3];

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::create([
            'name' => 'Örnek Acenta',
            'slug' => 'ornek-acenta',
            'email' => 'ornek@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
    }

    private function makeTour(string $title, string $destination, bool $international, string $searchText): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'title' => $title,
            'destination' => $destination,
            'departure_city' => 'İstanbul',
            'duration_days' => 5,
            'currency' => 'TRY',
            'price' => 30000,
            'is_active' => true,
            'is_international' => $international,
            'departure_date' => today()->addDays(30),
            'embedding' => self::VECTOR,      // vektörler özdeş → ayrıştırmayı eksenler/kelime yapar
            'search_text' => $searchText,
        ]);
    }

    /** @param array<string,mixed> $intent */
    private function search(string $query, array $intent): array
    {
        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode($intent)]]]]),
            EmbeddingResponse::fake(['data' => [['object' => 'embedding', 'index' => 0, 'embedding' => self::VECTOR]]]),
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'İşte sana en uygun seçenekler.']]]]),
        ]);

        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(app('session.store'));

        $result = app(AiSearchController::class)->performAiSearch($request, $query);

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('error', $result, $result['error'] ?? '');

        return $result;
    }

    public function test_yurt_disi_romantik_balayi_sorgusu_dogru_turu_one_cikarir_alakasizi_eler(): void
    {
        // Envanter: 2 yurt dışı (biri birebir "balayı" eşleşmesi) + 1 yurt içi
        $venedik = $this->makeTour(
            'Venedik Balayı Turu',
            'İtalya',
            true,
            'romantik balayi turu italya venedik gondol cift'
        );
        $ispanya = $this->makeTour(
            'İspanya Kültür Turu',
            'İspanya',
            true,
            'ispanya kultur turu muze tarih gezi'
        );
        $antalya = $this->makeTour(
            'Antalya Deniz Turu',
            'Antalya',
            false, // yurt içi → yön uyuşmuyor
            'antalya deniz kum gunes yaz'
        );

        $result = $this->search(
            'yurt dışında romantik bir balayı turu istiyorum',
            ['is_international' => true],
        );

        $ids = collect($result['results'])->pluck('id')->all();

        // 1) Alakasız elenir: yurt içi tur, yön asla gevşetilmediği için ASLA önerilmez.
        $this->assertNotContains($antalya->id, $ids, 'Yurt içi tur, yurt dışı isteyene gösterilmemeli');

        // 2) Tam ihtiyaç öne çıkar: "balayı" birebir eşleşen Venedik turu ilk sırada.
        $this->assertNotEmpty($ids);
        $this->assertSame($venedik->id, $ids[0], 'Sorguya birebir uyan tur ilk sırada olmalı');

        // 3) Diğer geçerli (yurt dışı) tur listede var ama daha aşağıda.
        $this->assertContains($ispanya->id, $ids);
        $this->assertLessThan(
            array_search($ispanya->id, $ids, true),
            array_search($venedik->id, $ids, true),
            'Birebir eşleşen tur, alakası zayıf olandan yukarıda sıralanmalı'
        );

        // 4) Gerçek eşleşme bulunduğu için gevşetme notu yok (dürüst sonuç).
        $this->assertNull($result['relaxation_note'] ?? null);
    }
}
