<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Chat\LlmProfileBuilder;
use App\Services\Matching\Rubric;
use App\Services\Matching\TourMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

/**
 * "Sessiz sakin koy istedim, bana Kastamonu kanyonu geldi" şikayetinin
 * iki sebebini kilitler: tek ifadeden iki eksene ceza, ve çeşitlilik
 * kuralının belirgin ölçüde kötü turu öne alması.
 * Hepsi LLM'siz: boş fake kaçak çağrıyı patlatır.
 */
class MatcherKaliteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OpenAI::fake([]);
    }

    /** @param  array<string, int|null>  $scores1to5 */
    private function tur(string $baslik, array $scores1to5): Tour
    {
        $agency = Agency::create([
            'name' => 'A '.uniqid(), 'slug' => 'a-'.uniqid(), 'email' => uniqid().'@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id, 'title' => $baslik, 'destination' => 'Testşehir',
            'description' => 'd', 'price' => 10000, 'currency' => 'TRY', 'duration_days' => 5,
            'departure_date' => today()->addDays(10), 'return_date' => today()->addDays(15),
            'is_active' => true,
        ]);

        $payload = [];
        foreach (Rubric::dimensions() as $d) {
            $v = array_key_exists($d, $scores1to5) ? $scores1to5[$d] : 3;
            $payload[$d] = ['value' => $v, 'confidence' => 'high', 'evidence' => $v === null ? null : 'test'];
        }
        TourRubricScore::create([
            'tour_id' => $tour->id, 'rubric_version' => Rubric::VERSION,
            'input_hash' => 'h'.uniqid(), 'scores' => $payload, 'review_status' => 'auto', 'scored_at' => now(),
        ]);

        return $tour;
    }

    /**
     * Aynı alıntıdan iki boyut türetilirse ağırlık bölüşülür: tek dilek için
     * iki kez ceza kesilmesin.
     */
    public function test_ayni_alintidan_turetilen_boyutlar_agirligi_bolusur(): void
    {
        $builder = app(LlmProfileBuilder::class);
        $transkript = 'sessiz sakin koyları olan bir yer istiyorum';

        $tek = $builder->build(
            ['tempo' => ['deger' => 20, 'kanit' => 'sessiz sakin']],
            [], $transkript,
        );
        $cift = $builder->build([
            'tempo' => ['deger' => 20, 'kanit' => 'sessiz sakin'],
            'kalabaliklik' => ['deger' => 20, 'kanit' => 'sessiz sakin'],
        ], [], $transkript);

        $this->assertEqualsWithDelta(
            $tek['agirliklar']['tempo'],
            $cift['agirliklar']['tempo'] + $cift['agirliklar']['kalabaliklik'],
            0.01,
            'Aynı alıntıdan gelen iki boyutun toplam ağırlığı, tek boyutlu halinden fazla olmamalı',
        );
    }

    /** Ayrı alıntılar bölüşmez — gerçekten iki ayrı dilek iki kat etki eder. */
    public function test_ayri_alintilar_agirligi_bolusmez(): void
    {
        $sonuc = app(LlmProfileBuilder::class)->build([
            'tempo' => ['deger' => 20, 'kanit' => 'sakin olsun'],
            'konfor' => ['deger' => 90, 'kanit' => 'lüks otel'],
        ], [], 'sakin olsun ve lüks otel istiyorum');

        $this->assertEqualsWithDelta(0.6, $sonuc['agirliklar']['tempo'], 0.01);
        $this->assertEqualsWithDelta(0.8, $sonuc['agirliklar']['konfor'], 0.01);
    }

    /**
     * Çeşitlilik uğruna belirgin ölçüde kötü tur listeye sokulmaz:
     * aynı bantta üç iyi tur varken, başka banttan çok düşük puanlı tur
     * onların önüne geçemez.
     */
    public function test_cesitlilik_cok_daha_kotu_turu_one_almaz(): void
    {
        // doga_sehir 4 (=75, "yuksek" bant) → üçü de aynı bantta ve iyi
        $this->tur('İyi A', ['doga_sehir' => 4, 'tempo' => 1, 'kalabaliklik' => 1]);
        $this->tur('İyi B', ['doga_sehir' => 4, 'tempo' => 1, 'kalabaliklik' => 1]);
        $this->tur('İyi C', ['doga_sehir' => 4, 'tempo' => 1, 'kalabaliklik' => 1]);
        // doga_sehir 1 (=0, "dusuk" bant) ama tercihlerden çok uzak
        $zayif = $this->tur('Zayıf Şehir', ['doga_sehir' => 1, 'tempo' => 5, 'kalabaliklik' => 5]);

        $sonuc = app(TourMatcher::class)->match([
            'degerler' => ['tempo' => 10, 'kalabaliklik' => 10, 'doga_sehir' => 80],
            'agirliklar' => ['tempo' => 0.8, 'kalabaliklik' => 0.8, 'doga_sehir' => 0.6],
        ]);

        $basliklar = array_column($sonuc['tours'], 'title');
        $this->assertContains('İyi C', $basliklar, 'Üçüncü iyi tur bant kuralına kurban gitmemeli');
        $ilkUc = array_slice($basliklar, 0, 3);
        $this->assertNotContains($zayif->title, $ilkUc, 'Çok düşük puanlı tur ilk üçe girmemeli');
    }

    /**
     * Eşiği geçen tur azken liste iki kartta bitmez: eksik "yakın turlar"
     * ile tamamlanır — AMA önerilen listeye karışmaz (brif §6).
     */
    public function test_ince_listede_yakin_turlar_ayri_listede_doner(): void
    {
        $this->tur('Tam Uyan', ['tempo' => 1, 'kalabaliklik' => 1, 'doga_sehir' => 4]);
        $this->tur('Kısmen 1', ['tempo' => 3, 'kalabaliklik' => 3, 'doga_sehir' => 3]);
        $this->tur('Kısmen 2', ['tempo' => 3, 'kalabaliklik' => 3, 'doga_sehir' => 4]);

        $sonuc = app(TourMatcher::class)->match([
            'degerler' => ['tempo' => 10, 'kalabaliklik' => 10, 'doga_sehir' => 80],
            'agirliklar' => ['tempo' => 0.8, 'kalabaliklik' => 0.8, 'doga_sehir' => 0.6],
        ]);

        $rules = Rubric::resultRules();

        // Brif §6 bozulmadı: önerilenlerin hepsi eşiğin üstünde
        foreach ($sonuc['tours'] as $kart) {
            $this->assertGreaterThanOrEqual($rules['min_score'], $kart['match_percent']);
        }
        $this->assertLessThan($rules['min_candidates'], count($sonuc['tours']), 'Kurgu gereği önerilen liste ince olmalı');

        // Eksik, ayrı listeden tamamlanıyor
        $this->assertNotEmpty($sonuc['yakin_turlar'], 'İnce listede yakın turlar dolmalı');
        $this->assertCount(
            $rules['min_candidates'],
            array_merge($sonuc['tours'], $sonuc['yakin_turlar']),
            'İki liste birlikte asgari aday sayısına ulaşmalı',
        );

        foreach ($sonuc['yakin_turlar'] as $kart) {
            $this->assertTrue($kart['yakin'], 'Yakın tur bayrağı taşımalı');
            $this->assertNull($kart['compatibility_score'], 'Yakın turda uyum rozeti olmamalı');
            $this->assertLessThan($rules['min_score'], $kart['match_percent']);
        }
    }

    /**
     * Kanıtı düşen boyut, hayatta kalanın ağırlığını bölmemeli: payda yalnız
     * KABUL EDİLEN boyutlardan sayılır.
     */
    public function test_dusen_boyut_paydayi_sismez(): void
    {
        $sonuc = app(LlmProfileBuilder::class)->build([
            'tempo' => ['deger' => 20, 'kanit' => 'sessiz sakin'],
            // Aynı alıntı ama transkriptte GEÇMEYEN bir boyut → düşer
            'kalabaliklik' => ['deger' => 20, 'kanit' => 'kalabalık sevmem'],
        ], [], 'sessiz sakin bir yer istiyorum');

        $this->assertContains('kalabaliklik', $sonuc['dusurulen']);
        $this->assertEqualsWithDelta(0.6, $sonuc['agirliklar']['tempo'], 0.01,
            'Tek başına kalan boyut tam ağırlığını korumalı');
    }

    /** Eşik üstü havuz doluyken, az kart gösterilse bile yakın tur üretilmez. */
    public function test_havuz_doluyken_az_kart_gosterilse_de_yakin_tur_uretilmez(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $ad) {
            $this->tur('İyi '.$ad, ['tempo' => 1, 'kalabaliklik' => 1, 'doga_sehir' => 4]);
        }
        $this->tur('Zayıf', ['tempo' => 5, 'kalabaliklik' => 5, 'doga_sehir' => 2]);

        $sonuc = app(TourMatcher::class)->match([
            'degerler' => ['tempo' => 10, 'kalabaliklik' => 10, 'doga_sehir' => 80],
            'agirliklar' => ['tempo' => 0.8, 'kalabaliklik' => 0.8, 'doga_sehir' => 0.6],
        ], ['top_n' => 1]);

        $this->assertCount(1, $sonuc['tours']);
        $this->assertSame([], $sonuc['yakin_turlar'], 'Havuz doluyken zayıf tur eklenmemeli');
    }

    /** Önerilen liste zaten doluysa yakın tur listesi üretilmez. */
    public function test_liste_doluyken_yakin_tur_uretilmez(): void
    {
        foreach (['A', 'B', 'C', 'D'] as $ad) {
            $this->tur('İyi '.$ad, ['tempo' => 1, 'kalabaliklik' => 1, 'doga_sehir' => 4]);
        }
        $this->tur('Zayıf', ['tempo' => 5, 'kalabaliklik' => 5, 'doga_sehir' => 2]);

        $sonuc = app(TourMatcher::class)->match([
            'degerler' => ['tempo' => 10, 'kalabaliklik' => 10, 'doga_sehir' => 80],
            'agirliklar' => ['tempo' => 0.8, 'kalabaliklik' => 0.8, 'doga_sehir' => 0.6],
        ]);

        $this->assertGreaterThanOrEqual(Rubric::resultRules()['min_candidates'], count($sonuc['tours']));
        $this->assertSame([], $sonuc['yakin_turlar']);
    }

    /**
     * Teşhis komutu skoru doğru açıyor: tablo toplamı ile TourMatcher::skor()
     * ayrışırsa komut uyarı basar — burada uyarının HİÇ çıkmaması beklenir.
     */
    public function test_teshis_komutu_skoru_dogru_acar(): void
    {
        $tur = $this->tur('Teşhis Turu', ['tempo' => 4, 'kalabaliklik' => 4, 'doga_sehir' => 3]);

        $this->artisan('app:rubric-why', [
            'tur' => $tur->id,
            '--profil' => 'tempo=20,kalabaliklik=20,doga_sehir=70',
        ])
            ->expectsOutputToContain('Tempo')
            ->expectsOutputToContain('SKOR')
            ->doesntExpectOutputToContain('formüller ayrışmış')
            ->assertSuccessful();
    }
}
