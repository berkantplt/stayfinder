<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\SavedSearch;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\SavedSearchMatchNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Kayıtlı arama: kaydet, listele, sil; günlük komut uyan yeni turda bildirim atar. */
class SavedSearchTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->agency = Agency::create([
            'name' => 'Arama Acenta', 'slug' => 'arama-acenta', 'email' => 'a@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        $this->user = User::factory()->create();
    }

    private function tur(string $baslik, string $dest = 'Kapadokya'): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id, 'title' => $baslik, 'destination' => $dest, 'description' => 'x',
            'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3, 'departure_date' => today()->addDays(20), 'is_active' => true,
        ]);
    }

    public function test_turlar_sayfasindan_kaydedilir_ve_listelenir(): void
    {
        $this->tur('Kapadokya A');

        $this->actingAs($this->user)->get(route('tours.index', ['destination' => 'Kapadokya']))
            ->assertOk()->assertSee('Bu aramayı kaydet');

        $this->actingAs($this->user)->post(route('customer.saved-searches.store'), [
            'params' => ['destination' => 'Kapadokya', 'page' => 3, 'sort' => 'newest'],
        ])->assertSessionHas('success');

        $kayit = SavedSearch::first();
        $this->assertSame(['destination' => 'Kapadokya'], $kayit->params, 'sayfa ve sıralama kaydedilmez');
        $this->assertSame('Kapadokya', $kayit->name);

        $this->actingAs($this->user)->get(route('tours.index', ['destination' => 'Kapadokya']))
            ->assertOk()->assertSee('Kayıtlı arama')->assertDontSee('Bu aramayı kaydet');

        $this->actingAs($this->user)->get(route('customer.saved-searches.index'))->assertOk()->assertSee('Kapadokya');
    }

    public function test_bos_filtre_kaydedilmez_ve_baskasinin_kaydi_silinemez(): void
    {
        $this->actingAs($this->user)->post(route('customer.saved-searches.store'), ['params' => ['page' => 2]])
            ->assertSessionHasErrors('params');

        $baska = User::factory()->create();
        $kayit = $baska->savedSearches()->create(['name' => 'x', 'params' => ['destination' => 'Bodrum']]);
        $this->actingAs($this->user)->delete(route('customer.saved-searches.destroy', $kayit))->assertForbidden();
        $this->actingAs($baska)->delete(route('customer.saved-searches.destroy', $kayit))->assertRedirect();
        $this->assertDatabaseMissing('saved_searches', ['id' => $kayit->id]);
    }

    public function test_komut_uyan_yeni_turda_bildirim_atar_ve_tekrar_etmez(): void
    {
        $this->tur('Eski Kapadokya');
        $kayit = $this->user->savedSearches()->create([
            'name' => 'Kapadokya', 'params' => ['destination' => 'Kapadokya'], 'last_checked_at' => now(),
        ]);

        $this->travel(1)->hours();
        $this->tur('Yeni Kapadokya');
        $this->tur('Bodrum Turu', 'Bodrum'); // filtreye uymaz

        $this->artisan('app:check-saved-searches')->assertSuccessful();

        $bildirimler = $this->user->notifications()->where('type', SavedSearchMatchNotification::class)->get();
        $this->assertCount(1, $bildirimler);
        $this->assertStringContainsString('Yeni Kapadokya', $bildirimler->first()->data['message']);
        $this->assertSame($kayit->fresh()->url(), $bildirimler->first()->data['url']);

        $this->artisan('app:check-saved-searches')->assertSuccessful();
        $this->assertCount(1, $this->user->notifications()->where('type', SavedSearchMatchNotification::class)->get(), 'aynı tur iki kez bildirilmez');
    }
}
