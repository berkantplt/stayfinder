<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C18 — Tur arşivleme onayı: "Emin misiniz?" yerine etkiyi sayılarla söyler
 * (yorum, favori) ve 30 günlük geri alma penceresini hatırlatır.
 */
class AgencyTourArchiveConfirmTest extends TestCase
{
    use RefreshDatabase;

    public function test_onay_metni_yorum_ve_favori_sayisini_soyler(): void
    {
        $metin = Tour::archiveConfirmText('Paris Turu', 2, 1);

        $this->assertStringContainsString('"Paris Turu" arşive taşınacak', $metin);
        $this->assertStringContainsString('30 gün içinde', $metin);
        $this->assertStringContainsString('2 yorumu ve 1 kez favorilenmesi var', $metin);
        $this->assertStringContainsString("\n\nArşive taşınsın mı?", $metin);
    }

    public function test_onay_metni_etki_yoksa_sade_kalir(): void
    {
        $metin = Tour::archiveConfirmText('Paris Turu', 0, 0);

        $this->assertStringNotContainsString('Dikkat', $metin);
        $this->assertStringContainsString('30 gün sonra tur kalıcı silinir.', $metin);
    }

    public function test_liste_ve_detay_sayfasi_sayilari_tasir(): void
    {
        $agency = Agency::create([
            'name' => 'Arşiv Acenta', 'slug' => 'arsiv-acenta', 'email' => 'arsiv@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
        $agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);
        $parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        $category = Category::create(['name' => 'Avrupa', 'slug' => 'avrupa', 'is_active' => true, 'parent_id' => $parent->id]);

        $tour = Tour::create([
            'agency_id' => $agency->id, 'category_id' => $category->id, 'title' => 'Paris Turu', 'slug' => 'paris-turu',
            'destination' => 'Fransa', 'departure_city' => 'İstanbul', 'duration_days' => 4, 'price' => 30000,
            'currency' => 'TRY', 'is_active' => true, 'requires_visa' => true,
        ]);

        $musteri1 = User::factory()->create(['role' => 'user']);
        $musteri2 = User::factory()->create(['role' => 'user']);
        Review::create(['tour_id' => $tour->id, 'user_id' => $musteri1->id, 'rating' => 5, 'comment' => 'Harika']);
        Review::create(['tour_id' => $tour->id, 'user_id' => $musteri2->id, 'rating' => 4, 'comment' => 'İyi']);
        $tour->favoritedBy()->attach($musteri1->id);

        $beklenen = '2 yorumu ve 1 kez favorilenmesi var';

        $this->actingAs($agencyUser)->get(route('agency.tours.index'))->assertOk()
            ->assertSee(\Illuminate\Support\Js::from(Tour::archiveConfirmText('Paris Turu', 2, 1))->toHtml(), false)
            ->assertSee('Arşivle');

        $this->actingAs($agencyUser)->get(route('agency.tours.show', $tour))->assertOk()
            ->assertSee(\Illuminate\Support\Js::from(Tour::archiveConfirmText('Paris Turu', 2, 1))->toHtml(), false);

        $this->assertStringContainsString($beklenen, Tour::archiveConfirmText('Paris Turu', 2, 1));
    }
}
