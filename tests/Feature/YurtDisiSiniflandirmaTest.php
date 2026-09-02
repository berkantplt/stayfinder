<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\DestinationProfile;
use App\Models\Tour;
use App\Services\DestinationOriginResolver;
use App\Support\DestinationClassifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Yurt içi/dışı bayrağı.
 *
 * Canlı hata: "yurt içinde olsun" diyen kullanıcıya Rovaniemi (Lapland) turu
 * çıktı. Sebep bayrağın YANLIŞ değil BOŞ kalması — statik liste Rovaniemi'yi
 * tanımıyordu, null dönünce kayıt anında bayrağa dokunulmuyordu, kolon
 * varsayılanı da false olduğu için tur "yurt içi" görünüyordu.
 */
class YurtDisiSiniflandirmaTest extends TestCase
{
    use RefreshDatabase;

    public function test_kuzey_sehirleri_artik_taniniyor(): void
    {
        foreach (['Rovaniemi', 'Lapland', 'Tromso', 'Reykjavik', 'Riga'] as $yer) {
            $this->assertTrue(DestinationClassifier::isInternational($yer), $yer.' yurt dışı olmalı');
        }
    }

    public function test_yurt_ici_yerler_bozulmadi(): void
    {
        foreach (['Fethiye', 'Kapadokya', 'Bodrum', 'Antalya', 'Ege Bölgesi', 'Doğu Anadolu', 'Kaş, Kalkan'] as $yer) {
            $this->assertFalse(DestinationClassifier::isInternational($yer), $yer.' yurt içi olmalı');
        }
    }

    public function test_liste_disi_yer_profil_ulkesinden_cozulur(): void
    {
        DestinationProfile::create([
            'city' => 'Ittoqqortoormiit', 'normalized_city' => DestinationProfile::normalize('Ittoqqortoormiit'),
            'crowd_score' => 0.1, 'liveliness_score' => 0.1, 'country' => 'Grönland',
            'source' => DestinationProfile::SOURCE_LLM, 'generated_at' => now(),
        ]);

        $resolver = app(DestinationOriginResolver::class);

        $this->assertNull(DestinationClassifier::isInternational('Ittoqqortoormiit'));
        $this->assertTrue($resolver->isInternational('Ittoqqortoormiit'));
    }

    public function test_profil_turkiye_diyorsa_yurt_ici_sayilir(): void
    {
        DestinationProfile::create([
            'city' => 'Kıyıköy', 'normalized_city' => DestinationProfile::normalize('Kıyıköy'),
            'crowd_score' => 0.2, 'liveliness_score' => 0.2, 'country' => 'Türkiye',
            'source' => DestinationProfile::SOURCE_LLM, 'generated_at' => now(),
        ]);

        $this->assertFalse(app(DestinationOriginResolver::class)->isInternational('Kıyıköy'));
    }

    public function test_profil_yoksa_null_kalir_bayraga_dokunulmaz(): void
    {
        $this->assertNull(app(DestinationOriginResolver::class)->isInternational('Bilinmeyenşehir'));
    }

    public function test_zenginlesmemis_profil_ulkesi_turkiye_sanilmaz(): void
    {
        DestinationProfile::create([
            'city' => 'Yenişehir X', 'normalized_city' => DestinationProfile::normalize('Yenişehir X'),
            'crowd_score' => 0.5, 'liveliness_score' => 0.5, 'country' => null,
            'source' => DestinationProfile::SOURCE_DEFAULT, 'generated_at' => now(),
        ]);

        $this->assertNull(app(DestinationOriginResolver::class)->isInternational('Yenişehir X'));
    }

    public function test_backfill_komutu_yanlis_bayragi_duzeltir(): void
    {
        $agency = Agency::create([
            'name' => 'A', 'slug' => 'a', 'email' => 'a@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);
        $tour = Tour::create([
            'agency_id' => $agency->id, 'title' => 'Lapland Turu', 'destination' => 'Rovaniemi',
            'description' => 'd', 'price' => 3619, 'currency' => 'EUR', 'duration_days' => 4,
            'departure_date' => today()->addDays(60), 'is_active' => true,
            'is_international' => false,   // hatalı miras
        ]);

        $this->artisan('app:classify-tour-destinations')->assertSuccessful();

        $this->assertTrue((bool) $tour->fresh()->is_international);
    }
}
