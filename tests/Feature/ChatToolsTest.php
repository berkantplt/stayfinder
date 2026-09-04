<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\DestinationProfile;
use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Chat\ChatPrompts;
use App\Services\Chat\ConversationState;
use App\Services\Chat\LlmProfileBuilder;
use App\Services\Chat\Tools\EnvanterOzeti;
use App\Services\Chat\Tools\SehirBilgisi;
use App\Services\Chat\Tools\TurAra;
use App\Services\Chat\Tools\TurDetay;
use App\Services\Matching\Rubric;
use App\Support\OpenAiChatParams;
use App\Support\VisaStatus;
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
        // API kısıtı: araç varken reasoning_effort 'none' olmak ZORUNDA, yoksa
        // "Function tools with reasoning_effort are not supported" hatası gelir
        $this->assertSame('none', $params['reasoning_effort']);
    }

    public function test_araclar_kapaliyken_reasoning_effort_korunur(): void
    {
        $params = OpenAiChatParams::tools('gpt-5.4', [['role' => 'user', 'content' => 'x']], [], 500, 'auto', 'medium');

        // Son cevap turunda araç yok → düşünme seviyesi kullanılabilir
        $this->assertSame('medium', $params['reasoning_effort']);
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

    /** Katalogda hiç puanlı tur yoksa bu SİSTEM durumu — "sana uyan tur yok"
     *  ile karıştırılmamalı, yoksa bot kullanıcıya yanlış bilgi verir. */
    public function test_puanli_tur_yoksa_katalog_hazir_degil_sinyali(): void
    {
        // Tur var ama rubrik puanı YOK
        $agency = Agency::create([
            'name' => 'A', 'slug' => 'a-'.uniqid(), 'email' => uniqid().'@x.com',
            'is_active' => true, 'legacy_category_access' => true,
        ]);
        Tour::create([
            'agency_id' => $agency->id, 'title' => 'Puansız Tur', 'destination' => 'Antalya',
            'description' => 'd', 'price' => 10000, 'currency' => 'TRY', 'duration_days' => 5,
            'departure_date' => today()->addDays(10), 'return_date' => today()->addDays(15),
            'is_active' => true,
        ]);

        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 20, 'kanit' => 'sakin bir tatil istiyorum']],
        ]);

        $this->assertTrue($sonuc['katalog_hazir_degil']);
        $this->assertStringContainsString('DEME', $sonuc['hata']); // modele açık talimat
    }

    /** Tek bir doğrulanamayan alıntı tüm turu incelemeye düşürmemeli —
     *  bu kural 36 turluk katalogun tamamını bloke etmişti. */
    public function test_tek_dogrulanmayan_alinti_turu_bloklamaz(): void
    {
        $tour = $this->makeTour('Tur');
        $scores = TourRubricScore::where('tour_id', $tour->id)->firstOrFail();

        // 10 boyuttan yalnız 1'i doğrulanamamış → sistematik değil
        $payload = $scores->scores;
        $boyutlar = Rubric::dimensions();
        foreach ($boyutlar as $i => $d) {
            $payload[$d]['evidence_verified'] = $i === 0 ? false : true;
        }
        $scores->update(['scores' => $payload, 'review_status' => TourRubricScore::STATUS_NEEDS_REVIEW]);

        $this->artisan('app:rubric-recheck')->assertSuccessful();

        $this->assertSame(TourRubricScore::STATUS_AUTO, $scores->fresh()->review_status);
    }

    /** İki-geçiş uyuşmazlığından işaretlenen eski kayıtlar da yayına alınmalı:
     *  yeni kuralda uyuşmazlık BOYUTU düşürür, turu bloklamaz (brif §3.5). */
    public function test_uyusmazliktan_isaretli_kayit_yayina_alinir(): void
    {
        $tour = $this->makeTour('Uyuşmazlık Turu');
        $scores = TourRubricScore::where('tour_id', $tour->id)->firstOrFail();

        // Alıntı sorunu YOK — işaret yalnız iki geçiş ayrışmasından
        $payload = $scores->scores;
        foreach (Rubric::dimensions() as $d) {
            $payload[$d]['evidence_verified'] = true;
        }
        $scores->update(['scores' => $payload, 'review_status' => TourRubricScore::STATUS_NEEDS_REVIEW]);

        $this->artisan('app:rubric-recheck')->assertSuccessful();

        $this->assertSame(TourRubricScore::STATUS_AUTO, $scores->fresh()->review_status);
    }

    /** Alıntı doğrulaması hiçbir durumda turu BLOKLAMAZ (kural: kontrol boyutu
     *  etkiler, turu değil) — ama teşhis çıktısında görünür kalır. */
    public function test_alinti_sorunu_turu_bloklamaz_ama_raporlanir(): void
    {
        $tour = $this->makeTour('Şüpheli Tur');
        $scores = TourRubricScore::where('tour_id', $tour->id)->firstOrFail();

        $payload = $scores->scores;
        foreach (Rubric::dimensions() as $i => $d) {
            $payload[$d]['evidence_verified'] = $i < 7 ? false : true; // 7/10 doğrulanmadı
        }
        $scores->update(['scores' => $payload, 'review_status' => TourRubricScore::STATUS_NEEDS_REVIEW]);

        $this->artisan('app:rubric-recheck')
            ->expectsOutputToContain('alıntısı doğrulanamayan boyut: 7')
            ->assertSuccessful();

        // Yayına alınır: 36 turluk katalogun tamamı bu kural yüzünden kilitlenmişti
        $this->assertSame(TourRubricScore::STATUS_AUTO, $scores->fresh()->review_status);
    }

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

    // ---- TurAra: vize ----

    /** Vize üç değerlidir ve KAPIDA AYRIDIR: "vizesiz" isteyene kapıda vizeli
     *  tur verilemez. Beyan edilmemiş tur da hiçbir yönde listeye girmez. */
    public function test_tur_ara_vizesiz_filtresi_kapidayi_ve_beyansizi_eler(): void
    {
        $yurtDisi = ['is_international' => true];
        $this->makeTour('Vizesiz Tur', [], $yurtDisi + ['requires_visa' => false, 'visa_on_arrival' => false]);
        $this->makeTour('Kapıda Vize Turu', [], $yurtDisi + ['requires_visa' => true, 'visa_on_arrival' => true]);
        $this->makeTour('Vizeli Tur', [], $yurtDisi + ['requires_visa' => true, 'visa_on_arrival' => false]);
        $this->makeTour('Beyansız Tur', [], $yurtDisi + ['requires_visa' => null, 'visa_on_arrival' => null]);

        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            'filtre' => ['vize' => VisaStatus::VIZESIZ],
        ]);

        $this->assertSame(['Vizesiz Tur'], array_column($sonuc['turlar'], 'title'));
    }

    public function test_tur_ara_kapida_vize_ayri_bir_deger(): void
    {
        $this->makeTour('Kapıda Vize Turu', [], [
            'requires_visa' => true, 'visa_on_arrival' => true, 'is_international' => true,
        ]);
        $this->makeTour('Vizeli Tur', [], [
            'requires_visa' => true, 'visa_on_arrival' => false, 'is_international' => true,
        ]);

        $kapida = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            'filtre' => ['vize' => VisaStatus::KAPIDA],
        ]);
        $vizeli = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            'filtre' => ['vize' => VisaStatus::VIZELI],
        ]);

        $this->assertSame(['Kapıda Vize Turu'], array_column($kapida['turlar'], 'title'));
        $this->assertSame(['Vizeli Tur'], array_column($vizeli['turlar'], 'title'));
    }

    /** Eski kayıtta visa_on_arrival boş kalmış olabilir; kıyas ekranı bunu
     *  "Vize gerekiyor" diye okuyor, arama da öyle okumalı. */
    public function test_tur_ara_vizeli_dalinda_kapida_bayragi_bos_kayit_da_gelir(): void
    {
        $this->makeTour('Eski Vizeli Tur', [], [
            'requires_visa' => true, 'visa_on_arrival' => null, 'is_international' => true,
        ]);

        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            'filtre' => ['vize' => VisaStatus::VIZELI],
        ]);

        $this->assertSame(['Eski Vizeli Tur'], array_column($sonuc['turlar'], 'title'));
    }

    /** CANLI ŞİKAYET: "ilk defa yurt dışına çıkıcam vizesiz tur öner" ilk
     *  mesajda tur yerine SORU alıyordu — vize hiçbir kutuya yazılamadığı için
     *  elde somut kısıt yok sayılıyordu. Vize tek başına arama yapmaya yeter. */
    public function test_vizesiz_istegi_tek_basina_tur_listeler(): void
    {
        $this->makeTour('Vizesiz Yurt Dışı Turu', [], [
            'requires_visa' => false, 'visa_on_arrival' => false, 'is_international' => true,
        ]);
        $this->makeTour('Vizeli Yurt Dışı Turu', [], [
            'requires_visa' => true, 'visa_on_arrival' => false, 'is_international' => true,
        ]);

        // Boyut YOK: kullanıcı tatil tarzından hiç söz etmedi
        $sonuc = app(TurAra::class)->run([
            'boyutlar' => [],
            'filtre' => ['vize' => VisaStatus::VIZESIZ, 'yurt_disi' => true],
            'transkript' => 'ilk defa yurt dışına çıkıcam vizesiz tur öner',
        ]);

        $this->assertArrayNotHasKey('hata', $sonuc);
        $this->assertTrue($sonuc['profilsiz_liste']);
        $this->assertSame(['Vizesiz Yurt Dışı Turu'], array_column($sonuc['turlar'], 'title'));
        // "önce kartları göster" dalı: soru sordurtan metin DEĞİL
        $this->assertSame(ChatPrompts::TUR_ARA_PROFILSIZ_NOT_KISITLI, $sonuc['not']);
    }

    /** Yurt içi turların HEPSİ vizesiz: vize filtresi yurt dışını ima etmezse
     *  "vizesiz tur öner" isteğine Kapadokya/Antalya turları dolar. */
    public function test_vize_filtresi_yurt_disini_ima_eder(): void
    {
        $this->makeTour('Yurt İçi Vizesiz Tur', [], [
            'requires_visa' => false, 'visa_on_arrival' => false, 'is_international' => false,
        ]);
        $this->makeTour('Yurt Dışı Vizesiz Tur', [], [
            'requires_visa' => false, 'visa_on_arrival' => false, 'is_international' => true,
        ]);

        // Kullanıcı "yurt dışı" kelimesini HİÇ kurmadı, yalnız "vizesiz" dedi
        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            'filtre' => ['vize' => VisaStatus::VIZESIZ],
        ]);

        $this->assertSame(['Yurt Dışı Vizesiz Tur'], array_column($sonuc['turlar'], 'title'));
    }

    /** Model yanlışlıkla yurt_disi=false yazarsa iki filtre çelişip listeyi
     *  BOŞALTMAMALI — yurt içi + vizeli diye bir tur yok, vize kazanır. */
    public function test_vize_celisen_yurt_disi_bayragini_yener(): void
    {
        $this->makeTour('Yurt Dışı Vizesiz Tur', [], [
            'requires_visa' => false, 'visa_on_arrival' => false, 'is_international' => true,
        ]);

        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            'filtre' => ['vize' => VisaStatus::VIZESIZ, 'yurt_disi' => false],
        ]);

        $this->assertSame(['Yurt Dışı Vizesiz Tur'], array_column($sonuc['turlar'], 'title'));
    }

    /** Kartta vize durumu olmazsa model turun vizesini kendi dünya bilgisinden
     *  söyler; doğrulayıcı da metindeki bu iddiayı denetlemiyor. */
    public function test_tur_ara_karti_vize_durumunu_tasir(): void
    {
        $this->makeTour('Kapıda Vize Turu', [], [
            'requires_visa' => true, 'visa_on_arrival' => true, 'is_international' => true,
        ]);

        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
        ]);

        $this->assertSame(VisaStatus::KAPIDA, $sonuc['turlar'][0]['vize']);
    }

    /** Model uydurma bir kod gönderirse arama BOŞALMAMALI (fail-open). */
    public function test_tur_ara_taninmayan_vize_kodunu_yok_sayar(): void
    {
        $this->makeTour('Vizesiz Tur', [], ['requires_visa' => false, 'visa_on_arrival' => false]);

        $sonuc = app(TurAra::class)->run([
            'boyutlar' => ['tempo' => ['deger' => 50, 'kanit' => 'normal bir tempo olsun']],
            'filtre' => ['vize' => 'belki'],
        ]);

        $this->assertSame(['Vizesiz Tur'], array_column($sonuc['turlar'], 'title'));
    }

    /** Vize kısıtı konuşma boyunca taşınmalı; geçersiz kod hafızaya girmemeli. */
    public function test_vize_kisiti_hafizada_beyaz_listeden_gecer(): void
    {
        $durum = ConversationState::fromArray([]);
        $durum->absorb(TurAra::name(), ['filtre' => ['vize' => VisaStatus::VIZESIZ]], ['turlar' => []]);
        $this->assertSame(VisaStatus::VIZESIZ, $durum->varsayilanFiltre()['vize']);

        $bozuk = ConversationState::fromArray([]);
        $bozuk->absorb(TurAra::name(), ['filtre' => ['vize' => 'belki']], ['turlar' => []]);
        $this->assertArrayNotHasKey('vize', $bozuk->varsayilanFiltre());
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

    /** Vize KAYNAĞI acentanın beyanı; şehir profilindeki alan LLM dünya bilgisi
     *  ve hatalı olduğu doğrulandı (kıyas ekranıyla aynı duruş). */
    public function test_tur_detay_vizeyi_sehir_profilinden_degil_tur_beyanindan_okur(): void
    {
        DestinationProfile::create([
            'city' => 'Roma', 'normalized_city' => DestinationProfile::normalize('Roma'),
            'crowd_score' => 0.9, 'liveliness_score' => 0.7, 'source' => DestinationProfile::SOURCE_LLM,
            'requires_visa_for_tr' => false, // profil "vize yok" diyor — acenta aksini beyan etti
        ]);
        $tour = $this->makeTour('Roma Turu', [], [
            'destination' => 'Roma',
            'requires_visa' => true, 'visa_on_arrival' => true,
            'included' => 'Uçak bileti, otel',
            'cancellation_policy' => '15 gün öncesine kadar ücretsiz',
            'itinerary' => [['title' => 'Varış', 'content' => 'Havalimanı karşılama']],
        ]);

        $sonuc = app(TurDetay::class)->run(['tur_id' => $tour->id]);

        $this->assertSame('Roma Turu', $sonuc['baslik']);
        $this->assertSame(VisaStatus::KAPIDA, $sonuc['vize']);
        $this->assertSame('Kapıda vize', $sonuc['vize_etiketi']);
        $this->assertNotNull($sonuc['vize_notu']); // prosedür anlatma, acentaya yönlendir
        $this->assertStringContainsString('otel', $sonuc['fiyata_dahil']);
        $this->assertSame(1, $sonuc['gun_gun_program'][0]['gun']);
    }

    /** Beyan yoksa "vizesiz" diye OKUNMAZ: model hüküm vermemeli. */
    public function test_tur_detay_beyansiz_turda_vizeyi_bos_dondurur(): void
    {
        $tour = $this->makeTour('Beyansız Tur', [], ['requires_visa' => null, 'visa_on_arrival' => null]);

        $sonuc = app(TurDetay::class)->run(['tur_id' => $tour->id]);

        $this->assertNull($sonuc['vize']);
        $this->assertStringContainsString('DEME', $sonuc['vize_notu']); // modele açık talimat
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
