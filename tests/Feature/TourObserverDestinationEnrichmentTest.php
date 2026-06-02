<?php

namespace Tests\Feature;

use App\Jobs\GenerateDestinationProfileJob;
use App\Models\Agency;
use App\Models\DestinationProfile;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TourObserverDestinationEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_tour_dispatches_destination_enrichment_for_unknown_city(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = $this->makeAgency();

        Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Reykjavik Aurora Turu',
            'destination' => 'Reykjavik',
            'description' => 'Kuzey ışıkları',
            'price' => 30000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(30),
            'return_date' => today()->addDays(34),
            'is_active' => true,
        ]);

        Queue::assertPushed(GenerateDestinationProfileJob::class, function ($job) {
            return $job->city === 'Reykjavik';
        });

        $this->assertDatabaseHas('destination_profiles', [
            'normalized_city' => 'reykjavik',
            'source' => DestinationProfile::SOURCE_DEFAULT,
        ]);
    }

    public function test_known_fresh_destination_does_not_redispatch(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = $this->makeAgency();

        DestinationProfile::create([
            'city' => 'Dubai',
            'normalized_city' => 'dubai',
            'crowd_score' => 0.85,
            'liveliness_score' => 0.92,
            'source' => DestinationProfile::SOURCE_LLM,
            'enrichment_version' => DestinationProfile::CURRENT_ENRICHMENT_VERSION,
            'generated_at' => now(),
        ]);

        Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Dubai 5 Gün',
            'destination' => 'Dubai',
            'description' => 'Çöl + alışveriş',
            'price' => 40000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(60),
            'return_date' => today()->addDays(64),
            'is_active' => true,
        ]);

        Queue::assertNotPushed(GenerateDestinationProfileJob::class);
    }

    public function test_stale_destination_profile_triggers_redispatch(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = $this->makeAgency();

        DestinationProfile::create([
            'city' => 'Paris',
            'normalized_city' => 'paris',
            'crowd_score' => 0.86,
            'liveliness_score' => 0.88,
            'source' => DestinationProfile::SOURCE_MANUAL,
            'enrichment_version' => 1,
            'generated_at' => now()->subYear(),
        ]);

        Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Paris Hafta Sonu',
            'destination' => 'Paris',
            'description' => 'Romantik',
            'price' => 25000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(45),
            'return_date' => today()->addDays(47),
            'is_active' => true,
        ]);

        Queue::assertPushed(GenerateDestinationProfileJob::class, function ($job) {
            return $job->city === 'Paris';
        });
    }

    public function test_multi_city_destination_dispatches_for_each_part(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = $this->makeAgency();

        Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Avrupa Başkentleri',
            'destination' => 'Prag, Viyana, Budapeşte',
            'description' => 'Üçlü tur',
            'price' => 50000,
            'currency' => 'TRY',
            'duration_days' => 7,
            'departure_date' => today()->addDays(60),
            'return_date' => today()->addDays(67),
            'is_active' => true,
        ]);

        Queue::assertPushed(GenerateDestinationProfileJob::class, 3);

        $this->assertDatabaseHas('destination_profiles', ['normalized_city' => 'prag']);
        $this->assertDatabaseHas('destination_profiles', ['normalized_city' => 'viyana']);
        $this->assertDatabaseHas('destination_profiles', ['normalized_city' => 'budapeste']);
    }

    private function makeAgency(): Agency
    {
        return Agency::create([
            'name' => 'Test Acenta',
            'slug' => 'test-acenta-' . uniqid(),
            'email' => 'test-' . uniqid() . '@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }
}
