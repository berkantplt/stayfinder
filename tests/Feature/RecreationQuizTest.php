<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Matching\QuizEvaluator;
use App\Services\Matching\Rubric;
use App\Services\Matching\TourMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

/**
 * Tur eşleştirme testi — brif §8 kabul kriterleri + uç durumlar.
 * Akışın tamamı LLM'siz olmalı: boş OpenAI::fake herhangi bir kaçak çağrıyı patlatır.
 */
class RecreationQuizTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OpenAI::fake([]);
    }

    // ---- yardımcılar ----

    private function makeTour(string $title, array $scores1to5, array $attrs = []): Tour
    {
        $agency = Agency::create([
            'name' => 'A '.uniqid(), 'slug' => 'a-'.uniqid(), 'email' => uniqid().'@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);

        $tour = Tour::create(array_merge([
            'agency_id' => $agency->id, 'title' => $title, 'destination' => 'Testşehir',
            'description' => 'd', 'price' => 10000, 'currency' => 'TRY', 'duration_days' => 5,
            'departure_date' => today()->addDays(10), 'return_date' => today()->addDays(15),
            'is_active' => true,
        ], $attrs));

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

    private function score(array $scores1to5, array $kullanici, array $agirliklar): ?int
    {
        $payload = [];
        foreach (Rubric::dimensions() as $d) {
            $v = array_key_exists($d, $scores1to5) ? $scores1to5[$d] : 3;
            $payload[$d] = ['value' => $v, 'confidence' => 'high', 'evidence' => null];
        }
        $rubricScore = new TourRubricScore(['scores' => $payload]);

        return app(TourMatcher::class)->skor($rubricScore, $kullanici, $agirliklar);
    }

    /** Orta yolcu cevap seti (tüm sorularda orta şık, q8 boş). */
    private function midAnswers(): array
    {
        return ['q1' => 1, 'q2' => 1, 'q3' => 1, 'q4' => 2, 'q5' => 1, 'q6' => 1, 'q7' => 1, 'q8' => []];
    }

    // ---- brif §8 kabul kriterleri ----

    /** 1. Uç profil: sakin + düşük fiziksel + yüksek konfor → trekking ilk 3'e giremez. */
    public function test_kabul_1_uc_profil_trekking_elenir(): void
    {
        $this->makeTour('Zorlu Trekking', ['tempo' => 4, 'fiziksel' => 5, 'konfor' => 1, 'doga_sehir' => 5]);
        $this->makeTour('Resort Tatili', ['tempo' => 1, 'fiziksel' => 1, 'konfor' => 5, 'doga_sehir' => 2]);
        $this->makeTour('Sakin Kasaba', ['tempo' => 1, 'fiziksel' => 2, 'konfor' => 3, 'doga_sehir' => 3]);
        $this->makeTour('Hafif Kültür', ['tempo' => 2, 'fiziksel' => 2, 'konfor' => 4, 'doga_sehir' => 2, 'kultur' => 4]);

        // q1=0 sakin, q2=0 minimum yürüyüş, q5=2 üst segment konfor
        $response = $this->postJson('/tatil-karakteri', ['answers' => [
            'q1' => 0, 'q2' => 0, 'q3' => 1, 'q4' => 2, 'q5' => 2, 'q6' => 0, 'q7' => 1, 'q8' => [1],
        ]])->assertOk();

        $titles = array_column($response->json('tours'), 'title');
        $this->assertNotContains('Zorlu Trekking', $titles);
        $this->assertNotEmpty($titles);
        // Brif §6: taban altı turlar "önerilen" diye gösterilmez — bu profilde
        // yalnız gerçekten uyan tur(lar) döner, liste 3'e doldurulmaz.
        $this->assertFalse($response->json('below_floor'));
        foreach ($response->json('tours') as $t) {
            $this->assertGreaterThanOrEqual(60, $t['match_percent']);
        }
    }

    /** 2. Asimetri: 20 isteyene 80'lik tur, 80 isteyene 20'lik turdan belirgin daha çok ceza. */
    public function test_kabul_2_tavan_asimetrisi(): void
    {
        $w = ['fiziksel' => 1.0];

        $azIsteyeneZorluTur = $this->score(['fiziksel' => 5], ['fiziksel' => 20], $w);   // tur 100, fark +80, ×2.5
        $cokIsteyeneHafifTur = $this->score(['fiziksel' => 1], ['fiziksel' => 80], $w);  // tur 0, fark -80, ×0.4

        $this->assertLessThan($cokIsteyeneHafifTur, $azIsteyeneZorluTur);
        $this->assertGreaterThan(30, $cokIsteyeneHafifTur - $azIsteyeneZorluTur, 'Asimetri belirgin olmalı');
    }

    /** 3. "Fark etmez": boyut devre dışıyken o boyuttaki fark skoru değiştirmez. */
    public function test_kabul_3_fark_etmez_boyutu_etkisizlestirir(): void
    {
        $kullanici = ['tempo' => 20];
        $w = ['tempo' => 1.0, 'gastronomi' => 0.0]; // gastronomi fark etmez

        $gastroYogun = $this->score(['tempo' => 1, 'gastronomi' => 5], $kullanici, $w);
        $gastroYok = $this->score(['tempo' => 1, 'gastronomi' => 1], $kullanici, $w);

        $this->assertSame($gastroYogun, $gastroYok);
    }

    /** 4. Normalizasyon: uçta cevaplayanla ortada cevaplayanın EN İYİ skorları benzer bantta. */
    public function test_kabul_4_normalizasyon_bandi(): void
    {
        $this->makeTour('Uçlara Uygun', ['tempo' => 5, 'fiziksel' => 5, 'doga_sehir' => 5, 'adrenalin' => 4, 'konfor' => 1, 'kalabaliklik' => 1, 'yapilandirilmislik' => 4]);
        $this->makeTour('Ortalama Tur', ['tempo' => 3, 'fiziksel' => 3, 'doga_sehir' => 3, 'adrenalin' => 2, 'konfor' => 3, 'kalabaliklik' => 3]);

        $uc = $this->postJson('/tatil-karakteri', ['answers' => [
            'q1' => 2, 'q2' => 2, 'q3' => 2, 'q4' => 3, 'q5' => 0, 'q6' => 2, 'q7' => 0, 'q8' => [3, 5],
        ]])->assertOk()->json('tours');
        $orta = $this->postJson('/tatil-karakteri', ['answers' => $this->midAnswers()])->assertOk()->json('tours');

        $this->assertNotEmpty($uc);
        $this->assertNotEmpty($orta);
        $this->assertLessThan(25, abs($uc[0]['match_percent'] - $orta[0]['match_percent']),
            'Uç ve orta profillerin en iyi skorları kıyaslanabilir bantta olmalı');
    }

    /** 5. Null: kanıtsız boyut ne ceza ne avantaj getirir. */
    public function test_kabul_5_null_boyut_notr(): void
    {
        $kullanici = ['tempo' => 20, 'gastronomi' => 90];
        $w = ['tempo' => 1.0, 'gastronomi' => 1.0];

        $nullGastro = $this->score(['tempo' => 1, 'gastronomi' => null], $kullanici, $w);
        $sadeceTempo = $this->score(['tempo' => 1], $kullanici, ['tempo' => 1.0]);

        // Null boyut devre dışı: skor yalnız kalan aktif boyutlardan hesaplanır
        $this->assertSame($sadeceTempo, $nullGastro);
    }

    /** 6. Determinizm: aynı cevap seti her zaman aynı sıralama. */
    public function test_kabul_6_determinizm(): void
    {
        $this->makeTour('T1', ['tempo' => 2]);
        $this->makeTour('T2', ['tempo' => 3]);
        $this->makeTour('T3', ['tempo' => 4]);

        $a = $this->postJson('/tatil-karakteri', ['answers' => $this->midAnswers()])->json('tours');
        $b = $this->postJson('/tatil-karakteri', ['answers' => $this->midAnswers()])->json('tours');

        $this->assertSame(array_column($a, 'id'), array_column($b, 'id'));
    }

    // ---- ek davranışlar ----

    public function test_definition_endpoint_serves_quiz_v1(): void
    {
        $this->getJson('/tatil-karakteri')
            ->assertOk()
            ->assertJsonPath('quiz_version', 'v1')
            ->assertJsonCount(7, 'tercih_sorulari');
    }

    public function test_agirlik_turetme_kurallari(): void
    {
        $evaluator = app(QuizEvaluator::class);

        // q2=2 (fiziksel 85, uçluk 0.7) + q8'de fiziksel yok
        $profil = $evaluator->evaluate(['q2' => 2, 'q8' => []]);
        $this->assertSame(85.0, $profil['degerler']['fiziksel']);
        $this->assertGreaterThan(0, $profil['agirliklar']['fiziksel']);
        // Cevaplanmayan boyutlar ağırlıksız
        $this->assertSame(0.0, $profil['agirliklar']['gastronomi']);

        // "Fark etmez" (q2=3): fiziksel tamamen devre dışı
        $farkEtmez = $evaluator->evaluate(['q2' => 3, 'q8' => []]);
        $this->assertSame(0.0, $farkEtmez['agirliklar']['fiziksel']);
        $this->assertArrayNotHasKey('fiziksel', $farkEtmez['degerler']);

        // Normalizasyon: aktif ağırlıkların toplamı = aktif boyut sayısı
        $tam = $evaluator->evaluate(['q1' => 0, 'q2' => 0, 'q3' => 0, 'q4' => 0, 'q5' => 0, 'q6' => 0, 'q7' => 0, 'q8' => [0]]);
        $aktif = array_filter($tam['agirliklar'], fn ($w) => $w > 0);
        $this->assertEqualsWithDelta(count($aktif), array_sum($aktif), 0.01);
    }

    public function test_butce_filtresi_gevsetilirse_aciklanir(): void
    {
        $this->makeTour('Pahalı 1', [], ['price' => 50000]);
        $this->makeTour('Pahalı 2', [], ['price' => 52000]);
        $this->makeTour('Sınırda', [], ['price' => 45000]);

        $response = $this->postJson('/tatil-karakteri', [
            'answers' => $this->midAnswers(),
            'baglam' => ['butce_max_try' => 44000],
        ])->assertOk();

        $this->assertNotEmpty($response->json('relaxation_notes'));
        $this->assertStringContainsString('Bütçe', $response->json('relaxation_notes.0'));
    }

    public function test_puanlanmamis_tur_onerilmez(): void
    {
        // Rubrik puanı olmayan tur (makeTour kullanmadan)
        $agency = Agency::create(['name' => 'B', 'slug' => 'b-'.uniqid(), 'email' => uniqid().'@x.com', 'is_active' => true, 'legacy_category_access' => true]);
        Tour::create([
            'agency_id' => $agency->id, 'title' => 'Puansız', 'destination' => 'X', 'description' => 'd',
            'price' => 1000, 'currency' => 'TRY', 'duration_days' => 3,
            'departure_date' => today()->addDays(5), 'return_date' => today()->addDays(8), 'is_active' => true,
        ]);

        $tours = $this->postJson('/tatil-karakteri', ['answers' => $this->midAnswers()])->json('tours');
        $this->assertNotContains('Puansız', array_column($tours, 'title'));
    }

    public function test_gerekce_cumlesi_sablondan_uretilir(): void
    {
        $this->makeTour('Gerekçeli', ['tempo' => 1, 'konfor' => 2]);

        $tours = $this->postJson('/tatil-karakteri', ['answers' => [
            'q1' => 0, 'q2' => 1, 'q3' => 1, 'q4' => 2, 'q5' => 2, 'q6' => 0, 'q7' => 1, 'q8' => [0],
        ]])->json('tours');

        $this->assertNotEmpty($tours);
        $this->assertNotSame('', $tours[0]['reason']);
        $this->assertStringContainsString('örtüşüyor', $tours[0]['reason']);
    }

    public function test_sonuc_kalici_profile_yazilmaz(): void
    {
        $this->makeTour('T', []);
        $this->postJson('/tatil-karakteri', ['answers' => $this->midAnswers()])->assertOk();

        // Kapsam kısıtı: users tablosunda kalıcı profil kolonu yok
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('users', 'recreation_profile'));
    }

    public function test_bilinmeyen_cevap_anahtari_reddedilir(): void
    {
        $answers = $this->midAnswers();
        $answers['hack'] = str_repeat('x', 500);

        $this->postJson('/tatil-karakteri', ['answers' => $answers])->assertStatus(422);
    }

    // ---- inceleme bulgusu regresyonları ----

    /** %60 tabanı altındaki turlar "önerilen" diye gösterilmez; hepsi altındaysa
     *  en yakınlar + below_floor işaretiyle döner (brif §6). */
    public function test_taban_alti_turlar_onerilenlere_karismaz(): void
    {
        // Uç profil: her boyutta 100 isteyen kullanıcıya karşı taban altı tur
        $this->makeTour('Uyumsuz', ['tempo' => 1, 'fiziksel' => 1, 'kultur' => 1, 'doga_sehir' => 1,
            'adrenalin' => 1, 'gastronomi' => 1, 'sosyallik' => 1, 'konfor' => 1,
            'yapilandirilmislik' => 1, 'kalabaliklik' => 1]);

        $response = $this->postJson('/tatil-karakteri', ['answers' => [
            'q1' => 0, 'q2' => 0, 'q3' => 0, 'q4' => 0, 'q5' => 0, 'q6' => 0, 'q7' => 0, 'q8' => [],
        ]])->assertOk();

        $tours = $response->json('tours');
        if ($response->json('below_floor')) {
            // Taban altıysa mesaj dürüst olmalı
            $this->assertStringContainsString('tam uyan', $response->json('message'));
        }
        foreach ($tours as $t) {
            // below_floor false ise gösterilen her tur tabanı geçmiş olmalı
            if (! $response->json('below_floor')) {
                $this->assertGreaterThanOrEqual(60, $t['match_percent']);
            }
        }
    }

    /** needs_review işaretli puanlar canlı eşleştirmede kullanılmaz (brif §3.5). */
    public function test_needs_review_puanlar_canlida_kullanilmaz(): void
    {
        $tour = $this->makeTour('İncelemede', []);
        TourRubricScore::where('tour_id', $tour->id)
            ->update(['review_status' => TourRubricScore::STATUS_NEEDS_REVIEW]);

        $tours = $this->postJson('/tatil-karakteri', ['answers' => $this->midAnswers()])->json('tours');

        $this->assertNotContains('İncelemede', array_column($tours, 'title'));
    }

    /** Ay filtresi SQLite'ta da çalışmalı (MONTH() yerine whereMonth). */
    public function test_ay_filtresi_calisir(): void
    {
        $hedefAy = (int) today()->addMonths(2)->format('n');
        $this->makeTour('Hedef Ay', [], ['departure_date' => today()->addMonths(2), 'return_date' => today()->addMonths(2)->addDays(4)]);
        $this->makeTour('Baska Ay', [], ['departure_date' => today()->addMonths(5), 'return_date' => today()->addMonths(5)->addDays(4)]);

        $response = $this->postJson('/tatil-karakteri', [
            'answers' => $this->midAnswers(),
            'baglam' => ['aylar' => [$hedefAy]],
        ])->assertOk();

        $basliklar = array_column($response->json('tours'), 'title');
        $this->assertContains('Hedef Ay', $basliklar);
        $this->assertNotContains('Baska Ay', $basliklar);
    }

    /** Türkçe 'İ': "İstanbul" araması kalkış noktasıyla eşleşmeli. */
    public function test_kalkis_sehri_turkce_i_harfiyle_eslesir(): void
    {
        $this->makeTour('İstanbul Kalkışlı', [], ['departure_points' => "21:00 İstanbul Yenibosna\n22:00 Ankara"]);
        $this->makeTour('İzmir Kalkışlı', [], ['departure_points' => '20:00 İzmir Bornova']);

        $tours = $this->postJson('/tatil-karakteri', [
            'answers' => $this->midAnswers(),
            'baglam' => ['kalkis_sehri' => 'İstanbul'],
        ])->json('tours');

        $basliklar = array_column($tours, 'title');
        $this->assertContains('İstanbul Kalkışlı', $basliklar);
        $this->assertNotContains('İzmir Kalkışlı', $basliklar);
    }

    /** Sayısal string cevaplar sessizce yutulmamalı (integer kuralı "3" geçirir). */
    public function test_sayisal_string_cevaplar_da_degerlendirilir(): void
    {
        $this->makeTour('Sakin', ['tempo' => 1]);

        $intCevap = ['q1' => 0, 'q2' => 0, 'q3' => 0, 'q4' => 0, 'q5' => 0, 'q6' => 0, 'q7' => 0, 'q8' => []];
        $stringCevap = array_map(fn ($v) => is_array($v) ? $v : (string) $v, $intCevap);

        $a = app(QuizEvaluator::class)->evaluate($intCevap);
        $b = app(QuizEvaluator::class)->evaluate($stringCevap);

        $this->assertNotEmpty($a['degerler']);
        $this->assertSame($a['degerler'], $b['degerler']);
    }

    /** Kanıtsız (evidence boş) LLM puanı saklanmaz — boyut null'a düşer. */
    public function test_kanitsiz_puan_null_a_duser(): void
    {
        $method = new \ReflectionMethod(\App\Jobs\ScoreTourRubricJob::class, 'scoreOnce');
        $job = new \App\Jobs\ScoreTourRubricJob(1);

        $payload = ['scores' => []];
        foreach (Rubric::dimensions() as $d) {
            $payload['scores'][$d] = ['value' => 4, 'confidence' => 'high', 'evidence' => ''];
        }
        OpenAI::fake([
            \OpenAI\Responses\Chat\CreateResponse::fake([
                'choices' => [['message' => ['content' => json_encode($payload)]]],
            ]),
        ]);

        $sonuc = $method->invoke($job, 'Toplam süre: 5 gün');

        foreach (Rubric::dimensions() as $d) {
            $this->assertNull($sonuc[$d]['value'], $d.' kanıtsız olduğu için null olmalı');
        }
    }
}
