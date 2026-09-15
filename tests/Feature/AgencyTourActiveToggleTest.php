<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C2 — Düzenleme formundaki "Aktif" kutusu. İşaret kaldırılınca tarayıcı alanı
 * HİÇ göndermez; hidden `is_active=0` olmadan sunucu "pasif" isteğini asla
 * görmez ve tur aktif kalırdı. Bu test hem gövde davranışını hem de formun
 * hidden alanı taşıdığını mühürler.
 */
class AgencyTourActiveToggleTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $agency = Agency::create([
            'name' => 'Toggle Acenta',
            'slug' => 'toggle-acenta',
            'email' => 'toggle@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        $this->agencyUser = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        $this->category = Category::create([
            'name' => 'Kültür Turları',
            'slug' => 'kultur-turlari',
            'is_active' => true,
            'parent_id' => $parent->id,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title' => 'Roma Turu',
            'destination' => 'İtalya',
            'departure_city' => 'İstanbul',
            'duration_days' => 5,
            'currency' => 'TRY',
            'requires_visa' => '0',
            'pricing_options' => [
                ['price' => '25000', 'departure_dates' => [today()->addDays(30)->toDateString()]],
            ],
        ], $overrides);
    }

    private function makeTour(bool $active): Tour
    {
        return Tour::create([
            'agency_id' => $this->agencyUser->agency_id,
            'category_id' => $this->category->id,
            'title' => 'Roma Turu',
            'slug' => 'roma-turu-'.uniqid(),
            'destination' => 'İtalya',
            'departure_city' => 'İstanbul',
            'duration_days' => 5,
            'price' => 25000,
            'currency' => 'TRY',
            'is_active' => $active,
            'requires_visa' => false,
        ]);
    }

    public function test_kutu_kaldirilinca_tur_pasife_alinir(): void
    {
        $tour = $this->makeTour(true);

        // Tarayıcı işaretsiz kutuyu göndermez; formdaki hidden alan "0" gönderir.
        $this->actingAs($this->agencyUser)
            ->put(route('agency.tours.update', $tour), $this->payload(['is_active' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($tour->fresh()->is_active);
    }

    public function test_kutu_isaretlenince_tur_aktife_alinir(): void
    {
        $tour = $this->makeTour(false);

        // Hidden "0" + checkbox "1" aynı adla gider; PHP son değeri ("1") alır.
        $this->actingAs($this->agencyUser)
            ->put(route('agency.tours.update', $tour), $this->payload(['is_active' => '1']))
            ->assertSessionHasNoErrors();

        $this->assertTrue($tour->fresh()->is_active);
    }

    public function test_duzenleme_formu_hidden_alani_tasir(): void
    {
        $tour = $this->makeTour(true);

        $this->actingAs($this->agencyUser)
            ->get(route('agency.tours.edit', $tour))
            ->assertOk()
            ->assertSee('<input type="hidden" name="is_active" value="0">', false)
            ->assertSee('name="is_active" value="1" checked', false);
    }

    public function test_dogrulama_hatasinda_kutu_eski_secimi_korur(): void
    {
        $tour = $this->makeTour(true);

        // Başlık boş → doğrulama düşer; kutu kaldırılmıştı, geri dönüşte de kaldırılmış kalmalı.
        $response = $this->actingAs($this->agencyUser)
            ->from(route('agency.tours.edit', $tour))
            ->put(route('agency.tours.update', $tour), $this->payload(['is_active' => '0', 'title' => '']))
            ->assertSessionHasErrors('title');

        $this->actingAs($this->agencyUser)
            ->get(route('agency.tours.edit', $tour))
            ->assertOk()
            ->assertDontSee('name="is_active" value="1" checked', false);

        $this->assertTrue($tour->fresh()->is_active, 'Hatalı istek turu değiştirmemeli');
    }
}
