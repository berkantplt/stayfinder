<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\DestinationProfile;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Chat\LlmProfileBuilder;
use App\Services\Chat\Tools\EnvanterOzeti;
use App\Services\Chat\Tools\SehirBilgisi;
use App\Services\Chat\Tools\TurAra;
use App\Services\Chat\Tools\TurDetay;
use App\Services\Matching\Rubric;
use App\Support\OpenAiChatParams;
use Illuminate\Foundation\Testing\RefreshDatabase;
use OpenAI\Laravel\Facades\OpenAI;
use Tests\TestCase;

/**
 * Chatbot v2 araçları — hepsi DETERMİNİSTİK ve LLM'siz olmalı.
 * Boş OpenAI::fake herhangi bir kaçak LLM çağrısını anında patlatır.
 */
class ChatToolsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        OpenAI::fake([]);
    }

    private function makeTour(string $title, array $scores1to5 = [], array $attrs = []): Tour
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
            'input_hash' => 'h'.uniqid(), 'scores' => $payload,
            'review_status' => TourRubricScore::STATUS_AUTO, 'scored_at' => now(),
        ]);

        return $tour;
    }

    // ---- OpenAiChatParams::tools() ----

    public function test_tools_parametreleri_response_format_gondermez(): void
    {
        $params = OpenAiChatParams::tools('gpt-5.4', [['role' => 'user', 'content' => 'x']], [TurAra::schema()], 800);

        // json_object ile tools birlikte gidince araç akışı bozulur
        $this->assertArrayNotHasKey('response_format', $params);
        $this->assertSame('auto', $params['tool_choice']);
        $this->assertTrue($params['parallel_tool_calls']);
        // reasoning ailesi: max_tokens/temperature yok
        $this->assertArrayNotHasKey('max_tokens', $params);
        $this->assertSame(4800, $params['max_completion_tokens']);
        $this->assertSame('low', $params['reasoning_effort']);
    }

    public function test_tools_bos_ise_tool_alanlari_dusurulur(): void
    {
        $params = OpenAiChatParams::tools('gpt-4o-mini', [['role' => 'user', 'content' => 'x']], [], 500);

        $this->assertArrayNotHasKey('tools', $params);
        $this->assertArrayNotHasKey('tool_choice', $params);
        $this->assertSame(500, $params['max_tokens']); // legacy aile
    }

    // ---- LlmProfileBuilder: kanıt disiplini ----

    public function test_kanitsiz_boyut_dusurulur(): void
    {
        $sonuc = app(LlmProfileBuilder::class)->build([
            'tempo' => ['deger' => 20, 'kanit' => 'kafamı dinlemek istiyorum'],
            'gastronomi' => ['deger' => 50, 'kanit' => ''],   // kanıt yok
        ], [], 'sessiz bir yer istiyorum, kafamı dinlemek istiyorum');

        $this->assertArrayHasKey('tempo', $sonuc['degerler']);
        $this->assertArrayNotHasKey('gastronomi', $sonuc['degerler']);
        $this->assertContains('gastronomi', $sonuc['dusurulen']);
        $this->assertSame(0.0, $sonuc['agirliklar']['gastronomi']);
    }

    public function test_transkriptte_gecmeyen_kanit_dusurulur(): void
    {
        $sonuc = app(LlmProfileBuilder::class)->build([
            'tempo' => ['deger' => 20, 'kanit' => 'kafamı dinlemek istiyorum'],
            'konfor' => ['deger' => 90, 'kanit' => 'lüks bir otel olsun'], // kullanıcı bunu DEMEDİ
        ], [], 'sessiz bir yer istiyorum, kafamı dinlemek istiyorum');

        $this->assertArrayHasKey('tempo', $sonuc['degerler']);
        $this->assertArrayNotHasKey('konfor', $sonuc['degerler']);
        $this->assertContains('konfor', $sonuc['dusurulen']);
    }

    public function test_onemli_boyut_agirligi_artirir(): void
    {
        $transkript = 'çok sakin olsun';
        $normal = app(LlmProfileBuilder::class)->build(['tempo' => ['deger' => 10, 'kanit' => 'çok sakin olsun']], [], $transkript);
        $vurgulu = app(LlmProfileBuilder::class)->build(['tempo' => ['deger' => 10, 'kanit' => 'çok sakin olsun']], ['tempo'], $transkript);

        $this->assertGreaterThan($normal['agirliklar']['tempo'], $vurgulu['agirliklar']['tempo']);
        // Üst sınır aşılmamalı
        $this->assertLessThanOrEqual(Rubric::weightBounds()['max'], $vurgulu['agirliklar']['tempo']);
    }

    // ---- TurAra ----

    public function test_tur_ara_bos_boyutla_hata_dondurur(): void
    {
        $sonuc = app(TurAra::class)->run(['boyutlar' => []]);

        $this->assertSame([], $sonuc['turlar']);
        $this->assertArrayHasKey('hata', $sonuc);
    }

    public function test_tur_ara_karsilanmayan_istegi_bildirir(): void
    {
        // Katalogda yalnız çok sosyal/kalabalık bir tur var
        $this->makeTour('Kalabalık Grup Turu', ['sosyallik' => 5, 'tempo' => 4]);

        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['sosyallik' => ['deger' => 5, 'kanit' => 'kimse beni rahatsız etmesin']],
        ]);

        // Sosyallik isteği karşılanamadı → köprü cümlesinin veri kaynağı
        $this->assertContains(Rubric::label('sosyallik'), $sonuc['karsilanmayan']);
        $this->assertArrayHasKey('kapsam', $sonuc);
    }

    public function test_tur_ara_fiyat_ayrismasinda_butce_sorar(): void
    {
        $this->makeTour('Ucuz', [], ['price' => 8000]);
        $this->makeTour('Pahalı', [], ['price' => 60000]);
        $this->makeTour('Orta', [], ['price' => 20000]);

        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo']],
        ]);

        $this->assertSame('butce', $sonuc['sor']);
    }

    public function test_tur_ara_destinasyon_filtresi_turkce_i_ile_calisir(): void
    {
        $this->makeTour('İzmir Turu', [], ['destination' => 'İzmir']);
        $this->makeTour('Ankara Turu', [], ['destination' => 'Ankara']);

        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            'filtre' => ['destinasyon' => 'İzmir'],
        ]);

        $basliklar = array_column($sonuc['turlar'], 'title');
        $this->assertContains('İzmir Turu', $basliklar);
        $this->assertNotContains('Ankara Turu', $basliklar);
    }

    // ---- SehirBilgisi ----

    public function test_sehir_bilgisi_zenginlesmemis_profilde_veri_var_false(): void
    {
        $sonuc = app(SehirBilgisi::class)->run(['sehir' => 'Bilinmeyenşehir']);

        // Servis 0.50/0.50 döndürüyor; bayrak olmazsa model "orta yoğunlukta" diye uydurur
        $this->assertFalse($sonuc['veri_var']);
        $this->assertArrayNotHasKey('kalabaliklik', $sonuc);
    }

    public function test_sehir_bilgisi_zenginlesmis_profili_dondurur(): void
    {
        DestinationProfile::create([
            'city' => 'Kapadokya', 'normalized_city' => DestinationProfile::normalize('Kapadokya'),
            'crowd_score' => 0.8, 'liveliness_score' => 0.3, 'source' => DestinationProfile::SOURCE_LLM,
            'best_months' => [4, 5, 9, 10], 'summary' => 'Peribacaları',
        ]);

        $sonuc = app(SehirBilgisi::class)->run(['sehir' => 'Kapadokya']);

        $this->assertTrue($sonuc['veri_var']);
        $this->assertSame(0.8, $sonuc['kalabaliklik']);
        $this->assertSame([4, 5, 9, 10], $sonuc['en_iyi_aylar']);
    }

    // ---- EnvanterOzeti ----

    public function test_envanter_satmadigimiz_urun_tiplerini_bildirir(): void
    {
        $this->makeTour('Bir Tur', [], ['destination' => 'Antalya']);

        $sonuc = app(EnvanterOzeti::class)->run([]);

        // Villa yokluğunu model tahminle değil VERİDEN söylesin
        $this->assertNotEmpty($sonuc['satmadigimiz_urun_tipleri']);
        $this->assertStringContainsString('villa', mb_strtolower(implode(' ', $sonuc['satmadigimiz_urun_tipleri'])));
        $this->assertSame('Antalya', $sonuc['destinasyonlar'][0]['sehir']);
    }

    // ---- TurDetay ----

    public function test_tur_detay_veri_dondurur_ve_vize_notu_ekler(): void
    {
        DestinationProfile::create([
            'city' => 'Roma', 'normalized_city' => DestinationProfile::normalize('Roma'),
            'crowd_score' => 0.9, 'liveliness_score' => 0.7, 'source' => DestinationProfile::SOURCE_LLM,
            'requires_visa_for_tr' => true,
        ]);
        $tour = $this->makeTour('Roma Turu', [], [
            'destination' => 'Roma',
            'included' => 'Uçak bileti, otel',
            'cancellation_policy' => '15 gün öncesine kadar ücretsiz',
            'itinerary' => [['title' => 'Varış', 'content' => 'Havalimanı karşılama']],
        ]);

        $sonuc = app(TurDetay::class)->run(['tur_id' => $tour->id]);

        $this->assertSame('Roma Turu', $sonuc['baslik']);
        $this->assertTrue($sonuc['vize_gerekir_mi']);
        $this->assertNotNull($sonuc['vize_notu']); // prosedür anlatma, acentaya yönlendir
        $this->assertStringContainsString('otel', $sonuc['fiyata_dahil']);
        $this->assertSame(1, $sonuc['gun_gun_program'][0]['gun']);
    }

    public function test_tur_detay_pasif_turda_bulunamadi_dondurur(): void
    {
        $tour = $this->makeTour('Pasif Tur');
        Tour::whereKey($tour->id)->update(['is_active' => false]);

        $sonuc = app(TurDetay::class)->run(['tur_id' => $tour->id]);

        $this->assertTrue($sonuc['bulunamadi']);
    }

    // ---- Şema disiplini ----

    public function test_tum_araclarin_semasi_gecerli(): void
    {
        foreach ([TurAra::class, TurDetay::class, SehirBilgisi::class, EnvanterOzeti::class] as $tool) {
            $schema = $tool::schema();

            $this->assertSame('function', $schema['type'], $tool);
            $this->assertSame($tool::name(), $schema['function']['name'], $tool);
            $this->assertNotEmpty($schema['function']['description'], $tool);
            $this->assertSame('object', $schema['function']['parameters']['type'], $tool);
            // OpenAI şeması JSON'a serileşebilmeli
            $this->assertIsString(json_encode($schema, JSON_THROW_ON_ERROR), $tool);
        }
    }
}
