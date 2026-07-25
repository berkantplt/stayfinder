<?php

namespace Tests\Feature;

use App\Http\Controllers\AiSearchController;
use App\Jobs\GenerateTourCharacterJob;
use App\Jobs\GenerateTourEmbeddingJob;
use App\Models\Agency;
use App\Models\DestinationProfile;
use App\Models\Tour;
use App\Services\AiSearch\DestinationKnowledgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use Tests\TestCase;

/**
 * Destinasyon+tur bilgisi katmanları: envanter ("nerelere turunuz var"),
 * şehir karakteri ("İstanbul nasıl bir şehir") ve tur karakteri üretimi.
 * Sohbet cevapları TAMAMEN LLM'siz — OpenAI::fake([]) boşken 200 dönmesi kanıt.
 */
class AiDestinationKnowledgeTest extends TestCase
{
    use RefreshDatabase;

    private function makeAgency(bool $active = true): Agency
    {
        return Agency::create([
            'name' => 'Dest Acenta '.uniqid(),
            'slug' => 'dest-acenta-'.uniqid(),
            'email' => uniqid().'@example.com',
            'is_active' => $active,
            'legacy_category_access' => true,
        ]);
    }

    private function makeTour(Agency $agency, string $destination, int $departureInDays = 30, bool $active = true): Tour
    {
        return Tour::create([
            'agency_id' => $agency->id,
            'title' => $destination.' Turu '.uniqid(),
            'destination' => $destination,
            'description' => 'Test',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays($departureInDays),
            'is_active' => $active,
        ]);
    }

    public function test_envanter_cok_sehirli_destinasyonu_boler_gecmis_ve_gorunmez_turu_saymaz(): void
    {
        Queue::fake();
        $agency = $this->makeAgency();

        $this->makeTour($agency, 'Paris, Roma');
        $this->makeTour($agency, 'Paris');
        $this->makeTour($agency, 'Bodrum', -10); // geçmiş kalkış → envantere girmez
        $this->makeTour($this->makeAgency(active: false), 'İzmir'); // pasif acenta → görünmez

        DestinationKnowledgeService::flushInventory();
        $inventory = app(DestinationKnowledgeService::class)->inventory();

        $this->assertSame(2, $inventory['paris']['count']);
        $this->assertSame(1, $inventory['roma']['count']);
        $this->assertArrayNotHasKey('bodrum', $inventory);
        $this->assertArrayNotHasKey('izmir', $inventory);
    }

    public function test_nerelere_turunuz_var_llmsiz_deterministik_listeyle_cevaplanir(): void
    {
        OpenAI::fake([]);
        Queue::fake();
        $agency = $this->makeAgency();
        $this->makeTour($agency, 'Paris, Roma');
        $this->makeTour($agency, 'Paris');
        DestinationKnowledgeService::flushInventory();

        $r = $this->postJson(route('ai.search.message'), ['message' => 'nerelere turunuz var?'])
            ->assertOk();

        $content = $r->json('assistant_message.content');
        $this->assertStringContainsString('Paris (2 tur)', $content);
        $this->assertStringContainsString('Roma (1 tur)', $content);
        $this->assertStringContainsString('2 destinasyonda toplam 2 aktif', $content);
    }

    public function test_sehir_karakter_sorusu_profil_verisinden_llmsiz_cevaplanir(): void
    {
        OpenAI::fake([]);
        Queue::fake();
        // Profil turdan ÖNCE: TourObserver bilinmeyen şehre placeholder yazar,
        // sonradan create unique çakışması yaratır
        DestinationProfile::create([
            'city' => 'İstanbul',
            'normalized_city' => DestinationProfile::normalize('İstanbul'),
            'crowd_score' => 0.98,
            'liveliness_score' => 0.90,
            'source' => DestinationProfile::SOURCE_LLM,
            'enrichment_version' => DestinationProfile::CURRENT_ENRICHMENT_VERSION,
            'summary' => 'İki kıtayı birleştiren, tarih ve kültür dolu bir metropol.',
            'vibe_tags' => ['nightlife', 'cultural', 'historical'],
            'best_months' => [5, 9],
            'requires_visa_for_tr' => false,
        ]);
        $this->makeTour($this->makeAgency(), 'İstanbul');
        DestinationKnowledgeService::flushInventory();

        $r = $this->postJson(route('ai.search.message'), ['message' => 'İstanbul nasıl bir şehir? kalabalık mı?'])
            ->assertOk();

        $content = $r->json('assistant_message.content');
        // Karakter niteleyicileri gerçek profil verisinden (mb-büyük harfle başlar)
        $this->assertStringContainsString('Çok kalabalık', $content);
        $this->assertStringContainsString('gece hayatı', $content);
        $this->assertStringContainsString('İki kıtayı birleştiren', $content);
        $this->assertStringContainsString('Mayıs, Eylül', $content);
        // Envanter kesişimi: gerçekten kaç turumuz olduğu söylenir
        $this->assertStringContainsString('1 aktif turumuz var', $content);
    }

    public function test_profili_hazir_olmayan_sehirde_uydurma_niteleyici_yazilmaz(): void
    {
        OpenAI::fake([]);
        Queue::fake();
        DestinationProfile::create([
            'city' => 'Bolu',
            'normalized_city' => 'bolu',
            'crowd_score' => 0.50,
            'liveliness_score' => 0.50,
            'source' => DestinationProfile::SOURCE_DEFAULT,
            'enrichment_version' => 1,
        ]);
        DestinationKnowledgeService::flushInventory();

        $r = $this->postJson(route('ai.search.message'), ['message' => 'Bolu nasıl bir şehir?'])
            ->assertOk();

        $content = $r->json('assistant_message.content');
        $this->assertStringContainsString('henüz hazır değil', $content);
        $this->assertStringNotContainsString('kalabalık', $content);
        // Envanterde tur yok → dürüstçe söylenir
        $this->assertStringContainsString('aktif turumuz yok', $content);
    }

    public function test_yeni_tur_karakter_jobu_kuyruga_alinir(): void
    {
        Queue::fake();
        $tour = $this->makeTour($this->makeAgency(), 'Kapadokya');

        Queue::assertPushed(GenerateTourCharacterJob::class, fn ($job) => $job->tourId === $tour->id);
        Queue::assertPushed(GenerateTourEmbeddingJob::class);
    }

    public function test_tur_karakter_jobu_sonucu_yazar_ve_observer_dongusu_yaratmaz(): void
    {
        Queue::fake();
        $tour = $this->makeTour($this->makeAgency(), 'Kapadokya');

        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode([
                'pace' => 0.25,
                'character_summary' => 'Sakin vadilerden geçen, düşük tempolu bir dinlenme turu.',
            ])]]]]),
        ]);

        (new GenerateTourCharacterJob($tour->id))->handle();

        $tour->refresh();
        $this->assertSame('Sakin vadilerden geçen, düşük tempolu bir dinlenme turu.', $tour->character_summary);
        $this->assertSame(0.25, (float) $tour->pace_score);
        $this->assertSame(Tour::CURRENT_CHARACTER_VERSION, $tour->character_version);

        // Query-builder update model event tetiklemez → observer job'ı yeniden
        // dispatch etmedi (yalnız create anındaki 1 dispatch var)
        Queue::assertPushed(GenerateTourCharacterJob::class, 1);
    }

    public function test_yorum_promptuna_envanter_listesi_ve_tur_karakteri_girer(): void
    {
        Queue::fake();
        $agency = $this->makeAgency();
        $tour = $this->makeTour($agency, 'Kapadokya');
        Tour::whereKey($tour->id)->update([
            'character_summary' => 'Sakin, doğa ağırlıklı bir keşif turu.',
            'character_version' => Tour::CURRENT_CHARACTER_VERSION,
        ]);
        DestinationKnowledgeService::flushInventory();

        $controller = app(AiSearchController::class);
        [$systemPrompt] = (new \ReflectionMethod($controller, 'buildCommentPromptParts'))
            ->invoke($controller, 'kapadokya turu', Tour::whereKey($tour->id)->get(), '', null);

        $this->assertStringContainsString('SİTEDEKİ DESTİNASYONLAR', $systemPrompt);
        $this->assertStringContainsString('Kapadokya', $systemPrompt);
        $this->assertStringContainsString('| KARAKTER: Sakin, doğa ağırlıklı bir keşif turu.', $systemPrompt);
        $this->assertStringContainsString('listede olmayan bir şehir/ülke için turumuz olduğunu ima etme', $systemPrompt);
    }

    public function test_arama_mesaji_destinasyon_sorusu_sanilmaz(): void
    {
        // Arama/öneri/tur mesajları Phase 0d'ye TAKILMAMALI — inceleme
        // bulgusundaki gerçek repro mesajları burada kilitli.
        Queue::fake();
        $agency = $this->makeAgency();
        $this->makeTour($agency, 'İstanbul');
        $this->makeTour($agency, 'Kapadokya');
        DestinationKnowledgeService::flushInventory();

        $service = app(\App\Services\AiSearch\ConversationService::class);

        $wantsList = new \ReflectionMethod($service, 'wantsDestinationList');
        $this->assertFalse($wantsList->invoke($service, 'İstanbul turu var mı?'));
        $this->assertFalse($wantsList->invoke($service, '20 bin bütçeyle eylülde deniz tatili'));
        // Kriterli öneri istekleri envanter dökümü DEĞİL aramadır (intent'e işlenmeli)
        $this->assertFalse($wantsList->invoke($service, 'Eylülde 30 bin bütçeyle nereye tur önerirsin?'));
        $this->assertFalse($wantsList->invoke($service, 'Balayı için nereye gidebiliriz?'));
        $this->assertFalse($wantsList->invoke($service, 'Çocuklu aile için nerelere tur var?'));
        $this->assertTrue($wantsList->invoke($service, 'nerelere turunuz var acaba'));
        $this->assertTrue($wantsList->invoke($service, 'Hangi şehirlere gidiyorsunuz?'));

        $detect = new \ReflectionMethod($service, 'detectDestinationQuestion');
        $this->assertNull($detect->invoke($service, 'İstanbul turu var mı?'));
        $this->assertNull($detect->invoke($service, 'vizesiz tur istiyorum'));
        // "tur" kelimesi geçen mesajlar TUR akışına gider, şehir cevabına değil
        $this->assertNull($detect->invoke($service, "İstanbul'da gece hayatı olan tur öner"));
        $this->assertNull($detect->invoke($service, 'İstanbul turu hakkında bilgi verir misin'));
        $this->assertNull($detect->invoke($service, 'Kapadokya turunu anlatır mısın'));
        // Saf şehir sorusu yakalanmaya devam eder
        $this->assertNotNull($detect->invoke($service, 'İstanbul nasıl bir şehir?'));
    }

    public function test_sehir_eslesmesi_baska_kelimenin_on_ekine_takilmaz(): void
    {
        Queue::fake();
        $agency = $this->makeAgency();
        $this->makeTour($agency, 'Roma');
        $this->makeTour($agency, 'Van');
        DestinationKnowledgeService::flushInventory();

        $service = app(DestinationKnowledgeService::class);

        // Serbest ek toleransı tuzağı: Romanya ≠ Roma, Vancouver ≠ Van
        $this->assertNull($service->findCityInMessage('Romanya nasıl bir yer?'));
        $this->assertNull($service->findCityInMessage('Romantik bir tatil düşünüyorum nasıl olur'));
        $this->assertNull($service->findCityInMessage('Vancouver nasıl bir şehir?'));

        // Türkçe ekler çalışmaya devam eder
        $this->assertSame('Roma', $service->findCityInMessage("Roma'ya ne zaman gidilir?")['city']);
        $this->assertSame('Roma', $service->findCityInMessage('Romada hava nasıl olur')['city']);
        $this->assertSame('Van', $service->findCityInMessage('Van nasıl bir şehir?')['city']);
    }

    public function test_tarihsiz_tur_envanterde_sayilir(): void
    {
        Queue::fake();
        $agency = $this->makeAgency();
        // Tarihsiz (departure_date null, tarih listesi boş) tur satılabilirdir —
        // "turumuz yok" yalanı söylenmemeli
        Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Her Gün Kalkışlı Şehir Turu',
            'destination' => 'Mardin',
            'description' => 'Test',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 1,
            'departure_date' => null,
            'is_active' => true,
        ]);
        DestinationKnowledgeService::flushInventory();

        $inventory = app(DestinationKnowledgeService::class)->inventory();
        $this->assertSame(1, $inventory['mardin']['count']);
    }

    public function test_karakter_kilidi_job_bitince_birakilir(): void
    {
        Queue::fake();
        $tour = $this->makeTour($this->makeAgency(), 'Kapadokya');

        OpenAI::fake([
            ChatResponse::fake(['choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => json_encode([
                'pace' => 0.5, 'character_summary' => 'Orta tempolu bir keşif turu.',
            ])]]]]),
        ]);
        (new GenerateTourCharacterJob($tour->id))->handle();

        // Job bitti → kilit bırakıldı → sonraki içerik düzenlemesi yeniden
        // üretimi tetikleyebilir (600 sn bayat-kalma tuzağı kapalı)
        $tour->refresh();
        $tour->update(['description' => 'Program tamamen yenilendi']);

        Queue::assertPushed(GenerateTourCharacterJob::class, 2);
    }

    public function test_tarih_degisikligi_envanter_cachini_dusurur(): void
    {
        Queue::fake();
        $agency = $this->makeAgency();
        $tour = $this->makeTour($agency, 'Antalya', -5); // geçmiş kalkış → envanterde yok
        DestinationKnowledgeService::flushInventory();
        $this->assertArrayNotHasKey('antalya', app(DestinationKnowledgeService::class)->inventory());

        // Gelecek tarih eklenince cache düşer → tur envantere girer
        \App\Models\TourDate::create([
            'tour_id' => $tour->id,
            'departure_date' => today()->addDays(20),
            'return_date' => today()->addDays(25),
        ]);

        $this->assertSame(1, app(DestinationKnowledgeService::class)->inventory()['antalya']['count']);
    }
}
