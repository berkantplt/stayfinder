<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourPricingBlocksTest extends TestCase
{
    use RefreshDatabase;

    private User $agencyUser;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $agency = Agency::create([
            'name' => 'Fiyat Bloğu Acenta',
            'slug' => 'fiyat-blogu-acenta',
            'email' => 'fiyatblok@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        $this->agencyUser = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $this->category = Category::create([
            'name' => 'Yurt İçi Turlar',
            'slug' => 'yurt-ici-turlar',
            'is_active' => true,
        ]);
    }

    public function test_store_persists_pricing_blocks_and_derives_headline_price(): void
    {
        $depart = today()->addDays(20)->toDateString();

        $payload = [
            'category_id' => $this->category->id,
            'title' => 'Bodrum Yaz Turu',
            'destination' => 'Bodrum',
            'departure_city' => 'İstanbul',
            'duration_days' => 4,
            'currency' => 'TRY',
            'pricing_options' => [
                [
                    'departure_dates' => [$depart],
                    'packages' => [
                        [
                            'hotel' => '5★ Suhan Bodrum',
                            'double_pp' => ['old' => '12500', 'new' => '9900'],
                            'single' => ['old' => '', 'new' => '14000'],
                            'child_7_11' => ['old' => '', 'new' => '4500'],
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $payload)
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Bodrum Yaz Turu');
        $this->assertNotNull($tour);

        // Başlangıç fiyatı = en düşük yetişkin indirimli fiyat (double_pp new = 9900)
        $this->assertEquals(9900, (float) $tour->price);

        $blocks = $tour->pricing_blocks;
        $this->assertIsArray($blocks);
        $this->assertCount(1, $blocks);
        $this->assertSame([$depart], $blocks[0]['dates']);

        $pkg = $blocks[0]['packages'][0];
        $this->assertSame('5★ Suhan Bodrum', $pkg['hotel']);
        $this->assertEquals(9900, $pkg['prices']['double_pp']['new']);
        $this->assertEquals(12500, $pkg['prices']['double_pp']['old']);
        $this->assertEquals(14000, $pkg['prices']['single']['new']);
        $this->assertNull($pkg['prices']['single']['old']);
        $this->assertEquals(4500, $pkg['prices']['child_7_11']['new']);

        // Tarih de tour_dates tablosuna yazıldı
        $this->assertTrue(
            $tour->dates()->whereDate('departure_date', $depart)->exists()
        );
    }

    public function test_headline_price_prefers_double_over_cheaper_extra_bed(): void
    {
        // Jolly vakası: ilave yatak (29.099,50) çift kişilikten (32.599,50) ucuz —
        // kapak fiyatı yine de İKİ KİŞİLİK ODA KİŞİ BAŞI olmalı.
        $depart = today()->addDays(30)->toDateString();

        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), [
                'category_id' => $this->category->id,
                'title' => 'Uçaklı Likya Turu',
                'destination' => 'Fethiye',
                'departure_city' => 'İstanbul',
                'duration_days' => 5,
                'currency' => 'TRY',
                'pricing_options' => [
                    [
                        'departure_dates' => [$depart],
                        'packages' => [
                            [
                                'hotel' => 'Fethiye Otelleri',
                                'double_pp' => ['old' => '65199', 'new' => '32599.5'],
                                'single' => ['old' => '76799', 'new' => '38399.5'],
                                'extra_bed' => ['old' => '58199', 'new' => '29099.5'],
                                'child_0_2' => ['old' => '', 'new' => '999'],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Uçaklı Likya Turu');
        $this->assertNotNull($tour);
        $this->assertEquals(32599.5, (float) $tour->price, 'Kapak fiyatı ilave yatak DEĞİL, double_pp olmalı');
    }

    public function test_headline_price_falls_back_to_extra_bed_when_no_room_price(): void
    {
        $depart = today()->addDays(30)->toDateString();

        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), [
                'category_id' => $this->category->id,
                'title' => 'Sadece İlave Yatak Turu',
                'destination' => 'Bodrum',
                'departure_city' => 'İzmir',
                'duration_days' => 3,
                'currency' => 'TRY',
                'pricing_options' => [
                    [
                        'departure_dates' => [$depart],
                        'packages' => [
                            [
                                'hotel' => 'Tek Tip Otel',
                                'extra_bed' => ['old' => '', 'new' => '5000'],
                            ],
                        ],
                    ],
                ],
            ])
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Sadece İlave Yatak Turu');
        $this->assertNotNull($tour);
        $this->assertEquals(5000, (float) $tour->price);
    }

    public function test_store_keeps_note_when_room_type_has_no_price(): void
    {
        $depart = today()->addDays(25)->toDateString();

        $payload = [
            'category_id' => $this->category->id,
            'title' => 'Bebek Kabul Etmeyen Tur',
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'duration_days' => 2,
            'currency' => 'TRY',
            'pricing_options' => [
                [
                    'departure_dates' => [$depart],
                    'packages' => [
                        [
                            'hotel' => '5★ Otel',
                            'double_pp' => ['old' => '', 'new' => '8000'],
                            // 0-1,99 yaş için fiyat yok, sebep girilmiş
                            'child_0_2' => ['old' => '', 'new' => '', 'note' => 'Kabul edilmiyor'],
                        ],
                    ],
                ],
            ],
        ];

        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $payload)
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Bebek Kabul Etmeyen Tur');
        $pkg = $tour->pricing_blocks[0]['packages'][0];

        $this->assertEquals(8000, $pkg['prices']['double_pp']['new']);
        $this->assertNull($pkg['prices']['child_0_2']['new']);
        $this->assertNull($pkg['prices']['child_0_2']['old']);
        $this->assertSame('Kabul edilmiyor', $pkg['prices']['child_0_2']['note']);

        // Detay sayfasında not görünür, fiyat sütununda "Kabul edilmiyor" yazar
        $this->get(route('tours.show', $tour))
            ->assertOk()
            ->assertSee('Kabul edilmiyor');
    }

    public function test_store_without_packages_keeps_single_price_and_null_blocks(): void
    {
        $depart = today()->addDays(15)->toDateString();

        $payload = [
            'category_id' => $this->category->id,
            'title' => 'Kapadokya Turu',
            'destination' => 'Nevşehir',
            'departure_city' => 'İstanbul',
            'duration_days' => 2,
            'currency' => 'TRY',
            'pricing_options' => [
                ['price' => '7500', 'departure_dates' => [$depart]],
            ],
        ];

        $this->actingAs($this->agencyUser)
            ->post(route('agency.tours.store'), $payload)
            ->assertRedirect(route('agency.tours.index'));

        $tour = Tour::firstWhere('title', 'Kapadokya Turu');
        $this->assertNotNull($tour);
        $this->assertEquals(7500, (float) $tour->price);
        $this->assertNull($tour->pricing_blocks);
    }
}
