<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CampaignDatePriceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
    }

    public function test_active_campaign_applies_discount_to_each_date_card(): void
    {
        [$tour, $dates] = $this->seedTourWithDates([10000, 12000, 15000]);

        // %20 indirim (10K base → 8K)
        Campaign::create([
            'tour_id' => $tour->id,
            'discount_price' => 8000,
            'label' => 'Yaz İndirimi',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(10),
            'is_active' => true,
        ]);

        $response = $this->get(route('tours.show', $tour))->assertOk();
        $html = $response->getContent();

        // Pro-rata: 12K → 9.6K, 15K → 12K (eski fiyat çizili olmalı, yeni fiyat yeşil bantta)
        // Eski fiyatlar üstü çizili (s tag) olarak görünmeli
        $this->assertStringContainsString('<s', $html);
        $this->assertStringContainsString('10.000', $html);  // base original
        $this->assertStringContainsString('12.000', $html);  // date 2 original
        $this->assertStringContainsString('15.000', $html);  // date 3 original

        // İndirimli değerler (pro-rata): 8.000, 9.600, 12.000
        $this->assertStringContainsString('8.000', $html);
        $this->assertStringContainsString('9.600', $html);
        // 12.000 zaten var (date 2 orig), ama bunu indirimli olarak da görmeliyiz (date 3 → 12K)

        // İndirim yüzdesi
        $this->assertStringContainsString('-%20', $html);
    }

    public function test_no_campaign_keeps_original_prices_unstruck(): void
    {
        [$tour] = $this->seedTourWithDates([10000, 12000]);

        $response = $this->get(route('tours.show', $tour))->assertOk();
        $html = $response->getContent();

        // Üstü çizili eski fiyat OLMAMALI (kampanya yok)
        $this->assertStringNotContainsString('<s style="color:#94a3b8', $html);
        $this->assertStringNotContainsString('-%', $html);
        $this->assertStringContainsString('10.000', $html);
        $this->assertStringContainsString('12.000', $html);
    }

    public function test_expired_campaign_does_not_apply(): void
    {
        [$tour] = $this->seedTourWithDates([10000]);

        Campaign::create([
            'tour_id' => $tour->id,
            'discount_price' => 6000,
            'label' => 'Eski',
            'starts_at' => now()->subDays(30),
            'ends_at' => now()->subDay(), // dün bitti
            'is_active' => true,
        ]);

        $response = $this->get(route('tours.show', $tour))->assertOk();
        $html = $response->getContent();

        // Süresi bitmiş — indirim uygulanmamalı
        $this->assertStringNotContainsString('<s style="color:#94a3b8', $html);
        $this->assertStringNotContainsString('-%', $html);
    }

    /**
     * @return array{0: Tour, 1: array<int, TourDate>}
     */
    private function seedTourWithDates(array $prices): array
    {
        $agency = Agency::create([
            'name' => 'Camp Agency',
            'slug' => 'camp-' . uniqid(),
            'email' => uniqid() . '@x.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Test Tour',
            'destination' => 'Bodrum',
            'description' => 'd',
            'price' => $prices[0], // tur base price = ilk tarih
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(15),
            'is_active' => true,
        ]);

        $dates = [];
        foreach ($prices as $i => $price) {
            $dates[] = TourDate::create([
                'tour_id' => $tour->id,
                'departure_date' => today()->addDays(10 + $i * 7),
                'return_date' => today()->addDays(15 + $i * 7),
                'price' => $price,
            ]);
        }

        return [$tour->fresh()->load('dates'), $dates];
    }
}
