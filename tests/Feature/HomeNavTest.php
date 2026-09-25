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
 * Ana sayfa gezinme bloğu: kategori ağacı (mega menü) + filtre barı
 * (config/ui.php: home_nav).
 *
 * En önemli bekçi: hiçbir mod KOD SİLMİYOR — 'filter' moduna dönüldüğünde
 * eski bar aynen çalışıyor olmalı. Fikir değişirse geri alma commit'i değil,
 * tek env satırı yetsin diye.
 *
 * Menü iki katman (2026-09-25): kapalı şerit = üst kategoriler, açık panel =
 * alt kategoriler + kart görseli. Ara "kova" katmanı yok.
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

    public function test_alt_kategoriler_ust_kategorinin_panelinde_listelenir(): void
    {
        $ust = Category::create(['name' => 'Doğa Turları', 'slug' => 'doga-menu', 'is_active' => true]);
        Category::create(['name' => 'Kamp ve Yayla', 'slug' => 'kamp-menu', 'parent_id' => $ust->id, 'is_active' => true]);

        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        $r = $this->get(route('home'))->assertOk();

        // Üst kategori şeritte, alt kategorisi panelde
        $r->assertSee('Doğa Turları');
        $r->assertSee('Kamp ve Yayla');
        $r->assertSee(LandingSlug::urlForCategory(Category::where('slug', 'kamp-menu')->first()), false);
        // Şeritteki düğme hangi paneli açacağını aria-controls ile söyler
        $r->assertSee('aria-controls="mega-panel-doga-menu"', false);
        $r->assertSee('id="mega-panel-doga-menu"', false);
    }

    /**
     * Her üst kategorinin kendi paneli var; panel başlığındaki "Tümünü gör"
     * üst kategorinin landing adresine gider — alt kırılım seçmek istemeyen
     * kullanıcı tüm dalı tek tıkla görebilmeli.
     */
    public function test_her_ust_kategorinin_kendi_paneli_ve_tumunu_gor_linki_olur(): void
    {
        $ust = Category::create(['name' => 'Doğa Turları', 'slug' => 'doga-menu', 'is_active' => true]);
        Category::create(['name' => 'Kamp ve Yayla', 'slug' => 'kamp-menu', 'parent_id' => $ust->id, 'is_active' => true]);

        MegaMenu::forget();
        $agac = collect(MegaMenu::build())->keyBy('key');

        $this->assertSame(LandingSlug::urlForCategory($ust), $agac['doga-menu']['url']);
        $this->assertSame('Kamp ve Yayla', $agac['doga-menu']['children'][0]['name']);

        config(['ui.home_nav' => 'both']);
        $this->get(route('home'))->assertOk()->assertSee('Tümünü gör');
    }

    /**
     * Şerit = admin'in kurduğu ağacın üst kategorileri, sort_order sırasıyla.
     * Ara bir gruplama katmanı yok; alt kategori şeritte görünmez.
     */
    public function test_serit_ust_kategorileri_sirali_verir_alt_kategori_seritte_yoktur(): void
    {
        $sonraki = Category::create(['name' => 'Gemi Turları', 'slug' => 'gemi-menu', 'sort_order' => 9, 'is_active' => true]);
        $onceki = Category::create(['name' => 'Yurt Dışı Turları', 'slug' => 'yurtdisi-menu', 'sort_order' => 3, 'is_active' => true]);
        Category::create(['name' => 'Avrupa', 'slug' => 'avrupa-menu', 'parent_id' => $onceki->id, 'is_active' => true]);

        MegaMenu::forget();
        $anahtarlar = collect(MegaMenu::build())->pluck('key')->all();

        $this->assertSame(['kultur-menu', 'yurtdisi-menu', 'gemi-menu'], $anahtarlar);
        $this->assertNotContains('avrupa-menu', $anahtarlar);
    }

    /**
     * Panelin sağındaki kart üst kategorinin "Kart görseli"ni kullanır; görsel
     * yoksa turkuaz zemin + ikon (mega-card-bos).
     */
    public function test_panel_karti_ust_kategorinin_gorselini_kullanir(): void
    {
        Category::create(['name' => 'Gemi Turları', 'slug' => 'gemi-menu', 'image' => 'https://cdn.example.com/gemi.jpg', 'is_active' => true]);

        config(['ui.home_nav' => 'both']);
        MegaMenu::forget();

        $r = $this->get(route('home'))->assertOk();

        $r->assertSee("background-image:url('https://cdn.example.com/gemi.jpg');", false);
        // setUp'taki Kültür'ün görseli yok → boş kart sınıfı
        $r->assertSee('mega-card-bos', false);
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
        $agac = collect(MegaMenu::build())->keyBy('key');

        $this->assertSame(1, $agac['deniz-menu']['count']);
        $this->assertSame(1, $agac['deniz-menu']['children'][0]['count']);
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
