<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * "Tümünü gör" bağlantısı aktif filtreleri /turlar sayfasına taşımalı.
 *
 * Canlı şikayet: "Mısır" seçiliyken buton sitedeki TÜM turlara gidiyordu.
 * İki sayfa farklı parametre adları kullandığı için eşleme gerekiyor
 * (days[] bandı → min_days/max_days, budget_max → max_price, special → tarih).
 */
class SeeAllLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();

        $agency = Agency::create([
            'name' => 'Link Acenta',
            'slug' => 'link-acenta',
            'email' => 'link@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Vitrin Turu',
            'destination' => 'Kapadokya',
            'description' => 'Test',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(30),
            'is_active' => true,
        ]);
    }

    /** Sayfadaki "Tümünü gör" bağlantısının href'ini döndürür. */
    private function link(array $params = []): string
    {
        $html = $this->get(route('home', $params))->assertOk()->getContent();
        preg_match('/href="([^"]*)">Tümünü gör/u', $html, $m);

        return html_entity_decode(urldecode($m[1] ?? ''));
    }

    public function test_filtre_yokken_duz_turlar_sayfasina_gider(): void
    {
        $this->assertStringEndsWith('/turlar', $this->link());
    }

    public function test_kategori_secimi_tasinir(): void
    {
        Category::create(['name' => 'Kültür', 'slug' => 'kultur-link', 'is_active' => true]);

        $this->assertStringContainsString('category=kultur-link', $this->link(['category' => 'kultur-link']));
    }

    public function test_destinasyon_secimi_tasinir(): void
    {
        $this->assertStringContainsString(
            'destination=Kapadokya',
            $this->link(['destinations' => ['Kapadokya']])
        );
    }

    public function test_coklu_destinasyon_dizi_olarak_tasinir(): void
    {
        $link = $this->link(['destinations' => ['Kapadokya', 'Fethiye']]);

        $this->assertStringContainsString('Kapadokya', $link);
        $this->assertStringContainsString('Fethiye', $link);
    }

    public function test_gun_bandi_min_max_gune_cevrilir(): void
    {
        $link = $this->link(['days' => ['4-6']]);

        $this->assertStringContainsString('min_days=4', $link);
        $this->assertStringContainsString('max_days=6', $link);
    }

    /** 10+ bandının üst sınırı yok — max_days yazılmamalı. */
    public function test_acik_uclu_gun_bandinda_ust_sinir_yazilmaz(): void
    {
        $link = $this->link(['days' => ['10+']]);

        $this->assertStringContainsString('min_days=10', $link);
        $this->assertStringNotContainsString('max_days', $link);
    }

    public function test_butce_max_price_olur(): void
    {
        $this->assertStringContainsString('max_price=20000', $this->link(['budget_max' => 20000]));
    }

    public function test_ozel_donem_yaklasan_araliga_cevrilir(): void
    {
        $link = $this->link(['special' => 'yilbasi']);

        $this->assertStringContainsString('date_start=', $link);
        $this->assertStringContainsString('date_end=', $link);

        // Yaklaşan aralık seçilmeli; min/max alınsaydı pencere araya giren
        // bütün yılı kapsardı.
        preg_match('/date_start=([\d-]+).*date_end=([\d-]+)/', $link, $m);
        $this->assertLessThan(
            40,
            now()->parse($m[1])->diffInDays(now()->parse($m[2])),
            'Tarih penceresi tek döneme sığmalı'
        );
    }

    public function test_birden_fazla_filtre_birlikte_tasinir(): void
    {
        $link = $this->link(['destinations' => ['Kapadokya'], 'budget_max' => 15000, 'days' => ['4-6']]);

        $this->assertStringContainsString('destination=Kapadokya', $link);
        $this->assertStringContainsString('max_price=15000', $link);
        $this->assertStringContainsString('min_days=4', $link);
    }

    /** Taşınan link gerçekten aynı turu bulmalı — parametre adı uyuşmazsa boş döner. */
    public function test_tasinan_link_ayni_turu_getirir(): void
    {
        $link = $this->link(['destinations' => ['Kapadokya'], 'budget_max' => 20000]);

        $this->get($link)
            ->assertOk()
            ->assertSee('Vitrin Turu');
    }
}
