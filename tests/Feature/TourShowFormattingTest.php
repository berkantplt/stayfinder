<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TourShowFormattingTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_strips_duplicate_bullets_and_day_prefix(): void
    {
        $agency = Agency::create([
            'name' => 'Format Acenta',
            'slug' => 'format-acenta',
            'email' => 'format@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
        $category = Category::create(['name' => 'Tur', 'slug' => 'tur', 'is_active' => true]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'title' => 'Format Turu',
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 2,
            'departure_date' => today()->addDays(10),
            'is_active' => true,
            // kaynak metinde madde işaretli gelmiş
            'included' => "• Otelde 1 gece konaklama\n- Profesyonel rehberlik",
            // başlıkta zaten "2. Gün:" var
            'itinerary' => [
                ['title' => '2. Gün: Tuz Gölü – Ihlara', 'content' => 'Detay metni.'],
            ],
        ]);

        $html = $this->get(route('tours.show', $tour))->assertOk()->getContent();

        // Çift madde işareti olmamalı
        $this->assertStringNotContainsString('• •', $html);
        $this->assertStringNotContainsString('• -', $html);
        // Gün başlığı tekrarlanmamalı
        $this->assertStringNotContainsString('2. Gün: 2. Gün', $html);
        $this->assertStringContainsString('Tuz Gölü – Ihlara', $html);
        // İçerik yine görünür
        $this->assertStringContainsString('Otelde 1 gece konaklama', $html);
        $this->assertStringContainsString('Profesyonel rehberlik', $html);
    }
}
