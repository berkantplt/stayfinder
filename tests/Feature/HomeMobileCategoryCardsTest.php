<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Support\LandingSlug;
use App\Support\MegaMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mobil ana sayfa kategori kartları (2026-10-01): elle yazılmış 6 kısayol yerine
 * admin'in üst kategorileri (web mega menüsüyle AYNI kaynak) + "Özel Dönem
 * Turları". Her kartın alt kategori paneli sunucuda <template> olarak basılır.
 */
class HomeMobileCategoryCardsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        MegaMenu::forget();
    }

    private function tur(Category $kategori, string $baslik = 'Örnek Tur'): Tour
    {
        $agency = Agency::create([
            'name' => 'Acenta '.uniqid(),
            'slug' => 'acenta-'.uniqid(),
            'email' => uniqid().'@ornek.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        return Tour::create([
            'agency_id' => $agency->id,
            'category_id' => $kategori->id,
            'title' => $baslik,
            'destination' => 'Saraybosna',
            'description' => 'Tur.',
            'price' => 9000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(20),
            'is_active' => true,
        ]);
    }

    /** @return string[] kart href'leri */
    private function kartAdresleri(string $html): array
    {
        preg_match_all('/<a href="([^"]+)" class="m-cat"/', $html, $m);

        return $m[1];
    }

    public function test_kartlar_admin_ust_kategorilerinden_gelir_ve_kendi_sayfasina_gider(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı Turlar', 'slug' => 'yurt-disi-turlar', 'icon' => '✈️', 'sort_order' => 1]);
        $alt = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari', 'parent_id' => $ust->id]);
        $this->tur($alt);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-m-cat="yurt-disi-turlar"', $html);
        $this->assertStringContainsString('<a href="'.LandingSlug::urlForCategory($ust).'" class="m-cat"', $html);
        $this->assertStringContainsString('1 alt kategori · 1 tur', $html);

        // Eski elle yazılmış kısayollar yok: hiçbir kart /turlar filtresine gitmez
        foreach ($this->kartAdresleri($html) as $adres) {
            $this->assertStringNotContainsString('yurt=', $adres);
            $this->assertStringNotContainsString('visa=', $adres);
            $this->assertStringNotContainsString('category=', $adres);
        }
    }

    public function test_panel_sablonu_alt_kategorileri_sayac_ve_yakinda_ile_listeler(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı Turlar', 'slug' => 'yurt-disi-turlar', 'sort_order' => 1]);
        $dolu = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari', 'parent_id' => $ust->id, 'sort_order' => 1]);
        $bos = Category::create(['name' => 'Afrika Turları', 'slug' => 'afrika-turlari', 'parent_id' => $ust->id, 'sort_order' => 2]);
        $this->tur($dolu);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('<template id="m-cat-sheet-yurt-disi-turlar" data-title="Yurt Dışı Turlar">', $html);
        $this->assertStringContainsString('<a href="'.LandingSlug::urlForCategory($dolu).'" class="m-cat-row ">', $html);
        $this->assertStringContainsString('<span class="m-cat-row-say">1 tur</span>', $html);
        // Turu olmayan alt kategori listede kalır (web menüsü kuralı), soluk + Yakında
        $this->assertStringContainsString('<a href="'.LandingSlug::urlForCategory($bos).'" class="m-cat-row bos">', $html);
        $this->assertStringContainsString('<span class="m-cat-row-say">Yakında</span>', $html);
        $this->assertStringContainsString('Tümünü gör · 1 tur', $html);
    }

    public function test_alt_kategori_gorseli_panelde_kucuk_gorsel_olarak_basilir(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı Turlar', 'slug' => 'yurt-disi-turlar']);
        Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari', 'parent_id' => $ust->id, 'image' => 'categories/balkan.jpg']);

        $agac = MegaMenu::build();
        $this->assertSame(asset('storage/categories/balkan.jpg'), $agac[0]['children'][0]['image']);

        $this->get(route('home'))->assertOk()->assertSee(asset('storage/categories/balkan.jpg'), false);
    }

    public function test_ozel_donem_karti_yalniz_yaklasan_donemleri_listeler(): void
    {
        $b1 = today()->addDays(40)->toDateString();
        $e1 = today()->addDays(44)->toDateString();
        $b2 = today()->addDays(90)->toDateString();
        $e2 = today()->addDays(95)->toDateString();
        config(['special_periods' => [
            'yilbasi' => ['label' => 'Yılbaşı', 'ranges' => [[$b2, $e2]]],
            'somestr' => ['label' => 'Sömestr tatili', 'ranges' => [['2020-01-18', '2020-02-01'], [$b1, $e1]]],
            'gecmis' => ['label' => 'Geçmiş Bayram', 'ranges' => [['2020-05-01', '2020-05-05']]],
        ]]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-m-cat="_ozel-donem"', $html);
        $this->assertStringContainsString('Özel Dönem Turları', $html);
        // İlk yaklaşan dönem Sömestr (daha erken başlıyor)
        $this->assertStringContainsString('2 dönem · ilki Sömestr tatili', $html);
        // JS'siz yedek: kart ilk yaklaşan dönemin tarih aralığına gider
        $this->assertStringContainsString(
            '<a href="'.e(route('tours.index', ['date_start' => $b1, 'date_end' => $e1])).'" class="m-cat" data-m-cat="_ozel-donem"',
            $html
        );

        // Panel şablonuna SINIRLI kontrol: masaüstü filtre barı tüm dönemleri (geçmiş dahil) basar
        $this->assertMatchesRegularExpression('/<template id="m-cat-sheet-_ozel-donem" data-title="Özel Dönem Turları">(.*?)<\/template>/s', $html);
        preg_match('/<template id="m-cat-sheet-_ozel-donem"[^>]*>(.*?)<\/template>/s', $html, $m);
        $this->assertNotEmpty($m, 'Özel Dönem şablonu yok');
        $panel = $m[1];
        // Panel sırası başlangıç tarihine göre: Sömestr (40 gün sonra) Yılbaşı'ndan (90) önce
        $this->assertLessThan(strpos($panel, 'Yılbaşı'), strpos($panel, 'Sömestr tatili'));
        $this->assertStringContainsString('date_start='.$b1.'&amp;date_end='.$e1, $panel);
        $this->assertStringContainsString('date_start='.$b2.'&amp;date_end='.$e2, $panel);
        $this->assertStringContainsString('Sömestr tatili', $panel);
        $this->assertStringContainsString('Yılbaşı', $panel);
        $this->assertStringNotContainsString('Geçmiş Bayram', $panel);
        $this->assertStringNotContainsString('date_start=2020-01-18', $panel);
    }

    public function test_yaklasan_donem_yoksa_ozel_donem_karti_basilmaz(): void
    {
        config(['special_periods' => [
            'eski' => ['label' => 'Eski Dönem', 'ranges' => [['2020-01-01', '2020-01-05']]],
        ]]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringNotContainsString('data-m-cat="_ozel-donem"', $html);
        $this->assertStringNotContainsString('Özel Dönem Turları', $html);
    }

    public function test_ozel_donem_tarih_etiketi_ayni_ay_ve_ay_gecisi(): void
    {
        config(['special_periods' => [
            'kurban' => ['label' => 'Kurban Bayramı', 'ranges' => [['2027-05-16', '2027-05-21']]],
            'yilbasi' => ['label' => 'Yılbaşı', 'ranges' => [['2027-12-29', '2028-01-02']]],
        ]]);

        $html = $this->get(route('home'))->assertOk()->getContent();
        preg_match('/<template id="m-cat-sheet-_ozel-donem"[^>]*>(.*?)<\/template>/s', $html, $m);
        $panel = $m[1] ?? '';

        $this->assertStringContainsString('16–21 Mayıs 2027', $panel);   // aynı ay
        $this->assertStringContainsString('29 Ara – 2 Oca 2028', $panel); // ay geçişi
    }

    public function test_ozel_donem_adli_admin_kategorisi_ile_id_cakismaz(): void
    {
        // Admin "Özel Dönem" üst kategorisi açarsa slug'ı 'ozel-donem' olur; sentetik
        // kartın anahtarı '_' ile başladığı için şablon id'leri ve data-m-cat tekil kalır.
        Category::create(['name' => 'Özel Dönem', 'slug' => 'ozel-donem']);
        config(['special_periods' => [
            'yilbasi' => ['label' => 'Yılbaşı', 'ranges' => [[today()->addDays(60)->toDateString(), today()->addDays(64)->toDateString()]]],
        ]]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        preg_match_all('/data-m-cat="([^"]+)"/', $html, $k);
        $this->assertSame(count($k[1]), count(array_unique($k[1])), 'data-m-cat anahtarları tekil olmalı');
        $this->assertContains('ozel-donem', $k[1]);
        $this->assertContains('_ozel-donem', $k[1]);
        $this->assertSame(1, substr_count($html, 'id="m-cat-sheet-_ozel-donem"'));
    }

    public function test_alt_kategorisi_olmayan_ust_icin_sablon_basilmaz_kart_dogrudan_gider(): void
    {
        $ust = Category::create(['name' => 'Günübirlik Turlar', 'slug' => 'gunubirlik-turlar']);
        $this->tur($ust);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('<a href="'.LandingSlug::urlForCategory($ust).'" class="m-cat" data-m-cat="gunubirlik-turlar"', $html);
        $this->assertStringNotContainsString('id="m-cat-sheet-gunubirlik-turlar"', $html);
        // "0 alt kategori" yazılmaz, yalnız tur sayısı
        $this->assertStringContainsString('<span class="m-cat-alt">1 tur</span>', $html);
        $this->assertStringNotContainsString('0 alt kategori', $html);
    }

    public function test_kategori_degisince_mega_menu_onbellegi_dusur(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı Turlar', 'slug' => 'yurt-disi-turlar']);
        $this->assertSame('Yurt Dışı Turlar', MegaMenu::build()[0]['name']); // önbellek ısındı

        $ust->update(['name' => 'Dünya Turları']);
        $this->assertSame('Dünya Turları', MegaMenu::build()[0]['name']);

        $ust->delete();
        $this->assertSame([], MegaMenu::build());
    }

    public function test_pasif_ust_kategori_kart_olmaz(): void
    {
        Category::create(['name' => 'Aktif Üst', 'slug' => 'aktif-ust', 'is_active' => true]);
        Category::create(['name' => 'Pasif Üst', 'slug' => 'pasif-ust', 'is_active' => false]);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('data-m-cat="aktif-ust"', $html);
        $this->assertStringNotContainsString('data-m-cat="pasif-ust"', $html);
    }
}
