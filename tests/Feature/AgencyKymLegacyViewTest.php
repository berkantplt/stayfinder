<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C17 — Geçiş (legacy) erişimli acentaya KYM sayfasında satın alma arayüzü
 * (sepet kutusu, "Satın Alınabilir Kategoriler", ödeme CTA'sı) gösterilmez;
 * normal acentada hepsi durur.
 */
class AgencyKymLegacyViewTest extends TestCase
{
    use RefreshDatabase;

    private function agencyUser(bool $legacy): User
    {
        $agency = Agency::create([
            'name' => 'KYM Acenta', 'slug' => 'kym-acenta-'.uniqid(), 'email' => uniqid().'@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(),
            'legacy_category_access' => $legacy,
        ]);

        return User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        Category::create(['name' => 'Avrupa', 'slug' => 'avrupa', 'is_active' => true, 'parent_id' => $parent->id, 'monthly_price' => 1000]);
    }

    public function test_gecis_erisimli_acenta_satin_alma_bloklarini_gormez(): void
    {
        $this->actingAs($this->agencyUser(legacy: true))
            ->get(route('agency.category-licenses.index'))
            ->assertOk()
            ->assertSee('geçiş kapsamına alındı')
            ->assertSee('bu sayfada sepet ve ödeme bulunmaz')
            ->assertDontSee('Satın Alınabilir Kategoriler')
            ->assertDontSee('Sepetiniz boş')
            ->assertDontSee('class="btn btn-primary" data-cta-odeme', false)
            ->assertDontSee('Henüz ödenmiş kategori satın alımı yok')
            ->assertSee('Aktif Kategori Yetkileri');
    }

    public function test_normal_acenta_satin_alma_bloklarini_gorur(): void
    {
        $this->actingAs($this->agencyUser(legacy: false))
            ->get(route('agency.category-licenses.index'))
            ->assertOk()
            ->assertSee('Satın Alınabilir Kategoriler')
            ->assertSee('Sepetiniz boş')
            ->assertSee('class="btn btn-primary" data-cta-odeme', false)
            ->assertSee('Henüz ödenmiş kategori satın alımı yok');
    }
}
