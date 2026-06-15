<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Announcement;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\PriceDropNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TourNotificationTargetingTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();

        $this->agency = Agency::create([
            'name' => 'Hedefli Bildirim Acenta',
            'slug' => 'hedefli-bildirim-acenta',
            'email' => 'hedefli@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    private function makeTour(array $overrides = []): Tour
    {
        return Tour::create(array_merge([
            'agency_id' => $this->agency->id,
            'category_id' => null,
            'title' => 'Hedefli Test Turu',
            'destination' => 'Bodrum',
            'description' => 'Hedefli bildirim testi.',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => 4,
            'departure_date' => today()->addDays(14),
            'return_date' => today()->addDays(18),
            'is_active' => true,
        ], $overrides));
    }

    public function test_new_tour_creates_single_announcement_and_no_user_notifications(): void
    {
        Notification::fake();
        User::factory()->count(3)->create();

        $tour = $this->makeTour();

        $this->assertDatabaseCount('announcements', 1);
        $this->assertDatabaseHas('announcements', [
            'type' => Announcement::TYPE_NEW_TOUR,
            'tour_id' => $tour->id,
        ]);
        Notification::assertNothingSent();
    }

    public function test_inactive_tour_is_not_announced(): void
    {
        $this->makeTour(['is_active' => false]);

        $this->assertDatabaseCount('announcements', 0);
    }

    public function test_price_drop_notifies_only_users_who_favorited_the_tour(): void
    {
        Notification::fake();

        $favoriter = User::factory()->create();
        $bystander = User::factory()->create();

        $tour = $this->makeTour();
        $favoriter->favoriteTours()->attach($tour->id);

        $tour->update(['price' => 8000]);

        Notification::assertSentTo($favoriter, PriceDropNotification::class);
        Notification::assertNotSentTo($bystander, PriceDropNotification::class);
    }

    public function test_price_increase_sends_no_notification(): void
    {
        Notification::fake();

        $favoriter = User::factory()->create();
        $tour = $this->makeTour();
        $favoriter->favoriteTours()->attach($tour->id);

        $tour->update(['price' => 12000]);

        Notification::assertNothingSent();
    }

    public function test_notifications_page_lists_announcements_and_marks_them_seen(): void
    {
        $user = User::factory()->create();
        $tour = $this->makeTour();

        $this->assertNull($user->fresh()->announcements_seen_at);

        $response = $this->actingAs($user)->get(route('notifications.index'));

        $response->assertOk();
        $response->assertSee('Yeni Tur Eklendi!');
        $response->assertSee($tour->title);
        $this->assertNotNull($user->fresh()->announcements_seen_at);
    }

    public function test_unseen_scope_uses_seen_timestamp_with_created_at_fallback(): void
    {
        $tour = $this->makeTour();

        // Duyurudan SONRA kayıt olan kullanıcı eski duyuruyu "okunmamış" görmez
        $lateUser = User::factory()->create(['created_at' => now()->addMinute()]);
        $this->assertSame(0, Announcement::unseenBy($lateUser)->count());

        // Duyurudan önce var olan kullanıcı görür; sayfayı açınca sıfırlanır
        $earlyUser = User::factory()->create(['created_at' => now()->subDay()]);
        $this->assertSame(1, Announcement::unseenBy($earlyUser)->count());

        $this->actingAs($earlyUser)->get(route('notifications.index'));
        $this->assertSame(0, Announcement::unseenBy($earlyUser->fresh())->count());
    }
}
