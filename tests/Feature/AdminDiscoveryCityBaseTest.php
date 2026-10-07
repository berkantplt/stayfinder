<?php

namespace Tests\Feature;

use App\Jobs\BuildDiscoveryCityBaseJob;
use App\Models\DiscoveryCityBase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** Admin "Keşif Tabanları": yalnız admin görür; sil ve yeniden üret çalışır. */
class AdminDiscoveryCityBaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    }

    private function makeBase(array $attrs = []): DiscoveryCityBase
    {
        return DiscoveryCityBase::create(array_merge([
            'normalized_city' => 'paris',
            'display_name' => 'Paris',
            'country' => 'Fransa',
            'base_payload' => [
                'destination' => ['name' => 'Paris', 'country' => 'Fransa', 'summary' => 'Özet'],
                'highlights' => [['name' => 'Eyfel Kulesi'], ['name' => 'Louvre']],
                'museums' => [['name' => 'Orsay']],
            ],
            'model' => 'gpt-5.4-mini',
            'hit_count' => 3,
            'generated_at' => now(),
        ], $attrs));
    }

    public function test_admin_listeyi_gorur(): void
    {
        $this->makeBase();

        $this->actingAs($this->admin)->get(route('admin.discovery-city-bases.index'))
            ->assertOk()
            ->assertSee('Keşif Tabanları')
            ->assertSee('Paris')
            ->assertSee('3 öğe');
    }

    public function test_admin_olmayan_kullanici_giremez(): void
    {
        $ziyaretci = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->actingAs($ziyaretci)->get(route('admin.discovery-city-bases.index'))->assertForbidden();
    }

    public function test_bayat_filtresi_yalnizca_eski_tabanlari_listeler(): void
    {
        $this->makeBase();
        $this->makeBase([
            'normalized_city' => 'roma', 'display_name' => 'Roma', 'country' => 'İtalya',
            'generated_at' => now()->subDays(DiscoveryCityBase::MAX_AGE_DAYS + 5),
        ]);

        $this->actingAs($this->admin)->get(route('admin.discovery-city-bases.index', ['stale' => 1]))
            ->assertOk()
            ->assertSee('Roma')
            ->assertDontSee('Fransa');
    }

    public function test_admin_tabani_siler(): void
    {
        $base = $this->makeBase();

        $this->actingAs($this->admin)->delete(route('admin.discovery-city-bases.destroy', $base))
            ->assertRedirect(route('admin.discovery-city-bases.index'));

        $this->assertDatabaseMissing('discovery_city_bases', ['id' => $base->id]);
    }

    public function test_admin_yeniden_uretimi_kayit_anahtariyla_kuyruga_alir(): void
    {
        $base = $this->makeBase();

        $this->actingAs($this->admin)
            ->from(route('admin.discovery-city-bases.index'))
            ->post(route('admin.discovery-city-bases.regenerate', $base))
            ->assertRedirect(route('admin.discovery-city-bases.index'));

        Queue::assertPushed(BuildDiscoveryCityBaseJob::class, fn ($job) => $job->cityInput === 'paris');
    }
}
