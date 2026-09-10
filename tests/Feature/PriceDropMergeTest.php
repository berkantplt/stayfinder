<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\PriceDropNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Fiyat düştü bildirimi: eski/yeni fiyat + yüzde; aynı gün ikinci düşüş tek bildirimde birleşir. */
class PriceDropMergeTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $agency = Agency::create([
            'name' => 'Bildirim Acenta', 'slug' => 'bildirim-acenta', 'email' => 'b@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
        $this->tour = Tour::create([
            'agency_id' => $agency->id, 'title' => 'Kapadokya Turu', 'destination' => 'Kapadokya', 'description' => 'x',
            'price' => 10000, 'currency' => 'TRY', 'duration_days' => 3, 'departure_date' => today()->addDays(20), 'is_active' => true,
        ]);
        $this->user = User::factory()->create();
        $this->user->favoriteTours()->attach($this->tour->id);
    }

    public function test_bildirim_eski_yeni_fiyat_ve_yuzde_tasir(): void
    {
        $this->tour->update(['price' => 9000]);

        $this->assertSame(1, $this->user->notifications()->count());
        $data = $this->user->notifications()->first()->data;
        $this->assertSame(10000.0, (float) $data['old_price']);
        $this->assertSame(9000.0, (float) $data['new_price']);
        $this->assertSame(10, $data['percent']);
        $this->assertStringContainsString('10.000 ₺ yerine 9.000 ₺', $data['message']);
        $this->assertStringContainsString('%10 düştü', $data['message']);
    }

    public function test_ayni_gun_ikinci_dusus_tek_bildirimde_birlesir(): void
    {
        $this->tour->update(['price' => 9000]);
        $this->tour->refresh()->update(['price' => 8000]);

        $this->assertSame(1, $this->user->notifications()->count());
        $data = $this->user->notifications()->first()->data;
        $this->assertSame(10000.0, (float) $data['old_price'], 'ilk eski fiyat korunur');
        $this->assertSame(8000.0, (float) $data['new_price']);
        $this->assertSame(20, $data['percent']);
    }

    public function test_okunmus_bildirim_varken_yeni_dusus_yeni_bildirim_acar(): void
    {
        $this->tour->update(['price' => 9000]);
        $this->user->notifications()->first()->markAsRead();
        $this->tour->refresh()->update(['price' => 8000]);

        $this->assertSame(2, $this->user->notifications()->count());
    }

    public function test_eski_cagri_bicimi_eski_fiyatsiz_calisir(): void
    {
        $payload = (new PriceDropNotification($this->tour))->toArray($this->user);
        $this->assertNull($payload['old_price']);
        $this->assertStringContainsString('fiyatı düştü', $payload['message']);
    }
}
