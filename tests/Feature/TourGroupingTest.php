<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Aynı turun farklı acenta teklifleri /turlar'da tek kart: en ucuz teklif, "N acentada", "…'den". */
class TourGroupingTest extends TestCase
{
    use RefreshDatabase;

    private Agency $a;

    private Agency $b;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->a = $this->acenta('Acenta A', 'acenta-a');
        $this->b = $this->acenta('Acenta B', 'acenta-b');
    }

    private function acenta(string $ad, string $slug): Agency
    {
        return Agency::create([
            'name' => $ad, 'slug' => $slug, 'email' => $slug.'@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
    }

    private function tur(Agency $agency, string $baslik, int $fiyat): Tour
    {
        return Tour::create([
            'agency_id' => $agency->id, 'title' => $baslik, 'destination' => 'Kapadokya', 'description' => 'x',
            'price' => $fiyat, 'currency' => 'TRY', 'duration_days' => 3, 'departure_date' => today()->addDays(20), 'is_active' => true,
        ]);
    }

    public function test_grup_anahtari_kayitta_uretilir(): void
    {
        $t = $this->tur($this->a, 'Kapadokya Turu', 5000);
        $this->assertSame('kapadokya-turu', $t->group_key);
        $this->assertSame(Tour::groupKeyFor('KAPADOKYA TURU'), $t->group_key);
        $this->assertNotSame($t->group_key, Tour::groupKeyFor('Kapadokya Turu 2 Gece'));
    }

    public function test_ayni_tur_iki_acentada_tek_kart_en_ucuz_teklifle(): void
    {
        $this->tur($this->a, 'Kapadokya Turu', 5000);
        $ucuz = $this->tur($this->b, 'Kapadokya Turu', 4499);
        $this->tur($this->a, 'Bodrum Turu', 9000);

        $r = $this->get(route('tours.index'))->assertOk();
        $html = $r->getContent();

        $this->assertSame(1, substr_count($html, 'Kapadokya Turu</div>'), 'tek kart');
        $r->assertSee('2 acentada')->assertSee("4.499 ₺")->assertSee(route('tours.show', $ucuz), false);
        $r->assertSee('<strong id="toursTotal">2</strong>', false);
    }

    public function test_fiyat_azalan_siralama_grubun_en_yuksek_teklifine_bakar(): void
    {
        $this->tur($this->a, 'Kapadokya Turu', 5000);
        $this->tur($this->b, 'Kapadokya Turu', 4499);
        $this->tur($this->a, 'Bodrum Turu', 4800);

        $this->get(route('tours.index', ['sort' => 'price_desc']))->assertOk()
            ->assertSeeInOrder(['Kapadokya Turu', 'Bodrum Turu']);
        $this->get(route('tours.index', ['sort' => 'price_asc']))->assertOk()
            ->assertSeeInOrder(['Kapadokya Turu', 'Bodrum Turu']);
    }

    public function test_tur_ile_baslayan_basliklar_gruplanir_ve_listede_gorunur(): void
    {
        // Anahtarı "tur-…" ile başlayan başlıklar nöbetçi anahtarla karışmamalı
        $this->tur($this->a, 'Tur Fethiye', 3000);
        $this->tur($this->b, 'Tur Ölüdeniz, Fethiye', 3500);

        $this->get(route('tours.index'))->assertOk()
            ->assertSee('Tur Fethiye')->assertSee('Tur Ölüdeniz, Fethiye')
            ->assertSee('<strong id="toursTotal">2</strong>', false);
    }

    public function test_gruplama_kapaliyken_her_teklif_ayri_kart(): void
    {
        config(['ui.tour_grouping' => false]);
        $this->tur($this->a, 'Kapadokya Turu', 5000);
        $this->tur($this->b, 'Kapadokya Turu', 4499);

        $r = $this->get(route('tours.index'))->assertOk();
        $this->assertSame(2, substr_count($r->getContent(), 'Kapadokya Turu</div>'));
        $r->assertDontSee('acentada');
    }
}
