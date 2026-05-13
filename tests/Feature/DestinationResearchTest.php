<?php

namespace Tests\Feature;

use App\Jobs\ResearchDestinationJob;
use App\Models\Agency;
use App\Models\Destination;
use App\Models\Tour;
use App\Services\DestinationResearcher;
use App\Support\CategoryLicensing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\TestCase;

class DestinationResearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // RefreshDatabase migration snapshot eski olabilir → eksik kolonları runtime'da ekle
        if (!Schema::hasColumn('destinations', 'ai_research')) {
            Schema::table('destinations', function ($t) {
                $t->json('ai_research')->nullable();
                $t->timestamp('ai_research_updated_at')->nullable();
                $t->string('ai_research_status')->nullable();
            });
        }
        CategoryLicensing::resetCache();
    }

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } catch (\Throwable $e) {
            // ignore mockery errors so transaction can roll back cleanly
        }
        parent::tearDown();
    }

    public function test_tour_observer_creates_destination_and_dispatches_research_job(): void
    {
        Bus::fake();

        $agency = Agency::create([
            'name' => 'Dubai Acentası',
            'email' => 'dubai@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'legacy_category_access' => true,
        ]);

        // Observer'ı aktif tut — sadece embedding job'unu engellemek için manuel olarak
        // destination ve ResearchDestinationJob'ı doğruluyoruz.
        Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Dubai 5 Gece',
            'slug' => 'dubai-5-gece',
            'destination' => 'Dubai',
            'description' => 'Lüks + çöl macera',
            'price' => 35000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => now()->addDays(20)->toDateString(),
            'is_active' => true,
            'is_international' => true,
            'requires_visa' => false,
        ]);

        $this->assertDatabaseHas('destinations', ['name' => 'Dubai', 'slug' => 'dubai']);

        Bus::assertDispatched(ResearchDestinationJob::class, function ($job) {
            $dest = Destination::where('slug', 'dubai')->first();
            return $job->destinationId === $dest->id;
        });
    }

    public function test_fresh_research_is_not_redispatched_on_tour_update(): void
    {
        $agency = Agency::create([
            'name' => 'Acenta',
            'email' => 'a@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'legacy_category_access' => true,
        ]);

        Destination::create([
            'name' => 'Kapadokya',
            'slug' => 'kapadokya',
            'is_active' => true,
            'ai_research' => ['summary' => 'Balonlar ve peribacaları.'],
            'ai_research_updated_at' => now()->subDays(5),
            'ai_research_status' => 'ok',
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Kapadokya 3 Gece',
            'slug' => 'kapadokya-3-gece',
            'destination' => 'Kapadokya',
            'price' => 12000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => now()->addDays(15)->toDateString(),
            'is_active' => true,
        ]);

        Bus::fake();

        // Destinasyon DEĞİŞMEDEN diğer alan güncellemesi → research job tetiklenmemeli
        $tour->update(['price' => 13000]);

        Bus::assertNotDispatched(ResearchDestinationJob::class);
    }

    public function test_destination_change_triggers_research_for_new_destination(): void
    {
        $agency = Agency::create([
            'name' => 'Acenta',
            'email' => 'a@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'legacy_category_access' => true,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Tur',
            'slug' => 'tur',
            'destination' => 'Antalya',
            'price' => 8000,
            'currency' => 'TRY',
            'duration_days' => 4,
            'departure_date' => now()->addDays(10)->toDateString(),
            'is_active' => true,
        ]);

        Bus::fake();
        $tour->update(['destination' => 'Bodrum']);

        $this->assertDatabaseHas('destinations', ['slug' => 'bodrum']);
        Bus::assertDispatched(ResearchDestinationJob::class, function ($job) {
            $dest = Destination::where('slug', 'bodrum')->first();
            return $job->destinationId === $dest->id;
        });
    }

    public function test_research_job_skips_when_data_is_fresh(): void
    {
        $destination = Destination::create([
            'name' => 'Roma',
            'slug' => 'roma',
            'is_active' => true,
            'ai_research' => ['summary' => 'Önceden var olan içerik.'],
            'ai_research_updated_at' => now()->subDays(10),
            'ai_research_status' => 'ok',
        ]);

        $mock = Mockery::mock(DestinationResearcher::class);
        $mock->shouldNotReceive('research');

        $job = new ResearchDestinationJob($destination->id);
        $job->handle($mock);

        $destination->refresh();
        $this->assertSame('ok', $destination->ai_research_status);
        // İçerik değişmemiş olmalı
        $this->assertSame('Önceden var olan içerik.', $destination->ai_research['summary']);
    }

    public function test_research_job_calls_researcher_when_data_is_stale_and_persists_result(): void
    {
        $destination = Destination::create([
            'name' => 'Bali',
            'slug' => 'bali',
            'is_active' => true,
            'ai_research' => null,
            'ai_research_updated_at' => null,
        ]);

        $payload = [
            'summary' => 'Tropikal cennet.',
            'best_seasons' => ['nisan-ekim'],
            'must_see' => ['Ubud', 'Uluwatu Tapınağı'],
            'avoid' => ['yağmur sezonu'],
            'visa_notes' => 'TC pasaport varış vizesi',
            'vibe' => 'rahat tropikal',
            'price_anchor_try' => '40-60 bin TL',
            'audience_fit' => ['couple' => 'çok uygun'],
            'safety_notes' => null,
            'sources' => ['example.com'],
        ];

        $mock = Mockery::mock(DestinationResearcher::class);
        $mock->shouldReceive('research')
            ->once()
            ->with('Bali', Mockery::any())
            ->andReturn($payload);

        $job = new ResearchDestinationJob($destination->id);
        $job->handle($mock);

        $destination->refresh();
        $this->assertSame('ok', $destination->ai_research_status);
        $this->assertSame('Tropikal cennet.', $destination->ai_research['summary']);
        $this->assertSame(['Ubud', 'Uluwatu Tapınağı'], $destination->ai_research['must_see']);
        $this->assertNotNull($destination->ai_research_updated_at);
    }

    public function test_research_job_marks_failed_when_researcher_returns_null(): void
    {
        $destination = Destination::create([
            'name' => 'Lima',
            'slug' => 'lima',
            'is_active' => true,
        ]);

        $mock = Mockery::mock(DestinationResearcher::class);
        $mock->shouldReceive('research')->once()->andReturn(null);

        $job = new ResearchDestinationJob($destination->id);
        $job->handle($mock);

        $destination->refresh();
        $this->assertSame('failed', $destination->ai_research_status);
        $this->assertNull($destination->ai_research);
    }
}
