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
 * Kategori facet sayacı ile filtrenin AYNI sonucu vermesi.
 *
 * Canlı vaka: panelde "Mısır Turları 0" yazıyordu ama ?category=misir-turlari
 * 4 tur döndürüyordu. Sebep iki ayrı hesap: filtre kategori + doğrudan
 * çocuklarını sayıyordu, facet yalnız kategorinin kendi category_id'sini.
 * Altında torun bulunan her kategori bu yüzden hep "0" görünüyordu.
 *
 * Artık ikisi de Category::descendantIds() kullanıyor.
 */
class CategoryFacetCountTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Cache::flush();

        $this->agency = Agency::create([
            'name' => 'Facet Acenta',
            'slug' => 'facet-acenta',
            'email' => 'facet@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
    }

    private function tur(int $categoryId, string $title): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'category_id' => $categoryId,
            'title' => $title,
            'destination' => 'Mısır',
            'description' => 'Test',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(30),
            'is_active' => true,
        ]);
    }

    public function test_torunundaki_turlar_ust_kategorinin_sayacina_girer(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi-f', 'is_active' => true]);
        $orta = Category::create(['name' => 'Mısır Turları', 'slug' => 'misir-turlari-f', 'parent_id' => $ust->id, 'is_active' => true]);
        $alt = Category::create(['name' => 'Hurgada', 'slug' => 'hurgada-f', 'parent_id' => $orta->id, 'is_active' => true]);

        $this->tur($alt->id, 'Hurgada Turu A');
        $this->tur($alt->id, 'Hurgada Turu B');

        // Panelde üst ve orta seviye görünür (torun basılmıyor). Hata tam
        // buradaydı: ortadaki "Mısır Turları" 0 yazıyordu.
        $this->assertSame(2, $this->facetSayisi($ust), 'Üst kategori torunları saymalı');
        $this->assertSame(2, $this->facetSayisi($orta), 'Orta kategori kendi torununu saymalı');
    }

    public function test_facet_sayaci_filtre_sonucuyla_ayni(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi-f2', 'is_active' => true]);
        $orta = Category::create(['name' => 'Mısır Turları', 'slug' => 'misir-turlari-f2', 'parent_id' => $ust->id, 'is_active' => true]);
        $alt = Category::create(['name' => 'Hurgada', 'slug' => 'hurgada-f2', 'parent_id' => $orta->id, 'is_active' => true]);

        $this->tur($orta->id, 'Kahire Turu');
        $this->tur($alt->id, 'Hurgada Turu');

        // Panelde görünen her kategori için: yanında yazan sayı, o kategoriyi
        // seçince gelen sonuçla birebir aynı olmalı.
        foreach ([$ust, $orta] as $kategori) {
            $filtre = Tour::query()->active()->whereIn('category_id', $kategori->descendantIds())->count();
            $this->assertSame(
                $filtre,
                $this->facetSayisi($kategori),
                "Kategori '{$kategori->name}' facet ile filtre ayrışıyor"
            );
        }

        // Torun panelde basılmıyor ama filtresi çalışmalı.
        $this->assertSame(1, Tour::query()->active()->whereIn('category_id', $alt->descendantIds())->count());
    }

    public function test_iki_seviyeli_agac_eskisi_gibi_calisir(): void
    {
        $ust = Category::create(['name' => 'Kültür', 'slug' => 'kultur-f', 'is_active' => true]);
        $alt = Category::create(['name' => 'Müze', 'slug' => 'muze-f', 'parent_id' => $ust->id, 'is_active' => true]);

        $this->tur($ust->id, 'Doğrudan Üst Tur');
        $this->tur($alt->id, 'Alt Kategori Turu');

        $this->assertSame(2, $this->facetSayisi($ust));
        $this->assertSame(1, $this->facetSayisi($alt));
    }

    public function test_turu_olmayan_kategori_sifir_kalir(): void
    {
        $bos = Category::create(['name' => 'Boş Kategori', 'slug' => 'bos-f', 'is_active' => true]);

        $this->assertSame(0, $this->facetSayisi($bos));
    }

    /** Ana sayfayı render edip panelde o kategorinin yanında yazan sayıyı okur. */
    private function facetSayisi(Category $kategori): int
    {
        Cache::flush();

        $html = $this->get(route('home'))->assertOk()->getContent();

        $ad = preg_quote($kategori->name, '/');
        preg_match('/'.$ad.'\s*<i>(\d+)<\/i>/u', $html, $m);

        return isset($m[1]) ? (int) $m[1] : -1;
    }
}
