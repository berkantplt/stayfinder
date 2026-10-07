<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Support\PricingBlocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkImportDedupePackagesCommandTest extends TestCase
{
    use RefreshDatabase;

    private function tour(Agency $agency, ?string $batch, array $blocks): Tour
    {
        return Tour::create([
            'agency_id' => $agency->id, 'title' => 'GAP Turu', 'destination' => 'Gaziantep',
            'price' => 9999, 'currency' => 'TRY', 'duration_days' => 4,
            'departure_date' => '2027-05-10', 'return_date' => '2027-05-13',
            'is_active' => true, 'import_batch' => $batch, 'pricing_blocks' => $blocks,
        ]);
    }

    private function dogru(): array
    {
        return ['hotel' => 'Bölge Otelleri', 'prices' => [
            'double_pp' => ['old' => null, 'new' => 9999], 'single' => ['old' => null, 'new' => 14999],
            'extra_bed' => ['old' => null, 'new' => 9999], 'child_3_5' => ['old' => null, 'new' => 4999], 'child_7_11' => ['old' => null, 'new' => 7249],
        ]];
    }

    /** Eski ayrıştırıcının bıraktığı kaymış kopya (masaüstü tablo). */
    private function kaymisKopya(): array
    {
        return ['hotel' => 'BÖLGE OTELLERİ', 'prices' => [
            'double_pp' => ['old' => null, 'new' => 9999], 'single' => ['old' => 14999, 'new' => 9999],
            'extra_bed' => ['old' => null, 'new' => 4999], 'child_3_5' => ['old' => null, 'new' => 7249],
        ]];
    }

    public function test_helper_drops_same_hotel_same_adult_price_but_keeps_real_packages(): void
    {
        $farkli = ['hotel' => 'Bölge Otelleri', 'prices' => ['double_pp' => ['old' => null, 'new' => 11999]]];
        $baskaOtel = ['hotel' => 'Lüks Otel', 'prices' => ['double_pp' => ['old' => null, 'new' => 9999]]];

        $result = PricingBlocks::dropDuplicatePackages([
            ['dates' => ['2027-05-10'], 'packages' => [$this->dogru(), $this->kaymisKopya(), $farkli, $baskaOtel]],
        ]);

        $this->assertSame(1, $result['dropped']);
        $this->assertSame(['Bölge Otelleri', 'Bölge Otelleri', 'Lüks Otel'], array_column($result['blocks'][0]['packages'], 'hotel'));
        $this->assertEquals(14999, $result['blocks'][0]['packages'][0]['prices']['single']['new'], 'İlk (doğru) paket kalır');
        $this->assertSame(['blocks' => null, 'dropped' => 0], PricingBlocks::dropDuplicatePackages(null));
    }

    public function test_command_fixes_only_the_batch_and_respects_dry(): void
    {
        $agency = Agency::create(['name' => 'Malitur', 'slug' => 'malitur', 'is_active' => true]);
        $hedef = $this->tour($agency, 'b1', [['dates' => ['2027-05-10'], 'packages' => [$this->dogru(), $this->kaymisKopya()]]]);
        $temiz = $this->tour($agency, 'b1', [['dates' => ['2027-05-10'], 'packages' => [$this->dogru()]]]);
        $baskaParti = $this->tour($agency, 'b2', [['dates' => ['2027-05-10'], 'packages' => [$this->dogru(), $this->kaymisKopya()]]]);

        $this->artisan('app:bulk-import-dedupe-packages', ['--batch' => 'b1', '--dry' => true])
            ->expectsOutputToContain('1 turda 1 kopya paket bulundu')
            ->assertSuccessful();
        $this->assertCount(2, $hedef->fresh()->pricing_blocks[0]['packages'], 'Kuru çalışma yazmaz');

        $this->artisan('app:bulk-import-dedupe-packages', ['--batch' => 'b1'])
            ->expectsOutputToContain('1 turda 1 kopya paket silindi')
            ->assertSuccessful();

        $this->assertCount(1, $hedef->fresh()->pricing_blocks[0]['packages']);
        $this->assertEquals(14999, $hedef->fresh()->pricing_blocks[0]['packages'][0]['prices']['single']['new']);
        $this->assertCount(1, $temiz->fresh()->pricing_blocks[0]['packages']);
        $this->assertCount(2, $baskaParti->fresh()->pricing_blocks[0]['packages'], 'Başka parti dokunulmaz');

        $this->artisan('app:bulk-import-dedupe-packages')->assertFailed();
    }
}
