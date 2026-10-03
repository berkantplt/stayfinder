<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Campaign;
use App\Models\Category;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
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
        // Süre: hazır seçenek yok, gece sayısı elle girilir
        $this->assertStringNotContainsString('name="sure[]"', $mobil);
        $this->assertStringContainsString('type="number" name="gece"', $mobil);
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

    public function test_gece_sayisi_filtresi_duration_nights_ve_gun_eksi_bir_kuraliyla_eslesir(): void
    {
        // Gece girilmemiş eski tur: 6 gün → 5 gece sayılır (Tour::duration_label kuralı)
        $this->tur(['title' => 'Eski Kayıt', 'agency_id' => $this->a->id, 'price' => 5000, 'duration_days' => 6, 'duration_nights' => null, 'departure_date' => '2027-09-15']);

        $this->get('/balkan-turlari?gece=7')->assertOk()
            ->assertSee('Uzun Balkan')->assertSee('Jolly Saraybosna')->assertDontSee('Kısa Belgrad')->assertDontSee('Eski Kayıt');

        $this->get('/balkan-turlari?gece=5')->assertOk()
            ->assertSee('Eski Kayıt')->assertDontSee('Uzun Balkan');

        $mobil = $this->mobilBlok($this->get('/balkan-turlari?gece=4')->assertOk()->getContent());
        $this->assertStringContainsString('name="gece" inputmode="numeric" min="0" max="365" step="1" placeholder="Örn. 4" value="4"', $mobil);
        $this->assertStringContainsString('class="lpm-cip on">4 gece', $mobil);   // şeritte kaldırılabilir çip
        $this->assertStringContainsString('<span class="lpm-sayac">1</span>', $mobil);

        $this->getJson('/balkan-turlari?gece=7&sayac=1')->assertOk()->assertExactJson(['adet' => 2]);
        $this->assertNull(LandingFilter::parse(['gece' => 'abc'])['gece']);
        $this->assertSame(0, LandingFilter::parse(['gece' => '0'])['gece']);
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
        // Panel düğmesi sunucuda da JS ile aynı metni basar (0 iken "0 turu göster" değil)
        $this->assertStringContainsString('>Bu filtrelerle tur yok</button>', $mobil);
        $this->assertStringNotContainsString('0 turu göster', $mobil);

        Category::create(['name' => 'Kayak Turları', 'slug' => 'kayak-turlari']);
        $mobil = $this->mobilBlok($this->get('/kayak-turlari')->assertOk()->getContent());
        $this->assertStringContainsString('<h1 class="lpm-h1">Kayak Turları</h1>', $mobil);
        $this->assertStringContainsString('Şu anda bu başlıkta yayında tur yok.', $mobil);
        $this->assertStringNotContainsString('class="lpm-serit"', $mobil);
    }

    public function test_mobil_kart_puan_rozeti_yalniz_yorumlu_turda(): void
    {
        $tur = Tour::where('title', 'Kısa Belgrad')->firstOrFail();
        foreach ([5, 4] as $puan) {
            Review::create(['user_id' => User::factory()->create()->id, 'tour_id' => $tur->id, 'rating' => $puan, 'comment' => 'Güzeldi']);
        }

        $mobil = $this->mobilBlok($this->get('/balkan-turlari')->assertOk()->getContent());

        $this->assertStringContainsString('4,5 <i>(2)</i>', $mobil);
        $this->assertSame(1, substr_count($mobil, 'class="lpm-puan"'), 'Yorumsuz turlarda puan rozeti basılmamalı');
    }

    public function test_mobil_kart_kampanya_fiyati_ve_indirim_rozeti_tek_sorguyla(): void
    {
        $tur = Tour::where('title', 'Kısa Belgrad')->firstOrFail();
        Campaign::create(['tour_id' => $tur->id, 'discount_price' => 7000, 'label' => 'Erken', 'is_active' => true, 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()]);
        // Süresi geçmiş kampanya sayılmaz
        Campaign::create(['tour_id' => $tur->id, 'discount_price' => 100, 'label' => 'Eski', 'is_active' => true, 'starts_at' => now()->subDays(10), 'ends_at' => now()->subDays(5)]);

        \Illuminate\Support\Facades\DB::enableQueryLog();
        $html = $this->get('/balkan-turlari')->assertOk()->getContent();
        $kampanyaSorgulari = collect(\Illuminate\Support\Facades\DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'campaigns'))->count();
        \Illuminate\Support\Facades\DB::disableQueryLog();

        $mobil = $this->mobilBlok($html);
        $this->assertStringContainsString('<strong class="kampanya">7.000 ₺</strong>', $mobil);
        $this->assertStringContainsString('<s>9.000 ₺</s>', $mobil);
        $this->assertStringContainsString('%22 İNDİRİM', $mobil);
        $this->assertSame(1, $kampanyaSorgulari, 'Kampanya tek IN sorgusuyla yüklenmeli (iki gövde × N tur değil)');
    }

    public function test_mobil_serit_cipleri_filtreyi_korur_ve_kaldirma_adresi_verir(): void
    {
        $mobil = $this->mobilBlok($this->get('/balkan-turlari?gece=7&acenta[]='.$this->a->id.'&sirala=fiyat_azalan')->assertOk()->getContent());
        $bas = strpos($mobil, 'class="lpm-serit"');
        $serit = substr($mobil, $bas, strpos($mobil, 'lpm-liste', $bas) - $bas);

        // Gece ve acenta kaldırma çipleri: ilgili parametre düşer, diğerleri ve sıralama kalır
        $this->assertMatchesRegularExpression('#href="[^"]*acenta%5B0%5D='.$this->a->id.'[^"]*sirala=fiyat_azalan" class="lpm-cip on">7 gece#', $serit);
        $this->assertMatchesRegularExpression('#href="[^"]*gece=7[^"]*sirala=fiyat_azalan" class="lpm-cip on">Acenta seçimi#', $serit);
        $this->assertStringNotContainsString('gece=7&amp;acenta', substr($serit, strpos($serit, 'Acenta seçimi') - 200, 200) === false ? '' : '');
        $this->assertStringContainsString('Sırala: <span>Fiyat (azalan)</span>', $serit);
        $this->assertStringContainsString('<span class="lpm-sayac">2</span>', $serit);
        // Panel formu adresten gelen acentayı gizli taşır
        $this->assertStringContainsString('<input type="hidden" name="acenta[]" value="'.$this->a->id.'">', $mobil);
    }

    public function test_canli_sayac_ucu_noindex_basligi_tasir_ve_robots_disallow_eder(): void
    {
        $this->getJson('/balkan-turlari?sayac=1')->assertOk()->assertHeader('X-Robots-Tag', 'noindex');

        // robots.txt kuralları yalnız production'da basılır (diğer ortamlar tümden kapalı)
        app()->detectEnvironment(fn () => 'production');
        $this->get('/robots.txt')->assertOk()->assertSee('Disallow: /*?*sayac=');
        app()->detectEnvironment(fn () => 'testing');
    }
}
