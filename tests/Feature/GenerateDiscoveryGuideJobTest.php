<?php

namespace Tests\Feature;

use App\Jobs\GenerateDiscoveryGuideJob;
use App\Models\DiscoveryGuide;
use App\Services\Discovery\DestinationContentService;
use App\Services\Discovery\DiscoveryGuideAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Keşif Rehberi üretim job'ı bekçileri: AI daima mock (OpenAI::fake), çıktı
 * şeması backend'de doğrulanır, gün sayısı birebir tutmalı, bozuk cevap
 * kullanıcıya asla sızmaz, aynı girdi cache'ten döner, anahtar yoksa
 * anlaşılır yapılandırma hatası oluşur.
 */
class GenerateDiscoveryGuideJobTest extends TestCase
{
    use RefreshDatabase;

    private function makeGuide(array $attrs = []): DiscoveryGuide
    {
        return DiscoveryGuide::create(array_merge([
            'destination_input' => 'Paris',
            'duration_days' => 4,
            'pace' => 'normal',
            'budget' => 'standard',
            'status' => DiscoveryGuide::STATUS_PENDING,
        ], $attrs));
    }

    private function runJob(DiscoveryGuide $guide): void
    {
        (new GenerateDiscoveryGuideJob($guide->id))->handle(
            app(DiscoveryGuideAiService::class),
            app(DestinationContentService::class),
        );
    }

    private function fakeResponse(array $payload): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => json_encode($payload, JSON_UNESCAPED_UNICODE)],
            ]],
        ]);
    }

    private function validPayload(int $gunSayisi = 4): array
    {
        $gunler = [];
        foreach (range(1, $gunSayisi) as $gun) {
            $gunler[] = [
                'day' => $gun,
                'title' => 'Gün '.$gun.' teması',
                'theme' => 'Tema açıklaması',
                'morning' => [['name' => 'Sabah durağı', 'category' => 'landmark', 'description' => 'Açıklama', 'suggested_duration' => '1-2 saat']],
                'afternoon' => [['name' => 'Öğle durağı', 'description' => 'Açıklama']],
                'evening' => [['name' => 'Akşam durağı', 'description' => 'Açıklama']],
                'foods_to_try' => ['Kruvasan'],
                'daily_tip' => 'Erken çıkın.',
            ];
        }

        return [
            'destination' => ['name' => 'Paris', 'country' => 'Fransa', 'summary' => 'Işık şehri Paris.'],
            'assumptions' => ['traveler_type' => null, 'pace' => 'normal', 'budget' => 'standard', 'visit_type' => 'first_visit_general'],
            'highlights' => [['name' => 'Eyfel Kulesi', 'category' => 'landmark', 'description' => 'Simge.', 'why_visit' => 'Manzara.']],
            'things_to_do' => [['name' => 'Seine turu', 'description' => 'Tekne gezisi.']],
            'historical_places' => [['name' => 'Notre-Dame', 'description' => 'Katedral.']],
            'museums' => [['name' => 'Louvre', 'description' => 'Dünyaca ünlü müze.']],
            'local_foods' => [['name' => 'Croissant', 'description' => 'Hamur işi.', 'when_to_try' => 'Kahvaltı']],
            'daily_plan' => $gunler,
            'travel_tips' => ['Metro kartı alın.'],
            'related_destination_keywords' => ['Paris', 'Fransa'],
        ];
    }

    public function test_gecerli_cevapta_rehber_tamamlanir_ve_tam_4_gun_icerir(): void
    {
        OpenAI::fake([$this->fakeResponse($this->validPayload(4))]);

        $guide = $this->makeGuide();
        $this->runJob($guide);

        $guide->refresh();
        $this->assertSame(DiscoveryGuide::STATUS_COMPLETED, $guide->status);
        $this->assertCount(4, $guide->guide_payload['daily_plan']);
        $this->assertSame('Paris', $guide->guide_payload['destination']['name']);
        // Varsayımlar AI'dan değil kayıttan yazılır
        $this->assertSame('first_visit_general', $guide->guide_payload['assumptions']['visit_type']);
        $this->assertNull($guide->guide_payload['assumptions']['traveler_type']);
    }

    public function test_gun_sayisi_tutmayan_cevap_reddedilir_kullaniciya_sizmaz(): void
    {
        // İki denemede de 3 günlük plan dönerse üretim başarısız olmalı
        OpenAI::fake([
            $this->fakeResponse($this->validPayload(3)),
            $this->fakeResponse($this->validPayload(3)),
        ]);

        $guide = $this->makeGuide();

        try {
            $this->runJob($guide);
            $this->fail('Şemaya uymayan cevap exception fırlatmalıydı');
        } catch (\RuntimeException) {
            // beklenen — job retry mekanizmasına düşer
        }

        $guide->refresh();
        $this->assertNotSame(DiscoveryGuide::STATUS_COMPLETED, $guide->status);
        $this->assertNull($guide->guide_payload); // yarım/bozuk içerik kaydedilmez

        // Son deneme de düşünce failed() jenerik Türkçe mesaj yazar; ham hata sızmaz
        (new GenerateDiscoveryGuideJob($guide->id))->failed(new \RuntimeException('daily_plan 4 gün olmalı'));
        $guide->refresh();
        $this->assertSame(DiscoveryGuide::STATUS_FAILED, $guide->status);
        $this->assertStringNotContainsString('daily_plan', (string) $guide->error_message);
        $this->assertStringContainsString('tekrar deneyin', mb_strtolower((string) $guide->error_message, 'UTF-8'));
    }

    public function test_bozuk_json_cevabi_kontrollu_retry_ile_denenir_sonra_reddedilir(): void
    {
        OpenAI::fake([
            $this->fakeResponse([]), // parse edilir ama şemaya uymaz
            $this->fakeResponse($this->validPayload(4)), // düzeltme denemesi başarılı
        ]);

        $guide = $this->makeGuide();
        $this->runJob($guide);

        $guide->refresh();
        $this->assertSame(DiscoveryGuide::STATUS_COMPLETED, $guide->status);
        $this->assertCount(4, $guide->guide_payload['daily_plan']);
    }

    public function test_api_anahtari_yoksa_anlasilir_yapilandirma_hatasi(): void
    {
        config(['openai.api_key' => '']);

        $guide = $this->makeGuide();
        $this->runJob($guide); // OpenAI hiç çağrılmaz — fake bile gerekmez

        $guide->refresh();
        $this->assertSame(DiscoveryGuide::STATUS_FAILED, $guide->status);
        $this->assertStringContainsString('yapılandırılmamış', (string) $guide->error_message);
    }

    public function test_ayni_girdi_ikinci_kez_ai_cagrisi_yapmaz_cacheten_doner(): void
    {
        // Tek fake cevap: ikinci bir AI çağrısı olsaydı fake kuyruğu boş kalır ve patlardı
        OpenAI::fake([$this->fakeResponse($this->validPayload(4))]);

        $birinci = $this->makeGuide();
        $this->runJob($birinci);
        $this->assertSame(DiscoveryGuide::STATUS_COMPLETED, $birinci->fresh()->status);

        $ikinci = $this->makeGuide();
        $this->runJob($ikinci);

        $ikinci->refresh();
        $this->assertSame(DiscoveryGuide::STATUS_COMPLETED, $ikinci->status);
        $this->assertSame($birinci->fresh()->guide_payload, $ikinci->guide_payload);
    }

    public function test_basarili_uretim_dispatch_kilidini_birakir(): void
    {
        OpenAI::fake([$this->fakeResponse($this->validPayload(4))]);

        $guide = $this->makeGuide();
        $kilit = GenerateDiscoveryGuideJob::DISPATCH_LOCK_PREFIX.$guide->id;
        Cache::add($kilit, 1, 180);

        $this->runJob($guide);

        $this->assertSame(DiscoveryGuide::STATUS_COMPLETED, $guide->fresh()->status);
        $this->assertNull(Cache::get($kilit)); // sonraki personalize dispatch edebilmeli
    }

    public function test_kalici_hata_dispatch_kilidini_birakir(): void
    {
        $guide = $this->makeGuide();
        $kilit = GenerateDiscoveryGuideJob::DISPATCH_LOCK_PREFIX.$guide->id;
        Cache::add($kilit, 1, 180);

        (new GenerateDiscoveryGuideJob($guide->id))->failed(new \RuntimeException('x'));

        $this->assertSame(DiscoveryGuide::STATUS_FAILED, $guide->fresh()->status);
        $this->assertNull(Cache::get($kilit));
    }

    public function test_farkli_tercihler_farkli_cache_anahtari_uretir(): void
    {
        $ai = app(DiscoveryGuideAiService::class);

        $genel = $this->makeGuide();
        $aileli = $this->makeGuide(['traveler_type' => 'family']);

        $this->assertNotSame($ai->cacheKey($genel), $ai->cacheKey($aileli));
    }
}
