<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Favoriye eklendiği andaki fiyat saklanır; sayfa "eklediğinden beri %N düştü" der, yalnız aynı birimde. */
class FavoritePriceDiffTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $agency = Agency::create([
            'name' => 'Fark Acenta', 'slug' => 'fark-acenta', 'email' => 'f@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        $this->tour = Tour::create([
            'agency_id' => $agency->id, 'title' => 'Fark Turu', 'destination' => 'Kapadokya', 'description' => 'x',
            'price' => 10000, 'currency' => 'TRY', 'duration_days' => 3, 'departure_date' => today()->addDays(20), 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
    }

    public function test_favori_eklenince_fiyat_anlik_goruntusu_saklanir_ve_dusus_gosterilir(): void
    {
        $this->actingAs($this->user)->post(route('favorites.toggle', $this->tour))->assertRedirect();
        $pivot = $this->user->favoriteTours()->first()->pivot;
        $this->assertSame(10000.0, (float) $pivot->price_at_save);
        $this->assertSame('TRY', $pivot->currency_at_save);

        $this->tour->update(['price' => 9000]);

        $this->actingAs($this->user)->get(route('favorites.index'))->assertOk()
            ->assertSee('%10 düştü')
            ->assertSee('başlangıç fiyatı');
    }

    public function test_para_birimi_degisince_fark_basilmaz(): void
    {
        $this->user->favoriteTours()->attach($this->tour->id, ['price_at_save' => 200, 'currency_at_save' => 'EUR']);

        $this->actingAs($this->user)->get(route('favorites.index'))->assertOk()->assertDontSee('eklediğinde');
    }

    public function test_eski_favoride_anlik_goruntu_yoksa_satir_yok(): void
    {
        $this->user->favoriteTours()->attach($this->tour->id);

        $this->actingAs($this->user)->get(route('favorites.index'))->assertOk()->assertDontSee('eklediğinde');
    }
}
