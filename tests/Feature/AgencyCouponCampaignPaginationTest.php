<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C14 — Kupon ve kampanya listeleri sayfalanır (20/sayfa); kampanya tur seçimi
 * aranabilir alan; pasife alınmış turun kampanyası listeden kaybolmaz.
 */
class AgencyCouponCampaignPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::create([
            'name' => 'Sayfa Acenta', 'slug' => 'sayfa-acenta', 'email' => 'sayfa@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
        $this->agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $this->agency->id]);
        $parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        $this->category = Category::create(['name' => 'Avrupa', 'slug' => 'avrupa', 'is_active' => true, 'parent_id' => $parent->id]);
    }

    private function makeTour(string $title, bool $active = true): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id, 'category_id' => $this->category->id, 'title' => $title,
            'slug' => \Illuminate\Support\Str::slug($title).'-'.uniqid(), 'destination' => 'Fransa',
            'departure_city' => 'İstanbul', 'duration_days' => 4, 'price' => 30000, 'currency' => 'TRY',
            'is_active' => $active, 'requires_visa' => true,
        ]);
    }

    public function test_kuponlar_20_satirda_sayfalanir(): void
    {
        foreach (range(1, 25) as $i) {
            Coupon::create(['agency_id' => $this->agency->id, 'code' => 'KOD'.$i, 'discount_type' => 'fixed', 'discount_value' => 100, 'is_active' => true]);
        }

        $response = $this->actingAs($this->agencyUser)->get(route('agency.coupons.index'))->assertOk();
        $response->assertSee('KOD25')->assertDontSee('>KOD1<', false)->assertSee('page=2');

        $this->actingAs($this->agencyUser)->get(route('agency.coupons.index', ['page' => 2]))->assertOk()->assertSee('>KOD1<', false);
    }

    public function test_kampanyalar_sayfalanir_ve_tur_secimi_aranabilir(): void
    {
        $aktif = $this->makeTour('Aktif Tur');
        $pasif = $this->makeTour('Pasif Tur', active: false);

        foreach (range(1, 21) as $i) {
            Campaign::create([
                'tour_id' => $aktif->id, 'discount_price' => 1000 + $i, 'label' => 'Kampanya '.$i,
                'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
            ]);
        }
        Campaign::create([
            'tour_id' => $pasif->id, 'discount_price' => 900, 'label' => 'Pasif Tur Kampanyasi',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
        ]);

        $response = $this->actingAs($this->agencyUser)->get(route('agency.campaigns.index'))->assertOk();
        $response
            ->assertSee('page=2')
            ->assertSee('data-searchable', false)
            ->assertSee('enhanceSearchableSelect', false)
            // seçim kutusunda yalnız yayındaki tur; pasif turun kampanyası listede kalır (en yeni → ilk sayfa)
            ->assertSee('<option value="'.$aktif->id.'"', false)
            ->assertDontSee('<option value="'.$pasif->id.'"', false)
            ->assertSee('Pasif Tur Kampanyasi');
    }

    public function test_kampanya_duzenleme_secimi_aranabilir(): void
    {
        $tour = $this->makeTour('Aktif Tur');
        $campaign = Campaign::create([
            'tour_id' => $tour->id, 'discount_price' => 1000, 'label' => 'Kampanya',
            'starts_at' => now()->subDay(), 'ends_at' => now()->addDays(10),
        ]);

        $this->actingAs($this->agencyUser)->get(route('agency.campaigns.edit', $campaign))->assertOk()
            ->assertSee('data-searchable', false)
            ->assertSee('enhanceSearchableSelect', false);
    }
}
