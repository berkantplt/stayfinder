<?php

namespace Tests\Feature;

use App\Jobs\GenerateDestinationProfileJob;
use App\Models\DestinationProfile;
use App\Services\AiSearch\DestinationProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DestinationProfileServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_known_city_returns_db_profile(): void
    {
        DestinationProfile::create([
            'city' => 'Antalya',
            'normalized_city' => 'antalya',
            'crowd_score' => 0.74,
            'liveliness_score' => 0.86,
            'source' => DestinationProfile::SOURCE_MANUAL,
            'generated_at' => now(),
        ]);

        Queue::fake();

        $result = app(DestinationProfileService::class)->get('Antalya');

        $this->assertEqualsWithDelta(0.74, $result['crowd'], 0.01);
        $this->assertEqualsWithDelta(0.86, $result['lively'], 0.01);
        $this->assertSame(DestinationProfile::SOURCE_MANUAL, $result['source']);
        Queue::assertNotPushed(GenerateDestinationProfileJob::class);
    }

    public function test_unknown_city_returns_default_and_dispatches_enrichment_job(): void
    {
        Queue::fake();

        $result = app(DestinationProfileService::class)->get('Reykjavik');

        $this->assertEqualsWithDelta(0.50, $result['crowd'], 0.01);
        $this->assertEqualsWithDelta(0.50, $result['lively'], 0.01);
        $this->assertSame(DestinationProfile::SOURCE_DEFAULT, $result['source']);

        Queue::assertPushed(GenerateDestinationProfileJob::class, function ($job) {
            return $job->city === 'Reykjavik';
        });

        // Placeholder DB kaydı oluşturuldu mu (sonraki çağrılar tekrar dispatch etmesin)
        $this->assertDatabaseHas('destination_profiles', [
            'normalized_city' => 'reykjavik',
            'source' => DestinationProfile::SOURCE_DEFAULT,
        ]);
    }

    public function test_repeated_request_for_unknown_city_does_not_dispatch_twice(): void
    {
        Queue::fake();

        $service = app(DestinationProfileService::class);

        $service->get('Tashkent');
        $service->get('Tashkent');
        $service->get('Tashkent');

        Queue::assertPushed(GenerateDestinationProfileJob::class, 1);
    }

    public function test_multi_city_destination_string_matches_substring(): void
    {
        DestinationProfile::create([
            'city' => 'Paris',
            'normalized_city' => 'paris',
            'crowd_score' => 0.86,
            'liveliness_score' => 0.88,
            'source' => DestinationProfile::SOURCE_MANUAL,
            'generated_at' => now(),
        ]);

        Queue::fake();

        // Tour destination "Paris, Amsterdam, Brüksel" — Paris match etmeli
        $result = app(DestinationProfileService::class)->get('Paris, Amsterdam, Brüksel');

        $this->assertEqualsWithDelta(0.86, $result['crowd'], 0.01);
        Queue::assertNotPushed(GenerateDestinationProfileJob::class);
    }

    public function test_normalized_lookup_handles_turkish_chars(): void
    {
        DestinationProfile::create([
            'city' => 'İstanbul',
            'normalized_city' => 'istanbul',
            'crowd_score' => 0.98,
            'liveliness_score' => 0.90,
            'source' => DestinationProfile::SOURCE_MANUAL,
            'generated_at' => now(),
        ]);

        Queue::fake();

        // İ ile başlayan, üst-küçük karışık varyant
        $result = app(DestinationProfileService::class)->get('İSTANBUL');
        $this->assertEqualsWithDelta(0.98, $result['crowd'], 0.01);

        $result2 = app(DestinationProfileService::class)->get('istanbul');
        $this->assertEqualsWithDelta(0.98, $result2['crowd'], 0.01);
    }
}
