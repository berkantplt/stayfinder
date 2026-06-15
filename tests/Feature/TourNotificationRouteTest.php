<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\PriceDropNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TourNotificationRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_drop_notification_stores_public_tour_route_with_tour_id(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = Agency::create([
            'name' => 'Bildirim Acenta',
            'slug' => 'bildirim-acenta',
            'email' => 'bildirim@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'category_id' => null,
            'title' => 'Bildirim Test Turu',
            'destination' => 'Antalya',
            'description' => 'Bildirim rota testi.',
            'price' => 4500,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(7),
            'return_date' => today()->addDays(9),
            'is_active' => true,
        ]);

        $user = User::factory()->create();

        $payload = (new PriceDropNotification($tour))->toArray($user);

        $this->assertSame(route('tours.show', $tour), $payload['url']);
        $this->assertSame($tour->id, $payload['tour_id']);
    }
}
