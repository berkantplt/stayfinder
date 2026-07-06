<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiSearchConversation;
use App\Models\AiSearchLog;
use App\Models\Tour;
use App\Services\AiSearch\ConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Faz 4: tur sayfası danışman barı, WhatsApp devri, hafızalı karşılama sinyalleri.
 */
class AiPhase4Test extends TestCase
{
    use RefreshDatabase;

    private function makeAgencyTour(): Tour
    {
        $agency = Agency::create([
            'name' => 'Devir Acenta', 'slug' => 'devir-acenta', 'email' => 'devir@example.com',
            'phone' => '0532 111 22 33',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(), 'legacy_category_access' => true,
        ]);

        return Tour::create([
            'agency_id' => $agency->id, 'title' => 'Kaş Balayı Turu', 'destination' => 'Kaş',
            'duration_days' => 4, 'currency' => 'TRY', 'price' => 20000, 'is_active' => true,
            'departure_date' => today()->addDays(20),
        ]);
    }

    public function test_whatsapp_devri_ozet_dolu_baglanti_uretir(): void
    {
        $tour = $this->makeAgencyTour();
        $conversation = AiSearchConversation::create([
            'session_id' => Str::limit(app('session.store')->getId(), 64, ''),
            'last_message_at' => now(),
            'last_result_tour_ids' => [$tour->id],
            'current_intent' => ['max_budget' => 25000, 'traveler_profile' => 'balayi'],
        ]);

        $request = Request::create('/test', 'POST');
        $request->setLaravelSession(app('session.store'));

        // Deterministik akış — hiç OpenAI çağrısı yok (fake bile gerekmez)
        $result = app(ConversationService::class)->respond($request, $conversation, 'acentayla konuşmak istiyorum, beni arayın');

        $handoff = $result['payload']['handoff'] ?? null;
        $this->assertNotNull($handoff);
        $this->assertSame('Devir Acenta', $handoff['agency_name']);
        $this->assertStringContainsString('wa.me/905321112233', $handoff['whatsapp_link']);
        $this->assertStringContainsString(rawurlencode('Kaş Balayı Turu'), $handoff['whatsapp_link']);
        $this->assertStringContainsString('25.000', rawurldecode($handoff['whatsapp_link']));

        // Devir conversation'a işlendi (acenta lead raporu için)
        $conversation->refresh();
        $this->assertNotNull($conversation->handoff_at);
        $this->assertSame($tour->id, (int) $conversation->handoff_tour_id);
    }

    public function test_tur_sayfasi_danisman_bari_ai_baglaminda_gosterilir(): void
    {
        $tour = $this->makeAgencyTour();
        $user = \App\Models\User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        $log = AiSearchLog::create([
            'user_id' => $user->id,
            'session_id' => '', // sahiplik kullanıcı üzerinden (test istekleri arasında session id değişir)
            'raw_query' => 'balayı için sakin bir yer',
            'normalized_query' => 'balayı',
            'intent' => ['max_budget' => 25000],
            'applied_filters' => [],
            'candidate_count' => 1,
            'result_tour_ids' => [$tour->id],
            'result_scores' => [['tour_id' => $tour->id, 'rank' => 1, 'compatibility_score' => 0.87]],
            'latency_ms' => 100,
        ]);

        $response = $this->get(route('tours.show', $tour).'?ai_log_id='.$log->id.'&ai_rank=1');

        $response->assertOk();
        $response->assertSee('Aradığın', false);
        $response->assertSee('%87 uyumlu');
        $response->assertSee('Sohbete dön');
        $response->assertSee('bütçe', false);

        // Tıklama loglandı
        $log->refresh();
        $this->assertSame($tour->id, (int) $log->selected_tour_id);
    }

    public function test_karsilama_sinyali_son_aramanin_ozetini_tasir(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        AiSearchConversation::create([
            'user_id' => $user->id,
            'session_id' => 's',
            'last_message_at' => now(),
            'title' => 'Kapadokya araması',
            'current_intent' => ['preferred_destination' => 'Kapadokya', 'preferred_month' => 9, 'max_budget' => 30000],
        ]);

        $response = $this->get(route('ai.search'));

        $response->assertOk();
        $response->assertSee('Kaldığın yerden devam', false);
        $response->assertSee('Kapadokya', false);
        $response->assertSee('Eylül', false);
    }
}
