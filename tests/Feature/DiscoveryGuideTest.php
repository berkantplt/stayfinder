<?php

namespace Tests\Feature;

use App\Jobs\GenerateDiscoveryGuideJob;
use App\Models\Agency;
use App\Models\DiscoveryGuide;
use App\Models\Tour;
use App\Models\User;
use App\Services\Discovery\DiscoveryGuideAiService;
use App\Services\Discovery\DiscoveryGuideService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Keşif Rehberi HTTP katmanı bekçileri: zorunlu alanlar yalnız destinasyon +
 * gün, kalanı opsiyonel; misafir oluşturabilir; sahiplik korunur; ilgili
 * turlar yalnız turXtur veritabanından gelir; rate limit uygulanır.
 * AI hiç çağrılmaz: üretim job'ı TestCase'in kısmi Queue::fake listesinde.
 */
class DiscoveryGuideTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $ekstra = []): array
    {
        return array_merge(['destination' => 'Paris', 'duration_days' => 4], $ekstra);
    }

    public function test_sadece_destinasyon_ve_gun_ile_istek_kabul_edilir(): void
    {
        $r = $this->postJson(route('discovery.store'), $this->payload());

        $r->assertCreated()->assertJsonStructure(['uuid', 'status', 'redirect_url']);

        $guide = DiscoveryGuide::firstOrFail();
        $this->assertSame('Paris', $guide->destination_input);
        $this->assertSame(4, $guide->duration_days);
        $this->assertNull($guide->traveler_type);
        $this->assertSame('normal', $guide->pace);
        $this->assertSame('standard', $guide->budget);
        $this->assertSame(DiscoveryGuide::STATUS_PENDING, $guide->status);

        Queue::assertPushed(GenerateDiscoveryGuideJob::class, fn ($job) => $job->guideId === $guide->id);
    }

    public function test_traveler_type_bos_gonderilebilir(): void
    {
        $this->postJson(route('discovery.store'), $this->payload([
            'traveler_type' => null,
            'interests' => [],
        ]))->assertCreated();

        $this->assertNull(DiscoveryGuide::firstOrFail()->traveler_type);
    }

    public function test_sure_1den_kucuk_veya_7den_buyukse_validation_hatasi(): void
    {
        $this->postJson(route('discovery.store'), $this->payload(['duration_days' => 0]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_days');

        $this->postJson(route('discovery.store'), $this->payload(['duration_days' => 8]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration_days');

        $this->assertDatabaseCount('discovery_guides', 0);
    }

    public function test_gecersiz_enum_degerleri_reddedilir(): void
    {
        $this->postJson(route('discovery.store'), $this->payload(['traveler_type' => 'yalniz']))
            ->assertUnprocessable()->assertJsonValidationErrors('traveler_type');

        $this->postJson(route('discovery.store'), $this->payload(['pace' => 'cok-hizli']))
            ->assertUnprocessable()->assertJsonValidationErrors('pace');

        $this->postJson(route('discovery.store'), $this->payload(['budget' => 'lux']))
            ->assertUnprocessable()->assertJsonValidationErrors('budget');

        $this->postJson(route('discovery.store'), $this->payload(['interests' => ['yoga']]))
            ->assertUnprocessable()->assertJsonValidationErrors('interests.0');
    }

    public function test_misafir_kullanici_rehber_olusturabilir(): void
    {
        // Not: test istemcisi istekler arasında oturum çerezini taşımadığı için
        // misafirin "kendi rehberini açma" akışı burada değil, login'li
        // kullanıcı üzerinden doğrulanıyor (AiConversationAccessTest kalıbı).
        $this->postJson(route('discovery.store'), $this->payload())->assertCreated();

        $guide = DiscoveryGuide::firstOrFail();
        $this->assertNull($guide->user_id);
        $this->assertNotNull($guide->session_id);
        $this->assertSame(DiscoveryGuide::STATUS_PENDING, $guide->status);
    }

    public function test_giris_yapmis_kullanicinin_rehberi_hesabina_baglanir(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('discovery.store'), $this->payload())->assertCreated();

        $guide = DiscoveryGuide::firstOrFail();
        $this->assertSame($user->id, $guide->user_id);
        $this->assertNull($guide->session_id);
    }

    public function test_baskasinin_rehberine_erisim_403(): void
    {
        $sahip = User::factory()->create();
        $davetsiz = User::factory()->create();

        $this->actingAs($sahip)->postJson(route('discovery.store'), $this->payload())->assertCreated();
        $guide = DiscoveryGuide::firstOrFail();

        $this->actingAs($davetsiz)->get(route('discovery.show', $guide))->assertForbidden();
        $this->actingAs($davetsiz)->getJson(route('discovery.status', $guide))->assertForbidden();
        $this->actingAs($davetsiz)
            ->postJson(route('discovery.personalize', $guide), ['traveler_type' => 'family'])
            ->assertForbidden();
    }

    public function test_durum_ucu_status_doner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('discovery.store'), $this->payload())->assertCreated();
        $guide = DiscoveryGuide::firstOrFail();

        $this->actingAs($user)->getJson(route('discovery.status', $guide))
            ->assertOk()
            ->assertJson(['status' => DiscoveryGuide::STATUS_PENDING, 'error_message' => null]);
    }

    public function test_kisisellestirme_tercihi_gunceller_ve_yeniden_uretir(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('discovery.store'), $this->payload())->assertCreated();
        $guide = DiscoveryGuide::firstOrFail();
        $this->simuleJobTamamlandi($guide);

        $this->actingAs($user)->postJson(route('discovery.personalize', $guide), ['traveler_type' => 'with_kids'])
            ->assertOk()
            ->assertJson(['status' => DiscoveryGuide::STATUS_PENDING]);

        $guide->refresh();
        $this->assertSame('with_kids', $guide->traveler_type);
        $this->assertSame(DiscoveryGuide::STATUS_PENDING, $guide->status);

        Queue::assertPushed(GenerateDiscoveryGuideJob::class, 2);
    }

    public function test_ust_uste_kisisellestirme_tek_job_dispatch_eder(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('discovery.store'), $this->payload())->assertCreated();
        $guide = DiscoveryGuide::firstOrFail();
        $this->simuleJobTamamlandi($guide);

        // İlk kişiselleştirme kilidi alır ve dispatch eder; job (fake) kilidi
        // bırakmadığı için ikincisi dispatch ETMEZ — ama tercihi yine günceller
        // (çalışan job bitişte bayatlık kontrolüyle güncel tercihi üretir).
        $this->actingAs($user)->postJson(route('discovery.personalize', $guide), ['traveler_type' => 'couple'])->assertOk();
        $this->actingAs($user)->postJson(route('discovery.personalize', $guide), ['traveler_type' => 'family'])->assertOk();

        Queue::assertPushed(GenerateDiscoveryGuideJob::class, 2); // create + ilk personalize
        $this->assertSame('family', $guide->fresh()->traveler_type);
    }

    public function test_takili_rehber_tembel_zaman_asimiyla_failed_olur(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('discovery.store'), $this->payload())->assertCreated();
        $guide = DiscoveryGuide::firstOrFail();

        // Job kuyrukta kayboldu senaryosu: kayıt 15 dk'dır kıpırdamıyor
        // (model update updated_at'i tazeleyeceği için query builder ile)
        DB::table('discovery_guides')->where('id', $guide->id)
            ->update(['updated_at' => now()->subMinutes(15)]);

        $this->actingAs($user)->getJson(route('discovery.status', $guide))
            ->assertOk()
            ->assertJsonPath('status', DiscoveryGuide::STATUS_FAILED);

        $guide->refresh();
        $this->assertTrue($guide->isFailed());
        $this->assertStringContainsString('uzun sürdü', (string) $guide->error_message);
    }

    // ---------------------------------------------------------------
    // Dispatch ÖNCESİ yeniden kullanım: hazır içerik varsa kuyruk atlanır
    // ---------------------------------------------------------------

    public function test_cache_isabetinde_rehber_kuyruksuz_tamamlanir(): void
    {
        $ai = app(DiscoveryGuideAiService::class);
        $anahtar = $ai->cacheKey(new DiscoveryGuide([
            'destination_input' => 'Paris', 'duration_days' => 4,
            'traveler_type' => null, 'interests' => null, 'pace' => 'normal', 'budget' => 'standard',
        ]));
        $ai->rememberPayload($anahtar, $this->hazirPayload());

        $r = $this->postJson(route('discovery.store'), $this->payload());

        $r->assertCreated()->assertJsonPath('status', DiscoveryGuide::STATUS_COMPLETED);

        $guide = DiscoveryGuide::firstOrFail();
        $this->assertTrue($guide->isCompleted());
        $this->assertSame('Paris', $guide->guide_payload['destination']['name']);
        Queue::assertNotPushed(GenerateDiscoveryGuideJob::class);
    }

    public function test_ayni_girdili_tamamlanmis_rehber_veritabanindan_yeniden_kullanilir(): void
    {
        // Başka kullanıcının, farklı yazımla ("PARİS") tamamlanmış rehberi;
        // cache boş (deploy sonrası cache:clear senaryosu).
        $sahip = User::factory()->create();
        $kaynak = DiscoveryGuide::create([
            'user_id' => $sahip->id,
            'destination_input' => 'PARİS',
            'duration_days' => 4,
            'pace' => 'normal',
            'budget' => 'standard',
            'status' => DiscoveryGuide::STATUS_COMPLETED,
            'guide_payload' => $this->hazirPayload(),
        ]);

        // Okuyucu giriş yapmış: misafir oturum çerezi test istemcisinde
        // istekler arası taşınmadığı için sonuç sayfası sahiplik kontrolünü
        // login'li kullanıcıyla geçer (AiConversationAccessTest kalıbı).
        $okuyucu = User::factory()->create();
        $r = $this->actingAs($okuyucu)->postJson(route('discovery.store'), $this->payload());

        $r->assertCreated()->assertJsonPath('status', DiscoveryGuide::STATUS_COMPLETED);

        $yeni = DiscoveryGuide::whereKeyNot($kaynak->id)->firstOrFail();
        $this->assertSame($kaynak->guide_payload, $yeni->guide_payload);
        Queue::assertNotPushed(GenerateDiscoveryGuideJob::class);

        // Cache ısıtıldı: bir sonraki istek DB taramasına bile girmez
        $ai = app(DiscoveryGuideAiService::class);
        $this->assertNotNull($ai->cachedPayload($ai->cacheKey($yeni)));

        // Sonuç sayfası doğrudan tamamlanmış içeriği basar (polling yok)
        $this->actingAs($okuyucu)->get($r->json('redirect_url'))
            ->assertOk()
            ->assertSee('Işık şehri Paris.');
    }

    public function test_eski_veya_farkli_parametreli_rehber_yeniden_kullanilmaz(): void
    {
        $eski = DiscoveryGuide::create([
            'destination_input' => 'Paris', 'duration_days' => 4, 'pace' => 'normal', 'budget' => 'standard',
            'status' => DiscoveryGuide::STATUS_COMPLETED, 'guide_payload' => $this->hazirPayload(),
        ]);
        DB::table('discovery_guides')->where('id', $eski->id)
            ->update(['updated_at' => now()->subDays(DiscoveryGuideService::REUSE_DAYS + 10)]);

        // Taze ama 3 günlük — gün sayısı farklı, eşleşmemeli
        DiscoveryGuide::create([
            'destination_input' => 'Paris', 'duration_days' => 3, 'pace' => 'normal', 'budget' => 'standard',
            'status' => DiscoveryGuide::STATUS_COMPLETED, 'guide_payload' => $this->hazirPayload(),
        ]);

        // Taze, aynı parametreler ama ilgi alanı farklı — eşleşmemeli
        DiscoveryGuide::create([
            'destination_input' => 'Paris', 'duration_days' => 4, 'pace' => 'normal', 'budget' => 'standard',
            'interests' => ['gastronomy'],
            'status' => DiscoveryGuide::STATUS_COMPLETED, 'guide_payload' => $this->hazirPayload(),
        ]);

        $this->postJson(route('discovery.store'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('status', DiscoveryGuide::STATUS_PENDING);

        Queue::assertPushed(GenerateDiscoveryGuideJob::class, 1);
    }

    public function test_kisisellestirmede_hazir_icerik_kuyruksuz_tamamlanir(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson(route('discovery.store'), $this->payload())->assertCreated();
        $guide = DiscoveryGuide::firstOrFail();
        $this->simuleJobTamamlandi($guide);

        // "Çocuklarla" tercihi için hazır içerik cache'te
        $ai = app(DiscoveryGuideAiService::class);
        $hedef = (clone $guide)->fill(['traveler_type' => 'with_kids']);
        $ai->rememberPayload($ai->cacheKey($hedef), $this->hazirPayload());

        $this->actingAs($user)->postJson(route('discovery.personalize', $guide), ['traveler_type' => 'with_kids'])
            ->assertOk()
            ->assertJsonPath('status', DiscoveryGuide::STATUS_COMPLETED);

        $guide->refresh();
        $this->assertSame('with_kids', $guide->traveler_type);
        $this->assertTrue($guide->isCompleted());
        $this->assertNotNull($guide->guide_payload);

        Queue::assertPushed(GenerateDiscoveryGuideJob::class, 1); // yalnız create
    }

    /** Sonuç sayfasının render edebileceği asgari geçerli rehber payload'u. */
    private function hazirPayload(): array
    {
        $gunler = [];
        foreach (range(1, 4) as $gun) {
            $gunler[] = [
                'day' => $gun,
                'title' => 'Gün '.$gun,
                'theme' => 'Tema',
                'morning' => [['name' => 'Sabah durağı', 'description' => 'Açıklama']],
                'afternoon' => [['name' => 'Öğle durağı', 'description' => 'Açıklama']],
                'evening' => [['name' => 'Akşam durağı', 'description' => 'Açıklama']],
                'foods_to_try' => ['Kruvasan'],
                'daily_tip' => 'Erken çıkın.',
            ];
        }

        return [
            'destination' => ['name' => 'Paris', 'country' => 'Fransa', 'summary' => 'Işık şehri Paris.'],
            'assumptions' => ['traveler_type' => null, 'pace' => 'normal', 'budget' => 'standard', 'visit_type' => 'first_visit_general'],
            'unknown_destination' => false,
            'highlights' => [['name' => 'Eyfel Kulesi', 'category' => 'landmark', 'description' => 'Simge.', 'why_visit' => 'Manzara.']],
            'things_to_do' => [['name' => 'Seine turu', 'description' => 'Tekne gezisi.']],
            'historical_places' => [['name' => 'Notre-Dame', 'description' => 'Katedral.']],
            'museums' => [['name' => 'Louvre', 'description' => 'Müze.']],
            'local_foods' => [['name' => 'Croissant', 'description' => 'Hamur işi.', 'when_to_try' => 'Kahvaltı']],
            'daily_plan' => $gunler,
            'travel_tips' => ['Metro kartı alın.'],
            'related_destination_keywords' => ['Paris'],
        ];
    }

    /** Gerçek job'ın başarı yolunu taklit eder: status completed + kilit bırakılır. */
    private function simuleJobTamamlandi(DiscoveryGuide $guide): void
    {
        $guide->update(['status' => DiscoveryGuide::STATUS_COMPLETED]);
        Cache::forget(GenerateDiscoveryGuideJob::DISPATCH_LOCK_PREFIX.$guide->id);
    }

    public function test_ilgili_turlar_yalnizca_turxtur_veritabanindan_gelir(): void
    {
        $agency = Agency::create([
            'name' => 'Rehber Acenta',
            'slug' => 'rehber-acenta',
            'email' => 'rehber@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        Tour::create([
            'agency_id' => $agency->id, 'title' => 'Paris Rüyası Turu', 'destination' => 'Paris',
            'description' => 'Test', 'price' => 30000, 'currency' => 'TRY',
            'duration_days' => 4, 'is_active' => true,
        ]);
        Tour::create([
            'agency_id' => $agency->id, 'title' => 'Roma Gezisi', 'destination' => 'Roma',
            'description' => 'Test', 'price' => 25000, 'currency' => 'TRY',
            'duration_days' => 4, 'is_active' => true,
        ]);

        $user = User::factory()->create();
        $r = $this->actingAs($user)->postJson(route('discovery.store'), $this->payload())->assertCreated();
        $guide = DiscoveryGuide::firstOrFail();
        $guide->update([
            'status' => DiscoveryGuide::STATUS_COMPLETED,
            'guide_payload' => $this->tamamlanmisPayload(),
        ]);

        $sayfa = $this->actingAs($user)->get($r->json('redirect_url'))->assertOk();
        // Veritabanındaki eşleşen tur görünür; başka şehrin turu görünmez
        $sayfa->assertSee('Bu destinasyondaki turları keşfet');
        $sayfa->assertSee('Paris Rüyası Turu');
        $sayfa->assertDontSee('Roma Gezisi');
    }

    public function test_rate_limit_uygulanir(): void
    {
        // ai_search limiter: anonim 10/dk — 11. istek 429
        foreach (range(1, 10) as $i) {
            $this->postJson(route('discovery.store'), $this->payload())->assertCreated();
        }

        $this->postJson(route('discovery.store'), $this->payload())->assertStatus(429);
    }

    public function test_bayrak_kapaliyken_ucler_404(): void
    {
        config(['ai.discovery_enabled' => false]);

        $this->get('/kesif-rehberi')->assertNotFound();
        $this->postJson('/kesif-rehberi', $this->payload())->assertNotFound();
    }

    public function test_form_sayfasi_render_olur(): void
    {
        $this->get(route('discovery.index'))
            ->assertOk()
            ->assertSee('Keşif Rehberi')
            ->assertSee('Planı kişiselleştir')
            ->assertSee('Kaç gün?');
    }

    private function tamamlanmisPayload(): array
    {
        $gunler = [];
        foreach (range(1, 4) as $gun) {
            $gunler[] = [
                'day' => $gun,
                'title' => 'Tema '.$gun,
                'theme' => 'Gün teması '.$gun,
                'morning' => [['name' => 'Sabah durağı '.$gun, 'description' => 'Açıklama']],
                'afternoon' => [['name' => 'Öğle durağı '.$gun, 'description' => 'Açıklama']],
                'evening' => [],
                'foods_to_try' => ['Kruvasan'],
                'daily_tip' => 'Erken çık.',
            ];
        }

        return [
            'destination' => ['name' => 'Paris', 'country' => 'Fransa', 'summary' => 'Işık şehri.'],
            'assumptions' => ['traveler_type' => null, 'pace' => 'normal', 'budget' => 'standard', 'visit_type' => 'first_visit_general'],
            'unknown_destination' => false,
            'highlights' => [['name' => 'Eyfel Kulesi', 'description' => 'Simge yapı.']],
            'things_to_do' => [['name' => 'Seine Nehri gezisi', 'description' => 'Tekneyle şehir turu.']],
            'historical_places' => [],
            'museums' => [],
            'local_foods' => [],
            'daily_plan' => $gunler,
            'travel_tips' => ['Metro kartı alın.'],
            'related_destination_keywords' => ['Paris', 'Fransa'],
        ];
    }
}
