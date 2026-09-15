<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D1 — Müşteri hesabı ortak gezinmesi: Profil / Favoriler / Kuponlarım /
 * Bildirimler / Kayıtlı Aramalar / Güvenlik sekmeleri tüm hesap sayfalarında;
 * /hesabim ortak kapı; acenta/admin kullanıcısında sekmeler basılmaz.
 */
class AccountNavTest extends TestCase
{
    use RefreshDatabase;

    public function test_musteri_hesap_sayfalarinda_sekmeleri_gorur(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);

        foreach (['profile.show', 'profile.edit', 'profile.security', 'favorites.index', 'customer.coupons.index', 'notifications.index', 'customer.saved-searches.index'] as $route) {
            $this->actingAs($user)->get(route($route))->assertOk()
                ->assertSee('class="hesap-nav"', false)
                ->assertSee('Kuponlarım')
                ->assertSee('Güvenlik')
                ->assertSee(route('customer.saved-searches.index'), false);
        }
    }

    public function test_aktif_sekme_isaretlenir_ve_hesabim_yonlendirir(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->actingAs($user)->get(route('customer.coupons.index'))->assertOk()
            ->assertSee('<a href="'.route('customer.coupons.index').'" class="active"', false)
            ->assertDontSee('<a href="'.route('profile.show').'" class="active"', false);

        $this->actingAs($user)->get('/hesabim')->assertRedirect(route('profile.show'));
    }

    public function test_guvenlik_sayfasi_sifre_formunu_tasir_duzenleme_sayfasi_tasimaz(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->actingAs($user)->get(route('profile.security'))->assertOk()
            ->assertSee('name="current_password"', false);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()
            ->assertDontSee('name="current_password"', false)
            ->assertSee(route('profile.security'), false);
    }

    public function test_acenta_kullanicisi_sekmeleri_gormez(): void
    {
        $agency = Agency::create([
            'name' => 'Nav Acenta', 'slug' => 'nav-acenta', 'email' => 'nav@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        $agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);

        $this->actingAs($agencyUser)->get(route('profile.show'))->assertOk()
            ->assertDontSee('class="hesap-nav"', false);
    }
}
