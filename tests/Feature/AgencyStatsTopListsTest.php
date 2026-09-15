<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\TourView;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * C8 — İstatistik sayfası "En Çok Görüntülenen / Tıklanan" listeleri: satır
 * başına Tour::find yerine tek sorgu; arşivlenmiş (soft-deleted) turun
 * geçmişi bağlantısız gösterilir (eskiden find null dönüp sayfa 500 verirdi).
 */
class AgencyStatsTopListsTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::create([
            'name' => 'İstatistik Acenta', 'slug' => 'istatistik-acenta', 'email' => 'stat@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
        $this->agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $this->agency->id]);
        $parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        $this->category = Category::create(['name' => 'Avrupa', 'slug' => 'avrupa', 'is_active' => true, 'parent_id' => $parent->id]);
    }

    private function makeTour(string $title): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id, 'category_id' => $this->category->id, 'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(), 'destination' => 'Fransa',
            'departure_city' => 'İstanbul', 'duration_days' => 4, 'price' => 30000, 'currency' => 'TRY',
            'is_active' => true, 'requires_visa' => true,
        ]);
    }

    private function addViews(Tour $tour, int $times): void
    {
        foreach (range(1, $times) as $i) {
            TourView::create(['tour_id' => $tour->id, 'session_id' => 's'.$i, 'viewed_at' => now()->subHours($i)]);
        }
    }

    private function addClicks(Tour $tour, int $times): void
    {
        foreach (range(1, $times) as $i) {
            TourClick::create(['tour_id' => $tour->id, 'agency_id' => $this->agency->id, 'ip_address' => '127.0.0.1', 'clicked_at' => now()->subHours($i)]);
        }
    }

    public function test_listeler_basliklari_gosterir_arsivli_tur_baglantisiz_kalir(): void
    {
        $paris = $this->makeTour('Paris Turu');
        $roma = $this->makeTour('Roma Turu');
        $this->addViews($paris, 3);
        $this->addViews($roma, 1);
        $this->addClicks($roma, 2);
        $roma->delete(); // A10 arşiv — tıklama geçmişi (agency_id) listede kalır

        $this->actingAs($this->agencyUser)
            ->get(route('agency.stats'))
            ->assertOk()
            ->assertSee(route('agency.tours.show', $paris), false)
            ->assertSee('Roma Turu')
            ->assertSee('(arşivde)')
            ->assertDontSee(route('agency.tours.show', $roma), false);
    }

    public function test_sorgu_sayisi_liste_satiri_ile_buyumez(): void
    {
        $this->addViews($this->makeTour('Tur 1'), 2);
        $this->actingAs($this->agencyUser)->get(route('agency.stats'))->assertOk(); // ısınma

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->agencyUser)->get(route('agency.stats'))->assertOk();
        $az = count(DB::getQueryLog());

        foreach (range(2, 8) as $i) {
            $tour = $this->makeTour('Tur '.$i);
            $this->addViews($tour, $i);
            $this->addClicks($tour, 1);
        }

        DB::flushQueryLog();
        $this->actingAs($this->agencyUser)->get(route('agency.stats'))->assertOk();
        $cok = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($az, $cok, 'Top listeler satır başına sorgu atmamalı');
    }
}
