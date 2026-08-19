<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Support\LandingSlug;
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

        $r->assertSee('Kültür');
        $r->assertSee('home-filter-form', false);
    }

    public function test_mega_modunda_filtre_gizlenir_ama_kodu_durur(): void
    {
        config(['ui.home_nav' => 'mega']);
        MegaMenu::forget();

        $r = $this->get(route('home'))->assertOk();

        $r->assertSee('Kültür');
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
        $r->assertDontSee('mega-card', false);
        $r->assertSee('home-filter-form', false);
    }

    /**
     * Kullanıcı kararı (2026-08-13): menü envanterden değil kategori ağacından
     * türer. Turu olmayan kategori de görünür — menü ile filtre barındaki
     * "Kategoriler" paneli aynı listeyi göstermek zorunda.
     */
    public function test_turu_olmayan_kategori_de_menude_gorunur(): void
    {
        Category::create(['name' => 'Kayak ve Kış', 'slug' => 'kayak-menu', 'is_active' => true]);

        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Kayak ve Kış')
            ->assertSee(LandingSlug::urlForCategory(Category::where('slug','kayak-menu')->first()), false);
    }

    public function test_alt_kategoriler_panelin_orta_sutununda_listelenir(): void
    {
        $ust = Category::create(['name' => 'Doğa Turları', 'slug' => 'doga-menu', 'is_active' => true]);
        Category::create(['name' => 'Kamp ve Yayla', 'slug' => 'kamp-menu', 'parent_id' => $ust->id, 'is_active' => true]);

        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        $r = $this->get(route('home'))->assertOk();

        // Ana kategori sol rayda, alt kategorisi orta sütunda
        $r->assertSee('Doğa Turları');
        $r->assertSee('Kamp ve Yayla');
        $r->assertSee(LandingSlug::urlForCategory(Category::where('slug', 'kamp-menu')->first()), false);
        // Sol raydaki kart hangi paneli açacağını data-show ile söyler
        $r->assertSee('data-show="mega-diger-doga-menu"', false);
    }

    /**
     * Ray → orta sütun bağı: her dalın kendi paneli var ve panelin ilk satırı
     * "Tüm {ana kategori}" — alt kırılım seçmek istemeyen kullanıcı tüm dalı
     * tek tıkla görebilmeli.
     */
    public function test_her_dalin_kendi_paneli_ve_tumu_linki_olur(): void
    {
        $ust = Category::create(['name' => 'Doğa Turları', 'slug' => 'doga-menu', 'is_active' => true]);
        Category::create(['name' => 'Kamp ve Yayla', 'slug' => 'kamp-menu', 'parent_id' => $ust->id, 'is_active' => true]);

        MegaMenu::forget();
        $dallar = collect(MegaMenu::build())->pluck('rail')->flatten(1)->keyBy('key');

        $this->assertSame('Tüm Doğa Turları', $dallar['doga-menu']['links'][0]['label']);
        $this->assertSame('Kamp ve Yayla', $dallar['doga-menu']['links'][1]['label']);
        $this->assertSame(
            LandingSlug::urlForCategory($ust),
            $dallar['doga-menu']['links'][0]['url']
        );
    }

    /**
     * config/mega_menu.php'de bir kovaya yazılmamış ana kategori KAYBOLMAZ,
     * "Diğer Turlar" kovasına düşer. Admin yeni kategori açtığında menüden
     * sessizce silinmesin diye.
     */
    public function test_kovaya_yazilmamis_kategori_diger_kovasina_duser(): void
    {
        config(['ui.home_nav' => 'both', 'mega_menu.buckets' => []]);
        MegaMenu::forget();

        $kovalar = MegaMenu::build();

        $this->assertCount(1, $kovalar);
        $this->assertSame('diger', $kovalar[0]['key']);
        $this->assertContains('kultur-menu', collect($kovalar[0]['rail'])->pluck('key')->all());
    }

    public function test_kova_tanimliysa_kategorileri_o_kovaya_girer(): void
    {
        config([
            'ui.home_nav' => 'both',
            'mega_menu.buckets' => [[
                'key' => 'kultur-sehir',
                'label' => 'Kültür & Şehir',
                'icon' => '🏛️',
                'categories' => ['kultur-menu'],
            ]],
        ]);
        MegaMenu::forget();

        $kovalar = collect(MegaMenu::build())->keyBy('key');

        $this->assertSame('Kültür & Şehir', $kovalar['kultur-sehir']['label']);
        $this->assertSame(['kultur-menu'], collect($kovalar['kultur-sehir']['rail'])->pluck('key')->all());
    }

    public function test_pasif_kategori_menuye_girmez(): void
    {
        Category::create(['name' => 'Gizli Kategori', 'slug' => 'gizli-menu', 'is_active' => false]);

        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        $this->get(route('home'))
            ->assertOk()
            ->assertDontSee('Gizli Kategori');
    }

    /**
     * Sayaç kuralı filtre barıyla aynı olmalı: üst kategori kendi turlarını DEĞİL
     * kendisi + tüm alt seviyelerini sayar. Ayrı hesaplanırsa menü "0" derken
     * filtre tur döndürür (canlı vaka için bkz. Category::descendantIds).
     */
    public function test_kategori_sayaci_alt_seviyeleri_de_toplar(): void
    {
        $ust = Category::create(['name' => 'Deniz Turları', 'slug' => 'deniz-menu', 'is_active' => true]);
        $alt = Category::create(['name' => 'Tekne Turu', 'slug' => 'tekne-menu', 'parent_id' => $ust->id, 'is_active' => true]);
        $this->tur('Bodrum Tekne Turu', 'Bodrum', false, $alt->id);

        MegaMenu::forget();
        $dallar = collect(MegaMenu::build())->pluck('rail')->flatten(1)->keyBy('key');

        $this->assertSame(1, $dallar['deniz-menu']['count']);
        // links[0] = "Tüm Deniz Turları", links[1] = alt kategori
        $this->assertSame(1, $dallar['deniz-menu']['links'][1]['count']);
    }

    public function test_menu_linkleri_mevcut_filtre_urllerine_gider(): void
    {
        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        // Yeni sayfa açılmıyor: menü var olan /turlar filtresine bağlanıyor.
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(LandingSlug::urlForCategory(Category::where('slug','kultur-menu')->first()), false);
    }
}
