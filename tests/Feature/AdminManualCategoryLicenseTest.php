<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin'in acentaya manuel kategori lisansı ekleme/iptal etme özelliği.
 * Yanlış satın alma telafisi: abonelik satın alma akışıyla aynı kalıpta
 * yaratılır (0 TL manuel sipariş + denetim izi), iptalde kayıt silinmez.
 */
class AdminManualCategoryLicenseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Agency $agency;

    private Category $parent;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->agency = Agency::create([
            'name' => 'Manuel Test Acentası',
            'slug' => 'manuel-test-acentasi',
            'email' => 'manuel@example.com',
            'is_active' => true,
            'legacy_category_access' => false,
        ]);

        $this->parent = Category::create([
            'name' => 'Yurt İçi', 'slug' => 'yurt-ici', 'monthly_price' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);

        $this->category = Category::create([
            'name' => 'Kültür Turları',
            'slug' => 'kultur-turlari',
            'icon' => '🏛️',
            'parent_id' => $this->parent->id,
            'monthly_price' => 2000,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function makeTour(): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'category_id' => $this->category->id,
            'title' => 'Manuel Lisans Turu',
            'destination' => 'Ankara',
            'description' => 'Manuel lisans görünürlük testi.',
            'price' => 3000,
            'currency' => 'TRY',
            'duration_days' => 2,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(12),
            'is_active' => true,
        ]);
    }

    public function test_admin_grants_category_and_tours_become_visible(): void
    {
        $tour = $this->makeTour();
        $this->assertFalse($tour->fresh()->isPubliclyVisible());

        $this->actingAs($this->admin)
            ->post(route('admin.agencies.categories.grant', $this->agency), [
                'category_id' => $this->category->id,
                'months' => 3,
            ])
            ->assertRedirect();

        $subscription = AgencyCategorySubscription::where('agency_id', $this->agency->id)
            ->where('category_id', $this->category->id)
            ->first();

        $this->assertNotNull($subscription);
        $this->assertSame(AgencyCategorySubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->expires_at->isSameDay(today()->addMonths(3)));
        // Tarife fiyatı kayda geçer, sipariş 0 TL manuel olur
        $this->assertSame('2000.00', (string) $subscription->monthly_price);
        $this->assertNotNull($subscription->last_order_id);
        $this->assertSame(AgencyCategoryOrder::PROVIDER_MANUAL, $subscription->lastOrder->payment_provider);
        $this->assertSame(AgencyCategoryOrder::STATUS_PAID, $subscription->lastOrder->status);
        $this->assertSame('0.00', (string) $subscription->lastOrder->subtotal);

        $this->assertTrue($tour->fresh()->isPubliclyVisible());
        $this->assertTrue(Tour::active()->whereKey($tour->id)->exists());
    }

    public function test_granting_active_subscription_extends_expiry(): void
    {
        AgencyCategorySubscription::create([
            'agency_id' => $this->agency->id,
            'category_id' => $this->category->id,
            'monthly_price' => 2000,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today()->subMonth(),
            'expires_at' => today()->addDays(10),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.agencies.categories.grant', $this->agency), [
                'category_id' => $this->category->id,
                'months' => 2,
            ])
            ->assertRedirect();

        $subscription = AgencyCategorySubscription::where('agency_id', $this->agency->id)
            ->where('category_id', $this->category->id)
            ->sole();

        // Mevcut bitişin üzerine eklenir, başlangıç korunur
        $this->assertTrue($subscription->expires_at->isSameDay(today()->addDays(10)->addMonths(2)));
        $this->assertTrue($subscription->started_at->isSameDay(today()->subMonth()));
    }

    public function test_admin_revokes_subscription_and_tours_are_hidden(): void
    {
        $tour = $this->makeTour();

        $subscription = AgencyCategorySubscription::create([
            'agency_id' => $this->agency->id,
            'category_id' => $this->category->id,
            'monthly_price' => 2000,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today(),
            'expires_at' => today()->addMonth(),
        ]);

        $this->assertTrue($tour->fresh()->isPubliclyVisible());

        $this->actingAs($this->admin)
            ->post(route('admin.agencies.categories.revoke', [$this->agency, $subscription]))
            ->assertRedirect();

        $subscription->refresh();
        $this->assertSame(AgencyCategorySubscription::STATUS_CANCELLED, $subscription->status);

        $this->assertFalse($tour->fresh()->isPubliclyVisible());
        $this->assertFalse(Tour::active()->whereKey($tour->id)->exists());
    }

    public function test_revoked_category_can_be_granted_again(): void
    {
        $subscription = AgencyCategorySubscription::create([
            'agency_id' => $this->agency->id,
            'category_id' => $this->category->id,
            'monthly_price' => 2000,
            'status' => AgencyCategorySubscription::STATUS_CANCELLED,
            'started_at' => today()->subMonths(2),
            'expires_at' => today()->addDays(20),
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.agencies.categories.grant', $this->agency), [
                'category_id' => $this->category->id,
                'months' => 1,
            ])
            ->assertRedirect();

        $subscription->refresh();
        // İptal edilmiş kayıt yeniden aktive edilir, süre bugünden başlar
        $this->assertSame(AgencyCategorySubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($subscription->started_at->isSameDay(today()));
        $this->assertTrue($subscription->expires_at->isSameDay(today()->addMonth()));
    }

    public function test_parent_category_cannot_be_granted(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.agencies.categories.grant', $this->agency), [
                'category_id' => $this->parent->id,
                'months' => 1,
            ])
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseMissing('agency_category_subscriptions', [
            'agency_id' => $this->agency->id,
            'category_id' => $this->parent->id,
        ]);
    }

    public function test_non_admin_cannot_grant_or_revoke(): void
    {
        $customer = User::factory()->create(['role' => 'user']);

        $subscription = AgencyCategorySubscription::create([
            'agency_id' => $this->agency->id,
            'category_id' => $this->category->id,
            'monthly_price' => 2000,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today(),
            'expires_at' => today()->addMonth(),
        ]);

        $this->actingAs($customer)
            ->post(route('admin.agencies.categories.grant', $this->agency), [
                'category_id' => $this->category->id,
                'months' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($customer)
            ->post(route('admin.agencies.categories.revoke', [$this->agency, $subscription]))
            ->assertForbidden();
    }

    public function test_revoke_rejects_subscription_of_another_agency(): void
    {
        $otherAgency = Agency::create([
            'name' => 'Başka Acenta',
            'slug' => 'baska-acenta',
            'email' => 'baska@example.com',
            'is_active' => true,
        ]);

        $subscription = AgencyCategorySubscription::create([
            'agency_id' => $otherAgency->id,
            'category_id' => $this->category->id,
            'monthly_price' => 2000,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today(),
            'expires_at' => today()->addMonth(),
        ]);

        // Yanlış acenta üzerinden başka acentanın aboneliği iptal edilemez
        $this->actingAs($this->admin)
            ->post(route('admin.agencies.categories.revoke', [$this->agency, $subscription]))
            ->assertNotFound();

        $this->assertSame(
            AgencyCategorySubscription::STATUS_ACTIVE,
            $subscription->fresh()->status
        );
    }
}
