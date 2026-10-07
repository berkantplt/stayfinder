<?php

namespace Tests\Feature;

use App\Services\Tours\TourPayloadBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Panel formu yükünü kayda çeviren servis: controller'dan taşınan mantığın
 * (tarih/fiyat, matris, URL temizliği, vize, durak şehri) birebir korunduğu.
 */
class TourPayloadBuilderTest extends TestCase
{
    use RefreshDatabase;

    private TourPayloadBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = app(TourPayloadBuilder::class);
    }

    public function test_visa_flags_map_three_options_to_two_columns(): void
    {
        $this->assertSame([false, false], $this->builder->visaFlags('0'));
        $this->assertSame([true, false], $this->builder->visaFlags('1'));
        $this->assertSame([true, true], $this->builder->visaFlags('kapida'));
        $this->assertSame([null, null], $this->builder->visaFlags(null));
        $this->assertSame([null, null], $this->builder->visaFlags('evet'));
    }

    public function test_clean_tour_url_strips_tracking_parameters_only(): void
    {
        $clean = $this->builder->cleanTourUrl('https://acenta.com/tur/gap?utm_source=x&gclid=123&sayfa=2&_gl=abc');

        $this->assertSame('https://acenta.com/tur/gap?sayfa=2', $clean);
        $this->assertNull($this->builder->cleanTourUrl('  '));
    }

    public function test_package_matrix_derives_start_price_and_dates(): void
    {
        $options = [[
            'price' => '',
            'departure_dates' => ['2027-06-24', '2027-06-10'],
            'packages' => [
                ['hotel' => 'Otel A', 'double_pp' => ['old' => '14000', 'new' => '12500'], 'single' => ['old' => '', 'new' => '16000']],
                ['hotel' => 'Otel B', 'double_pp' => ['old' => '', 'new' => '11900'], 'extra_bed' => ['old' => '', 'new' => '9000']],
            ],
        ]];

        $derived = $this->builder->pricingOptionsWithDerivedPrices($options);
        $this->assertEquals(11900, $derived[0]['price'], 'Kapak fiyatı en düşük double_pp olmalı (ilave yatak değil)');

        $dates = $this->builder->prepareValidatedDatePrices($derived, [], null, 4);
        $this->assertCount(2, $dates, 'Tarihler kronolojik sıralanır');
        $this->assertSame('2027-06-10', $dates[0]['departure_date']);
        $this->assertSame('2027-06-13', $dates[0]['return_date']);
        $this->assertEquals(11900, $dates[0]['price']);
        $this->assertEquals(11900, $this->builder->resolveBasePrice($dates));

        $blocks = $this->builder->buildPricingBlocks($derived);
        $this->assertCount(1, $blocks);
        $this->assertSame(['2027-06-10', '2027-06-24'], $blocks[0]['dates']);
        $this->assertEquals(12500, $blocks[0]['packages'][0]['prices']['double_pp']['new']);
        $this->assertNull($blocks[0]['packages'][0]['prices']['single']['old']);
    }

    public function test_duplicate_date_across_blocks_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        $this->builder->prepareValidatedDatePrices([
            ['price' => '5000', 'departure_dates' => ['2027-07-01']],
            ['price' => '6000', 'departure_dates' => ['2027-07-01']],
        ], [], null, 2);
    }

    public function test_blocks_without_matrix_are_not_stored_and_flat_price_requires_dates(): void
    {
        $this->assertNull($this->builder->buildPricingBlocks([
            ['price' => '5000', 'departure_dates' => ['2027-07-01'], 'packages' => []],
        ]));

        $this->expectException(ValidationException::class);
        $this->builder->prepareValidatedDatePrices([['price' => '5000', 'departure_dates' => []]], [], null, 2);
    }

    public function test_stop_cities_are_canonical_and_exclude_departure(): void
    {
        $this->assertSame(['Bolu', 'Ankara'], $this->builder->normalizeStopCities(['bolu', 'İstanbul', 'ANKARA', 'Atlantis', 'Bolu'], 'İstanbul'));
        $this->assertNull($this->builder->normalizeStopCities(['İstanbul'], 'İstanbul'));
        $this->assertNull($this->builder->normalizeStopCities('bolu', 'İstanbul'));
    }

    public function test_itinerary_drops_empty_days(): void
    {
        $days = $this->builder->normalizeItinerary([
            ['title' => '1. Gün', 'content' => 'Varış'],
            ['title' => '', 'content' => ''],
            'bozuk',
            ['title' => '', 'content' => 'Sadece içerik'],
        ]);

        $this->assertSame([
            ['title' => '1. Gün', 'content' => 'Varış'],
            ['title' => '', 'content' => 'Sadece içerik'],
        ], $days);
        $this->assertNull($this->builder->normalizeItinerary([]));
    }

    public function test_primary_date_is_first_upcoming_departure(): void
    {
        $dates = [
            ['departure_date' => '2020-01-01', 'return_date' => '2020-01-03', 'price' => 100.0],
            ['departure_date' => '2099-01-01', 'return_date' => '2099-01-03', 'price' => 200.0],
        ];
        $this->assertSame('2099-01-01', $this->builder->resolvePrimaryDate($dates)['departure_date']);

        $past = [$dates[0]];
        $this->assertSame('2020-01-01', $this->builder->resolvePrimaryDate($past)['departure_date']);
    }
}
