<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Campaign;
use App\Models\CurrencyRate;
use App\Models\Tour;
use App\Support\TourComparison;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tur karşılaştırma sayfasının hesap katmanı.
 *
 * Sayfanın değeri farkı BULMAKTA. Bu testler o hesabın sessizce bozulmasını
 * engeller: kur normalizasyonu, kampanyalı fiyatın kıyasa girmesi, gün başına
 * maliyet ve dahil/hariç listelerinin eşleştirilmesi.
 */
class TourComparisonTest extends TestCase
{
    use RefreshDatabase;

    private function acenta(): Agency
    {
        return Agency::create([
            'name' => 'Acenta '.uniqid(),
            'slug' => 'acenta-'.uniqid(),
            'email' => uniqid().'@ornek.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    private function tur(array $attributes = []): Tour
    {
        return Tour::create(array_merge([
            'agency_id' => $this->acenta()->id,
            'title' => 'Kapadokya Turu',
            'destination' => 'Kapadokya',
            'description' => 'Peri bacaları.',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => 4,
            'departure_date' => today()->addDays(20),
            'is_active' => true,
        ], $attributes));
    }

    /** @param  array<int, Tour>  $tours */
    private function kiyas(array $tours): array
    {
        // Controller dates'i eager-load ediyor; kalkış satırı ona dayanıyor.
        return TourComparison::build((new EloquentCollection($tours))->load('dates'));
    }

    // ── Fiyat ───────────────────────────────────────────────────────────────

    public function test_en_ucuz_rozeti_kur_normalize_fiyata_gore_verilir(): void
    {
        CurrencyRate::updateOrCreate(['currency' => 'EUR'], ['rate_to_try' => 40, 'fetched_at' => now()]);
        CurrencyRate::resetCache();

        $ucuz = $this->tur(['price' => 20000, 'currency' => 'TRY']);
        // Ham sayı olarak 600 < 20000; kıyas `price` üzerinden yapılsaydı EUR tur
        // "en ucuz" çıkardı. Gerçekte 600 EUR = 24.000 TL, yani daha pahalı.
        $pahali = $this->tur(['price' => 600, 'currency' => 'EUR']);

        $fiyatlar = $this->kiyas([$ucuz, $pahali])['fiyatlar'];

        $this->assertTrue($fiyatlar[$ucuz->id]['enUcuz']);
        $this->assertFalse($fiyatlar[$pahali->id]['enUcuz']);
        $this->assertSame(0, $fiyatlar[$ucuz->id]['farkYuzde']);
        $this->assertSame(20, $fiyatlar[$pahali->id]['farkYuzde']);
        $this->assertStringContainsString('24.000', $fiyatlar[$pahali->id]['tryEtiket']);
    }

    public function test_yabanci_para_biriminde_tl_karsiligi_gosterilir(): void
    {
        CurrencyRate::updateOrCreate(['currency' => 'EUR'], ['rate_to_try' => 40, 'fetched_at' => now()]);
        CurrencyRate::resetCache();

        $tl = $this->tur(['price' => 10000, 'currency' => 'TRY']);
        $euro = $this->tur(['price' => 400, 'currency' => 'EUR']);

        $fiyatlar = $this->kiyas([$tl, $euro])['fiyatlar'];

        // TL turda "≈" satırı gürültü olur, basılmaz.
        $this->assertNull($fiyatlar[$tl->id]['tryEtiket']);
        $this->assertStringContainsString('16.000', $fiyatlar[$euro->id]['tryEtiket']);
    }

    public function test_kampanyali_fiyat_kiyasa_girer(): void
    {
        $indirimli = $this->tur(['price' => 30000]);
        $duz = $this->tur(['price' => 25000]);

        Campaign::create([
            'tour_id' => $indirimli->id,
            'discount_price' => 18000,
            'label' => 'Erken rezervasyon',
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(7),
            'is_active' => true,
        ]);

        $fiyatlar = $this->kiyas([$indirimli->fresh(), $duz])['fiyatlar'];

        // Liste fiyatı 30.000 > 25.000; indirimli 18.000 < 25.000. Rozet
        // kullanıcının ödeyeceği fiyata bakmalı.
        $this->assertTrue($fiyatlar[$indirimli->id]['enUcuz']);
        $this->assertTrue($fiyatlar[$indirimli->id]['kampanya']);
        $this->assertStringContainsString('30.000', $fiyatlar[$indirimli->id]['eskiEtiket']);
    }

    public function test_gun_basina_maliyet_toplam_fiyattan_ayri_hesaplanir(): void
    {
        // Klasik tuzak: ucuz görünen tur günü pahalıya geliyor.
        $kisa = $this->tur(['price' => 20000, 'duration_days' => 4]);   // 5.000/gün
        $uzun = $this->tur(['price' => 28000, 'duration_days' => 7]);   // 4.000/gün

        $fiyatlar = $this->kiyas([$kisa, $uzun])['fiyatlar'];

        $this->assertTrue($fiyatlar[$kisa->id]['enUcuz']);
        $this->assertFalse($fiyatlar[$kisa->id]['enAvantajli']);
        $this->assertTrue($fiyatlar[$uzun->id]['enAvantajli']);
        $this->assertStringContainsString('4.000', $fiyatlar[$uzun->id]['gunlukEtiket']);
    }

    public function test_fiyatlar_esitse_rozet_basilmaz(): void
    {
        $a = $this->tur(['price' => 15000, 'duration_days' => 5]);
        $b = $this->tur(['price' => 15000, 'duration_days' => 5]);

        $fiyatlar = $this->kiyas([$a, $b])['fiyatlar'];

        // Herkeste rozet varsa rozet bilgi taşımaz.
        $this->assertFalse($fiyatlar[$a->id]['enUcuz']);
        $this->assertFalse($fiyatlar[$b->id]['enUcuz']);
        $this->assertFalse($fiyatlar[$a->id]['enAvantajli']);
    }

    // ── Satırlar ────────────────────────────────────────────────────────────

    public function test_hicbir_turda_olmayan_alan_satir_acmaz(): void
    {
        $a = $this->tur();
        $b = $this->tur();

        $etiketler = array_column($this->kiyas([$a, $b])['satirlar'], 'etiket');

        // İkisinde de boş: üç tane "—" basmanın kıyasa katkısı yok.
        $this->assertNotContains('Konaklama', $etiketler);
        $this->assertNotContains('İptal koşulları', $etiketler);
        $this->assertContains('Destinasyon', $etiketler);
    }

    public function test_ayni_deger_isaretlenir_bos_olan_fark_sayilir(): void
    {
        $a = $this->tur(['destination' => 'Kapadokya', 'hotel_info' => '4 yıldızlı otel']);
        $b = $this->tur(['destination' => 'Kapadokya']);

        $satirlar = collect($this->kiyas([$a, $b])['satirlar'])->keyBy('etiket');

        $this->assertTrue($satirlar['Destinasyon']['ayni']);
        // Birinde değer var, diğerinde yok — bu bir FARKTIR, "aynı" sayılıp
        // gizlenirse kullanıcı otel bilgisinin tek turda olduğunu göremez.
        $this->assertFalse($satirlar['Konaklama']['ayni']);
    }

    public function test_en_uzun_rozeti_sadece_fark_varken_basilir(): void
    {
        $kisa = $this->tur(['duration_days' => 3]);
        $uzun = $this->tur(['duration_days' => 6]);

        $sure = collect($this->kiyas([$kisa, $uzun])['satirlar'])->firstWhere('etiket', 'Süre');
        $this->assertSame('EN UZUN', $sure['rozetler'][$uzun->id]);
        $this->assertArrayNotHasKey($kisa->id, $sure['rozetler']);

        $a = $this->tur(['duration_days' => 5]);
        $b = $this->tur(['duration_days' => 5]);

        $esit = collect($this->kiyas([$a, $b])['satirlar'])->firstWhere('etiket', 'Süre');
        $this->assertSame([], $esit['rozetler']);
    }

    // ── Dahil / hariç diff ──────────────────────────────────────────────────

    public function test_dahil_listesinde_ortak_ve_ozel_maddeler_ayrilir(): void
    {
        $a = $this->tur(['included' => "Ulaşım\nKahvaltı\nMüze girişleri"]);
        $b = $this->tur(['included' => "• ulaşim\n- KAHVALTI\n* Rehberlik"]);

        $dahil = $this->kiyas([$a, $b])['dahil'];

        $aMaddeler = collect($dahil[$a->id]['maddeler'])->keyBy('metin');
        // Madde işareti ve büyük/küçük harf farkı eşleşmeyi bozmamalı:
        // "Ulaşım" ↔ "• ulaşim" tek madde.
        $this->assertTrue($aMaddeler['Ulaşım']['ortak']);
        $this->assertTrue($aMaddeler['Kahvaltı']['ortak']);
        $this->assertTrue($aMaddeler['Müze girişleri']['ozel']);

        $this->assertSame(1, $dahil[$a->id]['ozelSayisi']);
        $this->assertSame(1, $dahil[$b->id]['ozelSayisi']);
        $this->assertSame(2, $dahil[$a->id]['ortakSayisi']);
    }

    public function test_farkli_maddeler_listenin_basinda_gelir(): void
    {
        $a = $this->tur(['included' => "Ortak madde\nSadece burada olan"]);
        $b = $this->tur(['included' => "Ortak madde"]);

        $maddeler = $this->kiyas([$a, $b])['dahil'][$a->id]['maddeler'];

        // Kullanıcı bu sayfaya ortak olanı okumaya gelmedi.
        $this->assertSame('Sadece burada olan', $maddeler[0]['metin']);
    }

    // ── Uçtan uca ───────────────────────────────────────────────────────────

    public function test_karsilastirma_sayfasi_secim_sirasini_korur(): void
    {
        $ilk = $this->tur(['title' => 'Birinci tur']);
        $ikinci = $this->tur(['title' => 'İkinci tur']);

        // whereIn DB sırasında döner; kullanıcı tersten seçtiyse kolonlar da ters olmalı.
        $yanit = $this->get(route('tours.compare', ['ids' => [$ikinci->id, $ilk->id]]));

        $yanit->assertOk();
        $govde = $yanit->getContent();
        $this->assertLessThan(
            strpos($govde, 'data-kiyas-tur="'.$ilk->id.'"'),
            strpos($govde, 'data-kiyas-tur="'.$ikinci->id.'"')
        );
    }

    public function test_gecersiz_ve_tekrarli_idler_ayiklanir(): void
    {
        $tur = $this->tur();

        // Tek geçerli tur kalır → en az 2 şartı sağlanmaz, listeye döner.
        $this->get(route('tours.compare', ['ids' => [$tur->id, $tur->id, 'abc', -5, 0]]))
            ->assertRedirect(route('tours.index'));
    }

    public function test_pasif_tur_karsilastirmaya_girmez(): void
    {
        $aktif = $this->tur();
        $pasif = $this->tur(['is_active' => false]);

        $this->get(route('tours.compare', ['ids' => [$aktif->id, $pasif->id]]))
            ->assertRedirect(route('tours.index'));
    }

    public function test_doldurulmus_detay_alanlari_sayfada_satir_acar(): void
    {
        // Yerel demo veride bu alanlar boş; içe aktarılmış turlarda doluyor.
        // Satırların gerçekten basıldığı veriye bakılarak değil, burada kilitlenir.
        $detaylar = [
            'transport_type' => 'ucak',
            'departure_city' => 'İstanbul',
            'frequency' => 'Her Cuma',
            'hotel_info' => '4 yıldızlı otel, çift kişilik oda',
            'extras' => 'Balon turu, ATV safari',
            'guide_info' => 'Profesyonel Türkçe rehber',
            'cancellation_policy' => 'Kalkışa 15 gün kalaya kadar ücretsiz iptal',
            'pace_score' => 0.8,
            'itinerary' => [['title' => '1. Gün', 'content' => 'Varış']],
        ];

        $dolu = $this->tur($detaylar);
        $bos = $this->tur();

        $yanit = $this->get(route('tours.compare', ['ids' => [$dolu->id, $bos->id]]));
        $yanit->assertOk();

        foreach (['Ulaşım', 'Kalkış yeri', 'Hareket sıklığı', 'Konaklama', 'Program',
            'Tempo', 'Ekstra turlar', 'Rehber', 'İptal koşulları'] as $etiket) {
            $yanit->assertSee($etiket, false);
        }

        // Ham alan değil, kullanıcıya dönük etiketler basılmalı.
        $yanit->assertSee('Gidiş Dönüş Uçak', false);
        $yanit->assertSee('Tempolu gezi', false);
        $yanit->assertSee('1 günlük detaylı program', false);
    }

    public function test_bos_alan_satirda_tire_ile_gosterilir(): void
    {
        $dolu = $this->tur(['hotel_info' => 'Otel bilgisi var']);
        $bos = $this->tur();

        $satir = collect($this->kiyas([$dolu, $bos])['satirlar'])->firstWhere('etiket', 'Konaklama');

        $this->assertSame('Otel bilgisi var', $satir['degerler'][$dolu->id]);
        $this->assertNull($satir['degerler'][$bos->id]);
    }

    // ── Vize (üç durumlu) ───────────────────────────────────────────────────

    public function test_vize_satiri_belirtilmemisi_vizesiz_saymaz(): void
    {
        $vizeli = $this->tur(['requires_visa' => true]);
        $vizesiz = $this->tur(['requires_visa' => false]);
        $bilinmiyor = $this->tur(['requires_visa' => null]);

        $satir = collect($this->kiyas([$vizeli, $vizesiz, $bilinmiyor])['satirlar'])
            ->firstWhere('etiket', 'Vize');

        $this->assertSame('Vize gerekiyor', $satir['degerler'][$vizeli->id]);
        $this->assertSame('Vize gerekmiyor', $satir['degerler'][$vizesiz->id]);
        // Kritik ayrım: belirtilmemiş "vizesiz" DEĞİL. Boş kalır, tire basılır.
        $this->assertNull($satir['degerler'][$bilinmiyor->id]);
    }

    public function test_kapida_vize_ayri_gosterilir(): void
    {
        $kapida = $this->tur(['requires_visa' => true, 'visa_on_arrival' => true]);
        $konsolosluk = $this->tur(['requires_visa' => true, 'visa_on_arrival' => false]);

        $satir = collect($this->kiyas([$kapida, $konsolosluk])['satirlar'])
            ->firstWhere('etiket', 'Vize');

        // İkisi de "vize gerekiyor" ama yolcunun yapacağı iş bambaşka; aynı
        // metne katlanırsa karşılaştırmanın anlamı kalmaz.
        $this->assertSame('Kapıda vize', $satir['degerler'][$kapida->id]);
        $this->assertSame('Vize gerekiyor', $satir['degerler'][$konsolosluk->id]);
        $this->assertFalse($satir['ayni']);
    }

    public function test_hicbirinde_vize_girilmemisse_satir_acilmaz(): void
    {
        $a = $this->tur(['requires_visa' => null]);
        $b = $this->tur(['requires_visa' => null]);

        $etiketler = array_column($this->kiyas([$a, $b])['satirlar'], 'etiket');

        $this->assertNotContains('Vize', $etiketler);
    }
}
