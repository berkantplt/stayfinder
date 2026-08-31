<?php

namespace Tests\Feature;

use App\Services\AiSearch\TourSearchService;
use App\Jobs\GenerateTourCharacterJob;
use App\Jobs\GenerateTourEmbeddingJob;
use App\Models\Agency;
use App\Models\Tour;
use App\Services\AiSearch\DestinationKnowledgeService;
use App\Services\AiSearch\DestinationProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse as ChatResponse;
use Tests\TestCase;

/**
 * Destinasyon+tur bilgisi katmanları: envanter ("nerelere turunuz var"),
 * şehir eşleştirme ve tur karakteri üretimi. Bu katmanlar TAMAMEN LLM'siz
 * çalışır; envanter listesi arama yorumu promptuna da girer.
 *
 * Not: sohbet v1 kaldırıldığında bu dosyadaki sohbet uçlu cevap testleri ve
 * v1 servisinin private metotlarını yoklayan testler silindi.
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

        (new GenerateTourCharacterJob($tour->id))->handle(app(DestinationProfileService::class));

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

        [$systemPrompt] = app(TourSearchService::class)
            ->buildCommentPromptParts('kapadokya turu', Tour::whereKey($tour->id)->get(), '');

        $this->assertStringContainsString('SİTEDEKİ DESTİNASYONLAR', $systemPrompt);
        $this->assertStringContainsString('Kapadokya', $systemPrompt);
        $this->assertStringContainsString('| KARAKTER: Sakin, doğa ağırlıklı bir keşif turu.', $systemPrompt);
        $this->assertStringContainsString('listede olmayan bir şehir/ülke için turumuz olduğunu ima etme', $systemPrompt);
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
        (new GenerateTourCharacterJob($tour->id))->handle(app(DestinationProfileService::class));

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
