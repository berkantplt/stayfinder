<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkImportCleanupCommandTest extends TestCase
{
    use RefreshDatabase;

    private function tour(Agency $agency, string $title, ?string $batch): Tour
    {
        return Tour::create([
            'agency_id' => $agency->id, 'title' => $title, 'destination' => 'Antalya',
            'price' => 1000, 'currency' => 'TRY', 'duration_days' => 2,
            'departure_date' => '2027-06-01', 'return_date' => '2027-06-02',
            'is_active' => true, 'import_batch' => $batch,
        ]);
    }

    public function test_archives_only_the_given_batch(): void
    {
        $imported = Agency::create(['name' => 'Malitur', 'slug' => 'malitur', 'is_active' => true, 'import_batch' => 'b1']);
        $manual = Agency::create(['name' => 'Elle', 'slug' => 'elle', 'is_active' => true]);
        $t1 = $this->tour($imported, 'A', 'b1');
        $t2 = $this->tour($imported, 'B', 'b1');
        $other = $this->tour($manual, 'C', null);
        $otherBatch = $this->tour($manual, 'D', 'b2');

        $this->artisan('app:bulk-import-cleanup', ['--batch' => 'b1', '--force' => true])
            ->expectsOutputToContain('2 tur arşivlendi, 1 acenta arşivlendi')
            ->assertSuccessful();

        $this->assertSoftDeleted('tours', ['id' => $t1->id]);
        $this->assertSoftDeleted('tours', ['id' => $t2->id]);
        $this->assertSoftDeleted('agencies', ['id' => $imported->id]);
        $this->assertNull($other->fresh()->deleted_at);
        $this->assertNull($otherBatch->fresh()->deleted_at);
        $this->assertNull($manual->fresh()->deleted_at);
    }

    public function test_tours_only_keeps_agency_accounts(): void
    {
        $agency = Agency::create(['name' => 'Malitur', 'slug' => 'malitur', 'is_active' => true, 'import_batch' => 'b1']);
        $tour = $this->tour($agency, 'A', 'b1');

        $this->artisan('app:bulk-import-cleanup', ['--batch' => 'b1', '--tours-only' => true, '--force' => true])
            ->expectsOutputToContain('1 tur arşivlendi.')
            ->assertSuccessful();

        $this->assertSoftDeleted('tours', ['id' => $tour->id]);
        $this->assertNull($agency->fresh()->deleted_at);
    }

    public function test_empty_batch_and_missing_option(): void
    {
        $this->artisan('app:bulk-import-cleanup', ['--batch' => 'yok', '--force' => true])
            ->expectsOutputToContain('arşivlenecek kayıt yok')
            ->assertSuccessful();

        $this->artisan('app:bulk-import-cleanup')->assertFailed();
    }
}
