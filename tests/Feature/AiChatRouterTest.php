<?php

namespace Tests\Feature;

use App\Http\Controllers\AiSearchController;
use App\Models\Agency;
use App\Models\AiSearchConversation;
use App\Models\AiSearchLog;
use App\Models\Tour;
use App\Services\AiSearch\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use Tests\TestCase;

/**
 * Faz 1: mesaj yönlendirici + tur-içi soru + kıyas (chatbot "beni anlıyor" katmanı).
 */
class AiChatRouterTest extends TestCase
{
    use RefreshDatabase;

    private function makeTour(string $title = 'Kapadokya Balon Turu'): Tour
    {
        $agency = Agency::firstOrCreate(
            ['slug' => 'router-acenta'],
            [
                'name' => 'Router Acenta', 'email' => 'router@example.com', 'is_active' => true,
                'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
            ]
        );

        return Tour::create([
            'agency_id' => $agency->id,
            'title' => $title,
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'duration_days' => 3,
            'currency' => 'TRY',
            'price' => 15000,
            'is_active' => true,
            'departure_date' => today()->addDays(15),
            'hotel_info' => '5 yıldızlı Suhan Cappadocia',
            'itinerary' => [
                ['title' => 'Göreme', 'content' => 'Balon turu ve açık hava müzesi gezisi.'],
                ['title' => 'Ihlara', 'content' => 'Vadi yürüyüşü ve yeraltı şehri.'],
            ],
        ]);
    }

    public function test_ilk_mesajda_router_llmsiz_arama_der(): void
    {
        // OpenAI fake YOK: router LLM'e giderse patlardı — gitmemeli
        $conversation = AiSearchConversation::create(['session_id' => 't', 'last_message_at' => now()]);

        $service = app(ConversationService::class);
        $route = (new \ReflectionMethod($service, 'routeMessage'))->invoke($service, $conversation, 'kapadokya turu');

        $this->assertSame('search', $route['action']);
    }

    public function test_begenmedim_kalibi_routera_gitmeden_aramaya_gider(): void
    {
        $tour = $this->makeTour();
        $conversation = AiSearchConversation::create([
            'session_id' => 't', 'last_message_at' => now(), 'last_result_tour_ids' => [$tour->id],
        ]);

        $service = app(ConversationService::class);
        $route = (new \ReflectionMethod($service, 'routeMessage'))->invoke($service, $conversation, 'beğenmedim başka öner');

        $this->assertSame('search', $route['action']);
    }

    public function test_tur_sorusu_arama_pipelinesiz_cevaplanir(): void
    {
        $tour = $this->makeTour();
        $conversation = AiSearchConversation::create([
            'session_id' => \Illuminate\Support\Str::limit(app('session.store')->getId(), 64, ''),
            'last_message_at' => now(),
            'last_result_tour_ids' => [$tour->id],
        ]);

        // Router kararı (tek chat çağrısı); cevap üretimi mock'lu controller'dan
        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode(['action' => 'tour_question', 'tour_numbers' => [1]])]]]]),
        ]);

        $this->partialMock(AiSearchController::class, function ($mock) {
            $mock->shouldReceive('streamContextualAnswer')
                ->once()
                ->andReturn('Otel 5 yıldızlı Suhan Cappadocia; kahvaltı dahil.');
        });

        $request = Request::create('/test', 'POST');
        $request->setLaravelSession(app('session.store'));

        $result = app(ConversationService::class)->respond($request, $conversation, 'otel nasıl bu turda?');

        $this->assertTrue($result['payload']['is_answer'] ?? false);
        $this->assertStringContainsString('Suhan', $result['payload']['aiComment']);
        $this->assertSame([], $result['payload']['results']);
        // Arama pipeline'ı çalışmadı → arama logu YAZILMADI
        $this->assertSame(0, AiSearchLog::count());
        // Mesajlar konuşmaya kaydedildi
        $this->assertSame(2, $conversation->messages()->count());
    }

    public function test_kiyas_deterministik_tablo_ve_hukum_doner(): void
    {
        $tour1 = $this->makeTour('Kapadokya Turu');
        $tour2 = $this->makeTour('Karadeniz Yayla Turu');
        $conversation = AiSearchConversation::create([
            'session_id' => \Illuminate\Support\Str::limit(app('session.store')->getId(), 64, ''),
            'last_message_at' => now(),
            'last_result_tour_ids' => [$tour1->id, $tour2->id],
        ]);

        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode(['action' => 'compare', 'tour_numbers' => [1, 2]])]]]]),
        ]);

        $this->partialMock(AiSearchController::class, function ($mock) {
            $mock->shouldReceive('streamContextualAnswer')->once()->andReturn('Bütçe öncelikse ilki.');
            $mock->shouldReceive('buildComparisonTable')->passthru();
        });

        $request = Request::create('/test', 'POST');
        $request->setLaravelSession(app('session.store'));

        $result = app(ConversationService::class)->respond($request, $conversation, 'ikisini kıyaslar mısın');

        $comparison = $result['payload']['comparison'];
        $this->assertNotNull($comparison);
        $this->assertCount(2, $comparison['columns']);
        $this->assertSame('Kapadokya Turu', $comparison['columns'][0]['title']);
        $labels = array_column($comparison['rows'], 'label');
        $this->assertContains('Fiyat', $labels);
        $this->assertContains('Süre', $labels);
    }

    public function test_gerekce_deterministik_uretilir(): void
    {
        $controller = app(AiSearchController::class);
        $method = new \ReflectionMethod($controller, 'buildTourReason');

        $tour = new Tour(['price' => 9000, 'currency' => 'TRY']);
        $tour->price_try = 9000;
        $tour->month_score = 1.0;
        $tour->nature_score = 0.9;
        $tour->similarity = 0.8;

        $reason = $method->invoke($controller, $tour, 20000, 9, null, true, null, null);

        $this->assertIsString($reason);
        $this->assertStringContainsString('Eylül kalkışı var', $reason);
        $this->assertStringContainsString('bütçene uyuyor', $reason);
    }
}
