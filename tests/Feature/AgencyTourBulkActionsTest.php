<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Review;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Acenta tur listesi: kalıcı silme (satırdaki "Sil" + arşivdeki "Kalıcı sil") ve seç modu
 * toplu işlem (arşivle | sil). Kalıcı silme forceDelete: bağlı kayıtlar FK ile gider, görsel
 * dosyası başka tur kullanmıyorsa diskten silinir. Toplu işlemde yabancı id sessizce atlanır.
 */
class AgencyTourBulkActionsTest extends TestCase
{
    use RefreshDatabase;

    private function makeAgency(string $slug = 'toplu-acenta'): Agency
    {
        return Agency::create([
            'name' => 'Toplu Acenta '.$slug, 'slug' => $slug, 'email' => $slug.'@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
    }

    private function makeAgencyUser(Agency $agency): User
    {
        return User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);
    }

    private function makeTour(Agency $agency, string $title = 'Roma Turu', array $extra = []): Tour
    {
        $parent = Category::firstOrCreate(['slug' => 'yurt-disi'], ['name' => 'Yurt Dışı', 'is_active' => true]);
        $category = Category::firstOrCreate(['slug' => 'avrupa'], ['name' => 'Avrupa', 'is_active' => true, 'parent_id' => $parent->id]);

        return Tour::create(array_merge([
            'agency_id' => $agency->id, 'category_id' => $category->id, 'title' => $title, 'slug' => Str::slug($title),
            'destination' => 'İtalya', 'departure_city' => 'İstanbul', 'duration_days' => 4, 'price' => 30000,
            'currency' => 'TRY', 'is_active' => true, 'requires_visa' => true,
        ], $extra));
    }

    public function test_kalici_silme_turu_bagli_kayitlari_ve_gorselini_siler(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tours/kapak.jpg', 'x');

        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $tour = $this->makeTour($agency, 'Roma Turu', ['images' => ['/storage/tours/kapak.jpg']]);
        $date = TourDate::create(['tour_id' => $tour->id, 'departure_date' => now()->addMonth(), 'return_date' => now()->addMonth()->addDays(4), 'price' => 30000]);
        $musteri = User::factory()->create();
        $review = Review::create(['user_id' => $musteri->id, 'tour_id' => $tour->id, 'rating' => 5, 'comment' => 'Harika']);
        $tour->favoritedBy()->attach($musteri->id);

        $cevap = $this->actingAs($user)->deleteJson(route('agency.tours.force-destroy', $tour))->assertOk();

        $cevap->assertJson(['ok' => true, 'arsiv_sayisi' => 0])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'kalıcı olarak silindi'));

        $this->assertDatabaseMissing('tours', ['id' => $tour->id]);
        $this->assertDatabaseMissing('tour_dates', ['id' => $date->id]);
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
        $this->assertDatabaseMissing('favorites', ['tour_id' => $tour->id]);
        Storage::disk('public')->assertMissing('tours/kapak.jpg');
    }

    public function test_baska_turun_kullandigi_gorsel_diskte_kalir(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('tours/ortak.jpg', 'x');

        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $silinecek = $this->makeTour($agency, 'A Turu', ['images' => ['/storage/tours/ortak.jpg']]);
        $kalan = $this->makeTour($agency, 'B Turu', ['image' => '/storage/tours/ortak.jpg']);

        $this->actingAs($user)->deleteJson(route('agency.tours.force-destroy', $silinecek))->assertOk();

        $this->assertDatabaseMissing('tours', ['id' => $silinecek->id]);
        $this->assertDatabaseHas('tours', ['id' => $kalan->id]);
        Storage::disk('public')->assertExists('tours/ortak.jpg');
    }

    public function test_arsivdeki_tur_kalici_silinebilir(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $tour = $this->makeTour($agency);
        $tour->delete();

        $this->actingAs($user)->deleteJson(route('agency.tours.force-destroy', $tour))
            ->assertOk()->assertJson(['ok' => true, 'arsiv_sayisi' => 0]);

        $this->assertDatabaseMissing('tours', ['id' => $tour->id]);
    }

    public function test_baska_acentanin_turu_kalici_silinemez(): void
    {
        $sahip = $this->makeAgency('sahip');
        $tour = $this->makeTour($sahip);
        $yabanci = $this->makeAgencyUser($this->makeAgency('yabanci'));

        $this->actingAs($yabanci)->deleteJson(route('agency.tours.force-destroy', $tour))->assertForbidden();
        $this->assertDatabaseHas('tours', ['id' => $tour->id, 'deleted_at' => null]);
    }

    public function test_toplu_arsivleme_yalniz_kendi_turlarini_isler(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $t1 = $this->makeTour($agency, 'Roma Turu');
        $t2 = $this->makeTour($agency, 'Paris Turu');
        $yabanci = $this->makeTour($this->makeAgency('yabanci'), 'Berlin Turu');

        $cevap = $this->actingAs($user)->postJson(route('agency.tours.bulk'), [
            'islem' => 'arsivle', 'ids' => [$t1->id, $t2->id, $yabanci->id],
        ])->assertOk();

        $cevap->assertJson(['ok' => true, 'islem' => 'arsivle', 'arsiv_sayisi' => 2])
            ->assertJsonPath('message', fn ($m) => str_starts_with($m, '2 tur arşive taşındı'));
        $this->assertEqualsCanonicalizing([$t1->id, $t2->id], $cevap->json('ids'));

        $html = implode('', $cevap->json('arsiv_html'));
        $this->assertStringContainsString('data-arsiv-id="'.$t1->id.'"', $html);
        $this->assertStringContainsString('data-arsiv-id="'.$t2->id.'"', $html);
        $this->assertStringContainsString('Kalıcı sil', $html);

        $this->assertSoftDeleted('tours', ['id' => $t1->id]);
        $this->assertSoftDeleted('tours', ['id' => $t2->id]);
        $this->assertDatabaseHas('tours', ['id' => $yabanci->id, 'deleted_at' => null]);
    }

    public function test_toplu_kalici_silme_arsivdekini_de_kapsar(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $t1 = $this->makeTour($agency, 'Roma Turu');
        $t2 = $this->makeTour($agency, 'Paris Turu');
        $t2->delete();

        $cevap = $this->actingAs($user)->postJson(route('agency.tours.bulk'), [
            'islem' => 'sil', 'ids' => [$t1->id, $t2->id],
        ])->assertOk();

        $cevap->assertJson(['ok' => true, 'islem' => 'sil', 'arsiv_sayisi' => 0, 'arsiv_html' => []])
            ->assertJsonPath('message', '2 tur kalıcı olarak silindi.');
        $this->assertEqualsCanonicalizing([$t1->id, $t2->id], $cevap->json('ids'));

        $this->assertDatabaseMissing('tours', ['id' => $t1->id]);
        $this->assertDatabaseMissing('tours', ['id' => $t2->id]);
    }

    public function test_toplu_islem_dogrulama_ve_bos_secim(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);

        $this->actingAs($user)->postJson(route('agency.tours.bulk'), ['islem' => 'yok', 'ids' => [1]])
            ->assertUnprocessable()->assertJsonValidationErrors('islem');
        $this->actingAs($user)->postJson(route('agency.tours.bulk'), ['islem' => 'sil', 'ids' => []])
            ->assertUnprocessable()->assertJsonValidationErrors('ids');
        // Olmayan / yabancı id: doğrulama geçer ama hiçbir tur işlenmez → 422 + ok:false
        $this->actingAs($user)->postJson(route('agency.tours.bulk'), ['islem' => 'sil', 'ids' => [999999]])
            ->assertUnprocessable()->assertJson(['ok' => false]);
    }

    public function test_js_kapaliyken_yonlendirme_korunur(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $t1 = $this->makeTour($agency, 'Roma Turu');
        $t2 = $this->makeTour($agency, 'Paris Turu');

        $this->actingAs($user)->delete(route('agency.tours.force-destroy', $t1))
            ->assertRedirect(route('agency.tours.index'))
            ->assertSessionHas('success');

        $this->actingAs($user)->post(route('agency.tours.bulk'), ['islem' => 'arsivle', 'ids' => [$t2->id]])
            ->assertRedirect(route('agency.tours.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('tours', ['id' => $t1->id]);
        $this->assertSoftDeleted('tours', ['id' => $t2->id]);
    }

    public function test_liste_sayfasi_sec_modu_ve_sil_kancalarini_tasir(): void
    {
        $agency = $this->makeAgency();
        $user = $this->makeAgencyUser($agency);
        $tour = $this->makeTour($agency);
        $arsiv = $this->makeTour($agency, 'Eski Tur');
        $arsiv->delete();

        $this->actingAs($user)->get(route('agency.tours.index'))->assertOk()
            ->assertSee('data-sec-ac', false)
            ->assertSee('data-sec-tumu', false)
            ->assertSee('data-sec-kutu value="'.$tour->id.'"', false)
            ->assertSee('data-toplu-url="'.route('agency.tours.bulk').'"', false)
            ->assertSee('data-toplu-islem="sil"', false)
            ->assertSee(route('agency.tours.force-destroy', $tour), false)
            ->assertSee(route('agency.tours.force-destroy', $arsiv), false)
            ->assertSee('Kalıcı sil', false)
            ->assertSee(Js::from(Tour::permanentDeleteConfirmText('Roma Turu', 0, 0, 0))->toHtml(), false);
    }

    public function test_kalici_silme_onay_metni_etkiyi_sayar(): void
    {
        $metin = Tour::permanentDeleteConfirmText('Roma Turu', 2, 1, 3);
        $this->assertStringContainsString('"Roma Turu" KALICI olarak silinecek', $metin);
        $this->assertStringContainsString('Silinecekler: 3 tarih, 2 yorum, 1 favori.', $metin);
        $this->assertStringContainsString('geri alınamaz', $metin);

        $sade = Tour::permanentDeleteConfirmText('Roma Turu', 0, 0, 0);
        $this->assertStringNotContainsString('Silinecekler', $sade);
        $this->assertStringEndsWith('Kalıcı olarak silinsin mi?', $sade);
    }
}
