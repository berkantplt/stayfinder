<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Ürün kartındaki ulaşım ve kalkış bilgisi.
 *
 * Her ikisi de opsiyonel: mevcut turların çoğunda bu alanlar boş ve kartta
 * hiçbir şey basılmamalı — boş satır ya da yalnız ikon görünmemeli.
 */
class TourCardMetaTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->agency = Agency::create([
            'name' => 'Kart Acenta',
            'slug' => 'kart-acenta',
            'email' => 'kart@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
    }

    private function tur(array $ek = []): Tour
    {
        return Tour::create(array_merge([
            'agency_id' => $this->agency->id,
            'title' => 'Kart Turu '.uniqid(),
            'destination' => 'Kapadokya',
            'description' => 'Test',
            'price' => 4999,
            'currency' => 'TRY',
            'duration_days' => 2,
            'departure_date' => today()->addDays(30),
            'is_active' => true,
        ], $ek));
    }

    // ---- Ulaşım ----

    public function test_otobus_turunda_gidis_donus_otobus_yazar(): void
    {
        $this->assertSame('Gidiş Dönüş Otobüs', $this->tur(['transport_type' => 'otobus'])->transport_label);
    }

    public function test_ucak_turunda_gidis_donus_ucak_yazar(): void
    {
        $this->assertSame('Gidiş Dönüş Uçak', $this->tur(['transport_type' => 'ucak'])->transport_label);
    }

    public function test_kendi_araciyla_gidilen_turda_gidis_donus_denmez(): void
    {
        $this->assertSame('Kendi aracınızla', $this->tur(['transport_type' => 'kendi'])->transport_label);
    }

    public function test_ulasim_bilinmiyorsa_etiket_bostur(): void
    {
        $this->assertSame('', $this->tur()->transport_label);
        $this->assertSame('', $this->tur(['transport_type' => 'roket'])->transport_label);
    }

    // ---- Kalkış şehirleri ----

    public function test_kalkis_sehri_ve_duraklar_birlikte_yazilir(): void
    {
        $tur = $this->tur(['departure_city' => 'İstanbul', 'stop_cities' => ['Kocaeli', 'Bursa']]);

        $this->assertSame('İstanbul, Kocaeli, Bursa çıkışlı', $tur->departure_label);
    }

    public function test_ucten_fazla_sehir_kisaltilir(): void
    {
        $tur = $this->tur([
            'departure_city' => 'İstanbul',
            'stop_cities' => ['Kocaeli', 'Bursa', 'İzmir', 'Manisa'],
        ]);

        $this->assertSame('İstanbul, Kocaeli, Bursa +2 çıkışlı', $tur->departure_label);
    }

    public function test_kalkis_bilgisi_yoksa_etiket_bostur(): void
    {
        $this->assertSame('', $this->tur()->departure_label);
    }

    // ---- Kartta gerçekten görünüyor mu ----

    public function test_kartta_ulasim_ve_kalkis_gorunur(): void
    {
        $this->tur([
            'title' => 'Kapadokya Otobüs Turu',
            'transport_type' => 'otobus',
            'departure_city' => 'İstanbul',
        ]);

        $this->get(route('tours.index'))
            ->assertOk()
            ->assertSee('Gidiş Dönüş Otobüs')
            ->assertSee('İstanbul çıkışlı');
    }

    public function test_veri_yoksa_kartta_bos_satir_basilmaz(): void
    {
        $this->tur(['title' => 'Bilgisiz Tur']);

        $this->get(route('tours.index'))
            ->assertOk()
            ->assertSee('Bilgisiz Tur')
            ->assertDontSee('çıkışlı')
            ->assertDontSee('Gidiş Dönüş');
    }

    public function test_fiyat_kisi_basi_yazar(): void
    {
        $this->tur(['title' => 'Fiyat Turu']);

        $this->get(route('tours.index'))
            ->assertOk()
            ->assertSee('kişi başı');
    }

    // ---- Acenta kaydedebiliyor mu ----

    public function test_acenta_ulasim_tipini_kaydedebilir(): void
    {
        $kategori = \App\Models\Category::create([
            'name' => 'Kültür', 'slug' => 'kultur-'.uniqid(), 'is_active' => true,
        ]);
        $user = \App\Models\User::create([
            'name' => 'Acenta',
            'email' => 'ulasim@example.com',
            'password' => bcrypt('sifre12345'),
            'role' => 'agency',
            'agency_id' => $this->agency->id,
        ]);

        $this->actingAs($user)->post(route('agency.tours.store'), [
            'title' => 'Ulaşım Testi Turu',
            'category_id' => $kategori->id,
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'duration_days' => 2,
            'transport_type' => 'otobus',
            'currency' => 'TRY',
            'requires_visa' => '0',
            'pricing_options' => [[
                'departure_dates' => [today()->addDays(20)->toDateString()],
                'packages' => [[
                    'hotel' => '3★ Test Otel',
                    'double_pp' => ['old' => '', 'new' => '4999'],
                ]],
            ]],
        ])->assertRedirect();

        $tur = Tour::where('title', 'Ulaşım Testi Turu')->first();
        $this->assertSame('otobus', $tur?->transport_type);
        $this->assertSame('Gidiş Dönüş Otobüs', $tur?->transport_label);
    }

    public function test_gecersiz_ulasim_tipi_reddedilir(): void
    {
        $kategori = \App\Models\Category::create([
            'name' => 'Kültür', 'slug' => 'kultur-'.uniqid(), 'is_active' => true,
        ]);
        $user = \App\Models\User::create([
            'name' => 'Acenta',
            'email' => 'gecersiz@example.com',
            'password' => bcrypt('sifre12345'),
            'role' => 'agency',
            'agency_id' => $this->agency->id,
        ]);

        $this->actingAs($user)->post(route('agency.tours.store'), [
            'title' => 'Geçersiz Ulaşım',
            'category_id' => $kategori->id,
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'duration_days' => 2,
            'transport_type' => 'balon',
            'currency' => 'TRY',
        ])->assertSessionHasErrors('transport_type');
    }
}
