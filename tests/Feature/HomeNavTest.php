<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Support\MegaMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Ana sayfa gezinme bloğu: mega menü + filtre barı (config/ui.php: home_nav).
 *
 * En önemli bekçi: hiçbir mod KOD SİLMİYOR — 'filter' moduna dönüldüğünde
 * eski bar aynen çalışıyor olmalı. Fikir değişirse geri alma commit'i değil,
 * tek env satırı yetsin diye.
 */
class HomeNavTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        MegaMenu::forget();

        $this->agency = Agency::create([
            'name' => 'Menü Acenta',
            'slug' => 'menu-acenta',
            'email' => 'menu@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        $kategori = Category::create(['name' => 'Kültür', 'slug' => 'kultur-menu', 'is_active' => true]);

        // Eşiği (2 tur) geçen bir yurt içi destinasyon
        foreach (['Kapadokya Turu A', 'Kapadokya Turu B'] as $baslik) {
            $this->tur($baslik, 'Kapadokya', false, $kategori->id);
        }
        // Eşiğin altında kalan
        $this->tur('Tek Turluk Mardin', 'Mardin', false, $kategori->id);
        // Yurt dışı
        foreach (['Dubai Turu A', 'Dubai Turu B'] as $baslik) {
            $this->tur($baslik, 'Dubai', true, $kategori->id);
        }
    }

    private function tur(string $title, string $dest, bool $disi, int $categoryId): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'category_id' => $categoryId,
            'title' => $title,
            'destination' => $dest,
            'description' => 'Test',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(25),
            'is_international' => $disi,
            'is_active' => true,
        ]);
    }

    public function test_varsayilan_modda_menu_ve_filtre_birlikte_gorunur(): void
    {
        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        $r = $this->get(route('home'))->assertOk();

        $r->assertSee('Yurt İçi Turlar');
        $r->assertSee('home-filter-form', false);
    }

    public function test_mega_modunda_filtre_gizlenir_ama_kodu_durur(): void
    {
        config(['ui.home_nav' => 'mega']);
        MegaMenu::forget();

        $r = $this->get(route('home'))->assertOk();

        $r->assertSee('Yurt İçi Turlar');
        // Form hâlâ sayfada — yalnız gizli. Silinmedi.
        $r->assertSee('home-filter-form', false);
        $r->assertSee('display:none;', false);
    }

    public function test_filter_moduna_donulunce_mega_menu_kaybolur_bar_calisir(): void
    {
        config(['ui.home_nav' => 'filter']);
        MegaMenu::forget();

        $r = $this->get(route('home'))->assertOk();

        $r->assertDontSee('mega-trigger', false);
        $r->assertSee('home-filter-form', false);
    }

    public function test_menu_yalnizca_esigi_gecen_basliklari_gosterir(): void
    {
        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        $r = $this->get(route('home'))->assertOk();

        // "Mardin" kelimesi tur kartı başlığında da geçiyor; menüye girip
        // girmediğini link URL'sinden kontrol ediyoruz.
        $r->assertSee(route('tours.index', ['destination' => 'Kapadokya']), false);   // 2 tur
        $r->assertDontSee(route('tours.index', ['destination' => 'Mardin']), false);  // 1 tur
    }

    public function test_menu_yurt_ici_ve_disini_ayirir(): void
    {
        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Yurt İçi Turlar')
            ->assertSee('Yurt Dışı Turlar');
    }

    public function test_turu_olmayan_kova_hic_basilmaz(): void
    {
        Tour::query()->update(['is_international' => false]);
        MegaMenu::forget();
        config(['ui.home_nav' => 'both']);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Yurt İçi Turlar')
            ->assertDontSee('Yurt Dışı Turlar');
    }

    public function test_menu_linkleri_mevcut_filtre_urllerine_gider(): void
    {
        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        // Yeni sayfa açılmıyor: menü var olan /turlar filtresine bağlanıyor.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('tours.index', ['destination' => 'Kapadokya']), false);
    }
}
