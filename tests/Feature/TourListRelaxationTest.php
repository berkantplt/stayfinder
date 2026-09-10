<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Boş sonuçta akıllı gevşetme: "Tarihi kaldır: N tur" çipleri. */
class TourListRelaxationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $agency = Agency::create([
            'name' => 'Gevşetme Acenta', 'slug' => 'gevsetme-acenta', 'email' => 'g@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        foreach (['Kapadokya Turu A', 'Kapadokya Turu B'] as $baslik) {
            Tour::create([
                'agency_id' => $agency->id, 'title' => $baslik, 'destination' => 'Kapadokya', 'description' => 'x',
                'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3, 'departure_date' => today()->addDays(20), 'is_active' => true,
            ]);
        }
    }

    public function test_bos_sonucta_tarihi_kaldir_cipi_sayiyla_gelir(): void
    {
        $r = $this->get(route('tours.index', ['destination' => 'Kapadokya', 'date_start' => today()->addYears(3)->toDateString()]))->assertOk();

        $r->assertSee('Tarihi kaldır');
        $r->assertSee('2 tur');
        // Destinasyonu kaldırmak da sonuç vermez (tarih hâlâ uçuk) → o çip yok
        $r->assertDontSee('Destinasyonu kaldır');
    }

    public function test_sonuc_varken_gevsetme_basilmaz(): void
    {
        $this->get(route('tours.index', ['destination' => 'Kapadokya']))->assertOk()->assertDontSee('kaldır:');
    }

    public function test_cip_baglantisi_o_filtreyi_dusurur(): void
    {
        $r = $this->get(route('tours.index', ['destination' => 'Kapadokya', 'min_price' => 999999]))->assertOk();
        $r->assertSee('Fiyat aralığını kaldır');
        $html = $r->getContent();
        $this->assertStringContainsString('destination=Kapadokya', $html);
        $this->assertMatchesRegularExpression('/href="[^"]*destination=Kapadokya[^"]*"[^>]*class="m-chip"/', $html);
        $this->assertDoesNotMatchRegularExpression('/href="[^"]*min_price=999999[^"]*"[^>]*class="m-chip"/', $html);
    }
}
