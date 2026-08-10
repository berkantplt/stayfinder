<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Support\DestinationFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Destinasyon filtresi bekçileri.
 *
 * Asıl hata: filtre `where('destination', $x)` ile TAM eşleşme yapıyordu, oysa
 * kolon serbest metin. "Fethiye" seçen kullanıcı "Ölüdeniz, Fethiye" turunu
 * göremiyordu — 21 çağrı noktasının 12'si bu şekilde sonuç kaybediyordu.
 *
 * Bu testler SQLite'ta koşuyor, üretim MySQL. DestinationFilter collation'a
 * bilerek güvenmediği (harf katlamasını REPLACE ile kendisi yaptığı) için ikisi
 * aynı sonucu veriyor — testlerin üretimi temsil etmesinin sebebi bu.
 */
class DestinationFilterTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->agency = Agency::create([
            'name' => 'Destinasyon Acenta',
            'slug' => 'destinasyon-acenta',
            'email' => 'dest@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
    }

    private function tur(string $destination, string $title = null): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'title' => $title ?? ('Tur '.$destination),
            'destination' => $destination,
            'description' => 'Test turu',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(30),
            'is_active' => true,
        ]);
    }

    // ---- Asıl hatanın bekçileri ----

    public function test_cok_sehirli_tur_tekil_sehir_secildiginde_bulunur(): void
    {
        $cokSehirli = $this->tur('Ölüdeniz, Fethiye');
        $tekSehir = $this->tur('Fethiye');

        $this->get(route('tours.index', ['destination' => 'Fethiye']))
            ->assertOk()
            ->assertSee($cokSehirli->title)   // ← eskiden GÖRÜNMÜYORDU
            ->assertSee($tekSehir->title);
    }

    public function test_yalnizca_birlesik_dizgede_gecen_sehir_secilebilir(): void
    {
        $tur = $this->tur('Kapadokya, Nevşehir');

        // Nevşehir eskiden açılır listede hiç yoktu, seçilse de 0 sonuç dönerdi.
        $this->get(route('tours.index', ['destination' => 'Nevşehir']))
            ->assertOk()
            ->assertSee($tur->title);
    }

    public function test_acilir_liste_birlesik_dizge_yerine_parcalari_gosterir(): void
    {
        $this->tur('Kapadokya, Nevşehir');

        $sozluk = DestinationFilter::vocabulary(Tour::active());
        $sehirler = array_column($sozluk, 'city');

        $this->assertContains('Kapadokya', $sehirler);
        $this->assertContains('Nevşehir', $sehirler);
        $this->assertNotContains('Kapadokya, Nevşehir', $sehirler);
    }

    // ---- Kelime sınırı: yanlış pozitif olmamalı ----

    public function test_kisa_sehir_adi_daha_uzun_adin_icinde_eslesmez(): void
    {
        $kastamonu = $this->tur('Kastamonu');

        $this->get(route('tours.index', ['destination' => 'Kas']))
            ->assertOk()
            ->assertDontSee($kastamonu->title);
    }

    public function test_roma_aramasi_romanya_getirmez(): void
    {
        $romanya = $this->tur('Romanya');
        $roma = $this->tur('Roma');

        $this->get(route('tours.index', ['destination' => 'Roma']))
            ->assertOk()
            ->assertSee($roma->title)
            ->assertDontSee($romanya->title);
    }

    // ---- Türkçe harf katlaması ----

    public function test_noktasiz_i_ile_yazilmis_kayit_noktali_aramayla_bulunur(): void
    {
        // Canlı veride "Ayvalik" diye kayıtlı turlar var; MySQL collation 'ı' ile
        // 'i'yi katlamadığı için bunlar hiçbir aramada bulunamıyordu.
        $tur = $this->tur('Ayvalik');

        $this->get(route('tours.index', ['destination' => 'Ayvalık']))
            ->assertOk()
            ->assertSee($tur->title);
    }

    public function test_buyuk_i_harfi_kucuk_aramayla_eslesir(): void
    {
        $tur = $this->tur('İstanbul');

        $this->get(route('tours.index', ['destination' => 'istanbul']))
            ->assertOk()
            ->assertSee($tur->title);
    }

    public function test_normalize_birlesik_noktali_i_uretmez(): void
    {
        // mb_strtolower('İ') iki kod noktalı 'i̇' üretir ve SQL LOWER() ile ASLA
        // eşleşmez. TourMatcher'da not düşülen tuzağın unit karşılığı.
        $this->assertSame('istanbul', DestinationFilter::normalize('İstanbul'));
        $this->assertSame('istanbul', DestinationFilter::normalize('İSTANBUL'));
        $this->assertSame('ayvalik', DestinationFilter::normalize('Ayvalık'));
        $this->assertSame('oludeniz', DestinationFilter::normalize('Ölüdeniz'));
    }

    // ---- Bölme davranışı ----

    public function test_bitisik_tireli_ad_ikiye_bolunmez(): void
    {
        // "Bosna-Hersek" tek yer adı; boşluklu tire ise ayraç sayılır.
        $this->assertSame(['Bosna-Hersek'], DestinationFilter::splitCities('Bosna-Hersek'));
        $this->assertSame(['Napoli', 'Sorrento'], DestinationFilter::splitCities('Napoli - Sorrento'));
    }

    public function test_tamami_buyuk_harfli_etiket_baslik_bicimine_cekilir(): void
    {
        $this->tur('NAPOLI - SORRENTO');

        $sehirler = array_column(DestinationFilter::vocabulary(Tour::active()), 'city');

        $this->assertContains('Napoli', $sehirler);
        $this->assertNotContains('NAPOLI', $sehirler);
    }

    // ---- Kapsam: diğer yüzeyler ----

    public function test_bos_destinasyon_parametresi_filtre_uygulamaz(): void
    {
        $a = $this->tur('Fethiye');
        $b = $this->tur('Trabzon');

        $this->get(route('tours.index', ['destination' => '']))
            ->assertOk()
            ->assertSee($a->title)
            ->assertSee($b->title);
    }

    public function test_pasif_acentanin_turu_destinasyon_filtresinde_gorunmez(): void
    {
        $tur = $this->tur('Ölüdeniz, Fethiye');
        $this->agency->update(['is_active' => false]);

        $this->get(route('tours.index', ['destination' => 'Fethiye']))
            ->assertOk()
            ->assertDontSee($tur->title);
    }

    public function test_ana_sayfa_cogul_destinasyon_filtresi_cok_sehirli_turu_bulur(): void
    {
        $tur = $this->tur('Ölüdeniz, Fethiye');
        $this->tur('Trabzon');

        $r = $this->get(route('home', ['destinations' => ['Fethiye']]), ['X-Requested-With' => 'XMLHttpRequest']);

        $r->assertOk();
        $this->assertSame(1, $r->json('count'));
        $this->assertStringContainsString($tur->title, $r->json('html'));
    }

    public function test_ana_sayfa_eski_tekil_parametresi_ayni_sonucu_verir(): void
    {
        $tur = $this->tur('Kapadokya, Nevşehir');
        $this->tur('Trabzon');

        // Geriye dönük uyum: eski ?destination= linkleri bozulmamalı.
        $r = $this->get(route('home', ['destination' => 'Kapadokya']), ['X-Requested-With' => 'XMLHttpRequest']);

        $r->assertOk();
        $this->assertSame(1, $r->json('count'));
        $this->assertStringContainsString($tur->title, $r->json('html'));
    }

    /**
     * NOT: tours/show.blade.php şu an $similarTours'u HİÇ basmıyor (controller
     * hesaplayıp view'a geçiriyor, blade kullanmıyor). Bu yüzden render edilen
     * HTML'e değil, view verisine bakıyoruz — blade'e eklendiği gün test zaten
     * doğru sonucu koruyor olacak.
     */
    public function test_benzer_turlar_cok_sehirli_turda_bos_kalmaz(): void
    {
        $cokSehirli = $this->tur('Ölüdeniz, Fethiye');
        $komsu = $this->tur('Fethiye');

        $this->get(route('tours.show', $cokSehirli))
            ->assertOk()
            ->assertViewHas('similarTours', fn ($benzerler) => $benzerler->contains('id', $komsu->id));
    }
}
