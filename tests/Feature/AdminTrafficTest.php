<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminTrafficTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Agency $agency;

    private Tour $clickedTour;

    private Tour $quietTour;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();

        $this->admin = User::factory()->create(['role' => 'admin']);

        $this->agency = Agency::create([
            'name' => 'Trafik Acenta',
            'slug' => 'trafik-acenta',
            'email' => 'trafik@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $this->clickedTour = $this->makeTour('Tıklanan Tur', clicks: 12, views: 100);
        $this->quietTour = $this->makeTour('Sessiz Tur', clicks: 0, views: 0);
    }

    private function makeTour(string $title, int $clicks, int $views): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'title' => $title,
            'destination' => 'İzmir',
            'description' => 'Trafik testi.',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(13),
            'is_active' => true,
            'tour_url' => 'https://example.com/tur',
            'clicks_count' => $clicks,
            'views_count' => $views,
        ]);
    }

    public function test_dashboard_stat_boxes_point_at_filtered_targets(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        // Tıklama ve görüntülenme kutuları artık Trafik sayfasına gider
        $response->assertSee(route('admin.traffic', ['metric' => 'clicks']), false);
        $response->assertSee(route('admin.traffic', ['metric' => 'views']), false);
        // Aktif Tur kutusu filtreli tur listesine gider
        $response->assertSee(route('admin.tours', ['status' => 'active']), false);
    }

    public function test_traffic_page_lists_only_tours_with_traffic(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.traffic', ['metric' => 'clicks']));

        $response->assertOk();
        $response->assertSee('Tıklanan Tur');
        $response->assertDontSee('Sessiz Tur');
    }

    public function test_lifetime_totals_match_tour_counters(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.traffic'));

        $response->assertOk();
        $totals = $response->viewData('totals');

        // Dashboard kutusuyla aynı kaynak: tours.clicks_count / views_count
        $this->assertSame(12, $totals['clicks']);
        $this->assertSame(100, $totals['views']);
        $this->assertSame(12.0, $totals['ctr']);
        $this->assertSame(1, $totals['tours_with_traffic']);
    }

    public function test_ranged_mode_counts_raw_rows_and_ignores_older_ones(): void
    {
        DB::table('tour_clicks')->insert([
            ['tour_id' => $this->clickedTour->id, 'agency_id' => $this->agency->id, 'ip_address' => '1.1.1.1', 'clicked_at' => now()->subDays(2)],
            ['tour_id' => $this->clickedTour->id, 'agency_id' => $this->agency->id, 'ip_address' => '1.1.1.2', 'clicked_at' => now()->subDays(40)],
        ]);
        DB::table('tour_views')->insert([
            ['tour_id' => $this->clickedTour->id, 'session_id' => 'yeni', 'viewed_at' => now()->subDays(1)],
            ['tour_id' => $this->clickedTour->id, 'session_id' => 'eski', 'viewed_at' => now()->subDays(40)],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.traffic', ['range' => '7']));

        $response->assertOk();
        $totals = $response->viewData('totals');

        $this->assertSame(1, $totals['clicks']);
        $this->assertSame(1, $totals['views']);

        $rows = $response->viewData('tours');
        $this->assertCount(1, $rows->items());
        $this->assertSame(1, (int) $rows->items()[0]->range_clicks);
    }

    public function test_ranged_mode_hides_tour_whose_traffic_is_outside_the_window(): void
    {
        DB::table('tour_clicks')->insert([
            ['tour_id' => $this->clickedTour->id, 'agency_id' => $this->agency->id, 'ip_address' => '1.1.1.3', 'clicked_at' => now()->subDays(40)],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.traffic', ['range' => '7']));

        $response->assertOk();
        $response->assertSee('Bu aralıkta trafik alan tur yok.');
    }

    public function test_tour_list_can_filter_and_sort_by_clicks(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('admin.tours', ['traffic' => 'clicked', 'sort' => 'clicks_desc']));

        $response->assertOk();
        $tours = $response->viewData('tours');

        $this->assertCount(1, $tours->items());
        $this->assertSame('Tıklanan Tur', $tours->items()[0]->title);
    }

    public function test_tour_traffic_detail_page_renders(): void
    {
        DB::table('tour_clicks')->insert([
            ['tour_id' => $this->clickedTour->id, 'agency_id' => $this->agency->id, 'ip_address' => '9.9.9.9', 'clicked_at' => now()->subDays(3)],
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.traffic.show', [$this->clickedTour, 'days' => 30]));

        $response->assertOk();
        $response->assertSee('Tıklanan Tur');
        $this->assertSame(1, $response->viewData('rangeClicks'));
        // IP son okteti maskelenir
        $response->assertSee('9.9.9.x');
        $response->assertDontSee('9.9.9.9');
    }

    public function test_non_admin_cannot_reach_traffic_page(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAs($customer)->get(route('admin.traffic'))->assertForbidden();
    }
}
