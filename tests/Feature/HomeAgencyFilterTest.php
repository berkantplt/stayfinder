<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HomeAgencyFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
    }

    public function test_homepage_renders_agency_search_input(): void
    {
        Agency::create([
            'name' => 'Jolly Tur',
            'slug' => 'jolly-' . uniqid(),
            'email' => 'jolly@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
        Agency::create([
            'name' => 'ETS Tur',
            'slug' => 'ets-' . uniqid(),
            'email' => 'ets@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $response = $this->get(route('home'))->assertOk();

        // Input + datalist render edilmiş olmalı
        $response->assertSee('name="agency"', false);
        $response->assertSee('id="agency-options"', false);
        $response->assertSee('Jolly Tur');
        $response->assertSee('ETS Tur');
    }

    public function test_agency_filter_returns_only_matching_tours(): void
    {
        $jolly = $this->makeAgency('Jolly Tur');
        $ets = $this->makeAgency('ETS Tur');

        $jollyTour = $this->makeTour($jolly, 'Jolly Antalya Turu');
        $etsTour = $this->makeTour($ets, 'ETS Bodrum Turu');

        // AJAX request — popularTours döner
        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('home', ['agency' => 'Jolly']))
            ->assertOk();

        $response->assertSee('Jolly Antalya Turu');
        $response->assertDontSee('ETS Bodrum Turu');
    }

    public function test_partial_agency_match_works(): void
    {
        $coral = $this->makeAgency('Coral Travel');
        $this->makeTour($coral, 'Coral Mısır Turu');

        $other = $this->makeAgency('Tatil Sepeti');
        $this->makeTour($other, 'Tatil Sepeti Yunan');

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('home', ['agency' => 'cora']))
            ->assertOk();

        $response->assertSee('Coral Mısır Turu');
        $response->assertDontSee('Tatil Sepeti Yunan');
    }

    public function test_empty_agency_filter_does_not_restrict_tours(): void
    {
        $a1 = $this->makeAgency('A1');
        $a2 = $this->makeAgency('A2');
        $this->makeTour($a1, 'T1');
        $this->makeTour($a2, 'T2');

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('home', ['agency' => '']))
            ->assertOk();

        $response->assertSee('T1');
        $response->assertSee('T2');
    }

    public function test_agency_filter_combined_with_destination(): void
    {
        $jolly = $this->makeAgency('Jolly Tur');
        $this->makeTour($jolly, 'Jolly Antalya', destination: 'Antalya');
        $this->makeTour($jolly, 'Jolly Bodrum', destination: 'Bodrum');

        $response = $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route('home', ['agency' => 'Jolly', 'destination' => 'Antalya']))
            ->assertOk();

        $response->assertSee('Jolly Antalya');
        $response->assertDontSee('Jolly Bodrum');
    }

    private function makeAgency(string $name): Agency
    {
        return Agency::create([
            'name' => $name,
            'slug' => strtolower(str_replace(' ', '-', $name)) . '-' . uniqid(),
            'email' => uniqid() . '@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    private function makeTour(Agency $agency, string $title, string $destination = 'Test'): Tour
    {
        return Tour::create([
            'agency_id' => $agency->id,
            'title' => $title,
            'destination' => $destination,
            'description' => 'd',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(15),
            'is_active' => true,
        ]);
    }
}
