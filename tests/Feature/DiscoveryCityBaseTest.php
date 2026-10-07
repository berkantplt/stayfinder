<?php

namespace Tests\Feature;

use App\Jobs\BuildDiscoveryCityBaseJob;
use App\Jobs\GenerateDiscoveryGuideJob;
use App\Models\DiscoveryCityBase;
use App\Models\DiscoveryGuide;
use App\Services\Discovery\DestinationContentService;
use App\Services\Discovery\DiscoveryCityBaseService;
use App\Services\Discovery\DiscoveryGuideAiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Resources\Chat;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Şehir tabanı bekçileri: taban yokken tam üretim + arkadan taban job'ı;
 * taban varken YALNIZ günlük plan üretilir ve statik bölümler tabandan
 * birleşir; bayat taban kullanılmaz; tanınmayan destinasyon asla taban
 * olmaz; etiketler sözlüğe göre süzülür. AI daima mock (OpenAI::fake).
 */
class DiscoveryCityBaseTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Yardımcılar
    // ---------------------------------------------------------------

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

    private function makeBase(array $attrs = []): DiscoveryCityBase
    {
        return DiscoveryCityBase::create(array_merge([
            'normalized_city' => 'paris',
            'display_name' => 'Paris',
            'country' => 'Fransa',
            'base_payload' => $this->cleanBasePayload(),
            'source' => DiscoveryCityBase::SOURCE_GENERATED,
            'model' => 'gpt-5.4-mini',
            'generated_at' => now(),
        ], $attrs));
    }

    private function runGuideJob(DiscoveryGuide $guide): void
    {
        (new GenerateDiscoveryGuideJob($guide->id))->handle(
            app(DiscoveryGuideAiService::class),
            app(DestinationContentService::class),
            app(DiscoveryCityBaseService::class),
        );
    }

    private function runBaseJob(string $city): void
    {
        (new BuildDiscoveryCityBaseJob($city))->handle(
            app(DiscoveryGuideAiService::class),
            app(DestinationContentService::class),
            app(DiscoveryCityBaseService::class),
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

    private function days(int $gunSayisi, string $onek = 'Tam'): array
    {
        $gunler = [];
        foreach (range(1, $gunSayisi) as $gun) {
            $gunler[] = [
                'day' => $gun,
                'title' => $onek.' gün '.$gun,
                'theme' => 'Tema '.$gun,
                'morning' => [['name' => $onek.' sabah durağı '.$gun, 'category' => 'landmark', 'description' => 'Açıklama', 'suggested_duration' => '1-2 saat']],
                'afternoon' => [['name' => 'Öğle durağı', 'description' => 'Açıklama']],
                'evening' => [['name' => 'Akşam durağı', 'description' => 'Açıklama']],
                'foods_to_try' => ['Kruvasan'],
                'daily_tip' => 'Erken çıkın.',
            ];
        }

        return $gunler;
    }

    /** Tam üretim cevabı (taban yokken bugüne kadarki yol). */
    private function fullPayload(int $gunSayisi = 4, array $ekstra = []): array
    {
        return array_merge([
            'destination' => ['name' => 'Paris', 'country' => 'Fransa', 'summary' => 'Işık şehri Paris.'],
            'assumptions' => ['traveler_type' => null, 'pace' => 'normal', 'budget' => 'standard', 'visit_type' => 'first_visit_general'],
            'highlights' => [['name' => 'Tam üretim simgesi', 'category' => 'landmark', 'description' => 'Simge.', 'why_visit' => 'Manzara.']],
            'things_to_do' => [['name' => 'Seine turu', 'description' => 'Tekne gezisi.']],
            'historical_places' => [['name' => 'Notre-Dame', 'description' => 'Katedral.']],
            'museums' => [['name' => 'Louvre', 'description' => 'Müze.']],
            'local_foods' => [['name' => 'Croissant', 'description' => 'Hamur işi.', 'when_to_try' => 'Kahvaltı']],
            'daily_plan' => $this->days($gunSayisi),
            'travel_tips' => ['Metro kartı alın.'],
            'related_destination_keywords' => ['Paris'],
        ], $ekstra);
    }

    /** Tabanlı üretim cevabı: YALNIZ günlük plan. */
    private function dailyPayload(int $gunSayisi = 4): array
    {
        return ['daily_plan' => $this->days($gunSayisi, 'Havuzdan')];
    }

    /** Taban job'ına gelen ham cevap (bir uydurma etiket içerir — süzülmeli). */
    private function rawBasePayload(array $ekstra = []): array
    {
        return array_merge([
            'destination' => ['name' => 'Paris', 'country' => 'Fransa', 'summary' => 'Işık şehri Paris, taban özeti.'],
            'unknown_destination' => false,
            'highlights' => [
                ['name' => 'Eyfel Kulesi', 'category' => 'landmark', 'description' => 'Simge.', 'why_visit' => 'Manzara.',
                    'tags' => ['interests' => ['history', 'xyz'], 'budget' => 'standard', 'suits' => ['solo', 'couple', 'uzaylı'], 'time' => ['evening', 'gece']]],
                ['name' => 'Montmartre', 'category' => 'district', 'description' => 'Tepe mahalle.', 'why_visit' => 'Atmosfer.',
                    'tags' => ['interests' => ['art'], 'budget' => 'economy', 'suits' => ['friends'], 'time' => ['morning', 'afternoon']]],
            ],
            'things_to_do' => [['name' => 'Seine turu', 'description' => 'Tekne gezisi.', 'tags' => ['interests' => ['nature'], 'budget' => 'premium', 'suits' => ['family'], 'time' => ['evening']]]],
            'historical_places' => [['name' => 'Notre-Dame', 'description' => 'Katedral.', 'tags' => ['interests' => ['history'], 'budget' => 'economy']]],
            'museums' => [['name' => 'Louvre', 'description' => 'Müze.', 'tags' => ['interests' => ['museum', 'art'], 'budget' => 'standard', 'suits' => ['solo'], 'time' => ['morning']]]],
            'local_foods' => [['name' => 'Croissant', 'description' => 'Hamur işi.', 'when_to_try' => 'Kahvaltı', 'tags' => ['budget' => 'economy']]],
            'travel_tips' => ['Metro kartı alın.'],
            'related_destination_keywords' => ['Paris', 'Fransa'],
        ], $ekstra);
    }

    /** Doğrulanmış (temiz) taban payload'u — DB'ye bu yazılır. */
    private function cleanBasePayload(): array
    {
        return [
            'destination' => ['name' => 'Paris', 'country' => 'Fransa', 'summary' => 'Işık şehri Paris, taban özeti.'],
            'unknown_destination' => false,
            'highlights' => [
                ['name' => 'Eyfel Kulesi', 'category' => 'landmark', 'description' => 'Simge.', 'why_visit' => 'Manzara.', 'tags' => ['interests' => ['history'], 'budget' => 'standard', 'suits' => ['solo', 'couple'], 'time' => ['evening']]],
                ['name' => 'Montmartre', 'category' => 'district', 'description' => 'Tepe mahalle.', 'why_visit' => 'Atmosfer.', 'tags' => ['interests' => ['art'], 'budget' => 'economy', 'suits' => ['friends'], 'time' => ['morning', 'afternoon']]],
            ],
            'things_to_do' => [['name' => 'Seine turu', 'description' => 'Tekne gezisi.', 'tags' => ['interests' => ['nature'], 'budget' => 'premium', 'suits' => ['family'], 'time' => ['evening']]]],
            'historical_places' => [['name' => 'Notre-Dame', 'description' => 'Katedral.', 'tags' => ['interests' => ['history'], 'budget' => 'economy']]],
            'museums' => [['name' => 'Louvre', 'description' => 'Müze.', 'tags' => ['interests' => ['museum', 'art'], 'budget' => 'standard', 'suits' => ['solo'], 'time' => ['morning']]]],
            'local_foods' => [['name' => 'Croissant', 'description' => 'Hamur işi.', 'when_to_try' => 'Kahvaltı', 'tags' => ['budget' => 'economy']]],
            'travel_tips' => ['Metro kartı alın.'],
            'related_destination_keywords' => ['Paris', 'Fransa'],
        ];
    }

    // ---------------------------------------------------------------
    // Rehber job'ı: taban var / yok / bayat / tanınmayan
    // ---------------------------------------------------------------

    public function test_taban_yokken_tam_uretim_yapilir_ve_taban_job_arkadan_kuyruga_alinir(): void
    {
        OpenAI::fake([$this->fakeResponse($this->fullPayload(4))]);

        $guide = $this->makeGuide();
        $this->runGuideJob($guide);

        $guide->refresh();
        $this->assertSame(DiscoveryGuide::STATUS_COMPLETED, $guide->status);
        $this->assertSame('Tam üretim simgesi', $guide->guide_payload['highlights'][0]['name']);
        $this->assertArrayNotHasKey('city_base_id', $guide->guide_payload);

        Queue::assertPushed(BuildDiscoveryCityBaseJob::class, fn ($job) => $job->cityInput === 'Paris');
        $this->assertNotNull(Cache::get(DiscoveryCityBaseService::BUILD_LOCK_PREFIX.'paris'), 'şehir başına dispatch kilidi alınmalı');
    }

    public function test_bilinmeyen_destinasyonda_taban_job_atilmaz(): void
    {
        OpenAI::fake([$this->fakeResponse($this->fullPayload(4, ['unknown_destination' => true]))]);

        $guide = $this->makeGuide(['destination_input' => 'Xyzqwe Köyü']);
        $this->runGuideJob($guide);

        $this->assertSame(DiscoveryGuide::STATUS_COMPLETED, $guide->fresh()->status);
        Queue::assertNotPushed(BuildDiscoveryCityBaseJob::class);
    }

    public function test_taban_varken_yalnizca_gunluk_plan_uretilir_ve_tabanla_birlesir(): void
    {
        $base = $this->makeBase();
        OpenAI::fake([$this->fakeResponse($this->dailyPayload(4))]);

        $guide = $this->makeGuide(['destination_input' => 'PARİS', 'traveler_type' => 'with_kids', 'interests' => ['history']]);
        $this->runGuideJob($guide);

        $guide->refresh();
        $this->assertSame(DiscoveryGuide::STATUS_COMPLETED, $guide->status);
        $p = $guide->guide_payload;

        // Statik bölümler tabandan, günlük plan AI'dan
        $this->assertSame('Paris', $p['destination']['name']);
        $this->assertSame('Işık şehri Paris, taban özeti.', $p['destination']['summary']);
        $this->assertSame('Eyfel Kulesi', $p['highlights'][0]['name']);
        $this->assertSame('Louvre', $p['museums'][0]['name']);
        $this->assertSame(['Metro kartı alın.'], $p['travel_tips']);
        $this->assertCount(4, $p['daily_plan']);
        $this->assertSame('Havuzdan sabah durağı 1', $p['daily_plan'][0]['morning'][0]['name']);
        $this->assertSame($base->id, $p['city_base_id']);
        // Varsayımlar rehber kaydından (tabandan değil)
        $this->assertSame('with_kids', $p['assumptions']['traveler_type']);
        $this->assertSame('personalized', $p['assumptions']['visit_type']);

        // Havuz prompt'a veri olarak gitti, istek yalnız günlük plan istedi
        OpenAI::assertSent(Chat::class, function (string $method, array $params) {
            $system = $params['messages'][0]['content'];
            $user = $params['messages'][1]['content'];

            return str_contains($system, 'HAVUZ')
                && str_contains($system, '"daily_plan"')
                && ! str_contains($system, '"museums"')
                && str_contains($user, 'HAVUZ (veri)')
                && str_contains($user, 'Eyfel Kulesi')
                && str_contains($user, '"with_kids"')
                // 5 mekânlık havuz, 4 gün × 3 bölüm × 2 öğe = 24 slot → "en az 19 havuz dışı"
                && str_contains($user, 'En az 19 havuz dışı');
        });

        $this->assertSame(1, $base->fresh()->hit_count);
        Queue::assertNotPushed(BuildDiscoveryCityBaseJob::class);
    }

    public function test_havuz_yeterliyse_havuz_disi_notu_eklenmez(): void
    {
        $this->makeBase();
        OpenAI::fake([$this->fakeResponse($this->dailyPayload(1))]);

        // 1 gün × 3 bölüm × 1 öğe (relaxed) = 3 slot, havuzda 5 mekân var
        $guide = $this->makeGuide(['duration_days' => 1, 'pace' => 'relaxed']);
        $this->runGuideJob($guide);

        $this->assertSame(DiscoveryGuide::STATUS_COMPLETED, $guide->fresh()->status);
        OpenAI::assertSent(Chat::class, fn (string $method, array $params) => ! str_contains($params['messages'][1]['content'], 'havuz dışı'));
    }

    public function test_bayat_taban_kullanilmaz_tam_uretime_dusulur_ve_yenileme_kuyruga_alinir(): void
    {
        $this->makeBase(['generated_at' => now()->subDays(DiscoveryCityBase::MAX_AGE_DAYS + 10)]);
        OpenAI::fake([$this->fakeResponse($this->fullPayload(4))]);

        $guide = $this->makeGuide();
        $this->runGuideJob($guide);

        $p = $guide->fresh()->guide_payload;
        $this->assertSame('Tam üretim simgesi', $p['highlights'][0]['name']);
        $this->assertArrayNotHasKey('city_base_id', $p);

        Queue::assertPushed(BuildDiscoveryCityBaseJob::class);
        $this->assertSame(0, DiscoveryCityBase::first()->hit_count);
    }

    public function test_gunluk_plan_gun_sayisi_tutmazsa_reddedilir_tabanli_yolda_da(): void
    {
        $this->makeBase();
        OpenAI::fake([
            $this->fakeResponse($this->dailyPayload(3)),
            $this->fakeResponse($this->dailyPayload(3)),
        ]);

        $guide = $this->makeGuide();

        try {
            $this->runGuideJob($guide);
            $this->fail('RuntimeException bekleniyordu');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('4 gün olmalı', $e->getMessage());
        }

        $this->assertNotSame(DiscoveryGuide::STATUS_COMPLETED, $guide->fresh()->status);
        $this->assertNull($guide->fresh()->guide_payload);
    }

    // ---------------------------------------------------------------
    // Taban job'ı
    // ---------------------------------------------------------------

    public function test_taban_job_etiketli_havuzu_uretir_ve_sozluk_disi_etiketleri_suzer(): void
    {
        OpenAI::fake([$this->fakeResponse($this->rawBasePayload())]);
        Cache::add(DiscoveryCityBaseService::BUILD_LOCK_PREFIX.'paris', 1, 600);

        $this->runBaseJob('Paris');

        $base = DiscoveryCityBase::where('normalized_city', 'paris')->firstOrFail();
        $this->assertSame('Paris', $base->display_name);
        $this->assertSame('Fransa', $base->country);
        $this->assertSame('gpt-5.4-mini', $base->model);
        $this->assertFalse($base->isStale());
        $this->assertSame(6, $base->itemCount());

        $eyfel = $base->section('highlights')[0];
        $this->assertSame(['history'], $eyfel['tags']['interests'], 'xyz etiketi düşmeli');
        $this->assertSame(['solo', 'couple'], $eyfel['tags']['suits'], 'uydurma gezgin tipi düşmeli');
        $this->assertSame(['evening'], $eyfel['tags']['time'], 'gece dilimi sözlükte yok');
        $this->assertSame('standard', $eyfel['tags']['budget']);
        $this->assertSame(['budget' => 'economy'], $base->section('local_foods')[0]['tags']);

        $this->assertNull(Cache::get(DiscoveryCityBaseService::BUILD_LOCK_PREFIX.'paris'), 'kilit job bitince bırakılmalı');

        OpenAI::assertSent(Chat::class, function (string $method, array $params) {
            return str_contains($params['messages'][0]['content'], 'ŞEHİR TABANI')
                && str_contains($params['messages'][0]['content'], 'with_kids')
                && str_contains($params['messages'][1]['content'], '"Paris"');
        });
    }

    public function test_taban_job_bilinmeyen_destinasyonu_yazmaz(): void
    {
        OpenAI::fake([$this->fakeResponse([
            'destination' => ['name' => 'Xyzqwe Köyü', 'country' => null, 'summary' => ''],
            'unknown_destination' => true,
            'highlights' => [],
        ])]);

        $this->runBaseJob('Xyzqwe Köyü');

        $this->assertSame(0, DiscoveryCityBase::count());
    }

    public function test_taban_job_taze_taban_varsa_ai_cagirmaz(): void
    {
        $base = $this->makeBase(['generated_at' => now()->subDays(3)]);
        OpenAI::fake([]); // çağrı olsaydı fake kuyruğu boş olduğu için patlardı

        $this->runBaseJob('paris');

        $this->assertSame(1, DiscoveryCityBase::count());
        $this->assertTrue($base->generated_at->equalTo(DiscoveryCityBase::first()->generated_at));
    }

    public function test_taban_job_bayat_tabani_yerinde_yeniler_ve_kullanim_sayacini_korur(): void
    {
        $eski = $this->makeBase(['generated_at' => now()->subDays(120), 'hit_count' => 7, 'display_name' => 'paris']);
        OpenAI::fake([$this->fakeResponse($this->rawBasePayload())]);

        $this->runBaseJob('Paris');

        $this->assertSame(1, DiscoveryCityBase::count());
        $yeni = DiscoveryCityBase::first();
        $this->assertSame($eski->id, $yeni->id);
        $this->assertSame('Paris', $yeni->display_name);
        $this->assertSame(7, $yeni->hit_count);
        $this->assertFalse($yeni->isStale());
    }

    public function test_yetersiz_havuzlu_taban_reddedilir_ve_yazilmaz(): void
    {
        $zayif = $this->rawBasePayload(['highlights' => [['name' => 'Tek yer', 'description' => 'x']], 'things_to_do' => [], 'historical_places' => [], 'museums' => []]);
        OpenAI::fake([$this->fakeResponse($zayif), $this->fakeResponse($zayif)]);

        try {
            $this->runBaseJob('Paris');
            $this->fail('RuntimeException bekleniyordu');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('havuzu yetersiz', $e->getMessage());
        }

        $this->assertSame(0, DiscoveryCityBase::count());
    }

    public function test_queue_build_sehir_basina_tek_job_dispatch_eder(): void
    {
        $servis = app(DiscoveryCityBaseService::class);

        $this->assertTrue($servis->queueBuild('İstanbul'));
        $this->assertFalse($servis->queueBuild('istanbul'), 'aynı şehrin farklı yazımı kilide takılmalı');

        Queue::assertPushed(BuildDiscoveryCityBaseJob::class, 1);
    }
}
