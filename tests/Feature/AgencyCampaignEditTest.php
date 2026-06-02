<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AgencyCampaignEditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
    }

    public function test_agency_owner_can_view_edit_form(): void
    {
        [$user, $tour] = $this->seedAgencyAndTour();
        $campaign = $this->seedCampaign($tour);

        $this->actingAs($user)
            ->get(route('agency.campaigns.edit', $campaign))
            ->assertOk()
            ->assertSee($campaign->label)
            ->assertSee('Değişiklikleri Kaydet');
    }

    public function test_agency_owner_can_update_campaign_fields(): void
    {
        [$user, $tour] = $this->seedAgencyAndTour();
        $campaign = $this->seedCampaign($tour, [
            'label' => 'Eski Etiket',
            'discount_price' => 5000,
        ]);

        $this->actingAs($user)
            ->put(route('agency.campaigns.update', $campaign), [
                'tour_id' => $tour->id,
                'discount_price' => 7500,
                'label' => 'Yeni Yaz İndirimi',
                'starts_at' => now()->subDay()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDays(15)->format('Y-m-d\TH:i'),
                'is_active' => 1,
            ])
            ->assertRedirect(route('agency.campaigns.index'));

        $campaign->refresh();
        $this->assertSame('Yeni Yaz İndirimi', $campaign->label);
        $this->assertEqualsWithDelta(7500.0, (float) $campaign->discount_price, 0.01);
        $this->assertTrue((bool) $campaign->is_active);
    }

    public function test_agency_cannot_edit_another_agencies_campaign(): void
    {
        [$ownerUser, $ownerTour] = $this->seedAgencyAndTour('owner');
        [$intruderUser] = $this->seedAgencyAndTour('intruder');

        $campaign = $this->seedCampaign($ownerTour);

        $this->actingAs($intruderUser)
            ->get(route('agency.campaigns.edit', $campaign))
            ->assertForbidden();

        $this->actingAs($intruderUser)
            ->put(route('agency.campaigns.update', $campaign), [
                'tour_id' => $ownerTour->id,
                'discount_price' => 99,
                'label' => 'Hacked',
                'starts_at' => now()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertForbidden();

        $this->assertNotSame('Hacked', $campaign->fresh()->label);
    }

    public function test_cannot_switch_campaign_to_tour_owned_by_another_agency(): void
    {
        [$user, $myTour] = $this->seedAgencyAndTour('mine');
        [$_, $foreignTour] = $this->seedAgencyAndTour('foreign');

        $campaign = $this->seedCampaign($myTour);

        // Foreign tour'a transfer denemesi → 403
        $this->actingAs($user)
            ->put(route('agency.campaigns.update', $campaign), [
                'tour_id' => $foreignTour->id,
                'discount_price' => 100,
                'label' => 'Transfer Denemesi',
                'starts_at' => now()->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDay()->format('Y-m-d\TH:i'),
            ])
            ->assertForbidden();

        $this->assertSame($myTour->id, $campaign->fresh()->tour_id);
    }

    public function test_validation_rejects_invalid_dates(): void
    {
        [$user, $tour] = $this->seedAgencyAndTour();
        $campaign = $this->seedCampaign($tour);

        // Bitiş tarihi başlangıçtan önce
        $this->actingAs($user)
            ->put(route('agency.campaigns.update', $campaign), [
                'tour_id' => $tour->id,
                'discount_price' => 5000,
                'label' => 'X',
                'starts_at' => now()->addDays(10)->format('Y-m-d\TH:i'),
                'ends_at' => now()->addDays(5)->format('Y-m-d\TH:i'),
            ])
            ->assertSessionHasErrors('ends_at');
    }

    public function test_update_can_toggle_is_active_off(): void
    {
        [$user, $tour] = $this->seedAgencyAndTour();
        $campaign = $this->seedCampaign($tour, ['is_active' => true]);

        $this->actingAs($user)
            ->put(route('agency.campaigns.update', $campaign), [
                'tour_id' => $tour->id,
                'discount_price' => $campaign->discount_price,
                'label' => $campaign->label,
                'starts_at' => $campaign->starts_at->format('Y-m-d\TH:i'),
                'ends_at' => $campaign->ends_at->format('Y-m-d\TH:i'),
                // is_active checkbox işaretsiz → form'daki hidden field 0 gönderir
                'is_active' => 0,
            ])
            ->assertRedirect();

        $this->assertFalse((bool) $campaign->fresh()->is_active);
    }

    /**
     * @return array{0: User, 1: Tour}
     */
    private function seedAgencyAndTour(string $tag = 'a'): array
    {
        $agency = Agency::create([
            'name' => 'Agency ' . $tag,
            'slug' => 'agency-' . $tag . '-' . uniqid(),
            'email' => $tag . '-' . uniqid() . '@x.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $user = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Tour ' . $tag,
            'destination' => 'X',
            'description' => 'd',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(15),
            'is_active' => true,
        ]);

        return [$user, $tour];
    }

    private function seedCampaign(Tour $tour, array $overrides = []): Campaign
    {
        return Campaign::create(array_merge([
            'tour_id' => $tour->id,
            'discount_price' => 5000,
            'label' => 'Test Kampanya',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(10),
            'is_active' => true,
        ], $overrides));
    }
}
