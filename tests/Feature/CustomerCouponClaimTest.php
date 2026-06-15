<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCouponClaimTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['role' => 'visitor']);

        $this->agency = Agency::create([
            'name' => 'Claim Acenta',
            'slug' => 'claim-acenta',
            'email' => 'claim@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    private function makeCoupon(array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'agency_id' => $this->agency->id,
            'code' => 'CLAIM'.uniqid(),
            'discount_type' => 'percent',
            'discount_value' => 15,
            'is_active' => true,
            'expires_at' => now()->addMonth(),
            'max_uses' => null,
            'used_count' => 0,
        ], $overrides));
    }

    public function test_claim_creates_usage_increments_count_and_reveals_code(): void
    {
        $coupon = $this->makeCoupon(['code' => 'ACIKKOD15']);

        $this->actingAs($this->user)
            ->post(route('customer.coupons.claim', $coupon))
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'user_id' => $this->user->id,
        ]);
        $this->assertSame(1, $coupon->fresh()->used_count);

        // Claim sonrası kod listede artık görünür
        $this->actingAs($this->user)
            ->get(route('customer.coupons.index'))
            ->assertSee('ACIKKOD15');
    }

    public function test_double_claim_does_not_duplicate_usage_or_count(): void
    {
        $coupon = $this->makeCoupon();

        $this->actingAs($this->user)->post(route('customer.coupons.claim', $coupon));
        $this->actingAs($this->user)->post(route('customer.coupons.claim', $coupon))
            ->assertRedirect()
            ->assertSessionHas('success'); // dostane mesaj, hata değil

        $this->assertSame(1, CouponUsage::where('coupon_id', $coupon->id)->count());
        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_exhausted_coupon_cannot_be_claimed(): void
    {
        $coupon = $this->makeCoupon(['max_uses' => 5, 'used_count' => 5]);

        $this->actingAs($this->user)
            ->post(route('customer.coupons.claim', $coupon))
            ->assertSessionHasErrors('coupon');

        $this->assertSame(0, CouponUsage::count());
        $this->assertSame(5, $coupon->fresh()->used_count);
    }

    public function test_inactive_or_expired_coupon_cannot_be_claimed(): void
    {
        $inactive = $this->makeCoupon(['is_active' => false]);
        $expired = $this->makeCoupon(['expires_at' => now()->subDay()]);

        $this->actingAs($this->user)
            ->post(route('customer.coupons.claim', $inactive))
            ->assertSessionHasErrors('coupon');

        $this->actingAs($this->user)
            ->post(route('customer.coupons.claim', $expired))
            ->assertSessionHasErrors('coupon');

        $this->assertSame(0, CouponUsage::count());
    }

    public function test_last_claim_exhausts_coupon_and_hides_it_from_others_but_not_claimer(): void
    {
        $coupon = $this->makeCoupon(['code' => 'SONKULLANIM', 'max_uses' => 1, 'used_count' => 0]);
        $otherUser = User::factory()->create(['role' => 'visitor']);

        $this->actingAs($this->user)->post(route('customer.coupons.claim', $coupon));

        $this->assertSame(1, $coupon->fresh()->used_count);

        // Limiti dolan kupon başkalarının listesinden düşer
        $this->actingAs($otherUser)
            ->get(route('customer.coupons.index'))
            ->assertDontSee('SONKULLANIM')
            ->assertSee('Şu an kullanılabilir kupon yok');

        // Ama alan kullanıcı kodunu görmeye devam eder
        $this->actingAs($this->user)
            ->get(route('customer.coupons.index'))
            ->assertSee('SONKULLANIM');
    }

    public function test_guest_cannot_claim(): void
    {
        $coupon = $this->makeCoupon();

        $this->post(route('customer.coupons.claim', $coupon))
            ->assertRedirect(route('login'));

        $this->assertSame(0, CouponUsage::count());
    }
}
