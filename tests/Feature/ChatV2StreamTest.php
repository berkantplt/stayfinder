<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Chatbot v2 SSE ucu. Hafıza OTURUMDA (karar 2) — DB'ye yazılmaz.
 */
class ChatV2StreamTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.chat_v2_enabled' => true]);
    }

    private function textResponse(string $text): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text]]],
        ]);
    }

    private function toolCallResponse(string $tool, array $args): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant', 'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_1', 'type' => 'function',
                        'function' => ['name' => $tool, 'arguments' => json_encode($args)],
                    ]],
                ],
            ]],
        ]);
    }

    private function makeTour(string $title): Tour
    {
        $agency = Agency::create([
            'name' => 'A '.uniqid(), 'slug' => 'a-'.uniqid(), 'email' => uniqid().'@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);
        $tour = Tour::create([
            'agency_id' => $agency->id, 'title' => $title, 'destination' => 'Testşehir',
            'description' => 'd', 'price' => 25000, 'currency' => 'TRY', 'duration_days' => 5,
            'departure_date' => today()->addDays(20), 'return_date' => today()->addDays(25),
            'is_active' => true,
        ]);
        $payload = [];
        foreach (Rubric::dimensions() as $d) {
            $payload[$d] = ['value' => 3, 'confidence' => 'high', 'evidence' => 'test'];
        }
        TourRubricScore::create([
            'tour_id' => $tour->id, 'rubric_version' => Rubric::VERSION,
            'input_hash' => 'h'.uniqid(), 'scores' => $payload,
            'review_status' => TourRubricScore::STATUS_AUTO, 'scored_at' => now(),
        ]);

        return $tour;
    }

    private function sse(\Illuminate\Testing\TestResponse $response): string
    {
        return $response->streamedContent();
    }

    public function test_sse_basliklari_dogru(): void
    {
        OpenAI::fake([$this->textResponse('Merhaba!')]);

        $response = $this->post('/sohbet/akis', ['message' => 'selam']);

        $response->assertOk();
        $this->assertStringContainsString('text/event-stream', $response->headers->get('Content-Type'));
        // nginx buffering kapalı olmazsa akış birikip tek seferde düşer
        $this->assertSame('no', $response->headers->get('X-Accel-Buffering'));
    }

    public function test_akis_delta_ve_done_olaylarini_uretir(): void
    {
        OpenAI::fake([$this->textResponse('Nasıl bir tatil istiyorsun?')]);

        $govde = $this->sse($this->post('/sohbet/akis', ['message' => 'selam']));

        $this->assertStringContainsString(': keep-alive', $govde); // ilk bayt
        $this->assertStringContainsString('event: delta', $govde);
        $this->assertStringContainsString('event: done', $govde);
        $this->assertStringContainsString('tatil', $govde);
    }

    public function test_tur_bulununca_tours_olayi_gelir(): void
    {
        $this->makeTour('Sakin Koy Turu');

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', [
                'boyutlar' => ['tempo' => ['deger' => 20, 'kanit' => 'kafamı dinlemek istiyorum']],
            ]),
            $this->textResponse('Sakin bir kaçamak buldum.'),
        ]);

        $govde = $this->sse($this->post('/sohbet/akis', ['message' => 'kafamı dinlemek istiyorum']));

        $this->assertStringContainsString('event: tours', $govde);
        $this->assertStringContainsString('Sakin Koy Turu', $govde);
    }

    /**
     * Bilgi sorusuna da arama yapılırsa (model kuralı atlarsa) aynı kartlar
     * cevabın altına ikinci kez basılıyordu — kullanıcı bunu arıza sanıyor.
     */
    public function test_ayni_turlar_ikinci_kez_kart_olarak_basilmaz(): void
    {
        $this->makeTour('Sakin Koy Turu');

        $aramaArgumanlari = ['boyutlar' => ['tempo' => ['deger' => 20, 'kanit' => 'kafamı dinlemek istiyorum']]];

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', $aramaArgumanlari),
            $this->textResponse('Sakin bir kaçamak buldum.'),
            // İkinci tur: kullanıcı BİLGİ sordu ama model yine arama yaptı
            $this->toolCallResponse('tur_ara', $aramaArgumanlari),
            $this->textResponse('Kapadokya bu mevsimde serin ve sakin olur.'),
        ]);

        $ilk = $this->sse($this->post('/sohbet/akis', ['message' => 'kafamı dinlemek istiyorum']));
        $this->assertStringContainsString('event: tours', $ilk);

        $ikinci = $this->sse($this->post('/sohbet/akis', ['message' => 'kapadokya bu mevsimde nasıl']));

        $this->assertStringNotContainsString('event: tours', $ikinci);
        $this->assertStringContainsString('serin', $ikinci); // metin cevabı yine gelir
    }

    public function test_yeni_bir_tur_cikarsa_kartlar_yine_basilir(): void
    {
        $this->makeTour('Sakin Koy Turu');

        $aramaArgumanlari = ['boyutlar' => ['tempo' => ['deger' => 20, 'kanit' => 'kafamı dinlemek istiyorum']]];

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', $aramaArgumanlari),
            $this->textResponse('Bir tur buldum.'),
            $this->toolCallResponse('tur_ara', $aramaArgumanlari),
            $this->textResponse('Bir tane daha çıktı.'),
        ]);

        $this->post('/sohbet/akis', ['message' => 'kafamı dinlemek istiyorum'])->streamedContent();

        $this->makeTour('Yeni Koy Turu');
        $ikinci = $this->sse($this->post('/sohbet/akis', ['message' => 'başka ne var']));

        $this->assertStringContainsString('event: tours', $ikinci);
        $this->assertStringContainsString('Yeni Koy Turu', $ikinci);
    }

    /** Araçlar koşarken kullanıcı sessizlik görmemeli — faz olayı akmalı. */
    public function test_arac_kosarken_faz_olayi_akitilir(): void
    {
        $this->makeTour('Sakin Koy Turu');

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', [
                'boyutlar' => ['tempo' => ['deger' => 20, 'kanit' => 'kafamı dinlemek istiyorum']],
            ]),
            $this->textResponse('Buldum.'),
        ]);

        $govde = $this->sse($this->post('/sohbet/akis', ['message' => 'kafamı dinlemek istiyorum']));

        $this->assertStringContainsString('event: faz', $govde);
        $this->assertStringContainsString('Turları tarıyorum', $govde);
        // Faz, nihai cevaptan ÖNCE gelmeli
        $this->assertLessThan(strpos($govde, 'Buldum'), strpos($govde, 'event: faz'));
    }

    public function test_gecmis_ve_durum_oturumda_tutulur_db_ye_yazilmaz(): void
    {
        OpenAI::fake([$this->textResponse('Anladım.')]);

        $response = $this->post('/sohbet/akis', ['message' => 'sessiz bir yer istiyorum']);
        $this->sse($response);

        $oturum = session('chat_v2');
        $this->assertNotNull($oturum);
        $this->assertSame('sessiz bir yer istiyorum', $oturum['gecmis'][0]['content']);
        $this->assertSame('Anladım.', $oturum['gecmis'][1]['content']);

        // Karar 2: kalıcı sohbet kaydı YOK
        $this->assertDatabaseCount('ai_search_conversations', 0);
    }

    public function test_pii_maskelenir(): void
    {
        OpenAI::fake([$this->textResponse('Tamam.')]);

        $this->sse($this->post('/sohbet/akis', ['message' => 'kartım 4111 1111 1111 1111 numaralı']));

        // Ham kart numarası ne oturuma ne LLM'e gitmeli
        $this->assertStringNotContainsString('4111111111111111', json_encode(session('chat_v2')));
        OpenAI::assertSent(\OpenAI\Resources\Chat::class, function ($method, $params) {
            return ! str_contains(json_encode($params['messages']), '4111 1111 1111 1111');
        });
    }

    public function test_sifirlama_oturum_hafizasini_temizler(): void
    {
        OpenAI::fake([$this->textResponse('Tamam.')]);
        $this->sse($this->post('/sohbet/akis', ['message' => 'merhaba']));
        $this->assertNotNull(session('chat_v2'));

        $this->postJson('/sohbet/sifirla')->assertOk();

        $this->assertNull(session('chat_v2'));
    }

    /** Chat 5 kart gösterir; kalan sayısı "diğerleri" butonu için bildirilir. */
    public function test_bes_kart_gosterilir_kalan_bildirilir(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->makeTour('Tur '.$i);
        }

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', [
                'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            ]),
            $this->textResponse('Buldum.'),
        ]);

        $govde = $this->sse($this->post('/sohbet/akis', ['message' => 'normal bir tempo olsun']));

        preg_match('/event: tours\ndata: (.*)/', $govde, $m);
        $veri = json_decode($m[1], true);

        $this->assertCount(5, $veri['items']);
        $this->assertSame(9, $veri['toplam']);
        $this->assertSame(4, $veri['kalan']);   // 9 - 5
    }

    /** "Diğerleri" LLM'e gitmez ve chat'te gösterilenleri TEKRARLAMAZ. */
    public function test_digerleri_kalan_turlari_tekrarsiz_getirir(): void
    {
        for ($i = 1; $i <= 9; $i++) {
            $this->makeTour('Tur '.$i);
        }

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', [
                'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            ]),
            $this->textResponse('Buldum.'),
        ]);
        $ilk = $this->sse($this->post('/sohbet/akis', ['message' => 'normal bir tempo olsun']));
        preg_match('/event: tours\ndata: (.*)/', $ilk, $m);
        $ilkIdler = array_column(json_decode($m[1], true)['items'], 'id');

        // Boş fake: bu uç LLM'e HİÇ gitmemeli
        OpenAI::fake([]);
        $response = $this->postJson('/sohbet/digerleri')->assertOk();

        $digerIdler = array_column($response->json('items'), 'id');
        $this->assertNotEmpty($digerIdler);
        $this->assertSame([], array_intersect($ilkIdler, $digerIdler), 'Chat\'te gösterilen tur tekrarlanmamalı');
    }

    /** Çeşitlilik kuralı genişletilmiş listeyi tıkamamalı: aynı banttan çok tur
     *  olsa bile "diğerleri" dolu gelmeli (kural yalnız ilk 5 için geçerli). */
    public function test_digerleri_cesitlilik_kuralindan_tikanmaz(): void
    {
        // Hepsi aynı doga_sehir bandında (varsayılan 3 → orta bant)
        for ($i = 1; $i <= 14; $i++) {
            $this->makeTour('Aynı Bant '.$i);
        }

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', [
                'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            ]),
            $this->textResponse('Buldum.'),
        ]);
        $this->sse($this->post('/sohbet/akis', ['message' => 'normal bir tempo olsun']));

        OpenAI::fake([]);
        $items = $this->postJson('/sohbet/digerleri')->assertOk()->json('items');

        // Çeşitlilik açık kalsaydı 2 turda tıkanırdı
        $this->assertGreaterThan(3, count($items));
    }

    public function test_digerleri_profil_yokken_bos_doner(): void
    {
        $this->postJson('/sohbet/digerleri')->assertOk()->assertJson(['items' => []]);
    }

    public function test_bayrak_kapaliyken_404(): void
    {
        config(['ai.chat_v2_enabled' => false]);

        $this->post('/sohbet/akis', ['message' => 'selam'])->assertNotFound();
        $this->postJson('/sohbet/sifirla')->assertNotFound();
    }

    public function test_v2_bayragi_tek_basina_yeterli(): void
    {
        // Sohbet v1 kaldırıldı: v2'nin tek kapısı ai.chat_v2_enabled
        config(['ai.chat_v2_enabled' => true]);
        OpenAI::fake([$this->textResponse('Buradayım.')]);

        $this->post('/sohbet/akis', ['message' => 'selam'])->assertOk();
    }

    public function test_bos_mesaj_reddedilir(): void
    {
        $this->postJson('/sohbet/akis', ['message' => ''])->assertStatus(422);
        $this->postJson('/sohbet/akis', ['message' => str_repeat('a', 1001)])->assertStatus(422);
    }

    public function test_widget_bayrak_acikken_gorunur(): void
    {
        $this->get('/')->assertOk()->assertSee('id="cv2-trigger"', false);

        config(['ai.chat_v2_enabled' => false]);
        $this->get('/')->assertOk()->assertDontSee('id="cv2-trigger"', false);
    }
}
