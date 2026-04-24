<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgencyCategoryLicensingTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_agency_tours_remain_publicly_visible_without_subscription_records(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = Agency::create([
            'name' => 'Legacy Travel',
            'slug' => 'legacy-travel',
            'email' => 'legacy@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'category_id' => null,
            'title' => 'Legacy Tur',
            'destination' => 'Antalya',
            'description' => 'Geçiş erişimi ile görünür kalmalı.',
            'price' => 4500,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(7),
            'return_date' => today()->addDays(9),
            'is_active' => true,
        ]);

        $this->assertTrue($tour->fresh()->isPubliclyVisible());
        $this->assertTrue(Tour::active()->whereKey($tour->id)->exists());
    }

    public function test_agency_cannot_create_tour_in_unlicensed_category_until_checkout_completes(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = Agency::create([
            'name' => 'Yeni Acenta',
            'slug' => 'yeni-acenta',
            'email' => 'yeni@example.com',
            'is_active' => true,
            'legacy_category_access' => false,
        ]);

        $user = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $category = Category::create([
            'name' => 'Kültür Turları',
            'slug' => 'kultur-turlari',
            'monthly_price' => 2000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $payload = $this->validTourPayload($category->id);

        $this->actingAs($user)
            ->post(route('agency.tours.store'), $payload)
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('tours', 0);

        $this->actingAs($user)
            ->post(route('agency.category-licenses.cart.add'), ['category_id' => $category->id])
            ->assertRedirect();

        $this->actingAs($user)
            ->post(route('agency.category-licenses.checkout'))
            ->assertRedirect(route('agency.category-licenses.index'));

        $this->assertDatabaseHas('agency_category_orders', [
            'agency_id' => $agency->id,
            'subtotal' => '2000.00',
        ]);

        $this->assertDatabaseHas('agency_category_subscriptions', [
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->post(route('agency.tours.store'), $payload)
            ->assertRedirect(route('agency.tours.index'));

        $this->assertDatabaseCount('tours', 1);
        $this->assertTrue($agency->fresh()->hasCategoryAccess($category));
    }

    public function test_unlicensed_tour_is_not_publicly_accessible_for_new_agencies(): void
    {
        Queue::fake();
        Notification::fake();

        $category = Category::create([
            'name' => 'Doğa Turları',
            'slug' => 'doga-turlari',
            'monthly_price' => 2000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $agency = Agency::create([
            'name' => 'Kilitli Acenta',
            'slug' => 'kilitli-acenta',
            'email' => 'kilitli@example.com',
            'is_active' => true,
            'legacy_category_access' => false,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'title' => 'Kilitli Tur',
            'destination' => 'Kaş',
            'description' => 'Abonelik yoksa görünmemeli.',
            'price' => 5200,
            'currency' => 'TRY',
            'duration_days' => 4,
            'departure_date' => today()->addDays(14),
            'return_date' => today()->addDays(17),
            'is_active' => true,
        ]);

        $this->assertFalse($tour->fresh()->isPubliclyVisible());
        $this->assertFalse(Tour::active()->whereKey($tour->id)->exists());
        $this->get(route('tours.show', $tour))->assertNotFound();
    }

    private function validTourPayload(int $categoryId): array
    {
        return [
            'category_id' => $categoryId,
            'title' => 'Yetkili Test Turu',
            'destination' => 'Kapadokya',
            'description' => 'Kategori yetkisi test turu.',
            'duration_days' => 3,
            'currency' => 'TRY',
            'included' => 'Ulaşım',
            'excluded' => 'Kişisel harcamalar',
            'tour_url' => 'https://example.com/test-tur',
            'pricing_options' => [
                [
                    'price' => '7500',
                    'departure_dates' => [today()->addDays(10)->toDateString()],
                ],
            ],
        ];
    }
}
