<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\DestinationProfile;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use App\Services\Matching\TourMatcher;
use App\Support\SeaHolidayDestinations;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

/**
 * "Fethiye gibi" → deniz tatili.
 *
 * Canlı şikayet: kullanıcı Fethiye'yi kıyas gösterdi, sistem Kapadokya, Doğu
 * Anadolu ve Lapland önerdi. Sebep rubrikte deniz ekseninin olmaması — Fethiye
 * "doğa 80", Kapadokya da "doğa 80", matematiksel olarak aynı şey.
 *
 * Çözüm bilerek coğrafi çıkarım DEĞİL küratörlü liste: "kıyısı var mı" diye
 * sorulsa Antarktika da geçerdi.
 */
class DenizTatiliEslesmeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OpenAI::fake([]); // kaçak LLM çağrısı anında patlasın
    }

    private function makeTour(string $title, string $destination, ?string $kalkis = null): Tour
    {
        $agency = Agency::create([
            'name' => 'A '.uniqid(), 'slug' => 'a-'.uniqid(), 'email' => uniqid().'@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);
        $tour = Tour::create([
            'agency_id' => $agency->id, 'title' => $title, 'destination' => $destination,
            'description' => 'd', 'price' => 15000, 'currency' => 'TRY', 'duration_days' => 4,
            'departure_date' => $kalkis ?? today()->addDays(30)->toDateString(),
            'is_active' => true,
        ]);

        $payload = [];
        foreach (Rubric::dimensions() as $d) {
            $payload[$d] = ['value' => 3, 'confidence' => 'high', 'evidence' => 'test'];
        }
        TourRubricScore::create([
            'tour_id' => $tour->id, 'rubric_version' => Rubric::VERSION,
            'input_hash' => 'h'.uniqid(), 'scores' => $payload,
            'review_status' => TourRubricScore::STATUS_AUTO, 'scored_at' => now(),
        ]);

        return $tour;
    }

    /** @return string[] gösterilen turların destinasyonları */
    private function ara(array $baglam): array
    {
        $sonuc = app(TourMatcher::class)->match(
            ['degerler' => ['tempo' => 50], 'agirliklar' => ['tempo' => 1.0]],
            $baglam,
        );

        return [array_column($sonuc['tours'], 'destination'), $sonuc['relaxation_notes']];
    }

    // ---- liste: kıyı DEĞİL, deniz tatili ----

    public function test_kiyisi_olmak_yetmez_deniz_tatili_yeri_olmali(): void
    {
        $this->assertTrue(SeaHolidayDestinations::matches('Fethiye, Ölüdeniz'));
        $this->assertTrue(SeaHolidayDestinations::matches('Ege Bölgesi'));
        $this->assertTrue(SeaHolidayDestinations::matches('Bodrum'));

        // Hepsinin kıyısı var — hiçbiri deniz tatili değil
        $this->assertFalse(SeaHolidayDestinations::matches('Antarktika'));
        $this->assertFalse(SeaHolidayDestinations::matches('Rovaniemi'));
        $this->assertFalse(SeaHolidayDestinations::matches('Oslo'));
        $this->assertFalse(SeaHolidayDestinations::matches('İstanbul'));
        $this->assertFalse(SeaHolidayDestinations::matches('Kapadokya'));
    }

    public function test_iklim_kapisi_yalniz_veri_varken_isler(): void
    {
        $soguk = [1 => ['temp_c' => 11, 'condition' => 'soğuk']];
        $sicak = [8 => ['temp_c' => 31, 'condition' => 'sıcak']];

        $this->assertTrue(SeaHolidayDestinations::ayDenizeUygunDegilMi($soguk, 1));
        $this->assertFalse(SeaHolidayDestinations::ayDenizeUygunDegilMi($sicak, 8));
        // Veri yoksa "soğuk" varsayılmaz
        $this->assertFalse(SeaHolidayDestinations::ayDenizeUygunDegilMi(null, 1));
        $this->assertFalse(SeaHolidayDestinations::ayDenizeUygunDegilMi($soguk, null));
    }

    // ---- eşleştirici ----

    public function test_deniz_referansinda_ic_kesim_turlari_gelmez(): void
    {
        $this->makeTour('Fethiye Turu', 'Fethiye, Ölüdeniz');
        $this->makeTour('Kaş Kalkan Turu', 'Kaş, Kalkan');
        $this->makeTour('Bodrum Turu', 'Bodrum');
        $this->makeTour('Çeşme Alaçatı Turu', 'Çeşme, Alaçatı');
        $this->makeTour('Kapadokya Turu', 'Kapadokya');
        $this->makeTour('Doğu Anadolu Turu', 'Doğu Anadolu');
        $this->makeTour('Lapland Turu', 'Rovaniemi');

        [$destinasyonlar, $notlar] = $this->ara(['referans_yer' => 'Fethiye']);

        $this->assertCount(3, $destinasyonlar);
        foreach (['Kapadokya', 'Doğu Anadolu', 'Rovaniemi', 'Fethiye'] as $olmamali) {
            $this->assertNotContains($olmamali, $destinasyonlar);
        }
        $this->assertSame([], $notlar); // gevşetmeye gerek kalmadı
    }

    public function test_kiyas_yeri_deniz_degilse_sart_uygulanmaz(): void
    {
        $this->makeTour('Kapadokya Turu', 'Kapadokya');
        $this->makeTour('Doğu Anadolu Turu', 'Doğu Anadolu');
        $this->makeTour('Nemrut Turu', 'Nemrut');
        $this->makeTour('Bodrum Turu', 'Bodrum');

        [$destinasyonlar] = $this->ara(['referans_yer' => 'Kapadokya']);

        // İç kesim kıyaslamasında deniz şartı yok; yalnız Kapadokya'nın kendisi düşer
        $this->assertContains('Doğu Anadolu', $destinasyonlar);
        $this->assertNotContains('Kapadokya', $destinasyonlar);
    }

    public function test_liste_disi_yer_beach_etiketiyle_deniz_sayilir(): void
    {
        DestinationProfile::create([
            'city' => 'Sakinkoy', 'normalized_city' => DestinationProfile::normalize('Sakinkoy'),
            'crowd_score' => 0.3, 'liveliness_score' => 0.3, 'vibe_tags' => ['beach', 'nature'],
            'source' => DestinationProfile::SOURCE_LLM, 'generated_at' => now(),
        ]);

        $this->makeTour('Kaş Turu', 'Kaş');
        $this->makeTour('Bodrum Turu', 'Bodrum');
        $this->makeTour('Sakinköy Turu', 'Sakinkoy');
        $this->makeTour('Kapadokya Turu', 'Kapadokya');

        [$destinasyonlar, $notlar] = $this->ara(['referans_yer' => 'Fethiye']);

        $this->assertContains('Sakinkoy', $destinasyonlar);
        $this->assertNotContains('Kapadokya', $destinasyonlar);
        $this->assertSame([], $notlar);
    }

    public function test_denize_girilmeyen_ayda_kiyi_turu_deniz_sayilmaz(): void
    {
        DestinationProfile::create([
            'city' => 'Bodrum', 'normalized_city' => DestinationProfile::normalize('Bodrum'),
            'crowd_score' => 0.68, 'liveliness_score' => 0.86,
            'climate_by_month' => [1 => ['temp_c' => 12, 'condition' => 'soğuk']],
            'source' => DestinationProfile::SOURCE_LLM, 'generated_at' => now(),
        ]);

        $this->makeTour('Kaş Turu', 'Kaş');
        $this->makeTour('Çeşme Turu', 'Çeşme');
        $this->makeTour('Antalya Turu', 'Antalya');
        // Ocak kalkışlı Bodrum: yer deniz yeri ama o ay denize girilmiyor
        $this->makeTour('Kış Bodrum Turu', 'Bodrum', today()->addYear()->startOfYear()->toDateString());

        [$destinasyonlar, $notlar] = $this->ara(['referans_yer' => 'Fethiye']);

        $this->assertNotContains('Bodrum', $destinasyonlar);
        $this->assertSame([], $notlar);
    }

    public function test_deniz_turu_yoksa_once_kiyas_sonra_deniz_sarti_gevser(): void
    {
        $this->makeTour('Fethiye Turu', 'Fethiye');
        $this->makeTour('Kapadokya Turu', 'Kapadokya');

        [$destinasyonlar, $notlar] = $this->ara(['referans_yer' => 'Fethiye']);

        // Katalogda başka deniz turu yok: önce Fethiye geri gelir, sonra iç kesim
        $this->assertContains('Fethiye', $destinasyonlar);
        $this->assertContains('Kapadokya', $destinasyonlar);
        $metin = implode(' ', $notlar);
        $this->assertStringContainsString('Fethiye dışında', $metin);
        $this->assertStringContainsString('Deniz tatili', $metin);
    }
}
