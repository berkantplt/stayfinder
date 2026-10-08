<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Acenta tur listesinde Arşivle / Geri al sayfa yenilemeden çalışır: liste fetch ile
 * JSON ister, cevapta karşı tablonun satır HTML'i + arşiv sayısı gelir. JSON istemeyen
 * istek (JS kapalı) eski yönlendirme davranışını korur.
 */
class AgencyTourArchiveAjaxTest extends TestCase
{
    use RefreshDatabase;

    private function makeAgency(string $slug = 'ajax-acenta'): Agency
    {
        return Agency::create([
            'name' => 'Ajax Acenta '.$slug, 'slug' => $slug, 'email' => $slug.'@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
    }

    private function makeAgencyUser(Agency $agency): User
    {
        return User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);
    }

    private function makeTour(Agency $agency, string $title = 'Roma Turu'): Tour
    {
        $parent = Category::firstOrCreate(['slug' => 'yurt-disi'], ['name' => 'Yurt Dışı', 'is_active' => true]);
        $category = Category::firstOrCreate(['slug' => 'avrupa'], ['name' => 'Avrupa', 'is_active' => true, 'parent_id' => $parent->id]);

        return Tour::create([
            'agency_id' => $agency->id, 'category_id' => $category->id, 'title' => $title, 'slug' => \Illuminate\Support\Str::slug($title),
            'destination' => 'İtalya', 'departure_city' => 'İstanbul', 'duration_days' => 4, 'price' => 30000,
            'currency' => 'TRY', 'is_active' => true, 'requires_visa' => true,
        ]);
    }

    public function test_json_arsivleme_arsiv_satiri_ve_sayaci_doner(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $tour = $this->makeTour($agency);

        $cevap = $this->actingAs($user)->deleteJson(route('agency.tours.destroy', $tour))->assertOk();

        $cevap->assertJson(['ok' => true, 'arsiv_sayisi' => 1])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'arşive taşındı'));

        $html = $cevap->json('arsiv_html');
        $this->assertStringContainsString('data-arsiv-id="'.$tour->id.'"', $html);
        $this->assertStringContainsString('Roma Turu', $html);
        $this->assertStringContainsString('Geri al', $html);
        $this->assertStringContainsString(route('agency.tours.restore', $tour), $html);

        $this->assertSoftDeleted('tours', ['id' => $tour->id]);
    }

    public function test_json_geri_alma_liste_satirini_doner(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $tour = $this->makeTour($agency);
        $tour->delete();

        $cevap = $this->actingAs($user)->postJson(route('agency.tours.restore', $tour))->assertOk();

        $cevap->assertJson(['ok' => true, 'message' => 'Tur geri alındı.', 'arsiv_sayisi' => 0]);

        $html = $cevap->json('satir_html');
        $this->assertStringContainsString('data-tour-id="'.$tour->id.'"', $html);
        $this->assertStringContainsString('Roma Turu', $html);
        $this->assertStringContainsString('Arşivle', $html);
        $this->assertStringContainsString('Yayında', $html);
        // Onay metni sayaçlarla (0 yorum, 0 favori) tekrar üretilir — liste ile birebir
        $this->assertStringContainsString(
            \Illuminate\Support\Js::from(Tour::archiveConfirmText('Roma Turu', 0, 0))->toHtml(),
            $html
        );

        $this->assertDatabaseHas('tours', ['id' => $tour->id, 'deleted_at' => null]);
    }

    public function test_js_kapaliyken_yonlendirme_korunur(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $tour = $this->makeTour($agency);

        $this->actingAs($user)->delete(route('agency.tours.destroy', $tour))
            ->assertRedirect(route('agency.tours.index'))
            ->assertSessionHas('success');

        $this->actingAs($user)->post(route('agency.tours.restore', $tour))
            ->assertRedirect(route('agency.tours.index'))
            ->assertSessionHas('success', 'Tur geri alındı.');
    }

    public function test_baska_acentanin_turu_json_ile_de_arsivlenemez(): void
    {
        $sahip = $this->makeAgency('sahip');
        $tour = $this->makeTour($sahip);
        $yabanci = $this->makeAgencyUser($this->makeAgency('yabanci'));

        $this->actingAs($yabanci)->deleteJson(route('agency.tours.destroy', $tour))->assertForbidden();
        $this->assertDatabaseHas('tours', ['id' => $tour->id, 'deleted_at' => null]);
    }

    public function test_liste_sayfasi_js_kancalarini_tasir(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $this->makeTour($agency);

        // Arşiv boş: kutu basılır ama gizli; boş-durum satırı gizli
        $this->actingAs($user)->get(route('agency.tours.index'))->assertOk()
            ->assertSee('data-tur-listesi', false)
            ->assertSee('data-bos-satir hidden', false)
            ->assertSee('data-arsiv-kutu hidden', false)
            ->assertSee('data-arsiv-sayac>0</span>', false)
            ->assertSee('data-arsiv-form', false);
    }
}
