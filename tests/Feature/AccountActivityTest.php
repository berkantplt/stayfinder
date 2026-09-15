<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AiSearchLog;
use App\Models\DiscoveryGuide;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * D9 — "Aramalarım": AI aramaları, keşif rehberleri ve sunucuya yazılan
 * karşılaştırma listesi hesapta; liste JS ile eşitlenir (GET/PUT), 3 tur sınırı.
 */
class AccountActivityTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create([
            'name' => 'Etkinlik Acenta', 'slug' => 'etkinlik-acenta', 'email' => 'etkinlik@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        $this->user = User::factory()->create(['role' => User::ROLE_VISITOR]);
    }

    private function tur(string $title, bool $active = true): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id, 'title' => $title, 'slug' => Str::slug($title).'-'.uniqid(), 'destination' => 'Kapadokya',
            'description' => 'x', 'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3,
            'departure_date' => today()->addDays(20), 'is_active' => $active,
        ]);
    }

    public function test_karsilastirma_listesi_sunucuda_esitlenir(): void
    {
        [$a, $b, $c, $d] = [$this->tur('A'), $this->tur('B'), $this->tur('C'), $this->tur('D')];
        $pasif = $this->tur('Pasif', active: false);

        // 3'ten fazlası reddedilir
        $this->actingAs($this->user)->putJson(route('account.compare.sync'), ['ids' => [$a->id, $b->id, $c->id, $d->id]])
            ->assertStatus(422);

        // Bilinmeyen ve pasif turlar süzülür
        $this->actingAs($this->user)->putJson(route('account.compare.sync'), ['ids' => [$a->id, 999999, $pasif->id]])
            ->assertOk()->assertJson(['ids' => [$a->id]]);

        // Tam eşitleme: çıkarılan gider, yeni gelir
        $this->actingAs($this->user)->putJson(route('account.compare.sync'), ['ids' => [$b->id, $c->id]])
            ->assertOk()->assertJson(['ids' => [$b->id, $c->id]]);

        $this->actingAs($this->user)->getJson(route('account.compare.index'))
            ->assertOk()->assertJson(['ids' => [$b->id, $c->id]]);

        // Başka kullanıcı kendi (boş) listesini görür
        $diger = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $this->actingAs($diger)->getJson(route('account.compare.index'))->assertOk()->assertJson(['ids' => []]);
    }

    public function test_aramalarim_sayfasi_gecmisi_listeler_ve_baskasininkini_gostermez(): void
    {
        config(['ai.discovery_enabled' => true]);
        $tour = $this->tur('Kıyas Turu');
        $this->user->compareTours()->attach($tour->id);
        AiSearchLog::create(['user_id' => $this->user->id, 'raw_query' => 'Eylülde sıcak deniz tatili', 'result_tour_ids' => [$tour->id]]);
        DiscoveryGuide::create(['uuid' => (string) Str::uuid(), 'user_id' => $this->user->id, 'destination_input' => 'Roma', 'duration_days' => 3, 'status' => DiscoveryGuide::STATUS_COMPLETED]);

        $diger = User::factory()->create(['role' => User::ROLE_VISITOR]);
        AiSearchLog::create(['user_id' => $diger->id, 'raw_query' => 'Gizli başka arama', 'result_tour_ids' => []]);

        $this->actingAs($this->user)->get(route('account.activity'))->assertOk()
            ->assertSee('Eylülde sıcak deniz tatili')
            ->assertSee('Roma · 3 gün')
            ->assertSee('Kıyas Turu')
            ->assertSee('Aramalarım')
            ->assertDontSee('Gizli başka arama');
    }

    public function test_listeden_cikarma_ve_js_esitleme_bayragi(): void
    {
        $tour = $this->tur('Çıkar Beni');
        $this->user->compareTours()->attach($tour->id);

        $this->actingAs($this->user)->delete(route('account.compare.remove', $tour))->assertRedirect();
        $this->assertSame(0, $this->user->compareTours()->count());

        // Giriş yapmış müşteride eşitleme uçları sayfaya gömülür (@json eğik çizgiyi kaçırır)
        $this->actingAs($this->user)->get(route('home'))->assertOk()->assertSee('\\/karsilastirma-listem', false);
    }

    public function test_misafirde_esitleme_uclari_gomulmez(): void
    {
        $this->get(route('home'))->assertOk()->assertDontSee('\\/karsilastirma-listem', false);
    }
}
