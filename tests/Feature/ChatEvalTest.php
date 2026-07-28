<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Chat\Eval\EvalJudge;
use App\Services\Chat\Eval\ScenarioRunner;
use App\Services\Matching\Rubric;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Eval koşucusu — sahte LLM yanıtlarıyla test edilir; gerçek eval koşumu
 * (app:chat-eval) canlı model gerektirir.
 */
class ChatEvalTest extends TestCase
{
    use RefreshDatabase;

    private function textResponse(string $text): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $text]]],
        ]);
    }

    private function toolCallResponse(string $tool, array $args): CreateResponse
    {
        return CreateResponse::fake([
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant', 'content' => null,
                    'tool_calls' => [[
                        'id' => 'c1', 'type' => 'function',
                        'function' => ['name' => $tool, 'arguments' => json_encode($args)],
                    ]],
                ],
            ]],
        ]);
    }

    private function makeTour(string $title): Tour
    {
        $agency = Agency::create([
            'name' => 'A '.uniqid(), 'slug' => 'a-'.uniqid(), 'email' => uniqid().'@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);
        $tour = Tour::create([
            'agency_id' => $agency->id, 'title' => $title, 'destination' => 'Testşehir',
            'description' => 'd', 'price' => 25000, 'currency' => 'TRY', 'duration_days' => 5,
            'departure_date' => today()->addDays(20), 'return_date' => today()->addDays(25),
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

    public function test_senaryo_dosyasi_gecerli_ve_dolu(): void
    {
        $yol = resource_path('eval/chatbot-v2-senaryolar.json');
        $this->assertTrue(File::exists($yol));

        $veri = json_decode(File::get($yol), true, flags: JSON_THROW_ON_ERROR);
        $this->assertGreaterThanOrEqual(20, count($veri['senaryolar']));

        foreach ($veri['senaryolar'] as $s) {
            $this->assertNotEmpty($s['ad']);
            $this->assertNotEmpty($s['kullanici_mesajlari']);
            // Somut başarısızlık işareti olmayan senaryo ölçülemez
            $this->assertNotEmpty($s['basarisizlik_isareti'], $s['ad']);
        }
    }

    public function test_kosucu_transkript_ve_arac_izini_toplar(): void
    {
        $this->makeTour('Sakin Koy Turu');

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', [
                'boyutlar' => ['tempo' => ['deger' => 20, 'kanit' => 'kafamı dinlemek istiyorum']],
            ]),
            $this->textResponse('Sakin bir kaçamak buldum.'),
        ]);

        $sonuc = app(ScenarioRunner::class)->run([
            'ad' => 'test', 'kullanici_mesajlari' => ['kafamı dinlemek istiyorum'],
        ]);

        $this->assertStringContainsString('KULLANICI:', $sonuc['transkript']);
        // Assert'ler araç çağrı dizisi üzerinde yapılabilsin diye iz transkripte girer
        $this->assertStringContainsString('[ARAÇLAR] tur_ara', $sonuc['transkript']);
        $this->assertStringContainsString('Sakin Koy Turu', $sonuc['transkript']);
        $this->assertSame([], $sonuc['ihlaller']);
    }

    public function test_uydurma_sayi_denemesi_ihlal_olarak_yakalanir(): void
    {
        $this->makeTour('Tur');

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', ['boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']]]),
            $this->textResponse('Bu tur 999.999 TL.'),
        ]);

        $sonuc = app(ScenarioRunner::class)->run([
            'ad' => 'test', 'kullanici_mesajlari' => ['normal bir tempo olsun'],
        ]);

        $this->assertNotEmpty($sonuc['ihlaller']);
        $this->assertStringContainsString('Uydurma sayı', $sonuc['ihlaller'][0]);
    }

    public function test_kanitsiz_boyut_denemesi_ihlal_olarak_yakalanir(): void
    {
        $this->makeTour('Tur');

        OpenAI::fake([
            $this->toolCallResponse('tur_ara', ['boyutlar' => [
                'tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun'],
                'konfor' => ['deger' => 95, 'kanit' => 'çok lüks olsun istiyorum'], // demedi
            ]]),
            $this->textResponse('Baktım.'),
        ]);

        $sonuc = app(ScenarioRunner::class)->run([
            'ad' => 'test', 'kullanici_mesajlari' => ['normal bir tempo olsun'],
        ]);

        $this->assertStringContainsString('Kanıtsız boyut', implode(' ', $sonuc['ihlaller']));
    }

    public function test_katalogda_olmayan_tur_adi_yakalanir(): void
    {
        $this->makeTour('Gerçek Tur');

        OpenAI::fake([$this->textResponse('Sana "Hayali Kapadokya Turu" öneriyorum.')]);

        $sonuc = app(ScenarioRunner::class)->run([
            'ad' => 'test', 'kullanici_mesajlari' => ['öneri ver'],
        ]);

        $this->assertStringContainsString('Katalogda olmayan tur adı', implode(' ', $sonuc['ihlaller']));
    }

    public function test_hakem_deterministik_ihlalde_llm_cagirmaz(): void
    {
        OpenAI::fake([]); // boş: LLM çağrısı olursa patlar

        $karar = app(EvalJudge::class)->judge(
            ['ad' => 't', 'beklenen_davranis' => 'x', 'basarisizlik_isareti' => 'y'],
            'transkript',
            ['Uydurma sayı denemesi']
        );

        $this->assertFalse($karar['gecti']);
        $this->assertStringContainsString('Deterministik ihlal', $karar['gerekce']);
    }

    public function test_hakem_llm_kararini_dondurur(): void
    {
        OpenAI::fake([
            CreateResponse::fake(['choices' => [['index' => 0, 'message' => [
                'role' => 'assistant',
                'content' => json_encode(['gecti' => true, 'gerekce' => 'Teşhis kurdu ve dürüst köprü attı.']),
            ]]]]),
        ]);

        $karar = app(EvalJudge::class)->judge(
            ['ad' => 't', 'beklenen_davranis' => 'teşhis kursun', 'basarisizlik_isareti' => 'kurmazsa'],
            'BOT: tarif ettiğin şey villa tatili...',
            []
        );

        $this->assertTrue($karar['gecti']);
        $this->assertStringContainsString('köprü', $karar['gerekce']);
    }

    public function test_hakem_hatasi_kalmis_sayilir(): void
    {
        OpenAI::fake([]); // yanıt yok → istisna

        $karar = app(EvalJudge::class)->judge(['ad' => 't'], 'x', []);

        $this->assertFalse($karar['gecti']);
    }
}
