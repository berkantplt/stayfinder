<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Chat\ChatAgent;
use App\Services\Chat\ConversationState;
use App\Services\Chat\ReferenceDestinationDetector;
use App\Services\Chat\Tools\TurAra;
use App\Services\Matching\Rubric;
use App\Services\Matching\TourMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * "Fethiye'ye gittik, benzerini öner" hattı.
 *
 * Canlı hata: model kıyas için anılan şehri filtre.destinasyon'a yazıyordu;
 * destinasyon SERT filtre olduğu için katalog o şehre kilitleniyor ve kullanıcı
 * zaten bildiği yeri geri görüyordu. Üstüne kısıtlar yalnız birikiyordu, yani
 * yanlış giren şehir konuşmanın sonuna kadar duruyordu.
 */
class ChatReferansYerTest extends TestCase
{
    use RefreshDatabase;

    private const BENZER_MESAJ = 'kanka geçen sene eşimle fethiyeye gittik çok güzeldi '
        .'bize o tarz benzer bir turlar önerir misin yurt içinde';

    private function detector(): ReferenceDestinationDetector
    {
        return app(ReferenceDestinationDetector::class);
    }

    private function makeTour(string $title, string $destination): Tour
    {
        $agency = Agency::create([
            'name' => 'A '.uniqid(), 'slug' => 'a-'.uniqid(), 'email' => uniqid().'@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);
        $tour = Tour::create([
            'agency_id' => $agency->id, 'title' => $title, 'destination' => $destination,
            'description' => 'd', 'price' => 15000, 'currency' => 'TRY', 'duration_days' => 4,
            'departure_date' => today()->addDays(30), 'return_date' => today()->addDays(34),
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

    // ---- ayrım: kıyas yeri mi, gidilecek yer mi ----

    public function test_kiyas_icin_anilan_yer_referansa_tasinir(): void
    {
        $filtre = $this->detector()->apply(
            ['destinasyon' => 'Fethiye', 'yurt_disi' => false],
            self::BENZER_MESAJ,
        );

        $this->assertSame('Fethiye', $filtre['referans_yer']);
        $this->assertArrayNotHasKey('destinasyon', $filtre);
        $this->assertFalse($filtre['yurt_disi']); // diğer kısıtlara dokunulmaz
    }

    public function test_noktalamasiz_uzak_cumlede_de_yakalanir(): void
    {
        $this->assertTrue($this->detector()->isReference('Kapadokya', 'Kapadokya çok güzeldi. Benzerini istiyorum.'));
        $this->assertTrue($this->detector()->isReference('Bodrum', 'bodrum tarzında bir yer olsun'));
        $this->assertTrue($this->detector()->isReference('Trabzon', 'geçen yaz trabzona gitmiştik'));
    }

    public function test_gitmek_istenen_yer_destinasyonda_kalir(): void
    {
        $filtre = $this->detector()->apply(
            ['destinasyon' => 'Fethiye'],
            'fethiye turu düşünüyorum 2 kişi 4 veya 5 gün',
        );

        $this->assertSame('Fethiye', $filtre['destinasyon']);
        $this->assertArrayNotHasKey('referans_yer', $filtre);
    }

    public function test_tekrar_ayni_yere_gitmek_isteyen_kullanicida_tasima_yapilmaz(): void
    {
        // Geçmiş ziyaretten söz ediyor ama gene ORAYA gitmek istiyor
        $filtre = $this->detector()->apply(
            ['destinasyon' => 'Fethiye'],
            'geçen sene fethiyeye gittik çok beğendik, yine fethiye istiyoruz',
        );

        $this->assertSame('Fethiye', $filtre['destinasyon']);
        $this->assertArrayNotHasKey('referans_yer', $filtre);
    }

    public function test_sehir_konusmada_hic_gecmiyorsa_filtreye_dokunulmaz(): void
    {
        $filtre = $this->detector()->apply(['destinasyon' => 'Bodrum'], 'benzer bir tatil öner');

        $this->assertSame('Bodrum', $filtre['destinasyon']);
    }

    public function test_model_ayni_yeri_iki_alana_da_yazarsa_kiyas_kazanir(): void
    {
        $filtre = $this->detector()->apply(
            ['destinasyon' => 'Fethiye', 'referans_yer' => 'Fethiye'],
            'fethiye gibi bir yer olsun',
        );

        $this->assertArrayNotHasKey('destinasyon', $filtre);
        $this->assertSame('Fethiye', $filtre['referans_yer']);
    }

    // ---- eşleştirici: kıyas yeri sonuçlardan çıkar ----

    public function test_kiyas_yerinin_turlari_listeden_cikarilir(): void
    {
        $this->makeTour('Fethiye Ölüdeniz Turu', 'Fethiye, Ölüdeniz');
        $this->makeTour('Kaş Kalkan Turu', 'Kaş, Kalkan');
        $this->makeTour('Datça Bozburun Turu', 'Datça');
        $this->makeTour('Ayvalık Cunda Turu', 'Ayvalık');

        $sonuc = app(TourMatcher::class)->match(
            ['degerler' => ['tempo' => 50], 'agirliklar' => ['tempo' => 1.0]],
            ['referans_yer' => 'Fethiye'],
        );

        $destinasyonlar = array_column($sonuc['tours'], 'destination');
        $this->assertNotEmpty($destinasyonlar);
        foreach ($destinasyonlar as $d) {
            $this->assertStringNotContainsStringIgnoringCase('Fethiye', $d);
        }
        $this->assertSame([], $sonuc['relaxation_notes']);
    }

    public function test_kiyas_disinda_yeterli_tur_yoksa_dislama_gevser_ve_soylenir(): void
    {
        $this->makeTour('Fethiye Ölüdeniz Turu', 'Fethiye, Ölüdeniz');

        $sonuc = app(TourMatcher::class)->match(
            ['degerler' => ['tempo' => 50], 'agirliklar' => ['tempo' => 1.0]],
            ['referans_yer' => 'Fethiye'],
        );

        // Katalogda başka seçenek yokken boş liste dönmek dürüst değil: tur geri
        // gelir ama kullanıcıya NEDEN geldiği söylenir
        $this->assertCount(1, $sonuc['tours']);
        $this->assertStringContainsString('Fethiye dışında', implode(' ', $sonuc['relaxation_notes']));
    }

    // ---- yapışkanlık: vazgeçilen kısıt hafızadan silinir ----

    public function test_kaldirilan_kisit_hafizadan_silinir(): void
    {
        $durum = ConversationState::fromArray(['kisitlar' => ['destinasyon' => 'Fethiye', 'butce_max_try' => 20000]]);

        $durum->absorb(TurAra::name(), [
            'filtre' => [],
            'kaldirilan_kisitlar' => ['destinasyon'],
        ], ['turlar' => []]);

        $this->assertArrayNotHasKey('destinasyon', $durum->varsayilanFiltre());
        $this->assertSame(20000.0, $durum->varsayilanFiltre()['butce_max_try']);
    }

    public function test_bilinmeyen_anahtar_kaldirma_yok_sayilir(): void
    {
        $durum = ConversationState::fromArray(['kisitlar' => ['destinasyon' => 'Fethiye']]);
        $durum->absorb(TurAra::name(), ['kaldirilan_kisitlar' => ['degerler', 'kisitlar']], []);

        $this->assertSame('Fethiye', $durum->varsayilanFiltre()['destinasyon']);
    }

    // ---- uçtan uca: yapışan yanlış destinasyon konuşmayı kilitlemez ----

    public function test_onceki_turdan_yapisan_destinasyon_kiyasa_cevrilir(): void
    {
        $this->makeTour('Kaş Kalkan Turu', 'Kaş, Kalkan');

        OpenAI::fake([
            CreateResponse::fake(['choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant', 'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_1', 'type' => 'function',
                        'function' => ['name' => 'tur_ara', 'arguments' => json_encode([
                            'boyutlar' => ['doga_sehir' => ['deger' => 80, 'kanit' => 'fethiyeye gittik']],
                        ])],
                    ]],
                ],
            ]]]),
            CreateResponse::fake(['choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => 'Benzer koyları getirdim.'],
            ]]]),
        ]);

        // Önceki turda yanlış yazılmış, hafızada duran destinasyon
        $durum = ConversationState::fromArray(['kisitlar' => ['destinasyon' => 'Fethiye']]);

        $sonuc = app(ChatAgent::class)->handle(self::BENZER_MESAJ, [], $durum);

        $filtre = $sonuc['iz'][0]['args']['filtre'];
        $this->assertArrayNotHasKey('destinasyon', $filtre);
        $this->assertSame('Fethiye', $filtre['referans_yer']);

        // Hafızada da kalmamalı: yoksa bir sonraki turda geri gelir
        $kalan = $sonuc['durum']->varsayilanFiltre();
        $this->assertArrayNotHasKey('destinasyon', $kalan);
        $this->assertSame('Fethiye', $kalan['referans_yer']);
    }
}
