<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Coupon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerCouponIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('customer.coupons.index'))
            ->assertRedirect(route('login'));
    }

    public function test_auth_user_sees_available_coupons_with_masked_code(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $agency = $this->makeAgency();

        $valid = $this->makeCoupon($agency, [
            'code' => 'YAZ2026',
            'discount_type' => 'percent',
            'discount_value' => 20,
            'expires_at' => now()->addDays(30),
        ]);

        // Kupon kartı görünür ama kod, "Kuponu Al" denene kadar maskeli
        $this->actingAs($user)
            ->get(route('customer.coupons.index'))
            ->assertOk()
            ->assertDontSee('YAZ2026')
            ->assertSee('Kuponu Al')
            ->assertSee('%20')
            ->assertSee($agency->name);
    }

    public function test_inactive_coupon_is_hidden(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $agency = $this->makeAgency();
        $this->makeCoupon($agency, ['code' => 'INACTIVE', 'is_active' => false]);

        $this->actingAs($user)
            ->get(route('customer.coupons.index'))
            ->assertOk()
            ->assertDontSee('INACTIVE');
    }

    public function test_expired_coupon_is_hidden(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $agency = $this->makeAgency();
        $this->makeCoupon($agency, ['code' => 'EXPIRED', 'expires_at' => now()->subDay()]);

        $this->actingAs($user)
            ->get(route('customer.coupons.index'))
            ->assertOk()
            ->assertDontSee('EXPIRED');
    }

    public function test_coupon_with_exhausted_uses_is_hidden(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $agency = $this->makeAgency();
        $this->makeCoupon($agency, [
            'code' => 'FULL',
            'max_uses' => 10,
            'used_count' => 10,
        ]);

        $this->actingAs($user)
            ->get(route('customer.coupons.index'))
            ->assertOk()
            ->assertDontSee('FULL');
    }

    public function test_coupon_with_future_starts_at_is_hidden(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $agency = $this->makeAgency();
        $this->makeCoupon($agency, [
            'code' => 'NEXT_WEEK',
            'starts_at' => now()->addWeek(),
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->get(route('customer.coupons.index'))
            ->assertOk()
            ->assertDontSee('NEXT_WEEK');
    }

    public function test_admin_global_coupon_with_null_agency_shown(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);

        Coupon::create([
            'agency_id' => null,
            'code' => 'GLOBAL10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
            'expires_at' => now()->addDays(10),
        ]);

        $this->actingAs($user)
            ->get(route('customer.coupons.index'))
            ->assertOk()
            ->assertSee('Kuponu Al')        // kart listede (kod maskeli)
            ->assertDontSee('GLOBAL10')
            ->assertSee('turXtur'); // agency yoksa fallback
    }

    public function test_empty_state_when_no_available_coupons(): void
    {
        $user = User::factory()->create(['role' => 'visitor']);
        $agency = $this->makeAgency();
        $this->makeCoupon($agency, ['is_active' => false]); // hidden

        $this->actingAs($user)
            ->get(route('customer.coupons.index'))
            ->assertOk()
            ->assertSee('Şu an kullanılabilir kupon yok');
    }

    public function test_coupon_unlimited_uses_remaining_is_null(): void
    {
        $agency = $this->makeAgency();
        $coupon = $this->makeCoupon($agency, ['max_uses' => null]);

        $this->assertNull($coupon->fresh()->remaining_uses);
    }

    public function test_coupon_remaining_uses_decrements(): void
    {
        $agency = $this->makeAgency();
        $coupon = $this->makeCoupon($agency, ['max_uses' => 10, 'used_count' => 3]);

        $this->assertSame(7, $coupon->fresh()->remaining_uses);
    }

    public function test_formatted_discount_renders_percent_and_fixed(): void
    {
        $agency = $this->makeAgency();

        $pct = $this->makeCoupon($agency, ['discount_type' => 'percent', 'discount_value' => 25]);
        $fixed = $this->makeCoupon($agency, ['discount_type' => 'fixed', 'discount_value' => 1500, 'code' => 'F1500']);

        $this->assertSame('%25', $pct->formatted_discount);
        $this->assertSame('1.500 ₺', $fixed->formatted_discount);
    }

    private function makeAgency(): Agency
    {
        return Agency::create([
            'name' => 'Coupon Agency '.uniqid(),
            'slug' => 'coupon-'.uniqid(),
            'email' => uniqid().'@x.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    private function makeCoupon(Agency $agency, array $overrides = []): Coupon
    {
        return Coupon::create(array_merge([
            'agency_id' => $agency->id,
            'code' => 'TEST'.uniqid(),
            'discount_type' => 'percent',
            'discount_value' => 10,
            'is_active' => true,
            'starts_at' => null,
            'expires_at' => now()->addMonth(),
            'max_uses' => null,
            'used_count' => 0,
        ], $overrides));
    }
}
