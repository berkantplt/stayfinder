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

    // ── Mobil gövde (≤768px): başlık bloğu + yapışkan şerit + alttan filtre paneli ──

    /** Mobil gövde: .lp-mobil açılışından editoryal bloğa kadar (Blade yorumu çıktıya basılmaz). */
    private function mobilBlok(string $html): string
    {
        $start = strpos($html, '<div class="lp-mobil">');
        $end = strpos($html, 'class="lp-editorial"', $start) ?: strlen($html);

        return substr($html, $start, $end - $start);
    }

    public function test_mobil_baslik_blogu_ust_kategori_etiketi_ve_rozetleri_basar(): void
    {
        $ust = Category::create(['name' => 'Yurt Dışı Turlar', 'slug' => 'yurt-disi-turlar']);
        $this->kategori->update(['parent_id' => $ust->id, 'icon' => '🌍']);

        $html = $this->get('/balkan-turlari')->assertOk()->getContent();
        $mobil = $this->mobilBlok($html);

        $this->assertStringContainsString('class="lpm-etiket">Yurt Dışı Turlar</a>', $mobil);
        $this->assertStringContainsString('<h1 class="lpm-h1">Balkan Turları</h1>', $mobil);
        $this->assertStringContainsString('3 tur</span>', $mobil);
        $this->assertStringContainsString('2 acenta</span>', $mobil);
        $this->assertStringContainsString('En düşük 9.000 ₺', $mobil);
        $this->assertStringContainsString('2 acentanın fiyatı karşılaştırmalı', $mobil);
        $this->assertStringContainsString('🌍', $mobil);
    }

    public function test_mobil_filtre_paneli_kendi_formu_ve_acenta_filtresi_yok(): void
    {
        $html = $this->get('/balkan-turlari?sure[]=8')->assertOk()->getContent();
        $mobil = $this->mobilBlok($html);

        $this->assertStringContainsString('class="lpm-filtrele"', $mobil);
        $this->assertStringContainsString('<span class="lpm-sayac">1</span>', $mobil);
        $this->assertStringContainsString('id="lpm-form"', $mobil);
        // Sıralama radyoları, süre/ay kutuları, kalkış radyoları; acenta YOK (kullanıcı kararı)
        $this->assertStringContainsString('name="sirala" value="fiyat_azalan"', $mobil);
        $this->assertStringContainsString('name="sure[]" value="8" checked', $mobil);
        $this->assertStringContainsString('name="ay[]" value="9"', $mobil);
        $this->assertStringContainsString('name="kalkis" value="İstanbul"', $mobil);
        // Fiyat: kaydırıcı değil, elle girilen sayı kutuları (sınırlar yer tutucu)
        $this->assertStringContainsString('type="number" name="min_fiyat" inputmode="numeric" min="0" step="1" placeholder="9000"', $mobil);
        $this->assertStringContainsString('type="number" name="max_fiyat" inputmode="numeric" min="0" step="1" placeholder="21000"', $mobil);
        $this->assertStringNotContainsString('type="range"', $mobil);
        $this->assertStringNotContainsString('name="acenta[]"', $mobil);
        $this->assertStringContainsString('2 turu göster', $mobil);
        // Masaüstü formunda acenta hâlâ var (yalnız mobil karar)
        $this->assertStringContainsString('name="acenta[]"', $html);
    }

    public function test_mobil_kart_ulasim_kalkis_ve_turu_incele_basar(): void
    {
        $html = $this->get('/balkan-turlari')->assertOk()->getContent();
        $mobil = $this->mobilBlok($html);

        $this->assertStringContainsString('<h3 class="lpm-kart-baslik">Kısa Belgrad</h3>', $mobil);
        $this->assertStringContainsString('İstanbul çıkışlı', $mobil);
        $this->assertStringContainsString('Turu incele', $mobil);
        $this->assertStringContainsString('class="m-fav', $mobil);
    }

    public function test_canli_sayac_ucu_filtreli_adedi_json_doner(): void
    {
        $this->getJson('/balkan-turlari?sayac=1')->assertOk()->assertExactJson(['adet' => 3]);
        $this->getJson('/balkan-turlari?sure[]=5&sayac=1')->assertOk()->assertExactJson(['adet' => 1]);
        $this->getJson('/balkan-turlari?acenta[]='.$this->b->id.'&kalkis=Ankara&sayac=1')->assertOk()->assertExactJson(['adet' => 0]);
    }

    public function test_mobil_bos_filtre_sonucu_ve_tursuz_kategori_mesajlari(): void
    {
        $mobil = $this->mobilBlok($this->get('/balkan-turlari?sure[]=3')->assertOk()->getContent());
        $this->assertStringContainsString('Bu filtrelerle tur bulunamadı', $mobil);
        $this->assertStringContainsString('class="lpm-serit"', $mobil); // şerit yerinde kalır

        Category::create(['name' => 'Kayak Turları', 'slug' => 'kayak-turlari']);
        $mobil = $this->mobilBlok($this->get('/kayak-turlari')->assertOk()->getContent());
        $this->assertStringContainsString('<h1 class="lpm-h1">Kayak Turları</h1>', $mobil);
        $this->assertStringContainsString('Şu anda bu başlıkta yayında tur yok.', $mobil);
        $this->assertStringNotContainsString('class="lpm-serit"', $mobil);
    }
}
