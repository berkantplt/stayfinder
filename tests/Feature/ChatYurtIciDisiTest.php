<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Chat\ChatAgent;
use App\Services\Chat\ConversationState;
use App\Services\Chat\OriginIntentDetector;
use App\Services\Matching\Rubric;
use App\Support\VisaStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * "Yurt içi olsun" hattı.
 *
 * Canlı şikayet: ilk mesajda yön belirtilmedi, model yurt dışı turlar önerdi;
 * kullanıcı "Yurt içi olsun" deyince metin yurt içi turları anlattı ama
 * kartlar aynı yurt dışı turlarda kaldı. yurt_disi filtresini yazmak tamamen
 * modele bırakılmıştı — kıyas yerinde olduğu gibi burada da sunucu tarafında
 * deterministik emniyet var.
 */
class ChatYurtIciDisiTest extends TestCase
{
    use RefreshDatabase;

    private function detect(string $mesaj): ?bool
    {
        return app(OriginIntentDetector::class)->detect($mesaj);
    }

    private function toolCallResponse(string $tool, array $args): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [[
                        'id' => 'call_'.uniqid(),
                        'type' => 'function',
                        'function' => ['name' => $tool, 'arguments' => json_encode($args)],
                    ]],
                ],
            ]],
        ]);
    }

    private function textResponse(string $text): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text]]],
        ]);
    }

    private function makeTour(string $title, array $attrs = []): Tour
    {
        $agency = Agency::create([
            'name' => 'A '.uniqid(), 'slug' => 'a-'.uniqid(), 'email' => uniqid().'@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);
        $tour = Tour::create(array_merge([
            'agency_id' => $agency->id, 'title' => $title, 'destination' => 'Testşehir',
            'description' => 'd', 'price' => 25000, 'currency' => 'TRY', 'duration_days' => 5,
            'departure_date' => today()->addDays(20), 'return_date' => today()->addDays(25),
            'is_active' => true,
        ], $attrs));

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

    /** İki tur: biri yurt içi, biri yurt dışı — filtre hangisini bıraktı görülsün. */
    private function ikiYonluKatalog(): void
    {
        $this->makeTour('Yurt İçi Tur', ['is_international' => false]);
        $this->makeTour('Yurt Dışı Tur', ['is_international' => true]);
    }

    /** Model yurt_disi filtresini YAZMADAN arar — canlıdaki senaryo. */
    private function filtresizArama(string $kanit): void
    {
        OpenAI::fake([
            $this->toolCallResponse('tur_ara', ['boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => $kanit]]]),
            $this->textResponse('Baktım.'),
        ]);
    }

    // ---- tespitçi ----

    public function test_yurt_ici_beyani_yakalanir(): void
    {
        foreach ([
            'Yurt içi olsun', 'yurtiçi bir yer olsun', 'yurt içinde kalalım', 'Türkiye içinde olsun',
            "Türkiye'de kalalım", 'Türkiye’de kalalım', 'ülke içi olsun',
        ] as $mesaj) {
            $this->assertFalse($this->detect($mesaj), $mesaj);
        }
    }

    public function test_yurt_disi_beyani_yakalanir(): void
    {
        foreach (['yurt dışı olsun', 'YURTDIŞI düşünüyorum', 'yurt dışına çıkmak istiyorum', 'ülke dışı olsun'] as $mesaj) {
            $this->assertTrue($this->detect($mesaj), $mesaj);
        }
    }

    public function test_olumsuz_beyan_tersine_cevrilir(): void
    {
        $this->assertFalse($this->detect('yurt dışı olmasın'));
        $this->assertFalse($this->detect('yurt dışına çıkmak istemiyorum'));
        $this->assertFalse($this->detect('yurt dışı değil'));
        $this->assertTrue($this->detect('yurt içi olmasın'));
    }

    public function test_beyan_yoksa_ya_da_belirsizse_null(): void
    {
        $this->assertNull($this->detect('Kanka karımla bu aylarda romantik bir tatil yapmak istiyorum ama aynı zamanda eğlenelim'));
        $this->assertNull($this->detect('yurt içi mi yurt dışı mı önerirsin'));
        $this->assertNull($this->detect('yurt içi yurt dışı fark etmez'));
        $this->assertNull($this->detect('bütçe 30 bin olsun'));
        // "yurt dışından gelen misafir" hedef beyanı değil
        $this->assertNull($this->detect('yurt dışından gelen arkadaşlarımla gezelim'));
    }

    public function test_kayitsizlik_yalniz_beyanin_yanindaysa_sayilir(): void
    {
        // "bütçe fark etmez" yurt içi beyanını geçersiz kılmaz
        $this->assertFalse($this->detect('bütçe fark etmez, yurt içi olsun'));
    }

    // ---- ChatAgent: filtre sunucuda yazılır ----

    public function test_yurt_ici_beyani_modelden_bagimsiz_filtreye_yazilir(): void
    {
        $this->ikiYonluKatalog();
        $this->filtresizArama('yurt içi olsun');

        $sonuc = app(ChatAgent::class)->handle('Yurt içi olsun');

        $this->assertSame(['Yurt İçi Tur'], array_column($sonuc['turlar'], 'title'));
        $this->assertFalse($sonuc['iz'][0]['args']['filtre']['yurt_disi']);
        // Sonraki turlara da taşınır
        $this->assertFalse($sonuc['durum']->kisitlar['yurt_disi']);
    }

    public function test_yurt_disi_beyani_true_yazar(): void
    {
        $this->ikiYonluKatalog();
        $this->filtresizArama('yurt dışı olsun');

        $sonuc = app(ChatAgent::class)->handle('yurt dışı olsun');

        $this->assertSame(['Yurt Dışı Tur'], array_column($sonuc['turlar'], 'title'));
        $this->assertTrue($sonuc['durum']->kisitlar['yurt_disi']);
    }

    /** Önceki turda yurt dışı denmişti; "yurt içi olsun" düzeltmesi hafızadaki yönü değiştirir. */
    public function test_beyan_hafizadaki_eski_yonu_ezer(): void
    {
        $this->ikiYonluKatalog();
        $this->filtresizArama('yurt içi olsun');
        $durum = ConversationState::fromArray(['kisitlar' => ['yurt_disi' => true]]);

        $sonuc = app(ChatAgent::class)->handle('yurt içi olsun', [], $durum);

        $this->assertSame(['Yurt İçi Tur'], array_column($sonuc['turlar'], 'title'));
        $this->assertFalse($sonuc['durum']->kisitlar['yurt_disi']);
    }

    /** Eşleştiricide vize yurt dışını ima eder ve yurt_disi=false'u ezer — yurt içi diyen için vize düşmeli. */
    public function test_yurt_ici_beyani_hafizadaki_vize_kisitini_dusurur(): void
    {
        // Yurt içi turda vize beyanı yok (null): vize filtresi kalsaydı hiç çıkmazdı
        $this->makeTour('Yurt İçi Tur', ['is_international' => false, 'requires_visa' => null]);
        $this->makeTour('Vizesiz Yurt Dışı Tur', ['is_international' => true, 'requires_visa' => false, 'visa_on_arrival' => false]);
        $this->filtresizArama('yurt içi olsun');
        $durum = ConversationState::fromArray(['kisitlar' => ['vize' => VisaStatus::VIZESIZ, 'yurt_disi' => true]]);

        $sonuc = app(ChatAgent::class)->handle('yurt içi olsun', [], $durum);

        $this->assertSame(['Yurt İçi Tur'], array_column($sonuc['turlar'], 'title'));
        $this->assertArrayNotHasKey('vize', $sonuc['durum']->kisitlar);
        $this->assertFalse($sonuc['durum']->kisitlar['yurt_disi']);
    }

    public function test_model_vazgecti_dese_de_kullanicinin_acik_sozu_kazanir(): void
    {
        $this->ikiYonluKatalog();
        OpenAI::fake([
            $this->toolCallResponse('tur_ara', [
                'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'yurt içi olsun']],
                'kaldirilan_kisitlar' => ['yurt_disi'],
            ]),
            $this->textResponse('Tamam.'),
        ]);

        $sonuc = app(ChatAgent::class)->handle('yurt içi olsun');

        $this->assertSame(['Yurt İçi Tur'], array_column($sonuc['turlar'], 'title'));
        $this->assertFalse($sonuc['durum']->kisitlar['yurt_disi']);
    }

    public function test_beyan_yoksa_filtreye_dokunulmaz(): void
    {
        $this->ikiYonluKatalog();
        $this->filtresizArama('romantik bir tatil');

        $sonuc = app(ChatAgent::class)->handle('romantik bir tatil');

        $this->assertCount(2, $sonuc['turlar']);
        $this->assertArrayNotHasKey('yurt_disi', $sonuc['durum']->kisitlar);
    }
}
