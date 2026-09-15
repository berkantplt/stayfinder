<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * D7 — Yorum yazma saatte 5 ile sınırlı (429); yorum kartında yorumcunun
 * üyelik tarihi ve toplam yorum sayısı güven işareti olarak gösterilir.
 */
class ReviewThrottleTrustTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create([
            'name' => 'Yorum Acenta', 'slug' => 'yorum-acenta', 'email' => 'yorum@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
    }

    private function tur(int $i): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id, 'title' => 'Yorum Turu '.$i, 'slug' => 'yorum-turu-'.$i, 'destination' => 'Kapadokya',
            'description' => 'x', 'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3,
            'departure_date' => today()->addDays(20), 'is_active' => true,
        ]);
    }

    public function test_saatte_besinci_yorumdan_sonra_429(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);

        foreach (range(1, 5) as $i) {
            $this->actingAs($user)->post(route('reviews.store', $this->tur($i)), ['rating' => 5, 'comment' => 'Çok güzel bir turdu, tavsiye ederim.'])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($user)->post(route('reviews.store', $this->tur(6)), ['rating' => 5, 'comment' => 'Çok güzel bir turdu, tavsiye ederim.'])
            ->assertStatus(429);

        $this->assertSame(5, Review::count());
    }

    public function test_yorum_kartinda_uyelik_ve_yorum_sayisi_gorunur(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR, 'created_at' => '2026-03-10 10:00:00', 'name' => 'Ayşe Y.']);
        $tour1 = $this->tur(1);
        $tour2 = $this->tur(2);
        Review::create(['user_id' => $user->id, 'tour_id' => $tour1->id, 'rating' => 4, 'comment' => 'Güzeldi, beğendik.']);
        Review::create(['user_id' => $user->id, 'tour_id' => $tour2->id, 'rating' => 5, 'comment' => 'Harikaydı, tekrar gideriz.']);

        $this->get(route('tours.show', $tour1))->assertOk()
            ->assertSee('Üye: Mar 2026')
            ->assertSee('2 yorum');
    }
}
