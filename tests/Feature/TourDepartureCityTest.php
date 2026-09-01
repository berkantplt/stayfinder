<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourDepartureCityTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private Agency $agency;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::create([
            'name' => 'Şehir Acenta',
            'slug' => 'sehir-acenta',
            'email' => 'sehir@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        $this->agencyUser = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $this->agency->id,
        ]);

        $this->category = Category::create([
            'name' => 'Kültür Turları',
            'slug' => 'kultur-turlari',
            'is_active' => true,
        ]);
    }

    private function tourPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title' => 'Ankara Çıkışlı GAP Turu',
            'destination' => 'Şanlıurfa',
            'departure_city' => 'Ankara',
            'stop_cities' => ['Kırıkkale', 'Kayseri'],
            'duration_days' => 3,
            'currency' => 'TRY',
            'requires_visa' => '0',
            'pricing_options' => [
                ['price' => '7500', 'departure_dates' => [today()->addDays(20)->toDateString()]],
            ],
        ], $overrides);
    }

    public function test_departure_city_is_required(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['departure_city' => '']))
            ->assertSessionHasErrors('departure_city');
    }

    public function test_invalid_departure_city_is_rejected(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['departure_city' => 'Paris']))
            ->assertSessionHasErrors('departure_city');
    }

    public function test_store_saves_departure_and_stop_cities(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload())
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Ankara Çıkışlı GAP Turu');
        $this->assertSame('Ankara', $tour->departure_city);
        $this->assertSame(['Kırıkkale', 'Kayseri'], $tour->stop_cities);
    }

    public function test_departure_city_is_removed_from_stop_cities(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload([
                'stop_cities' => ['Ankara', 'Kayseri'], // Ankara = kalkış → durakta olmamalı
            ]))
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Ankara Çıkışlı GAP Turu');
        $this->assertSame(['Kayseri'], $tour->stop_cities);
    }

    public function test_customer_filter_matches_departure_or_stop_city(): void
    {
        // Ankara kalkışlı, durağı Kayseri olan tur
        $gapTour = $this->createTour('GAP Turu', 'Ankara', ['Kayseri']);
        // İstanbul kalkışlı, durağı yok
        $egeTour = $this->createTour('Ege Turu', 'İstanbul', []);
        // Şehir bilgisi olmayan eski tur
        $legacyTour = $this->createTour('Eski Tur', null, null);

        // Kayseri seçen müşteri: GAP turunu görür (durak), diğerlerini görmez
        $this->get(route('tours.index', ['departure_city' => 'Kayseri']))
            ->assertOk()
            ->assertSee('GAP Turu')
            ->assertDontSee('Ege Turu')
            ->assertDontSee('Eski Tur'); // şehir bilgisi yok → filtrede gizli

        // Ankara seçen müşteri: GAP turunu görür (kalkış)
        $this->get(route('tours.index', ['departure_city' => 'Ankara']))
            ->assertOk()
            ->assertSee('GAP Turu')
            ->assertDontSee('Ege Turu');

        // Filtre yokken hepsi görünür
        $this->get(route('tours.index'))
            ->assertOk()
            ->assertSee('GAP Turu')
            ->assertSee('Ege Turu')
            ->assertSee('Eski Tur');
    }

    private function createTour(string $title, ?string $departureCity, ?array $stopCities): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'category_id' => $this->category->id,
            'title' => $title,
            'destination' => 'Test',
            'departure_city' => $departureCity,
            'stop_cities' => $stopCities,
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 2,
            'departure_date' => today()->addDays(10),
            'is_active' => true,
        ]);
    }
}
