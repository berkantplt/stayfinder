<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D3 — Favoriler 24'erli sayfalanır; profil sayfası tüm favori/yorumları
 * çekmek yerine sayılar + son kayıtlar gösterir, "tümünü gör" bağlantısı verir.
 */
class CustomerListsPaginationTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create([
            'name' => 'Liste Acenta', 'slug' => 'liste-acenta', 'email' => 'liste@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        $this->user = User::factory()->create();
    }

    private function tur(int $i): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id, 'title' => 'Favori Tur '.$i, 'slug' => 'favori-tur-'.$i, 'destination' => 'Kapadokya',
            'description' => 'x', 'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3,
            'departure_date' => today()->addDays(20), 'is_active' => true,
        ]);
    }

    public function test_favoriler_24_erli_sayfalanir(): void
    {
        foreach (range(1, 30) as $i) {
            $this->user->favoriteTours()->attach($this->tur($i)->id);
        }

        $this->actingAs($this->user)->get(route('favorites.index'))->assertOk()
            ->assertSee('(30)')
            ->assertSee('page=2');

        $this->actingAs($this->user)->get(route('favorites.index', ['page' => 2]))->assertOk()
            ->assertDontSee('page=3');
    }

    public function test_profil_sayilari_ve_tumunu_gor_baglantisi(): void
    {
        foreach (range(1, 8) as $i) {
            $tour = $this->tur($i);
            $this->user->favoriteTours()->attach($tour->id);
            if ($i <= 7) {
                Review::create(['user_id' => $this->user->id, 'tour_id' => $tour->id, 'rating' => 4, 'comment' => 'Yorum '.$i]);
            }
        }

        $html = $this->actingAs($this->user)->get(route('profile.show'))->assertOk()->getContent();

        $this->assertStringContainsString('Tümünü gör (8)', $html);
        $this->assertStringContainsString('Son 5 yorum gösteriliyor (7 toplam)', $html);
        // önizlemede 6 favori + 5 yorum; 8. favori ve 1. (en eski) yorum listede yok
        $this->assertStringNotContainsString('Yorum 1<', $html);
        $this->assertSame(6, substr_count($html, 'style="width:56px;height:40px;'));
    }
}
