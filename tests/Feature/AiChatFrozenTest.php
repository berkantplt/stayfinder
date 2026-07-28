<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ❄️ Sohbet asistanı dondurma anahtarı (config/ai.php: chat_enabled).
 *
 * Canlıda kapalı; kod ve veri silinmediği için diğer testler açık bayrakla
 * koşar (phpunit.xml). Bu test kapalı davranışı doğrular: uçlar 404, widget
 * görünmez, tur eşleştirme testi ETKİLENMEZ.
 */
class AiChatFrozenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.chat_enabled' => false]);
    }

    public function test_sohbet_sayfasi_kapaliyken_404(): void
    {
        $this->get('/yapay-zeka-arama')->assertNotFound();
    }

    public function test_mesaj_uclari_kapaliyken_404(): void
    {
        $this->postJson('/yapay-zeka-arama/mesaj', ['message' => 'merhaba'])->assertNotFound();
        $this->postJson('/yapay-zeka-arama/mesaj/akis', ['message' => 'merhaba'])->assertNotFound();
        $this->getJson('/yapay-zeka-arama-api?q=antalya')->assertNotFound();
    }

    public function test_widget_kapaliyken_sayfalarda_gorunmez(): void
    {
        $response = $this->get('/')->assertOk();

        // Markup ve JS render EDİLMEMELİ (guard dışında yalnız zararsız CSS
        // seçicileri kalır — onlar hedef alınmaz)
        $response->assertDontSee('id="ai-chat-trigger"', false);
        $response->assertDontSee('id="ai-chat-container"', false);
        $response->assertDontSee('toggleAIChat()', false);
        $response->assertDontSee('sendAIChatMessage', false);
    }

    /** Dondurma tur eşleştirme testini kapatmaz — o LLM'siz çalışır. */
    public function test_tur_eslestirme_testi_etkilenmez(): void
    {
        $this->getJson('/tatil-karakteri')->assertOk();
    }

    /** Bayrak açılınca her şey geri gelir (geri dönülebilirlik güvencesi). */
    public function test_bayrak_acilinca_geri_gelir(): void
    {
        config(['ai.chat_enabled' => true]);

        $this->get('/yapay-zeka-arama')->assertOk();
    }
}
