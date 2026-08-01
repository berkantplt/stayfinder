<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Ana sayfa yatay filtre barı (Etstur uyarlaması G2) bekçileri: her filtre
 * parametresi sorguya doğru uygulanır, AJAX cevabı {html, count} JSON'dur,
 * sayfa facet'lerle birlikte render olur.
 */
class HomeFilterTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->agency = Agency::create([
            'name' => 'Filtre Acenta',
            'slug' => 'filtre-acenta',
            'email' => 'filtre@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
    }

    private function makeTour(array $attrs = []): Tour
    {
        return Tour::create(array_merge([
            'agency_id' => $this->agency->id,
            'title' => 'Tur '.uniqid(),
            'destination' => 'Kapadokya',
            'description' => 'Test',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(30),
            'is_active' => true,
        ], $attrs));
    }

    private function ajax(array $params = [])
    {
        return $this->get(route('home', $params), ['X-Requested-With' => 'XMLHttpRequest']);
    }

    public function test_ana_sayfa_yatay_filtre_bariyla_render_olur(): void
    {
        $this->makeTour();
        Cache::forget('home_filter_facets_v1');

        $r = $this->get(route('home'))->assertOk();
        $r->assertSee('Kategoriler');
        $r->assertSee('Vizesiz turlar');
        $r->assertSee('Kalkış noktası');
        $r->assertSee('Özel günler');
        $r->assertSee('yLiveCount', false);
    }

    public function test_ajax_cevabi_html_ve_count_iceren_jsondur(): void
    {
        $this->makeTour(['title' => 'Json Turu']);

        $r = $this->ajax()->assertOk()->assertJsonStructure(['html', 'count']);
        $this->assertSame(1, $r->json('count'));
        $this->assertStringContainsString('Json Turu', $r->json('html'));
    }

    public function test_vize_filtresi_destinasyon_profillerinden_calisir(): void
    {
        // Kaynak: destination_profiles.requires_visa_for_tr (chatbot'la aynı).
        // Profili olmayan destinasyon = bilinmiyor → filtre dışı (uydurma yok).
        \App\Models\DestinationProfile::create([
            'city' => 'Belgrad', 'normalized_city' => 'belgrad',
            'crowd_score' => 0.5, 'liveliness_score' => 0.5,
            'source' => \App\Models\DestinationProfile::SOURCE_LLM,
            'enrichment_version' => 2, 'requires_visa_for_tr' => false,
        ]);
        \App\Models\DestinationProfile::create([
            'city' => 'Paris', 'normalized_city' => 'paris',
            'crowd_score' => 0.8, 'liveliness_score' => 0.8,
            'source' => \App\Models\DestinationProfile::SOURCE_LLM,
            'enrichment_version' => 2, 'requires_visa_for_tr' => true,
        ]);
        Cache::forget('home_visa_cities_v1');

        $this->makeTour(['title' => 'Belgrad Kacamagi', 'destination' => 'Belgrad']);
        $this->makeTour(['title' => 'Paris Turu', 'destination' => 'Paris']);
        $this->makeTour(['title' => 'Profilsiz Tur', 'destination' => 'Bilinmezistan']);

        $r = $this->ajax(['visa' => ['vizesiz']]);
        $this->assertSame(1, $r->json('count'));
        $this->assertStringContainsString('Belgrad Kacamagi', $r->json('html'));

        // İkisi de işaretliyse: vize bilgisi NET olanlar (profilsiz hariç)
        $r2 = $this->ajax(['visa' => ['vizesiz', 'vizeli']]);
        $this->assertSame(2, $r2->json('count'));
    }

    public function test_gun_bandi_filtresi(): void
    {
        $this->makeTour(['title' => 'Kisa', 'duration_days' => 2]);
        $this->makeTour(['title' => 'Orta', 'duration_days' => 5]);
        $this->makeTour(['title' => 'Uzun', 'duration_days' => 12]);

        $r = $this->ajax(['days' => ['4-6', '10+']]);
        $this->assertSame(2, $r->json('count'));
        $this->assertStringNotContainsString('Kisa', $r->json('html'));
    }

    /** Kalkış listesi sabit değil: yalnız DURAK olarak geçen şehir de seçenek
     *  olarak görünmeli ve yeni tur eklenince cache beklemeden gelmeli. */
    public function test_kalkis_secenekleri_durak_sehirlerini_de_kapsar(): void
    {
        $this->makeTour(['title' => 'A', 'departure_city' => 'İstanbul', 'stop_cities' => ['Bolu']]);
        $this->makeTour(['title' => 'B', 'departure_city' => 'İstanbul']);

        $html = $this->get(route('home'))->assertOk()->getContent();

        // Bolu hiçbir turun kalkış şehri DEĞİL, yalnız durak — yine de seçenek olmalı
        $this->assertStringContainsString('value="Bolu"', $html);
        $this->assertStringContainsString('value="İstanbul"', $html);

        // Yeni durak şehri eklenince liste kendiliğinden güncellenmeli (cache düşer)
        $this->makeTour(['title' => 'C', 'departure_city' => 'Ankara', 'stop_cities' => ['Kırıkkale']]);
        $html2 = $this->get(route('home'))->assertOk()->getContent();
        $this->assertStringContainsString('value="Kırıkkale"', $html2);
    }

    public function test_ay_filtresi_tekil_tarih_ve_tarih_listesini_kapsar(): void
    {
        // Hedef ayın ORTASI kullanılır: today()->addMonths(3) ayın 29'una denk
        // gelirse +3 gün bir sonraki aya taşıyor ve test ayın sonunda kendi
        // kendine kırılıyordu (filtre değil, kurgu hatasıydı).
        $target = today()->addMonths(3)->startOfMonth()->addDays(9);
        $this->makeTour(['title' => 'Tekil Tarihli', 'departure_date' => $target]);
        // Tarih listesi: departure_date başka ayda ama dates içinde hedef ay var
        $other = $this->makeTour(['title' => 'Coklu Tarihli', 'departure_date' => today()->addMonths(5)]);
        TourDate::create([
            'tour_id' => $other->id,
            'departure_date' => $target->copy()->addDays(3),
            'return_date' => $target->copy()->addDays(8),
        ]);
        $this->makeTour(['title' => 'Alakasiz', 'departure_date' => today()->addMonths(7)]);

        $r = $this->ajax(['months' => [$target->month]]);
        $this->assertSame(2, $r->json('count'));
        $this->assertStringNotContainsString('Alakasiz', $r->json('html'));
    }

    public function test_ozel_gun_filtresi_config_araligindan_calisir(): void
    {
        config(['special_periods.test-donemi' => [
            'label' => 'Test Dönemi',
            'ranges' => [[today()->addDays(10)->toDateString(), today()->addDays(14)->toDateString()]],
        ]]);

        $this->makeTour(['title' => 'Donem Ici', 'departure_date' => today()->addDays(12)]);
        $this->makeTour(['title' => 'Donem Disi', 'departure_date' => today()->addDays(40)]);

        $r = $this->ajax(['special' => 'test-donemi']);
        $this->assertSame(1, $r->json('count'));
        $this->assertStringContainsString('Donem Ici', $r->json('html'));
    }

    public function test_butce_filtresi_tl_normalize_fiyattan(): void
    {
        $this->makeTour(['title' => 'Ucuz', 'price' => 15000]);
        $this->makeTour(['title' => 'Pahali', 'price' => 80000]);

        $r = $this->ajax(['budget_max' => 30]);
        $this->assertSame(1, $r->json('count'));
        $this->assertStringContainsString('Ucuz', $r->json('html'));
    }

    public function test_kalkis_filtresi_durak_sehirlerini_de_kapsar(): void
    {
        $this->makeTour(['title' => 'Istanbuldan', 'departure_city' => 'İstanbul']);
        $this->makeTour(['title' => 'Duraktan', 'departure_city' => 'Bursa', 'stop_cities' => ['Ankara', 'İstanbul']]);
        $this->makeTour(['title' => 'Uzak', 'departure_city' => 'İzmir']);

        $r = $this->ajax(['departures' => ['İstanbul']]);
        $this->assertSame(2, $r->json('count'));
        $this->assertStringNotContainsString('Uzak', $r->json('html'));
    }

    public function test_destinasyon_filtresi_cok_sehirli_rotayi_yakalar(): void
    {
        $this->makeTour(['title' => 'Italya Turu', 'destination' => 'Paris, Roma ve Floransa']);
        $this->makeTour(['title' => 'Yerli Tur', 'destination' => 'Kapadokya']);

        $r = $this->ajax(['destinations' => ['Roma']]);
        $this->assertSame(1, $r->json('count'));
        $this->assertStringContainsString('Italya Turu', $r->json('html'));
    }

    public function test_kategori_filtresi_ust_kategori_cocuklarini_kapsar(): void
    {
        $parent = Category::create(['name' => 'Yurt Dışı', 'slug' => 'yurt-disi', 'is_active' => true]);
        $child = Category::create(['name' => 'Avrupa', 'slug' => 'avrupa', 'parent_id' => $parent->id, 'is_active' => true]);
        $this->makeTour(['title' => 'Cocukta', 'category_id' => $child->id]);
        $this->makeTour(['title' => 'Ustte', 'category_id' => $parent->id]);
        $this->makeTour(['title' => 'Kategorisiz', 'category_id' => null]);

        $r = $this->ajax(['category' => 'yurt-disi']);
        $this->assertSame(2, $r->json('count'));
        $this->assertStringNotContainsString('Kategorisiz', $r->json('html'));
    }

    public function test_tarihe_gore_siralama_tarihsizleri_sona_atar(): void
    {
        $this->makeTour(['title' => 'Gec Tarih', 'departure_date' => today()->addDays(60)]);
        $this->makeTour(['title' => 'Tarihsiz', 'departure_date' => null]);
        $this->makeTour(['title' => 'Erken Tarih', 'departure_date' => today()->addDays(5)]);

        $html = $this->ajax(['sort' => 'date_asc'])->json('html');
        $posErken = strpos($html, 'Erken Tarih');
        $posGec = strpos($html, 'Gec Tarih');
        $posTarihsiz = strpos($html, 'Tarihsiz');
        $this->assertLessThan($posGec, $posErken);
        $this->assertLessThan($posTarihsiz, $posGec);
    }

    public function test_eski_tekil_destination_linki_calismaya_devam_eder(): void
    {
        $this->makeTour(['title' => 'Eski Link', 'destination' => 'Kapadokya']);
        $this->makeTour(['title' => 'Baska', 'destination' => 'Bodrum']);

        $r = $this->ajax(['destination' => 'Kapadokya']);
        $this->assertSame(1, $r->json('count'));
    }
}
