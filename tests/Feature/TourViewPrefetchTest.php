<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D12 — Speculation rules ile ön yüklenen tur sayfası (Sec-Purpose: prefetch /
 * prefetch;prerender) görüntülenme SAYMAZ; gerçek ziyaret sayar.
 */
class TourViewPrefetchTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();
        $agency = Agency::create([
            'name' => 'Sayaç Acenta', 'slug' => 'sayac-acenta', 'email' => 'sayac@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        $this->tour = Tour::create([
            'agency_id' => $agency->id, 'title' => 'Sayaç Turu', 'slug' => 'sayac-turu', 'destination' => 'Kapadokya',
            'description' => 'x', 'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3,
            'departure_date' => today()->addDays(20), 'is_active' => true,
        ]);
    }

    public function test_on_yukleme_istegi_goruntulenme_saymaz(): void
    {
        $this->withHeaders(['Sec-Purpose' => 'prefetch;prerender'])
            ->get(route('tours.show', $this->tour))
            ->assertOk();

        $this->assertSame(0, TourView::count());
        $this->assertSame(0, (int) $this->tour->fresh()->views_count);
    }

    public function test_gercek_ziyaret_sayar(): void
    {
        $this->get(route('tours.show', $this->tour))->assertOk();

        $this->assertSame(1, TourView::count());
        $this->assertSame(1, (int) $this->tour->fresh()->views_count);
    }

    public function test_on_yukleme_listesinde_dogru_adresler_var(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('"/profilim"', $html);
        $this->assertStringContainsString('"/kuponlarim"', $html);
        $this->assertStringNotContainsString('"/profil"]', $html);
        // GET'te yan etkisi var (duyurular görüldü sayılır): ön yüklenmez
        $this->assertStringNotContainsString('"/bildirimler"', $html);
    }
}
