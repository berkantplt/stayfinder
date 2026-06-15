<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\User;
use App\Notifications\CategorySubscriptionExpiredNotification;
use App\Notifications\CategorySubscriptionExpiringNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CategorySubscriptionLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        $this->agency = Agency::create([
            'name' => 'Döngü Acenta',
            'slug' => 'dongu-acenta',
            'email' => 'dongu@example.com',
            'is_active' => true,
            'legacy_category_access' => false,
        ]);

        $this->agencyUser = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $this->agency->id,
        ]);

        $this->category = Category::create([
            'name' => 'Gemi Turları',
            'slug' => 'gemi-turlari',
            'monthly_price' => 2000,
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    private function makeSubscription(array $overrides = []): AgencyCategorySubscription
    {
        return AgencyCategorySubscription::create(array_merge([
            'agency_id' => $this->agency->id,
            'category_id' => $this->category->id,
            'monthly_price' => 2000,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today()->subMonth(),
            'expires_at' => today()->addDays(15),
        ], $overrides));
    }

    public function test_lapsed_subscription_is_marked_expired_and_agency_is_notified(): void
    {
        $subscription = $this->makeSubscription(['expires_at' => today()->subDay()]);

        $this->artisan('app:expire-category-subscriptions')->assertExitCode(0);

        $this->assertSame(
            AgencyCategorySubscription::STATUS_EXPIRED,
            $subscription->fresh()->status
        );
        Notification::assertSentTo($this->agencyUser, CategorySubscriptionExpiredNotification::class);
    }

    public function test_subscription_expiring_within_seven_days_gets_one_reminder(): void
    {
        $subscription = $this->makeSubscription(['expires_at' => today()->addDays(5)]);

        $this->artisan('app:expire-category-subscriptions')->assertExitCode(0);

        Notification::assertSentTo($this->agencyUser, CategorySubscriptionExpiringNotification::class);
        $this->assertNotNull($subscription->fresh()->renewal_reminder_sent_at);

        // İkinci çalıştırma: aynı dönem için tekrar göndermez
        Notification::fake();
        $this->artisan('app:expire-category-subscriptions')->assertExitCode(0);
        Notification::assertNothingSent();
    }

    public function test_subscription_expiring_later_than_seven_days_gets_no_reminder(): void
    {
        $this->makeSubscription(['expires_at' => today()->addDays(10)]);

        $this->artisan('app:expire-category-subscriptions')->assertExitCode(0);

        Notification::assertNothingSent();
        $this->assertNull(
            AgencyCategorySubscription::first()->renewal_reminder_sent_at
        );
    }

    public function test_already_expired_status_subscription_is_not_renotified(): void
    {
        $this->makeSubscription([
            'status' => AgencyCategorySubscription::STATUS_EXPIRED,
            'expires_at' => today()->subDays(10),
        ]);

        $this->artisan('app:expire-category-subscriptions')->assertExitCode(0);

        Notification::assertNothingSent();
    }

    public function test_expiring_notification_payload_links_to_license_page(): void
    {
        $subscription = $this->makeSubscription(['expires_at' => today()->addDays(3)]);
        $subscription->load('category');

        $payload = (new CategorySubscriptionExpiringNotification($subscription))->toArray($this->agencyUser);

        $this->assertSame(route('agency.category-licenses.index'), $payload['url']);
        $this->assertStringContainsString('Gemi Turları', $payload['message']);
        $this->assertStringContainsString('3 gün', $payload['message']);
    }
}
