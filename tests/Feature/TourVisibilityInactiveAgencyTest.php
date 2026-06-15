<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TourVisibilityInactiveAgencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();
    }

    private function makeAgency(bool $isActive, bool $legacy): Agency
    {
        return Agency::create([
            'name' => 'Görünürlük Acenta',
            'slug' => 'gorunurluk-acenta-'.($isActive ? 'aktif' : 'pasif').($legacy ? '-legacy' : '-lisans'),
            'email' => 'gorunurluk@example.com',
            'is_active' => $isActive,
            'legacy_category_access' => $legacy,
        ]);
    }

    private function makeTour(Agency $agency, ?int $categoryId = null): Tour
    {
        return Tour::create([
            'agency_id' => $agency->id,
            'category_id' => $categoryId,
            'title' => 'Görünürlük Test Turu',
            'destination' => 'Fethiye',
            'description' => 'Pasif acenta görünürlük testi.',
            'price' => 6000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(20),
            'return_date' => today()->addDays(25),
            'is_active' => true,
        ]);
    }

    public function test_inactive_legacy_agency_tour_is_hidden_from_active_scope_and_listing(): void
    {
        $agency = $this->makeAgency(isActive: false, legacy: true);
        $tour = $this->makeTour($agency);

        $this->assertFalse(Tour::active()->whereKey($tour->id)->exists());
        $this->assertFalse($tour->fresh()->isPubliclyVisible());

        $this->get(route('tours.index'))
            ->assertOk()
            ->assertDontSee('Görünürlük Test Turu');
    }

    public function test_inactive_licensed_agency_tour_is_hidden_even_with_valid_subscription(): void
    {
        $category = Category::create([
            'name' => 'Yat Turları',
            'slug' => 'yat-turlari',
            'monthly_price' => 2000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $agency = $this->makeAgency(isActive: false, legacy: false);

        AgencyCategorySubscription::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'monthly_price' => 2000,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today(),
            'expires_at' => today()->addMonth(),
        ]);

        $tour = $this->makeTour($agency, $category->id);

        // Geçerli abonelik bile olsa pasif acentanın turu görünmez
        $this->assertFalse(Tour::active()->whereKey($tour->id)->exists());
        $this->assertFalse($tour->fresh()->isPubliclyVisible());
    }

    public function test_reactivating_agency_restores_tour_visibility(): void
    {
        $agency = $this->makeAgency(isActive: false, legacy: true);
        $tour = $this->makeTour($agency);

        $this->assertFalse(Tour::active()->whereKey($tour->id)->exists());

        $agency->update(['is_active' => true]);

        $this->assertTrue(Tour::active()->whereKey($tour->id)->exists());
        $this->assertTrue($tour->fresh()->isPubliclyVisible());
    }

    public function test_scope_and_is_publicly_visible_agree_for_active_agency(): void
    {
        $agency = $this->makeAgency(isActive: true, legacy: true);
        $tour = $this->makeTour($agency);

        $this->assertSame(
            $tour->fresh()->isPubliclyVisible(),
            Tour::active()->whereKey($tour->id)->exists()
        );
    }
}
