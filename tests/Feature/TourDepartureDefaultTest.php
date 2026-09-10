<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Profil şehri /turlar'da "Nereden" varsayılanı: yalnız filtresiz girişte, görünür çiple. */
class TourDepartureDefaultTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $agency = Agency::create([
            'name' => 'Kalkış Acenta', 'slug' => 'kalkis-acenta', 'email' => 'k@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        foreach ([['İstanbul Kalkışlı Tur', 'İstanbul'], ['Ankara Kalkışlı Tur', 'Ankara']] as [$baslik, $sehir]) {
            Tour::create([
                'agency_id' => $agency->id, 'title' => $baslik, 'destination' => 'Kapadokya', 'description' => 'x',
                'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3, 'departure_date' => today()->addDays(20),
                'departure_city' => $sehir, 'is_active' => true,
            ]);
        }
        $this->user = User::factory()->create(['city' => 'İstanbul']);
    }

    public function test_filtresiz_giriste_profil_sehri_uygulanir_ve_cip_gorunur(): void
    {
        $r = $this->actingAs($this->user)->get(route('tours.index'))->assertOk();
        $r->assertSee('İstanbul Kalkışlı Tur')->assertDontSee('Ankara Kalkışlı Tur')->assertSee("İstanbul'dan kalkış (profilin)", false);
    }

    public function test_bos_departure_city_parametresi_varsayilani_kaldirir(): void
    {
        $r = $this->actingAs($this->user)->get(route('tours.index').'?departure_city=')->assertOk();
        $r->assertSee('Ankara Kalkışlı Tur')->assertDontSee('(profilin)');
    }

    public function test_baska_filtre_varken_varsayilan_uygulanmaz(): void
    {
        $r = $this->actingAs($this->user)->get(route('tours.index', ['destination' => 'Kapadokya']))->assertOk();
        $r->assertSee('Ankara Kalkışlı Tur')->assertDontSee('(profilin)');
    }

    public function test_ziyaretci_icin_varsayilan_yok(): void
    {
        $this->get(route('tours.index'))->assertOk()->assertSee('Ankara Kalkışlı Tur');
    }
}
