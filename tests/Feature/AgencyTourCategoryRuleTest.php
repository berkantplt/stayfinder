<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C19 — Tur formu kategori kuralı: üst kategoriler satılmaz ve tur alamaz,
 * pasif alt kategori de seçilemez. Daha önce `exists:categories,id` her
 * ikisini de geçiriyordu; geçiş (legacy) acentada üst kategori kaydedilebiliyordu.
 */
class AgencyTourCategoryRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private Category $parent;

    private Category $child;

    protected function setUp(): void
    {
        parent::setUp();

        $agency = Agency::create([
            'name' => 'Kural Acenta',
            'slug' => 'kural-acenta',
            'email' => 'kural@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true, // hasCategoryAccess() aktif her kategoriye true der
        ]);

        $this->agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);

        $this->parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        $this->child = Category::create(['name' => 'Avrupa', 'slug' => 'avrupa', 'is_active' => true, 'parent_id' => $this->parent->id]);
    }

    private function payload(int $categoryId): array
    {
        return [
            'category_id' => $categoryId,
            'title' => 'Paris Turu',
            'destination' => 'Fransa',
            'departure_city' => 'İstanbul',
            'duration_days' => 4,
            'currency' => 'TRY',
            'requires_visa' => '1',
            'pricing_options' => [
                ['price' => '30000', 'departure_dates' => [today()->addDays(20)->toDateString()]],
            ],
        ];
    }

    public function test_ust_kategori_ile_tur_olusturulamaz(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->payload($this->parent->id))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, Tour::count());
    }

    public function test_pasif_alt_kategori_ile_tur_olusturulamaz(): void
    {
        $this->child->update(['is_active' => false]);

        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->payload($this->child->id))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(0, Tour::count());
    }

    public function test_aktif_alt_kategori_ile_tur_olusturulur(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->payload($this->child->id))
            ->assertSessionHasNoErrors();

        $this->assertSame($this->child->id, Tour::firstOrFail()->category_id);
    }

    public function test_guncellemede_ust_kategoriye_tasinamaz(): void
    {
        $tour = Tour::create([
            'agency_id' => $this->agencyUser->agency_id,
            'category_id' => $this->child->id,
            'title' => 'Paris Turu',
            'slug' => 'paris-turu',
            'destination' => 'Fransa',
            'departure_city' => 'İstanbul',
            'duration_days' => 4,
            'price' => 30000,
            'currency' => 'TRY',
            'is_active' => true,
            'requires_visa' => true,
        ]);

        $this->actingAs($this->agencyUser)
            ->put(route('agency.tours.update', $tour), $this->payload($this->parent->id))
            ->assertSessionHasErrors('category_id');

        $this->assertSame($this->child->id, $tour->fresh()->category_id);
    }

    public function test_formda_ust_kategori_secenek_degil_optgroup_basligidir(): void
    {
        $response = $this->actingAs($this->agencyUser)->get(route('agency.tours.create'))->assertOk();

        $response->assertSee('<optgroup label=" Yurt Dışı">', false);
        $response->assertDontSee('<option value="'.$this->parent->id.'"', false);
        $response->assertSee('<option value="'.$this->child->id.'"', false);
    }

    public function test_ust_kategoriye_bagli_eski_tur_duzenlemede_uyarilir(): void
    {
        $tour = Tour::create([
            'agency_id' => $this->agencyUser->agency_id,
            'category_id' => $this->parent->id,
            'title' => 'Eski Tur',
            'slug' => 'eski-tur',
            'destination' => 'Fransa',
            'departure_city' => 'İstanbul',
            'duration_days' => 4,
            'price' => 30000,
            'currency' => 'TRY',
            'is_active' => true,
            'requires_visa' => true,
        ]);

        $this->actingAs($this->agencyUser)
            ->get(route('agency.tours.edit', $tour))
            ->assertOk()
            ->assertSee('Üst kategoriler tur alamaz');
    }
}
