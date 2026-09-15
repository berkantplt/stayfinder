<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * C13 — İndirim değeri türe bağlı sınırlanır: yüzde en fazla 100, sabit en fazla
 * 1.000.000 ₺, 0 kabul edilmez. Daha önce "%500" kaydedilebiliyordu.
 */
class CouponDiscountValueRuleTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $agency = Agency::create([
            'name' => 'Kupon Acenta', 'slug' => 'kupon-acenta', 'email' => 'kupon@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
        $this->agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    private function payload(string $type, string $value, string $code = 'KOD1'): array
    {
        return ['code' => $code, 'discount_type' => $type, 'discount_value' => $value, 'is_active' => '1'];
    }

    public function test_acenta_yuzde_100_ustu_reddedilir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.coupons.store'), $this->payload('percent', '150'))
            ->assertSessionHasErrors(['discount_value' => 'Yüzde indirim en fazla %100 olabilir.']);

        $this->assertSame(0, Coupon::count());
    }

    public function test_acenta_yuzde_100_ve_sabit_150_kabul_edilir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.coupons.store'), $this->payload('percent', '100', 'YUZDE'))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->agencyUser)
            ->post(route('agency.coupons.store'), $this->payload('fixed', '150', 'SABIT'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Coupon::count());
    }

    public function test_sifir_deger_reddedilir(): void
    {
        $this->actingAs($this->agencyUser)
            ->post(route('agency.coupons.store'), $this->payload('fixed', '0'))
            ->assertSessionHasErrors('discount_value');
    }

    public function test_admin_sabit_ust_siniri_asamaz_ve_guncellemede_de_kural_gecerli(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.coupons.store'), $this->payload('fixed', '2000000'))
            ->assertSessionHasErrors('discount_value');

        $coupon = Coupon::create(['code' => 'ESKI', 'discount_type' => 'percent', 'discount_value' => 10, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->put(route('admin.coupons.update', $coupon), $this->payload('percent', '101', 'ESKI'))
            ->assertSessionHasErrors('discount_value');

        $this->assertEquals(10, $coupon->fresh()->discount_value);
    }

    public function test_formlar_dinamik_ust_siniri_tasir(): void
    {
        $this->actingAs($this->agencyUser)->get(route('agency.coupons.index'))->assertOk()
            ->assertSee('data-discount-value', false)
            ->assertSee('max="1000000"', false);

        $this->actingAs($this->admin)->get(route('admin.coupons.index'))->assertOk()
            ->assertSee('data-discount-type', false);
    }
}
