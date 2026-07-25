<?php

namespace Tests\Feature;

use App\Models\AiSearchConversation;
use App\Models\User;
use App\Services\AiSearch\RecreationQuizService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

class RecreationQuizTest extends TestCase
{
    use RefreshDatabase;

    /** Testin tamamı LLM'siz olmalı — boş fake ile herhangi bir kaçak çağrı patlar. */
    protected function setUp(): void
    {
        parent::setUp();
        OpenAI::fake([]);
    }

    private function validAnswers(): array
    {
        return [
            's1' => 60,
            's2' => ['koy', 'zirve', 'antik'],
            's3' => 60,
            's4' => 1, 's5' => 2, 's6' => 1, 's7' => 1, 's8a' => 1, 's9' => 0,
        ];
    }

    public function test_definition_endpoint_returns_steps_without_weights(): void
    {
        $response = $this->getJson('/tatil-karakteri');

        $response->assertOk()
            ->assertJsonPath('version', RecreationQuizService::VERSION)
            ->assertJsonCount(10, 'steps');

        $this->assertStringNotContainsString('"ax"', $response->getContent());
    }

    public function test_anonymous_submit_stores_profile_in_conversation_meta(): void
    {
        $response = $this->postJson('/tatil-karakteri', ['answers' => $this->validAnswers()]);

        $response->assertOk()
            ->assertJsonStructure(['persona' => ['key', 'label'], 'vector', 'tempo', 'conversation_uuid', 'followup_query']);

        $conversation = AiSearchConversation::where('uuid', $response->json('conversation_uuid'))->firstOrFail();
        $meta = $conversation->current_intent[RecreationQuizService::META_KEY] ?? null;

        $this->assertNotNull($meta);
        $this->assertSame($response->json('vector'), $meta['vector']);
        $this->assertSame(RecreationQuizService::VERSION, $meta['quiz_version']);
    }

    public function test_authenticated_submit_persists_profile_on_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/tatil-karakteri', ['answers' => $this->validAnswers()]);

        $response->assertOk();
        $this->assertNotNull($user->fresh()->recreation_profile);
        $this->assertSame($response->json('vector'), $user->fresh()->recreation_profile['vector']);
    }

    public function test_adjusted_vector_is_clamped_and_persona_recomputed(): void
    {
        $response = $this->postJson('/tatil-karakteri', [
            'answers' => $this->validAnswers(),
            'adjusted_vector' => ['gastro' => 0.95, 'sukunet' => 0.1],
        ]);

        $response->assertOk();
        $this->assertSame(0.95, $response->json('vector.gastro'));
        // Düzeltme sonrası persona yeniden hesaplanır: gastro artık baskın
        $this->assertSame('gastro', $response->json('persona.key'));
    }

    public function test_adjusted_vector_rejects_out_of_range_values(): void
    {
        $this->postJson('/tatil-karakteri', [
            'answers' => $this->validAnswers(),
            'adjusted_vector' => ['gastro' => 1.7],
        ])->assertStatus(422);
    }

    public function test_submit_validates_missing_answers(): void
    {
        $this->postJson('/tatil-karakteri', ['answers' => ['s1' => 50]])->assertStatus(422);
    }

    public function test_submit_reuses_existing_conversation(): void
    {
        // Anonim istekte test framework'ü her çağrıya yeni session verdiği için
        // sahiplik user_id üzerinden kurulur — gerçek tarayıcıda çerez aynı işi görür
        $user = User::factory()->create();

        $first = $this->actingAs($user)->postJson('/tatil-karakteri', ['answers' => $this->validAnswers()]);
        $uuid = $first->json('conversation_uuid');

        $second = $this->actingAs($user)->postJson('/tatil-karakteri', [
            'answers' => $this->validAnswers(),
            'conversation_uuid' => $uuid,
        ]);

        $this->assertSame($uuid, $second->json('conversation_uuid'));
        $this->assertSame(1, AiSearchConversation::count());
    }

    public function test_login_migrates_anonymous_profile_to_user(): void
    {
        $submit = $this->postJson('/tatil-karakteri', ['answers' => $this->validAnswers()]);
        $submit->assertOk();

        $user = User::factory()->create();
        $this->assertNull($user->recreation_profile);

        // Login event'i gerçek akışta session regenerate'ten ÖNCE, konuşmanın
        // kaydedildiği oturum kimliğiyle tetiklenir — testte aynı durumu kur
        $conversation = AiSearchConversation::firstOrFail();
        session()->setId($conversation->session_id);
        session()->start();
        event(new \Illuminate\Auth\Events\Login('web', $user, false));

        $this->assertNotNull($user->fresh()->recreation_profile);
        $this->assertEquals($submit->json('vector'), $user->fresh()->recreation_profile['vector']);
    }

    public function test_submit_rejects_unknown_answer_keys(): void
    {
        $answers = $this->validAnswers();
        $answers['hack'] = str_repeat('x', 1000); // depolama şişirme denemesi

        $this->postJson('/tatil-karakteri', ['answers' => $answers])->assertStatus(422);
    }

    /** İnceleme bulgusu (kritik): arama merge'i _recreation_profile meta'sını
     *  silmemeli — yoksa ilk aramadan sonra bonus kalıcı kapanıyordu. */
    public function test_search_intent_merge_preserves_recreation_meta(): void
    {
        $submit = $this->postJson('/tatil-karakteri', ['answers' => $this->validAnswers()]);
        $conversation = AiSearchConversation::where('uuid', $submit->json('conversation_uuid'))->firstOrFail();

        $service = app(\App\Services\AiSearch\ConversationService::class);
        $method = new \ReflectionMethod($service, 'preserveRecreationMeta');

        // Arama sonrası LLM'den dönen intent meta içermez — helper geri taşımalı
        $merged = $method->invoke($service, ['max_budget' => 20000], $conversation->fresh());

        $this->assertArrayHasKey(RecreationQuizService::META_KEY, $merged);
        $this->assertEquals(
            $submit->json('vector'),
            $merged[RecreationQuizService::META_KEY]['vector']
        );
        // Profilsiz konuşmada anahtar eklenmez
        $bare = $method->invoke($service, ['max_budget' => 20000], new AiSearchConversation());
        $this->assertArrayNotHasKey(RecreationQuizService::META_KEY, $bare);
    }

    public function test_recreation_fit_bonus_math(): void
    {
        $controller = app(\App\Http\Controllers\AiSearchController::class);
        $method = new \ReflectionMethod($controller, 'scoreRecreationFit');

        $calmProfile = ['vector' => ['sukunet' => 0.9, 'sosyal' => 0.0, 'yenilik' => 0.2], 'tempo' => 20];

        $slowTour = new \App\Models\Tour(['is_international' => false]);
        $slowTour->pace_score = 0.2;
        $fastTour = new \App\Models\Tour(['is_international' => false]);
        $fastTour->pace_score = 0.9;

        $calmDest = ['lively' => 0.2];

        $slowBonus = $method->invoke($controller, $calmProfile, $slowTour, $calmDest);
        $fastBonus = $method->invoke($controller, $calmProfile, $fastTour, $calmDest);

        // Sakin profil: düşük tempolu tur pozitif, yüksek tempolu tur daha düşük skor almalı
        $this->assertGreaterThan($fastBonus, $slowBonus);
        $this->assertGreaterThan(0, $slowBonus);
        // Sınırlar: ±0.05
        $this->assertLessThanOrEqual(0.05, abs($slowBonus));
        $this->assertLessThanOrEqual(0.05, abs($fastBonus));

        // Profil yoksa sıralama etkilenmez
        $this->assertSame(0.0, $method->invoke($controller, null, $slowTour, $calmDest));
    }

    public function test_login_does_not_overwrite_existing_user_profile(): void
    {
        $this->postJson('/tatil-karakteri', ['answers' => $this->validAnswers()]);

        $existing = ['vector' => ['gastro' => 1.0], 'tempo' => 10, 'quiz_version' => 1];
        $user = User::factory()->create(['recreation_profile' => $existing]);

        $conversation = AiSearchConversation::firstOrFail();
        session()->setId($conversation->session_id);
        session()->start();
        event(new \Illuminate\Auth\Events\Login('web', $user, false));

        // JSON cast 1.0'ı 1 olarak saklayabilir — değer eşitliği yeterli
        $this->assertEquals(1.0, $user->fresh()->recreation_profile['vector']['gastro']);
    }
}
