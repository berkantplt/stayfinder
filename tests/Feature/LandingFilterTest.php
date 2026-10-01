<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Support\LandingFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Landing sayfası filtreleri (/balkan-turlari?sure[]=8&ay[]=9...): seçenekler
 * sayfanın KENDİ envanterinden türer, sayaçlar ve SEO blokları filtrelenmemiş
 * kümeden gelir, yalnız liste daralır.
 */
class LandingFilterTest extends TestCase
{
    use RefreshDatabase;

    private Category $kategori;

    private Agency $a;

    private Agency $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->kategori = Category::create(['name' => 'Balkan Turları', 'slug' => 'balkan-turlari']);
        $this->a = $this->acenta('TatilOne');
        $this->b = $this->acenta('Jolly');

        $this->tur(['title' => 'Kısa Belgrad', 'agency_id' => $this->a->id, 'price' => 9000, 'duration_days' => 5, 'duration_nights' => 4, 'departure_date' => '2027-09-10', 'departure_city' => 'İstanbul']);
        $this->tur(['title' => 'Uzun Balkan', 'agency_id' => $this->a->id, 'price' => 15000, 'duration_days' => 8, 'duration_nights' => 7, 'departure_date' => '2027-10-05', 'departure_city' => 'Ankara']);
        $this->tur(['title' => 'Jolly Saraybosna', 'agency_id' => $this->b->id, 'price' => 21000, 'duration_days' => 8, 'duration_nights' => 7, 'departure_date' => '2027-09-20', 'departure_city' => 'İstanbul']);
    }

    private function acenta(string $ad): Agency
    {
        return Agency::create([
            'name' => $ad,
            'slug' => \Illuminate\Support\Str::slug($ad).'-'.uniqid(),
            'email' => uniqid().'@ornek.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    private function tur(array $attributes): Tour
    {
        return Tour::create(array_merge([
            'category_id' => $this->kategori->id,
            'destination' => 'Balkanlar',
            'description' => 'Tur.',
            'currency' => 'TRY',
            'is_active' => true,
        ], $attributes));
    }

    public function test_filtresiz_sayfa_tum_turlari_ve_secenekleri_basar(): void
    {
        $this->get('/balkan-turlari')
            ->assertOk()
            ->assertSee('Kısa Belgrad')
            ->assertSee('Uzun Balkan')
            ->assertSee('Jolly Saraybosna')
            ->assertSee('(3 tur)', false)
            // facet seçenekleri + sayaçları
            ->assertSee('4 gece 5 gün')
            ->assertSee('7 gece 8 gün')
            ->assertSee('Eylül')
            ->assertSee('Ekim')
            ->assertSee('TatilOne')
            ->assertSee('Jolly')
            ->assertSee('İstanbul (2)')
            ->assertSee('Ankara (1)');
    }

    public function test_sure_filtresi_listeyi_daraltir_sayaclar_degismez(): void
    {
        $html = $this->get('/balkan-turlari?sure[]=5')->assertOk()->getContent();

        $this->assertStringContainsString('Kısa Belgrad', $html);
        $this->assertStringNotContainsString('Uzun Balkan', $html);
        $this->assertStringContainsString('(1 tur)', $html);
        // SEO blokları ve facet sayaçları filtrelenmemiş kümeden
        $this->assertStringContainsString('2 acentanın fiyatı karşılaştırmalı', $html);
        $this->assertStringContainsString('listelenen 3 turun fiyatı', $html);
    }

    public function test_ay_filtresi_coklu_secimi_kabul_eder(): void
    {
        $this->get('/balkan-turlari?ay[]=10')
            ->assertOk()
            ->assertSee('Uzun Balkan')
            ->assertDontSee('Kısa Belgrad');

        $this->get('/balkan-turlari?ay[]=9&ay[]=10')
            ->assertOk()
            ->assertSee('Uzun Balkan')
            ->assertSee('Kısa Belgrad')
            ->assertSee('Jolly Saraybosna');
    }

    public function test_acenta_ve_kalkis_filtreleri(): void
    {
        $this->get('/balkan-turlari?acenta[]='.$this->b->id)
            ->assertOk()
            ->assertSee('Jolly Saraybosna')
            ->assertDontSee('Kısa Belgrad');

        $this->get('/balkan-turlari?kalkis=Ankara')
            ->assertOk()
            ->assertSee('Uzun Balkan')
            ->assertDontSee('Jolly Saraybosna');
    }

    public function test_fiyat_araligi_tl_uzerinden_calisir(): void
    {
        $this->get('/balkan-turlari?min_fiyat=10000&max_fiyat=16000')
            ->assertOk()
            ->assertSee('Uzun Balkan')
            ->assertDontSee('Kısa Belgrad')
            ->assertDontSee('Jolly Saraybosna');
    }

    public function test_siralama_fiyat_azalan(): void
    {
        $html = $this->get('/balkan-turlari?sirala=fiyat_azalan')->assertOk()->getContent();

        $this->assertGreaterThan(
            strpos($html, 'Jolly Saraybosna'),
            strpos($html, 'Kısa Belgrad'),
            'Fiyat azalan sıralamada en pahalı tur önce gelmeli.'
        );
    }

    public function test_bos_sonucta_temizle_baglantisi_var_sayfa_kapanmaz(): void
    {
        $this->get('/balkan-turlari?sure[]=3')
            ->assertOk()
            ->assertSee('Bu filtrelerle tur bulunamadı')
            ->assertSee('Filtreleri temizle')
            // sayfa metni ve SSS yine basılır (envanter var, yalnız filtre boş)
            ->assertSee('Sıkça Sorulan Sorular');
    }

    public function test_gecersiz_parametreler_sessizce_duser(): void
    {
        $f = LandingFilter::parse(['sure' => ['abc', '0', '5'], 'ay' => '13', 'sirala' => 'hack', 'min_fiyat' => '-5', 'acenta' => 'x']);

        $this->assertSame([5], $f['sure']);
        $this->assertSame([], $f['ay']);
        $this->assertSame('onerilen', $f['sirala']);
        $this->assertNull($f['min_fiyat']);
        $this->assertSame([], $f['acenta']);
        $this->assertFalse(LandingFilter::isActive($f) && $f['sure'] === []);

        $this->get('/balkan-turlari?sure[]=abc&ay=13&sirala=hack')->assertOk()->assertSee('(3 tur)', false);
    }

    public function test_filtreli_adres_temiz_adrese_kanonik_verir(): void
    {
        $this->get('/balkan-turlari?sure[]=5&sirala=fiyat_azalan')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/balkan-turlari').'">', false);
    }
}
