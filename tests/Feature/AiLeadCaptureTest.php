<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiLead;
use App\Models\AiSearchConversation;
use App\Models\Favorite;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\NewAiLeadNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

/**
 * AI sohbet lead/alarm/PII akışları — TAMAMEN LLM'siz yollar: bu testlerde
 * OpenAI::fake([]) hiç yanıt içermez; herhangi bir LLM çağrısı olsaydı
 * "no fake responses" ile 500 dönerdi. 200 dönmesi akışın deterministik
 * kaldığının kanıtıdır.
 */
class AiLeadCaptureTest extends TestCase
{
    use RefreshDatabase;

    private function seedTour(): array
    {
        $agency = Agency::create([
            'name' => 'Lead Acenta',
            'slug' => 'lead-acenta',
            'email' => 'lead@example.com',
            'phone' => '0212 555 44 33',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'category_id' => null,
            'title' => 'Kapadokya Balon Turu',
            'destination' => 'Kapadokya',
            'description' => 'Test',
            'price' => 12500,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(30),
            'return_date' => today()->addDays(32),
            'is_active' => true,
        ]);

        $agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);

        return [$tour, $agency, $agencyUser];
    }

    public function test_callback_request_asks_for_contact_without_llm(): void
    {
        OpenAI::fake([]);

        $r = $this->postJson(route('ai.search.message'), ['message' => 'beni arayın lütfen'])
            ->assertOk();

        $this->assertStringContainsString('ad-soyad ve telefon', $r->json('assistant_message.content'));

        $conversation = AiSearchConversation::where('uuid', $r->json('conversation_uuid'))->firstOrFail();
        $this->assertSame(AiLead::INTENT_CALLBACK, $conversation->current_intent['_awaiting_lead']['intent']);
    }

    public function test_contact_reply_creates_lead_and_notifies_agency(): void
    {
        OpenAI::fake([]);
        Notification::fake();
        [$tour, $agency, $agencyUser] = $this->seedTour();
        // Çok-turlu testte konuşma erişimi user_id üzerinden (test harness'ında
        // anonim session ikinci istekte taşınmıyor; tarayıcıda sorun yok)
        $this->actingAs(User::factory()->create(['role' => 'visitor']));

        // 1) Tetik: konuşma oluşur, iletişim istenir
        $r1 = $this->postJson(route('ai.search.message'), ['message' => 'beni arayın'])
            ->assertOk();
        $conversation = AiSearchConversation::where('uuid', $r1->json('conversation_uuid'))->firstOrFail();

        // Sohbette daha önce bu tur gösterilmiş gibi bağla (lead tura/acentaya düşsün)
        $intent = $conversation->current_intent;
        $intent['_awaiting_lead']['tour_id'] = $tour->id;
        $conversation->update(['current_intent' => $intent]);

        // 2) Ad + telefon cevabı → lead + acenta bildirimi
        $r2 = $this->postJson(route('ai.search.message'), [
            'message' => 'Ayşe Yılmaz 0532 123 45 67',
            'conversation_uuid' => $conversation->uuid,
        ])->assertOk();

        $this->assertStringContainsString('Temsilcimiz', $r2->json('assistant_message.content'));

        $lead = AiLead::firstOrFail();
        $this->assertSame('Ayşe Yılmaz', $lead->name);
        $this->assertSame('05321234567', $lead->phone);
        $this->assertSame(AiLead::INTENT_CALLBACK, $lead->intent);
        $this->assertSame($tour->id, $lead->tour_id);
        $this->assertSame($agency->id, $lead->agency_id);
        $this->assertStringContainsString('Kapadokya Balon Turu', (string) $lead->note);

        Notification::assertSentTo($agencyUser, NewAiLeadNotification::class);

        // Bayrak temizlendi — sonraki mesaj normal akışa döner
        $conversation->refresh();
        $this->assertArrayNotHasKey('_awaiting_lead', $conversation->current_intent ?? []);
    }

    public function test_decline_clears_pending_lead(): void
    {
        OpenAI::fake([]);
        $this->actingAs(User::factory()->create(['role' => 'visitor']));

        $r1 = $this->postJson(route('ai.search.message'), ['message' => 'beni arayın'])->assertOk();
        $uuid = $r1->json('conversation_uuid');

        $r2 = $this->postJson(route('ai.search.message'), [
            'message' => 'gerek yok aslında',
            'conversation_uuid' => $uuid,
        ])->assertOk();

        $this->assertStringContainsString('Sorun değil', $r2->json('assistant_message.content'));
        $this->assertSame(0, AiLead::count());

        $conversation = AiSearchConversation::where('uuid', $uuid)->firstOrFail();
        $this->assertArrayNotHasKey('_awaiting_lead', $conversation->current_intent ?? []);
    }

    public function test_card_number_is_masked_and_llm_skipped(): void
    {
        OpenAI::fake([]);

        $r = $this->postJson(route('ai.search.message'), [
            'message' => 'kartım 4111 1111 1111 1111 bununla rezervasyon yap',
        ])->assertOk();

        $this->assertStringContainsString('güvenli rezervasyon', mb_strtolower($r->json('assistant_message.content')));
        // DB'ye maskeli yazıldı, ham numara hiçbir yerde yok
        $this->assertStringContainsString('**** **** **** 1111', $r->json('user_message.content'));
        $this->assertDatabaseMissing('ai_search_messages', ['content' => 'kartım 4111 1111 1111 1111 bununla rezervasyon yap']);
    }

    public function test_price_alert_for_logged_user_creates_favorite(): void
    {
        OpenAI::fake([]);
        [$tour] = $this->seedTour();
        $user = User::factory()->create(['role' => 'visitor']);
        $this->actingAs($user);

        // Konuşma oluşturup son gösterilen turu bağla
        $r1 = $this->postJson(route('ai.search.message'), ['message' => 'beni arayın'])->assertOk();
        $conversation = AiSearchConversation::where('uuid', $r1->json('conversation_uuid'))->firstOrFail();
        $conversation->update([
            'current_intent' => null, // bekleyen lead'i temizle
            'last_result_tour_ids' => [$tour->id],
        ]);

        $r2 = $this->postJson(route('ai.search.message'), [
            'message' => 'fiyatı düşünce haber verir misin',
            'conversation_uuid' => $conversation->uuid,
        ])->assertOk();

        $this->assertStringContainsString('takibe aldım', $r2->json('assistant_message.content'));
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'tour_id' => $tour->id]);
    }

    public function test_complaint_without_tour_asks_callback_without_emoji(): void
    {
        OpenAI::fake([]);

        $r = $this->postJson(route('ai.search.message'), [
            'message' => 'şikayetim var, mağdur oldum',
        ])->assertOk();

        $content = $r->json('assistant_message.content');
        $this->assertStringContainsString('üzgünüm', mb_strtolower($content));
        // Şikayette emoji yok (spec kuralı)
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $content);
    }
}
