<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategorySubscription;
use App\Models\Announcement;
use App\Models\Category;
use App\Models\Tour;
use App\Services\DestinationOriginResolver;
use App\Services\TourImage\TourImageService;
use App\Services\TourImport\TourUrlImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BulkImportToursCommandTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private Category $gap;

    private Category $sharm;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();

        $parent = Category::create(['name' => 'Yurt Dışı Turları', 'slug' => 'yurt-disi-turlari', 'is_active' => true, 'monthly_price' => 0]);
        $this->gap = Category::create(['name' => 'GAP Turları', 'slug' => 'gap-turlari', 'parent_id' => $parent->id, 'is_active' => true, 'monthly_price' => 1500]);
        $this->sharm = Category::create(['name' => 'Sharm El Sheikh Turları', 'slug' => 'sharm-el-sheikh-turlari', 'parent_id' => $parent->id, 'is_active' => true, 'monthly_price' => 1500]);

        $this->agency = Agency::create([
            'name' => 'Malitur', 'slug' => 'malitur', 'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED, 'legacy_category_access' => false,
        ]);
        foreach ([$this->gap, $this->sharm] as $category) {
            AgencyCategorySubscription::create([
                'agency_id' => $this->agency->id, 'category_id' => $category->id,
                'monthly_price' => 1500, 'status' => AgencyCategorySubscription::STATUS_ACTIVE,
                'started_at' => now()->toDateString(), 'expires_at' => now()->addYear()->toDateString(),
                'extra_tour_slots' => 8,
            ]);
        }

        // Yurt içi/dışı: Gaziantep yurt içi, Sharm yurt dışı — testi DB/LLM'den bağımsız kıl
        $this->mock(DestinationOriginResolver::class)
            ->shouldReceive('isInternational')
            ->andReturnUsing(fn (?string $d) => str_contains((string) $d, 'Sharm') ? true : false);

        // Görsel indirme ağa çıkmasın
        $this->mock(TourImageService::class)
            ->shouldReceive('downloadAndStore')
            ->andReturnUsing(fn (string $url) => '/storage/tours/'.md5($url).'.jpg');

        $this->file = sys_get_temp_dir().'/bulk-tours-'.uniqid().'.txt';
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        @unlink($this->file.'.tsv');
        parent::tearDown();
    }

    private function writeList(array $lines): void
    {
        file_put_contents($this->file, "# yorum\n\n".implode("\n", $lines)."\n");
    }

    private function fixture(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Uçaklı GAP Turu 3 Gece Otel Konaklamalı',
            'destination' => 'Gaziantep',
            'description' => '<p>Güneydoğu lezzet turu</p>',
            'price' => 12500,
            'currency' => 'TRY',
            'duration_days' => 4,
            'duration_nights' => 3,
            'transport_type' => 'ucak',
            'included' => "Uçak bileti\nOtel",
            'excluded' => 'Ekstralar',
            'departure_points' => null,
            'departure_city' => 'İstanbul',
            'stop_cities' => ['Ankara'],
            'itinerary' => [['title' => '1. Gün', 'content' => 'Gaziantep'], ['title' => '', 'content' => '']],
            'hotel_info' => 'Şehir merkezinde 4 yıldız',
            'extras' => null,
            'cancellation_policy' => null,
            'guide_info' => null,
            'frequency' => null,
            'pricing_blocks' => [[
                'dates' => ['2027-05-10'],
                'packages' => [[
                    'hotel' => 'Otel A',
                    'prices' => [
                        'double_pp' => ['old' => 14000, 'new' => 12500],
                        'single' => ['old' => null, 'new' => 16000],
                    ],
                ]],
            ]],
            'departure_dates' => ['2027-05-10', '2027-05-24'],
            'image_urls' => ['https://malitur.com/a.jpg', 'https://malitur.com/b.jpg'],
            'warnings' => ['Örnek uyarı'],
        ], $overrides);
    }

    private function mockImporter(array $fixture, int $times = 1): void
    {
        $this->mock(TourUrlImporter::class)
            ->shouldReceive('import')
            ->times($times)
            ->andReturn($fixture);
    }

    public function test_imports_tour_like_the_panel_and_suppresses_announcement(): void
    {
        $this->mockImporter($this->fixture());
        $this->writeList(['malitur;gap-turlari;https://malitur.com/ucakli-gap-turu-3-gece-istanbul-cikisli?utm_source=x']);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0])
            ->expectsOutputToContain('[eklendi]')
            ->expectsOutputToContain('Örnek uyarı')
            ->assertSuccessful();

        $tour = Tour::with('dates')->firstOrFail();
        $this->assertSame($this->agency->id, $tour->agency_id);
        $this->assertSame($this->gap->id, $tour->category_id);
        $this->assertSame('b1', $tour->import_batch);
        $this->assertSame('https://malitur.com/ucakli-gap-turu-3-gece-istanbul-cikisli', $tour->tour_url, 'utm temizlenir');
        $this->assertSame('Uçaklı GAP Turu 3 Gece Otel Konaklamalı', $tour->title);
        $this->assertSame('İstanbul', $tour->departure_city);
        $this->assertSame(['Ankara'], $tour->stop_cities);
        $this->assertSame(4, $tour->duration_days);
        $this->assertSame(3, $tour->duration_nights);
        $this->assertSame('ucak', $tour->transport_type);
        $this->assertEquals(12500, (float) $tour->price);
        $this->assertSame('2027-05-10', $tour->departure_date->toDateString());
        $this->assertSame('2027-05-13', $tour->return_date->toDateString());
        $this->assertCount(2, $tour->dates, 'Bloğu olmayan tarihe ilk bloğun matrisi şablon uygulanır (panel JS kuralı)');
        $this->assertEquals(12500, (float) $tour->dates->first(fn ($d) => $d->departure_date->toDateString() === '2027-05-24')->price);
        $this->assertCount(1, $tour->pricing_blocks);
        $this->assertSame(['2027-05-10', '2027-05-24'], $tour->pricing_blocks[0]['dates']);
        $this->assertEquals(12500, $tour->pricing_blocks[0]['packages'][0]['prices']['double_pp']['new']);
        $this->assertSame([['title' => '1. Gün', 'content' => 'Gaziantep']], $tour->itinerary);
        $this->assertFalse($tour->is_international);
        $this->assertFalse($tour->requires_visa, 'Yurt içi → vizesiz');
        $this->assertFalse($tour->visa_on_arrival);
        $this->assertTrue($tour->is_active);
        $this->assertCount(2, $tour->images);
        $this->assertSame($tour->images[0], $tour->image);

        $this->assertSame(0, Announcement::count(), 'Toplu aktarımda "Yeni Tur Eklendi!" duyurusu üretilmez');
    }

    public function test_existing_source_url_is_skipped_so_rerun_resumes(): void
    {
        $this->mockImporter($this->fixture(), 1);
        $this->writeList(['malitur;gap-turlari;https://malitur.com/gap-turu']);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0])->assertSuccessful();
        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0])
            ->expectsOutputToContain('zaten var')
            ->assertSuccessful();

        $this->assertSame(1, Tour::count());
    }

    public function test_check_validates_list_without_touching_network(): void
    {
        $this->mock(TourUrlImporter::class)->shouldNotReceive('import');
        $this->writeList([
            'malitur;gap-turlari;https://malitur.com/a',
            'yok-acenta;gap-turlari;https://malitur.com/b',
            'malitur;yurt-disi-turlari;https://malitur.com/c',
            'malitur;gap-turlari;https://malitur.com/a',
            'malitur;gap-turlari;bozuk-url',
            'malitur;gap-turlari;https://malitur.com/d;evet',
        ]);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--check' => true])
            ->expectsOutputToContain('acenta yok: yok-acenta')
            ->expectsOutputToContain('üst kategori')
            ->expectsOutputToContain('aynı URL')
            ->expectsOutputToContain('geçersiz URL')
            ->expectsOutputToContain("vize '0', '1' veya 'kapida'")
            ->assertFailed();

        $this->writeList(['malitur;gap-turlari;https://malitur.com/a']);
        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--check' => true])
            ->expectsOutputToContain('Liste geçerli')
            ->assertSuccessful();
        $this->assertSame(0, Tour::count());
    }

    public function test_dry_run_reads_but_does_not_write(): void
    {
        $this->mockImporter($this->fixture());
        $this->writeList(['malitur;gap-turlari;https://malitur.com/gap-turu']);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--dry' => true, '--sleep' => 0])
            ->expectsOutputToContain('[kuru]')
            ->expectsOutputToContain('2 tarih')
            ->assertSuccessful();

        $this->assertSame(0, Tour::count());
    }

    public function test_page_without_dates_fails_that_line_and_continues(): void
    {
        $this->mock(TourUrlImporter::class)
            ->shouldReceive('import')
            ->twice()
            ->andReturnUsing(fn (string $url) => str_contains($url, 'tarihsiz')
                ? $this->fixture(['pricing_blocks' => [], 'departure_dates' => []])
                : $this->fixture());
        $this->writeList([
            'malitur;gap-turlari;https://malitur.com/tarihsiz-tur',
            'malitur;gap-turlari;https://malitur.com/gap-turu',
        ]);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0])
            ->expectsOutputToContain('[başarısız]')
            ->expectsOutputToContain('kalkış tarihi bulunamadı')
            ->expectsOutputToContain('1 eklendi')
            ->assertFailed();

        $this->assertSame(1, Tour::count());
        $this->assertSame('https://malitur.com/gap-turu', Tour::first()->tour_url);
    }

    public function test_block_with_only_child_prices_uses_cover_price_not_child_price(): void
    {
        $this->mockImporter($this->fixture([
            'price' => 9999,
            'pricing_blocks' => [
                ['dates' => ['2027-05-10'], 'packages' => [['hotel' => 'Bölge Otelleri', 'prices' => ['double_pp' => ['old' => null, 'new' => 10999]]]]],
                ['dates' => ['2027-06-01'], 'packages' => [['hotel' => 'Bölge Otelleri', 'prices' => ['child_0_2' => ['old' => null, 'new' => 850]]]]],
            ],
            'departure_dates' => ['2027-05-10', '2027-06-01'],
        ]));
        $this->writeList(['malitur;gap-turlari;https://malitur.com/gap-turu']);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0])->assertSuccessful();

        $tour = Tour::with('dates')->firstOrFail();
        $this->assertEquals(9999, (float) $tour->price, 'Bebek fiyatı kapak fiyatı olmaz; sayfanın başlangıç fiyatı kullanılır');
        $this->assertEquals(9999, (float) $tour->dates->first(fn ($d) => $d->departure_date->toDateString() === '2027-06-01')->price);
        $this->assertEquals(10999, (float) $tour->dates->first(fn ($d) => $d->departure_date->toDateString() === '2027-05-10')->price);
    }

    public function test_extras_table_and_outlier_blocks_are_dropped_with_their_dates(): void
    {
        $matrix = fn (int $price) => [['hotel' => 'Bölge Otelleri', 'prices' => ['double_pp' => ['old' => null, 'new' => $price], 'child_3_5' => ['old' => null, 'new' => 4999]]]];
        $this->mockImporter($this->fixture([
            'price' => 850,
            'pricing_blocks' => [
                ['dates' => ['2027-05-10', '2027-05-17'], 'packages' => $matrix(10999)],
                ['dates' => ['2027-06-01'], 'packages' => $matrix(9999)],
                // İçe aktarıcının paket sandığı "Ekstra Tur ve Aktiviteler" tablosu (Malitur GAP vakası)
                ['dates' => ['2027-11-23'], 'packages' => [['hotel' => 'Ekstra Tur ve Aktiviteler', 'prices' => ['double_pp' => ['old' => null, 'new' => 850], 'single' => ['old' => null, 'new' => 450]]]]],
                // Etiketi masum ama fiyatı medyanın %25'inin altında → aykırı
                ['dates' => ['2027-12-05'], 'packages' => $matrix(700)],
            ],
            'departure_dates' => ['2027-05-10', '2027-05-17', '2027-06-01', '2027-11-23', '2027-12-05'],
        ]));
        $this->writeList(['malitur;gap-turlari;https://malitur.com/gap-turu']);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0])
            ->expectsOutputToContain('2027-11-23: yalnız ekstra tur/aktivite fiyatı içeren blok atıldı')
            ->expectsOutputToContain('aykırı fiyat bloğu atıldı (700')
            ->assertSuccessful();

        $tour = Tour::with('dates')->firstOrFail();
        $this->assertSame(['2027-05-10', '2027-05-17', '2027-06-01'], $tour->dates->map(fn ($d) => $d->departure_date->toDateString())->all());
        $this->assertEquals(9999, (float) $tour->price, 'Kapak fiyatı gerçek otel bloklarının en düşüğü; 850/700 artıkları değil');
        $this->assertCount(2, $tour->pricing_blocks);
    }

    public function test_absurd_price_from_dual_currency_page_is_rejected(): void
    {
        $this->mockImporter($this->fixture([
            'price' => 5590053105,
            'pricing_blocks' => [[
                'dates' => ['2027-05-10'],
                'packages' => [['hotel' => '4* Otel', 'prices' => ['double_pp' => ['old' => null, 'new' => 5590053105]]]],
            ]],
        ]));
        $this->writeList(['malitur;gap-turlari;https://malitur.com/balkan-baskentleri']);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0])
            ->expectsOutputToContain('Fiyat anormal')
            ->assertFailed();

        $this->assertSame(0, Tour::count());
    }

    public function test_visa_comes_from_list_column_slug_hint_or_category_default(): void
    {
        $this->mock(TourUrlImporter::class)
            ->shouldReceive('import')
            ->times(3)
            ->andReturnUsing(function (string $url) {
                return str_contains($url, 'sharm')
                    ? $this->fixture(['title' => 'Sharm El Sheikh Turu', 'destination' => 'Sharm El Sheikh'])
                    : $this->fixture();
            });
        $this->writeList([
            'malitur;gap-turlari;https://malitur.com/gap-turu-a;1',
            'malitur;gap-turlari;https://malitur.com/vizesiz-gap-turu-b',
            'malitur;sharm-el-sheikh-turlari;https://malitur.com/sharm-turu',
        ]);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0])
            ->expectsOutputToContain('vize: vizeli (listeden)')
            ->assertSuccessful();

        $a = Tour::where('tour_url', 'https://malitur.com/gap-turu-a')->firstOrFail();
        $this->assertTrue($a->requires_visa);
        $this->assertFalse($a->visa_on_arrival);

        $b = Tour::where('tour_url', 'https://malitur.com/vizesiz-gap-turu-b')->firstOrFail();
        $this->assertFalse($b->requires_visa);

        $sharm = Tour::where('tour_url', 'https://malitur.com/sharm-turu')->firstOrFail();
        $this->assertTrue($sharm->requires_visa);
        $this->assertTrue($sharm->visa_on_arrival, 'Sharm kategorisi varsayılanı kapıda vize');
        $this->assertTrue($sharm->is_international);
    }

    public function test_departure_city_falls_back_to_url_slug_then_istanbul(): void
    {
        $this->mock(TourUrlImporter::class)
            ->shouldReceive('import')
            ->twice()
            ->andReturn($this->fixture(['departure_city' => null, 'stop_cities' => []]));
        $this->writeList([
            'malitur;gap-turlari;https://malitur.com/gap-turu-3-gece-ankara-cikisli',
            'malitur;gap-turlari;https://malitur.com/gap-turu-3-gece',
        ]);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0])
            ->expectsOutputToContain("kalkış şehri URL'den: Ankara")
            ->expectsOutputToContain('İstanbul varsayıldı')
            ->assertSuccessful();

        $this->assertSame('Ankara', Tour::where('tour_url', 'like', '%ankara%')->first()->departure_city);
        $this->assertSame('İstanbul', Tour::where('tour_url', 'https://malitur.com/gap-turu-3-gece')->first()->departure_city);
    }

    public function test_report_tsv_and_limit_and_access_warning(): void
    {
        $noAccess = Category::create(['name' => 'Dubai Turları', 'slug' => 'dubai-turlari', 'parent_id' => $this->gap->parent_id, 'is_active' => true, 'monthly_price' => 1500]);
        $this->mockImporter($this->fixture(), 1);
        $this->writeList([
            'malitur;dubai-turlari;https://malitur.com/dubai-1',
            'malitur;gap-turlari;https://malitur.com/gap-2',
        ]);

        $this->artisan('app:bulk-import-tours', ['file' => $this->file, '--batch' => 'b1', '--sleep' => 0, '--limit' => 1, '--report' => $this->file.'.tsv'])
            ->expectsOutputToContain('GÖRÜNMEZ')
            ->expectsOutputToContain('--limit=1 doldu')
            ->assertSuccessful();

        $this->assertSame(1, Tour::count());
        $this->assertSame($noAccess->id, Tour::first()->category_id);

        $tsv = file($this->file.'.tsv', FILE_IGNORE_NEW_LINES);
        $this->assertCount(2, $tsv);
        $this->assertStringStartsWith("durum\tacenta\tkategori\turl", $tsv[0]);
        $this->assertStringStartsWith("eklendi\tmalitur\tdubai-turlari\thttps://malitur.com/dubai-1", $tsv[1]);
    }
}
