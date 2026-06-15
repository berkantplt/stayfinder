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

class AnalyticsPruningTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();

        $this->agency = Agency::create([
            'name' => 'Analitik Acenta',
            'slug' => 'analitik-acenta',
            'email' => 'analitik@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $this->tour = Tour::create([
            'agency_id' => $this->agency->id,
            'title' => 'Analitik Turu',
            'destination' => 'İzmir',
            'description' => 'Pruning testi.',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(13),
            'is_active' => true,
            'tour_url' => 'https://example.com/tur',
        ]);
    }

    public function test_old_rows_are_pruned_and_recent_rows_are_kept(): void
    {
        // tour_views: 180 gün retention
        DB::table('tour_views')->insert([
            ['tour_id' => $this->tour->id, 'session_id' => 'old', 'viewed_at' => now()->subDays(200)],
            ['tour_id' => $this->tour->id, 'session_id' => 'new', 'viewed_at' => now()->subDays(10)],
        ]);

        // tour_clicks: 180 gün
        DB::table('tour_clicks')->insert([
            ['tour_id' => $this->tour->id, 'agency_id' => $this->agency->id, 'ip_address' => '1.1.1.1', 'clicked_at' => now()->subDays(200)],
            ['tour_id' => $this->tour->id, 'agency_id' => $this->agency->id, 'ip_address' => '1.1.1.2', 'clicked_at' => now()->subDays(5)],
        ]);

        // ai_search_logs: 90 gün
        DB::table('ai_search_logs')->insert([
            ['raw_query' => 'eski arama', 'created_at' => now()->subDays(100), 'updated_at' => now()->subDays(100)],
            ['raw_query' => 'yeni arama', 'created_at' => now()->subDays(30), 'updated_at' => now()->subDays(30)],
        ]);

        // price_histories: 365 gün (Tour create'i 1 satır ekledi — bugünün kaydı)
        DB::table('price_histories')->insert([
            ['tour_id' => $this->tour->id, 'price' => 4000, 'recorded_at' => now()->subDays(400)->toDateString(), 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->artisan('app:prune-analytics')->assertExitCode(0);

        $this->assertSame(1, DB::table('tour_views')->count());
        $this->assertSame('new', DB::table('tour_views')->value('session_id'));

        $this->assertSame(1, DB::table('tour_clicks')->count());
        $this->assertSame('1.1.1.2', DB::table('tour_clicks')->value('ip_address'));

        $this->assertSame(1, DB::table('ai_search_logs')->count());
        $this->assertSame('yeni arama', DB::table('ai_search_logs')->value('raw_query'));

        // 400 günlük silindi, bugünkü (Tour create kaydı) durdu
        $this->assertSame(1, DB::table('price_histories')->count());
    }

    public function test_tour_view_increments_lifetime_counter(): void
    {
        $this->get(route('tours.show', $this->tour));

        $this->assertSame(1, (int) $this->tour->fresh()->views_count);
        $this->assertSame(1, DB::table('tour_views')->count());

        // Sayaç her zaman ham satırla birlikte artar (testte session her istekte
        // değiştiği için dedup davranışı burada doğrulanamaz)
        $this->get(route('tours.show', $this->tour));
        $this->assertSame(
            DB::table('tour_views')->count(),
            (int) $this->tour->fresh()->views_count
        );
    }

    public function test_tour_click_redirect_increments_lifetime_counter(): void
    {
        $this->get(route('tour.redirect', $this->tour))->assertRedirect('https://example.com/tur');

        $this->assertSame(1, (int) $this->tour->fresh()->clicks_count);
        $this->assertSame(1, DB::table('tour_clicks')->count());
    }

    public function test_admin_dashboard_totals_come_from_counters(): void
    {
        // Sayaçlar dolu ama ham tablolar boş (pruning sonrası senaryosu)
        DB::table('tours')->where('id', $this->tour->id)->update([
            'views_count' => 1500,
            'clicks_count' => 700,
        ]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('1500', false)
            ->assertSee('700', false);
    }
}
