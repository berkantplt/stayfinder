<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Support\DepartureCityExtractor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Faz 0.2 — kalkış şehri çıkarımı.
 *
 * En kritik test: UYDURMAMA. Yanlış kalkış şehri, "İstanbul kalkışlı Kapadokya
 * turları" sayfasına İzmir'den kalkan turu koyar; kullanıcı sayfaya güvenip
 * tıklar ve yanlış ürünle karşılaşır. Boş alan bundan iyidir.
 */
class DepartureCityExtractorTest extends TestCase
{
    use RefreshDatabase;

    private function tur(array $attributes = []): Tour
    {
        $agency = Agency::create([
            'name' => 'Acenta '.uniqid(),
            'slug' => 'acenta-'.uniqid(),
            'email' => uniqid().'@ornek.com',
            'is_active' => true,
            // Tour::active() kategori lisansı da kontrol eder; testin konusu
            // lisanslama değil, bu yüzden eski erişim hakkı verilir.
            'legacy_category_access' => true,
        ]);

        return Tour::create(array_merge([
            'agency_id' => $agency->id,
            'title' => 'Kapadokya Turu',
            'destination' => 'Kapadokya',
            'description' => 'Açıklama.',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(10),
            'is_active' => true,
        ], $attributes));
    }

    /** @dataProvider baslikKaliplari */
    public function test_baslik_kaliplarindan_sehir_cikar(string $baslik, string $beklenen): void
    {
        $sonuc = DepartureCityExtractor::extract($this->tur(['title' => $baslik]));

        $this->assertNotNull($sonuc, "Çıkarılamadı: {$baslik}");
        $this->assertSame($beklenen, $sonuc['city'], "Yanlış şehir: {$baslik}");
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function baslikKaliplari(): array
    {
        return [
            'kalkışlı' => ['İstanbul Kalkışlı Kapadokya Turu', 'İstanbul'],
            'küçük harf kalkışlı' => ['ankara kalkışlı karadeniz turu', 'Ankara'],
            'çıkışlı' => ['İzmir Çıkışlı Ege Turu', 'İzmir'],
            'hareketli' => ['Bursa Hareketli Abant Turu', 'Bursa'],
            'dan günübirlik' => ['Köprülü Kanyon Turu – Antalya\'dan Günübirlik', 'Antalya'],
            'den hareket' => ['Şile Ağva Turu, İstanbul\'den hareket', 'İstanbul'],
            'önekli sıfat' => ['Ucuz İstanbul Kalkışlı Balkan Turu', 'İstanbul'],
            'türkçe karakter' => ['Şanlıurfa Kalkışlı GAP Turu', 'Şanlıurfa'],
        ];
    }

    public function test_binis_noktalarindan_sehir_cikar(): void
    {
        // Yapısal alan: satırın kendisi zaten kalkış noktası, kalıp aranmaz.
        $tour = $this->tur(['departure_points' => "21:00 Ankara AŞTİ\n22:30 Kırıkkale"]);

        $sonuc = DepartureCityExtractor::extract($tour);

        $this->assertSame('Ankara', $sonuc['city']);
        $this->assertSame('departure_points', $sonuc['source']);
    }

    public function test_binis_noktasi_baslıktan_once_gelir(): void
    {
        // Acentanın girdiği yapısal alan, başlık metninden güvenilirdir.
        $tour = $this->tur([
            'title' => 'İzmir Kalkışlı Kapadokya Turu',
            'departure_points' => '06:00 Ankara Kızılay',
        ]);

        $this->assertSame('Ankara', DepartureCityExtractor::extract($tour)['city']);
    }

    public function test_programin_ilk_gununden_cikar(): void
    {
        $tour = $this->tur(['itinerary' => [
            ['title' => '1. Gün', 'content' => 'Konya kalkışlı hareketimizle yola çıkıyoruz.'],
        ]]);

        $sonuc = DepartureCityExtractor::extract($tour);

        $this->assertSame('Konya', $sonuc['city']);
        $this->assertSame('itinerary', $sonuc['source']);
    }

    // ── Uydurmama ───────────────────────────────────────────────────────────

    public function test_destinasyondan_kalkis_sehri_turetmez(): void
    {
        // "Kapadokya Turu" İstanbul'dan da Ankara'dan da kalkabilir.
        // Destinasyonu kalkış sanmak yanlış sayfa üretir.
        $tour = $this->tur(['title' => 'Kapadokya Turu', 'destination' => 'Nevşehir']);

        $this->assertNull(DepartureCityExtractor::extract($tour));
    }

    public function test_kalkis_ifadesi_yoksa_sehir_gecse_bile_cikarmaz(): void
    {
        // Başlıkta "İstanbul" var ama gidilen yer olarak — kalkış değil.
        $tour = $this->tur(['title' => 'İstanbul Boğaz Turu']);

        $this->assertNull(DepartureCityExtractor::extract($tour));
    }

    public function test_sablon_aciklamadan_cikarmaz(): void
    {
        $tour = $this->tur([
            'title' => 'Bodrum Tatili',
            'description' => 'Bu muhteşem tur ile Bodrum keşfedin. 4 gün boyunca unutulmaz anılar.',
        ]);

        $this->assertNull(DepartureCityExtractor::extract($tour));
    }

    public function test_gecersiz_sehir_adi_cikarmaz(): void
    {
        $tour = $this->tur(['title' => 'Otelden Kalkışlı Günübirlik Tur']);

        $this->assertNull(DepartureCityExtractor::extract($tour));
    }

    // ── Komut ───────────────────────────────────────────────────────────────

    public function test_komut_kuru_calismada_kaydetmez(): void
    {
        $tour = $this->tur(['title' => 'İstanbul Kalkışlı Kapadokya Turu']);

        $this->artisan('seo:backfill-departure-city --dry-run')->assertSuccessful();

        $this->assertNull($tour->fresh()->departure_city);
    }

    public function test_komut_kalkis_sehrini_kaydeder(): void
    {
        $tour = $this->tur(['title' => 'İstanbul Kalkışlı Kapadokya Turu']);

        $this->artisan('seo:backfill-departure-city')->assertSuccessful();

        $this->assertSame('İstanbul', $tour->fresh()->departure_city);
    }

    public function test_komut_dolu_alani_ezmez(): void
    {
        $tour = $this->tur([
            'title' => 'İstanbul Kalkışlı Kapadokya Turu',
            'departure_city' => 'Ankara',
        ]);

        $this->artisan('seo:backfill-departure-city')->assertSuccessful();

        $this->assertSame('Ankara', $tour->fresh()->departure_city);
    }

    public function test_force_ile_dolu_alan_guncellenir(): void
    {
        $tour = $this->tur([
            'title' => 'İstanbul Kalkışlı Kapadokya Turu',
            'departure_city' => 'Ankara',
        ]);

        $this->artisan('seo:backfill-departure-city --force')->assertSuccessful();

        $this->assertSame('İstanbul', $tour->fresh()->departure_city);
    }

    // ── Admin toplu düzenleme ekranı ────────────────────────────────────────

    private function admin(): \App\Models\User
    {
        return \App\Models\User::create([
            'name' => 'Yönetici',
            'email' => uniqid().'@ornek.com',
            'password' => bcrypt('sifre1234'),
            'role' => 'admin',
        ]);
    }

    public function test_admin_ekrani_eksik_turlari_listeler(): void
    {
        $eksik = $this->tur(['title' => 'Kalkışsız Tur']);
        $dolu = $this->tur(['title' => 'Dolu Tur', 'departure_city' => 'Ankara']);

        $this->actingAs($this->admin())
            ->get(route('admin.departure-cities'))
            ->assertOk()
            ->assertSee('Kalkışsız Tur')
            ->assertDontSee('Dolu Tur');
    }

    public function test_admin_ekrani_otomatik_oneriyi_gosterir(): void
    {
        $this->tur(['title' => 'İstanbul Kalkışlı Kapadokya Turu']);

        $this->actingAs($this->admin())
            ->get(route('admin.departure-cities'))
            ->assertOk()
            ->assertSee('öneri: İstanbul');
    }

    public function test_toplu_kaydetme_calisir(): void
    {
        $a = $this->tur(['title' => 'A Turu']);
        $b = $this->tur(['title' => 'B Turu']);

        $this->actingAs($this->admin())
            ->put(route('admin.departure-cities.update'), [
                'cities' => [$a->id => 'İzmir', $b->id => 'Bursa'],
            ])
            ->assertRedirect();

        $this->assertSame('İzmir', $a->fresh()->departure_city);
        $this->assertSame('Bursa', $b->fresh()->departure_city);
    }

    public function test_listede_olmayan_sehir_kaydedilmez(): void
    {
        // Serbest metin filtreyi bozar ("Istanbul" ≠ "İstanbul").
        $tour = $this->tur();

        $this->actingAs($this->admin())
            ->put(route('admin.departure-cities.update'), [
                'cities' => [$tour->id => 'Vakvakistan'],
            ])
            ->assertRedirect();

        $this->assertNull($tour->fresh()->departure_city);
    }

    public function test_bos_deger_alani_temizler(): void
    {
        $tour = $this->tur(['departure_city' => 'Ankara']);

        $this->actingAs($this->admin())
            ->put(route('admin.departure-cities.update'), ['cities' => [$tour->id => '']])
            ->assertRedirect();

        $this->assertNull($tour->fresh()->departure_city);
    }

    public function test_admin_olmayan_ekrana_giremez(): void
    {
        $this->get(route('admin.departure-cities'))->assertRedirect();
    }

    public function test_doldurulan_sehir_filtreyle_bulunur(): void
    {
        // Alanın asıl amacı: departsFrom scope'u ve şehir kalkışlı sayfalar.
        $tour = $this->tur(['title' => 'İstanbul Kalkışlı Kapadokya Turu']);
        $this->artisan('seo:backfill-departure-city');

        $this->assertTrue(
            Tour::active()->departsFrom('İstanbul')->whereKey($tour->getKey())->exists()
        );
    }
}
