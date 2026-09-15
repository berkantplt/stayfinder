<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * C7 — "Yaklaşan Popüler Tarihler" ölçüsü (clicked_at >= departure_date) gelecek
 * tarihler için hep 0'dı; liste rastgele sıralanıyor ve tarih başına COUNT
 * sorgusu atıyordu. Şimdi: en yakın tarih önce, turun son 30 gün tıklaması
 * bilgi amaçlı, tek GROUP BY.
 */
class AgencyStatsUpcomingDatesTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::create([
            'name' => 'Tarih Acenta', 'slug' => 'tarih-acenta', 'email' => 'tarih@example.com',
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

    private function addDate(Tour $tour, int $daysFromNow): TourDate
    {
        return TourDate::create([
            'tour_id' => $tour->id,
            'departure_date' => today()->addDays($daysFromNow),
            'return_date' => today()->addDays($daysFromNow + 3),
            'price' => 30000,
        ]);
    }

    public function test_en_yakin_tarih_once_gelir_ve_30_gunluk_tiklama_yazilir(): void
    {
        $uzak = $this->makeTour('Uzak Tarihli Tur');
        $yakin = $this->makeTour('Yakin Tarihli Tur');
        $gecmis = $this->makeTour('Gecmis Tarihli Tur');
        $this->addDate($uzak, 30);
        $this->addDate($yakin, 5);
        $this->addDate($gecmis, -1);

        // Yakın turun 2 güncel + 1 eski (40 gün) tıklaması var → "2 tıklama / 30 gün"
        foreach ([1, 2, 40] as $gun) {
            TourClick::create(['tour_id' => $yakin->id, 'agency_id' => $this->agency->id, 'ip_address' => '127.0.0.1', 'clicked_at' => now()->subDays($gun)]);
        }

        $this->actingAs($this->agencyUser)
            ->get(route('agency.stats'))
            ->assertOk()
            ->assertSee('Yaklaşan Tarihler')
            ->assertDontSee('Popüler Tarihler')
            ->assertSeeInOrder(['Yaklaşan Tarihler', 'Yakin Tarihli Tur', '2 tıklama / 30 gün', 'Uzak Tarihli Tur', '0 tıklama / 30 gün'])
            ->assertDontSee('Gecmis Tarihli Tur');
    }

    public function test_sorgu_sayisi_tarih_sayisi_ile_buyumez(): void
    {
        $tour = $this->makeTour('Tur');
        $this->addDate($tour, 3);
        $this->actingAs($this->agencyUser)->get(route('agency.stats'))->assertOk(); // ısınma

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->actingAs($this->agencyUser)->get(route('agency.stats'))->assertOk();
        $az = count(DB::getQueryLog());

        foreach (range(4, 20) as $gun) {
            $this->addDate($this->makeTour('Tur '.$gun), $gun);
        }

        DB::flushQueryLog();
        $this->actingAs($this->agencyUser)->get(route('agency.stats'))->assertOk();
        $cok = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame($az, $cok, 'Tarih listesi tarih başına sorgu atmamalı');
    }
}
