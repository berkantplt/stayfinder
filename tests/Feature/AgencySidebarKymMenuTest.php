<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C15 — Acenta kenar çubuğunda "Kategori Yetkileri" alt menüsü (Genel Bakış /
 * Sepet / Satın Alımlar) ve sepet kalem rozeti; geçiş erişimli acentada Sepet yok.
 */
class AgencySidebarKymMenuTest extends TestCase
{
    use RefreshDatabase;

    private function agencyUser(bool $legacy): User
    {
        $agency = Agency::create([
            'name' => 'Menü Acenta', 'slug' => 'menu-acenta-'.uniqid(), 'email' => uniqid().'@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(),
            'legacy_category_access' => $legacy,
        ]);

        return User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);
    }

    public function test_alt_menu_ve_rozet_her_sayfada_gorunur(): void
    {
        $user = $this->agencyUser(legacy: false);
        $parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        $c1 = Category::create(['name' => 'Avrupa', 'slug' => 'avrupa', 'is_active' => true, 'parent_id' => $parent->id, 'monthly_price' => 1000]);
        $c2 = Category::create(['name' => 'Asya', 'slug' => 'asya', 'is_active' => true, 'parent_id' => $parent->id, 'monthly_price' => 1000]);

        // 2 yeni kategori + 1 ekstra hak kalemi = 3
        $this->actingAs($user)
            ->withSession([
                'agency_category_license_cart' => [$c1->id, $c2->id],
                'agency_category_slot_cart' => [$c1->id => 2],
            ])
            ->get(route('agency.tours.index'))
            ->assertOk()
            ->assertSee(route('agency.category-licenses.cart.show'), false)
            ->assertSee(route('agency.category-licenses.orders'), false)
            ->assertSee('Satın Alımlar')
            ->assertSee('title="Sepette 3 kalem"', false);
    }

    public function test_sepet_bos_ise_rozet_yok(): void
    {
        $this->actingAs($this->agencyUser(legacy: false))
            ->get(route('agency.tours.index'))
            ->assertOk()
            ->assertDontSee('Sepette', false)
            ->assertSee('Genel Bakış');
    }

    public function test_gecis_erisimli_acentada_sepet_baglantisi_yok(): void
    {
        $this->actingAs($this->agencyUser(legacy: true))
            ->withSession(['agency_category_license_cart' => [99]])
            ->get(route('agency.tours.index'))
            ->assertOk()
            ->assertDontSee(route('agency.category-licenses.cart.show'), false)
            ->assertDontSee('Sepette', false)
            ->assertSee(route('agency.category-licenses.orders'), false);
    }

    public function test_gercek_sepete_ekleme_rozeti_artirir(): void
    {
        $user = $this->agencyUser(legacy: false);
        $parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        $category = Category::create(['name' => 'Avrupa', 'slug' => 'avrupa', 'is_active' => true, 'parent_id' => $parent->id, 'monthly_price' => 1000]);

        $this->actingAs($user)->post(route('agency.category-licenses.cart.add'), ['category_id' => $category->id]);

        $this->actingAs($user)->get(route('agency.tours.index'))->assertOk()
            ->assertSee('title="Sepette 1 kalem"', false);
    }
}
