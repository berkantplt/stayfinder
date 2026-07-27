<?php

namespace Tests\Unit;

use App\Http\Controllers\AiSearchController;
use App\Support\AiWeightEvaluator;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Uyumluluk skorunun ÇEKİRDEK matematiği (AiWeightEvaluator::score) için
 * doğrudan, deterministik birim testleri. "Hangi tur kazanır"ı belirleyen
 * saf fonksiyon; LLM'e ihtiyaç yok. Beklenen değerler kodu tekrar etmeden
 * elle türetilmiştir — formül/ağırlık kayarsa bu testler yakalar.
 *
 * Formül: score = clamp01( (b·sem + Σ_aktif w_k·s_k) / (b + Σ_aktif w_k) )
 *   b (semantik taban) = max(0.30, 0.56 - 0.06·aktifKriterSayısı)
 */
class AiWeightEvaluatorScoreTest extends TestCase
{
    public function test_kriter_yokken_skor_semantik_skoru_aynen_dondurur(): void
    {
        // Aktif kriter yok → yalnız semantik taban kalır, pay/payda sadeleşir.
        $this->assertEqualsWithDelta(0.73, AiWeightEvaluator::score(['semantic' => 0.73], []), 0.0001);
        $this->assertEqualsWithDelta(0.0, AiWeightEvaluator::score(['semantic' => 0.0], []), 0.0001);
        $this->assertEqualsWithDelta(1.0, AiWeightEvaluator::score(['semantic' => 1.0], []), 0.0001);
    }

    public function test_tek_eksen_agirlikli_ortalamayi_tam_hesaplar(): void
    {
        // 1 aktif kriter → b = 0.56 - 0.06 = 0.50
        // (0.50·0.50 + 0.20·1.00) / (0.50 + 0.20) = 0.45 / 0.70 = 0.642857
        $score = AiWeightEvaluator::score(
            ['semantic' => 0.50, 'budget' => 1.00],
            ['max_budget' => 10000],
            ['budget' => 0.20],
        );

        $this->assertEqualsWithDelta(0.642857, $score, 0.0001);
    }

    public function test_aktif_kriter_arttikca_semantigin_agirligi_azalir(): void
    {
        // Yalnız 'destination' eksene skor katkısı verir (weights sadece onu içerir);
        // diğer aktif kriterler yalnız aktifSayısını (dolayısıyla taban b'yi) değiştirir.
        $weights = ['destination' => 0.20]; // override tabanı 0.22'ye çıkarır (max ile)

        // A: 1 aktif kriter → b = 0.50 ; payda 0.50 + 0.22 = 0.72
        $aSemHeavy = AiWeightEvaluator::score(
            ['semantic' => 1.0, 'destination' => 0.0],
            ['preferred_destination' => 'Ege'],
            $weights,
        );
        // (0.50·1.0 + 0.22·0.0) / 0.72 = 0.694444
        $this->assertEqualsWithDelta(0.694444, $aSemHeavy, 0.0001);

        // B: 3 aktif kriter → b = 0.56 - 0.18 = 0.38 ; payda 0.38 + 0.22 = 0.60
        $bSemHeavy = AiWeightEvaluator::score(
            ['semantic' => 1.0, 'destination' => 0.0],
            ['preferred_destination' => 'Ege', 'max_budget' => 10000, 'wants_nature' => true],
            $weights,
        );
        // (0.38·1.0 + 0.22·0.0) / 0.60 = 0.633333
        $this->assertEqualsWithDelta(0.633333, $bSemHeavy, 0.0001);

        // Daha çok kriter aktifken semantik baskınlığı zayıflar.
        $this->assertGreaterThan($bSemHeavy, $aSemHeavy);
    }

    public function test_lively_ve_avoid_crowded_birlikte_agirliklari_override_eder(): void
    {
        // Her ikisi aktif → 2 kriter → b = 0.44
        // Override: lively 0.14→0.22, city_escape 0.12→0.10
        // (0.44·0.5 + 0.22·1.0 + 0.10·1.0) / (0.44 + 0.22 + 0.10)
        // = (0.22 + 0.22 + 0.10) / 0.76 = 0.54 / 0.76 = 0.710526
        $score = AiWeightEvaluator::score(
            ['semantic' => 0.5, 'lively' => 1.0, 'city_escape' => 1.0],
            ['wants_lively' => true, 'avoid_crowded_city' => true],
            ['lively' => 0.14, 'city_escape' => 0.12],
        );

        $this->assertEqualsWithDelta(0.710526, $score, 0.0001);
    }

    public function test_preferred_destination_agirligina_taban_deger_uygulanir(): void
    {
        // Verilen destination ağırlığı 0.05 olsa bile override en az 0.22'ye çeker.
        // 1 aktif kriter → b = 0.50 ; payda 0.50 + 0.22 = 0.72
        // (0.50·0.0 + 0.22·1.0) / 0.72 = 0.305556
        $score = AiWeightEvaluator::score(
            ['semantic' => 0.0, 'destination' => 1.0],
            ['preferred_destination' => 'Kapadokya'],
            ['destination' => 0.05],
        );

        $this->assertEqualsWithDelta(0.305556, $score, 0.0001);
    }

    public function test_isabet_daha_cok_kritere_uyan_tur_kazanir_ve_esigi_gecer(): void
    {
        // Senaryonun özü: "tam ihtiyacım olan tur" öne çıkmalı, alakasız elenemeli.
        // Kriter: bütçe + destinasyon (2 aktif → b = 0.44, destination override 0.22)
        $criteria = ['max_budget' => 20000, 'preferred_destination' => 'Ege'];
        $weights = AiWeightEvaluator::defaultWeights(); // budget 0.16, destination→0.22

        // Doğru tur: her iki eksende de tam uyum
        // (0.44·0.6 + 0.16·1.0 + 0.22·1.0) / (0.44+0.16+0.22)
        // = (0.264 + 0.16 + 0.22) / 0.82 = 0.644 / 0.82 = 0.785366
        $dogru = AiWeightEvaluator::score(
            ['semantic' => 0.6, 'budget' => 1.0, 'destination' => 1.0],
            $criteria,
            $weights,
        );

        // Yanlış tur: aynı semantik ama bütçe/destinasyon zayıf
        // (0.264 + 0.16·0.2 + 0.22·0.2) / 0.82 = (0.264 + 0.032 + 0.044) / 0.82 = 0.414634
        $yanlis = AiWeightEvaluator::score(
            ['semantic' => 0.6, 'budget' => 0.2, 'destination' => 0.2],
            $criteria,
            $weights,
        );

        $this->assertEqualsWithDelta(0.785366, $dogru, 0.0001);
        $this->assertEqualsWithDelta(0.414634, $yanlis, 0.0001);

        // Ayrıştırma: doğru tur yanlıştan yüksek olmalı
        $this->assertGreaterThan($yanlis, $dogru);

        // Ve eşiğin doğru tarafında olmalılar: doğru geçer, yanlış elenir.
        $this->assertGreaterThanOrEqual(AiSearchController::COMPATIBILITY_THRESHOLD, $dogru);
        $this->assertLessThan(AiSearchController::COMPATIBILITY_THRESHOLD, $yanlis);
    }

    public function test_skor_her_zaman_sifir_bir_araligina_kirpilir(): void
    {
        // Tüm eksenler tam → pay = payda → tavan 1.0'ı aşamaz.
        $tavan = AiWeightEvaluator::score(
            ['semantic' => 1.0, 'budget' => 1.0, 'destination' => 1.0],
            ['max_budget' => 5000, 'preferred_destination' => 'Ege'],
            AiWeightEvaluator::defaultWeights(),
        );
        $this->assertEqualsWithDelta(1.0, $tavan, 0.0001);

        // Tüm eksenler 0 → taban 0.0'ın altına inemez.
        $taban = AiWeightEvaluator::score(
            ['semantic' => 0.0, 'budget' => 0.0],
            ['max_budget' => 5000],
            ['budget' => 0.16],
        );
        $this->assertEqualsWithDelta(0.0, $taban, 0.0001);
    }

    public function test_kalibre_edilmis_agirliklar_cache_uzerinden_devreye_girer(): void
    {
        // activeWeights() varsayılanı cache override'ıyla birleştirir.
        $this->assertEqualsWithDelta(0.16, AiWeightEvaluator::activeWeights()['budget'], 0.0001);

        Cache::put(AiWeightEvaluator::CACHE_KEY, ['budget' => 0.99]);

        $this->assertEqualsWithDelta(0.99, AiWeightEvaluator::activeWeights()['budget'], 0.0001);
        // Diğer ağırlıklar varsayılanda kalır.
        $this->assertEqualsWithDelta(0.14, AiWeightEvaluator::activeWeights()['lively'], 0.0001);
    }
}
