<?php

namespace Tests\Feature;

use App\Http\Controllers\AiSearchController;
use App\Models\CurrencyRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Niyet heuristiklerinin alt-dizi/çekim tuzaklarına karşı testleri
 * (chatbot denetimi bulguları — Paket 2).
 */
class AiIntentHeuristicsTest extends TestCase
{
    use RefreshDatabase;

    private function invoke(string $method, mixed ...$args): mixed
    {
        $controller = app(AiSearchController::class);
        $reflection = new \ReflectionMethod($controller, $method);

        return $reflection->invoke($controller, ...$args);
    }

    public function test_icin_kelimesi_cin_sanilmaz(): void
    {
        // "için" içindeki 'cin' alt-dizisi Çin sanılıp yurt içi sorguyu yurt dışına çevirmesin
        $this->assertFalse($this->invoke('detectInternationalIntent', 'Ailem için Antalya tatili'));
        $this->assertNull($this->invoke('detectInternationalIntent', 'ailem için güzel bir tatil önerisi'));
        $this->assertTrue($this->invoke('detectInternationalIntent', 'Çin turu düşünüyorum'));
    }

    public function test_balik_ve_balikesir_bali_sanilmaz(): void
    {
        $this->assertNotTrue($this->invoke('detectInternationalIntent', 'Balıkesir turu var mı'));
        $this->assertNotTrue($this->invoke('detectInternationalIntent', 'balık tutma turu'));
        $this->assertTrue($this->invoke('detectInternationalIntent', 'bali düşünüyorum'));
    }

    public function test_kalkis_sehri_yurt_ici_sinyali_sayilmaz(): void
    {
        // Kalkış şehri destinasyon değildir: Paris araması yurt içine dönmesin
        $this->assertTrue($this->invoke('detectInternationalIntent', 'İstanbul kalkışlı Paris turu'));
        $this->assertTrue($this->invoke('detectInternationalIntent', "İstanbul'dan Paris turu"));
        $this->assertTrue($this->invoke('detectInternationalIntent', 'Ankara çıkışlı Roma gezisi'));
        // Locatif hal yurt içi sinyalidir ("İstanbul'da gezilecek yerler")
        $this->assertFalse($this->invoke('detectInternationalIntent', 'istanbulda gezilecek yerler'));
    }

    public function test_celisen_sinyallerde_karar_gptye_birakilir(): void
    {
        $this->assertNull($this->invoke('detectInternationalIntent', "Türkiye'den Avrupa turu istiyorum"));
        $this->assertNull($this->invoke('detectInternationalIntent', 'vize istemiyorum yurt içi olsun'));
    }

    public function test_nisanlimla_nisan_ayi_sanilmaz(): void
    {
        $this->assertNull($this->invoke('extractPreferredMonth', [], 'Nişanlımla romantik bir tatil'));
        $this->assertSame(6, $this->invoke('extractPreferredMonth', [], 'Haziranda balayı yapacağız'));
        $this->assertSame(4, $this->invoke('extractPreferredMonth', [], 'nisan ayında kültür turu'));
    }

    public function test_sezon_ifadeleri_aya_cevrilir(): void
    {
        $this->assertSame(7, $this->invoke('extractPreferredMonth', [], 'yaz tatili için öneri'));
        $this->assertSame(2, $this->invoke('extractPreferredMonth', [], 'sömestr tatilinde kayak'));
        $this->assertSame(1, $this->invoke('extractPreferredMonth', [], 'kış tatili düşünüyoruz'));
        $this->assertSame(10, $this->invoke('extractPreferredMonth', [], 'sonbahar gezisi'));
        // LLM cevabı her zaman önceliklidir
        $this->assertSame(9, $this->invoke('extractPreferredMonth', ['preferred_month' => 9], 'yaz tatili'));
    }

    public function test_denizli_ve_golf_doga_sanilmaz(): void
    {
        $this->assertNull($this->invoke('detectNatureIntent', 'Denizli turu'));
        $this->assertNull($this->invoke('detectNatureIntent', 'golf turu istiyorum'));
        $this->assertTrue($this->invoke('detectNatureIntent', 'göl kenarında huzurlu bir yer'));
        $this->assertTrue($this->invoke('detectNatureIntent', 'deniz manzaralı sakin otel'));
    }

    public function test_doviz_butcesi_tl_ye_cevrilir(): void
    {
        CurrencyRate::updateOrCreate(['currency' => 'EUR'], ['rate_to_try' => 35.0]);
        CurrencyRate::updateOrCreate(['currency' => 'USD'], ['rate_to_try' => 30.0]);
        CurrencyRate::resetCache();

        $this->assertSame(35000, $this->invoke('convertBudgetToTry', 1000, 'kişi başı 1000 euro bütçem var'));
        $this->assertSame(30000, $this->invoke('convertBudgetToTry', 1000, '1000 dolar civarı'));
        // Döviz geçmiyorsa TL varsayılır, tutar değişmez
        $this->assertSame(25000, $this->invoke('convertBudgetToTry', 25000, '25 bin TL bütçe'));

        CurrencyRate::resetCache();
    }

    public function test_dislama_kaliplari_cekimli_formlari_yakalar(): void
    {
        $this->assertTrue($this->invoke('isDestinationExplicitlyExcludedInText', 'istanbulu istemiyorum', 'İstanbul'));
        $this->assertTrue($this->invoke('isDestinationExplicitlyExcludedInText', 'istanbul olmasin sakin bir yer', 'İstanbul'));
        $this->assertTrue($this->invoke('isDestinationExplicitlyExcludedInText', 'istanbuldan farkli bir sehir', 'İstanbul'));
        $this->assertFalse($this->invoke('isDestinationExplicitlyExcludedInText', 'istanbul cok guzel bir sehir', 'İstanbul'));
    }

    public function test_cok_parcali_destinasyon_parca_ile_eslesir(): void
    {
        $this->assertTrue($this->invoke('queryMentionsDestination', 'pamukkale turu istiyorum', 'Salda Gölü, Pamukkale, Çeşme'));
        // Alt-dizi tuzağı: "kurfalı" içinde 'urfa' eşleşmesin
        $this->assertFalse($this->invoke('queryMentionsDestination', 'kurfali koyu gezisi', 'Urfa'));
    }
}
