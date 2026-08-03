<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Chat\ChatAgent;
use App\Services\Matching\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Kısa cevap döngüsü regresyonu.
 *
 * Canlıda görülen hata: kullanıcı tek kelimelik ("deniz-güneş", "yurt içi")
 * cevaplar verince hiçbir boyut kabul edilmiyor, tur_ara boş dönüyor ve bot
 * aynı soruyu tekrar tekrar soruyordu. İki savunma test edilir:
 *   1) tek kelimelik alıntı boyut üretebilir,
 *   2) ikinci turda da boyut çıkmazsa soru yerine kısıtlara uyan liste döner.
 */
class ChatKisaCevapTest extends TestCase
{
    use RefreshDatabase;

    private function toolCallResponse(string $tool, array $args, ?string $content = null): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => $content,
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

    public function test_tek_kelimelik_alinti_boyut_uretir(): void
    {
        $this->makeTour('Ege Sahil Turu');

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', [
                'boyutlar' => ['tempo' => ['deger' => 30, 'kanit' => 'deniz-güneş']],
            ]),
            $this->textResponse('Sahil turlarını getirdim.'),
        ]);

        $sonuc = app(ChatAgent::class)->handle('deniz-güneş');

        $adim = $sonuc['iz'][0];
        $this->assertNull($adim['hata'], 'Tek kelimelik alıntı boyutu düşürmemeli');
        $this->assertSame(1, $adim['tur_sayisi']);
        $this->assertSame(30.0, $sonuc['durum']->degerler['tempo']);
    }

    public function test_transkriptte_gecmeyen_alinti_hala_dusurulur(): void
    {
        $this->makeTour('Ege Sahil Turu');

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', [
                'boyutlar' => ['konfor' => ['deger' => 95, 'kanit' => 'ultra lüks']],  // demedi
            ]),
            $this->textResponse('Ne aradığını biraz daha anlatır mısın?'),
        ]);

        $sonuc = app(ChatAgent::class)->handle('deniz-güneş');

        $this->assertNotNull($sonuc['iz'][0]['hata'], 'Uydurma alıntı kabul edilmemeli');
        $this->assertContains(Rubric::label('konfor'), $sonuc['iz'][0]['olculemeyen_boyutlar']);
    }

    public function test_ikinci_turda_boyut_cikmazsa_soru_yerine_liste_doner(): void
    {
        $this->makeTour('Yakın Kalkışlı Tur', ['departure_date' => today()->addDays(5)]);
        $this->makeTour('Uzak Kalkışlı Tur', ['departure_date' => today()->addDays(90)]);

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', ['boyutlar' => []]),
            $this->textResponse('Elimdekilerden öne çıkanlar bunlar.'),
        ]);

        // Geçmişte zaten bir kullanıcı mesajı var → bu ikinci deneme
        $sonuc = app(ChatAgent::class)->handle('farketmez', [
            ['role' => 'user', 'content' => 'deniz-güneş'],
            ['role' => 'assistant', 'content' => 'Nasıl bir tatil istersin?'],
        ]);

        $adim = $sonuc['iz'][0];
        $this->assertNull($adim['hata'], 'İkinci turda hata yerine liste dönmeli');
        $this->assertSame(2, $adim['tur_sayisi']);
        $this->assertNotEmpty($sonuc['turlar'], 'Kullanıcıya kart gösterilmeli');
        // Uyum ölçülmediği için rozet basılmaz
        $this->assertNull($sonuc['turlar'][0]['compatibility_score']);
        // Kalkışı en yakın olan başta
        $this->assertSame('Yakın Kalkışlı Tur', $sonuc['turlar'][0]['title']);
    }

    public function test_ilk_turda_boyut_cikmazsa_hala_soru_sorulur(): void
    {
        $this->makeTour('Ege Sahil Turu');

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', ['boyutlar' => []]),
            $this->textResponse('Nasıl bir tatil istersin?'),
        ]);

        $sonuc = app(ChatAgent::class)->handle('selam');

        $this->assertNotNull($sonuc['iz'][0]['hata'], 'İlk turda sormak hâlâ doğru');
        $this->assertSame([], $sonuc['turlar']);
    }
}
