<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vize alanı ZORUNLU: vizeli / kapıda / vizesiz — üçünden biri seçilmeden
 * tur KAYDEDİLEMEZ (2026-09-01 kullanıcı kararı).
 *
 * Kolon hâlâ üç durumlu (null = belirtilmemiş) çünkü ESKİ kayıtlar ve admin
 * toplu ekranı null taşıyabiliyor; ama acenta formundan null ÜRETİLEMEZ.
 * Aşağıdaki testler iki şeyi birden mühürlüyor: (a) üç değer doğru kolonlara
 * yazılıyor, (b) işaretsiz gövde sessizce "vizesiz"e çökmüyor — reddediliyor.
 */
class TourVisaFieldTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $agency = Agency::create([
            'name' => 'Vize Acenta',
            'slug' => 'vize-acenta',
            'email' => 'vize@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        $this->agencyUser = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $this->category = Category::create([
            'name' => 'Kültür Turları',
            'slug' => 'kultur-turlari',
            'is_active' => true,
        ]);
    }

    private function tourPayload(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'title' => 'Roma Turu',
            'destination' => 'İtalya',
            'departure_city' => 'İstanbul',
            'duration_days' => 5,
            'currency' => 'TRY',
            'pricing_options' => [
                ['price' => '25000', 'departure_dates' => [today()->addDays(30)->toDateString()]],
            ],
        ], $overrides);
    }

    public function test_vizeli_isaretlenince_true_kaydedilir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['requires_visa' => '1']));

        $tour = Tour::latest('id')->first();
        $this->assertTrue($tour->requires_visa);
        // Konsolosluk vizesi kapıda DEĞİL; karışırsa yolcu randevusuz yola çıkar.
        $this->assertFalse($tour->visa_on_arrival);
    }

    public function test_vizesiz_isaretlenince_false_kaydedilir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['requires_visa' => '0']));

        $tour = Tour::latest('id')->first();
        $this->assertFalse($tour->requires_visa);
        $this->assertFalse($tour->visa_on_arrival);
    }

    public function test_kapida_vize_ayri_bayrakla_kaydedilir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['requires_visa' => 'kapida']));

        $tour = Tour::latest('id')->first();
        // Kapıda vize DE bir vizedir — "vizesiz tur" aramasına düşmemeli.
        $this->assertTrue($tour->requires_visa);
        $this->assertTrue($tour->visa_on_arrival);
    }

    public function test_hicbiri_isaretlenmezse_tur_kaydedilmez(): void
    {
        // Asıl mesele bu: işaretsiz gövde ("unknown") sessizce "vizesiz" ÜRETMEMELİ
        // ve artık kaydı da geçmemeli — alan zorunlu.
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['requires_visa' => 'unknown']))
            ->assertSessionHasErrors('requires_visa');

        $this->assertSame(0, Tour::count());
    }

    public function test_alan_hic_gonderilmezse_tur_kaydedilmez(): void
    {
        // Eski form gövdesi (alan hiç yok) da reddedilmeli — aksi hâlde zorunluluk
        // yalnız tarayıcıda olur, gövdeyi elle kuran her istemci deler.
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload())
            ->assertSessionHasErrors('requires_visa');

        $this->assertSame(0, Tour::count());
    }

    public function test_gecersiz_deger_reddedilir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['requires_visa' => 'belki']))
            ->assertSessionHasErrors('requires_visa');
    }

    public function test_duzenlemede_vize_temizlenemez(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['requires_visa' => '1']));

        $tour = Tour::latest('id')->first();
        $this->assertTrue($tour->requires_visa);

        // Zorunluluk DÜZENLEMEDE de geçerli: yayındaki bir tur "belirtilmemiş"e
        // geri döndürülemez, yoksa kapı tek kullanımlık olurdu.
        $this->actingAs($this->agencyUser)
            ->put(route('agency.tours.update', $tour), $this->tourPayload(['requires_visa' => 'unknown']))
            ->assertSessionHasErrors('requires_visa');

        $this->assertTrue($tour->fresh()->requires_visa);
    }

    public function test_duzenlemede_vize_degistirilebilir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['requires_visa' => '1']));

        $tour = Tour::latest('id')->first();

        // Yanlış işaretleyen acenta DÜZELTEBİLMELİ — zorunluluk, değiştirilemezlik
        // değil. Kapı yalnız "boş bırakma"ya kapalı.
        $this->actingAs($this->agencyUser)
            ->put(route('agency.tours.update', $tour), $this->tourPayload(['requires_visa' => '0']))
            ->assertSessionHasNoErrors();

        $this->assertFalse($tour->fresh()->requires_visa);
    }

    public function test_formda_iki_kutucuk_render_edilir(): void
    {
        $yanit = $this->actingAs($this->agencyUser)->get(route('agency.tours.create'));

        $yanit->assertOk();
        $yanit->assertSee('data-visa-deger="1"', false);
        $yanit->assertSee('data-visa-deger="kapida"', false);
        $yanit->assertSee('data-visa-deger="0"', false);
        $yanit->assertSee('Kapıda vize', false);
        $yanit->assertSee('Vizesiz', false);
    }

    public function test_duzenleme_formu_kayitli_degeri_isaretli_getirir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['requires_visa' => '0']));

        $tour = Tour::latest('id')->first();

        $this->actingAs($this->agencyUser)
            ->get(route('agency.tours.edit', $tour))
            ->assertOk()
            ->assertSee('name="requires_visa" value="0"', false);
    }

    public function test_duzenleme_formu_kapida_vizeyi_geri_getirir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $this->tourPayload(['requires_visa' => 'kapida']));

        $tour = Tour::latest('id')->first();

        // İki kolondan tek seçeneğe geri çevrim: "kapida" olarak açılmalı,
        // düz "1" değil — yoksa acenta her düzenlemede seçimini kaybeder.
        $this->actingAs($this->agencyUser)
            ->get(route('agency.tours.edit', $tour))
            ->assertOk()
            ->assertSee('name="requires_visa" value="kapida"', false);
    }
}
