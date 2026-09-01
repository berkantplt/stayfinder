<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Süre gösterimi: Türk tur sayfalarının standardı "7 gece 8 gün".
 *
 * duration_nights sonradan eklendi ve mevcut kayıtlarda NULL — o durumda gün-1
 * türetilir. Gerçek gece bilgisi geldiğinde (import veya acenta) onu ezer, çünkü
 * "9 gün / 7 gece otel + uçak" gibi turlarda gün-1 yanlış olur.
 */
class TourDurationLabelTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->agency = Agency::create([
            'name' => 'Süre Acenta',
            'slug' => 'sure-acenta',
            'email' => 'sure@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
    }

    private function tur(int $gun, ?int $gece = null): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'title' => 'Süre Turu '.uniqid(),
            'destination' => 'Kapadokya',
            'description' => 'Test',
            'price' => 10000,
            'currency' => 'TRY',
            'duration_days' => $gun,
            'duration_nights' => $gece,
            'departure_date' => today()->addDays(30),
            'is_active' => true,
        ]);
    }

    public function test_gece_bos_ise_gun_eksi_bir_turetilir(): void
    {
        $this->assertSame('1 gece 2 gün', $this->tur(2)->duration_label);
        $this->assertSame('4 gece 5 gün', $this->tur(5)->duration_label);
        $this->assertSame('7 gece 8 gün', $this->tur(8)->duration_label);
    }

    public function test_gercek_gece_bilgisi_turetmeyi_ezer(): void
    {
        // "9 gün / 7 gece otel + uçak" vakası: gün-1 = 8 yanlış olurdu.
        $this->assertSame('7 gece 9 gün', $this->tur(9, 7)->duration_label);
    }

    public function test_tek_gunluk_tur_gunubirlik_yazar(): void
    {
        $this->assertSame('Günübirlik', $this->tur(1)->duration_label);
        $this->assertSame('Günübirlik', $this->tur(1, 0)->duration_label);
    }

    public function test_konaklamasiz_cok_gunluk_tur_gunubirlik_demez(): void
    {
        // 2 gün süren ama konaklaması olmayan tur "Günübirlik" değildir.
        $this->assertSame('2 gün', $this->tur(2, 0)->duration_label);
    }

    public function test_kart_listesinde_gece_gun_gorunur(): void
    {
        $tur = $this->tur(8, 7);

        $this->get(route('tours.index'))
            ->assertOk()
            ->assertSee('7 gece 8 gün');
    }

    public function test_tur_detayinda_gece_gun_gorunur(): void
    {
        $tur = $this->tur(4);

        $this->get(route('tours.show', $tur))
            ->assertOk()
            ->assertSee('3 gece 4 gün');
    }

    public function test_acenta_gece_alanini_kaydedebilir(): void
    {
        $kategori = \App\Models\Category::create([
            'name' => 'Kültür', 'slug' => 'kultur-'.uniqid(), 'is_active' => true,
        ]);
        $user = \App\Models\User::create([
            'name' => 'Acenta Kullanıcı',
            'email' => 'acenta-sure@example.com',
            'password' => bcrypt('sifre12345'),
            'role' => 'agency',
            'agency_id' => $this->agency->id,
        ]);

        $this->actingAs($user)->post(route('agency.tours.store'), [
            'title' => 'Gece Testi Turu',
            'category_id' => $kategori->id,
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'duration_days' => 8,
            'duration_nights' => 7,
            'currency' => 'TRY',
            'requires_visa' => '0',
            'pricing_options' => [[
                'departure_dates' => [today()->addDays(20)->toDateString()],
                'packages' => [[
                    'hotel' => '4★ Test Otel',
                    'double_pp' => ['old' => '', 'new' => '15000'],
                ]],
            ]],
        ])->assertRedirect();

        $this->assertSame(7, Tour::where('title', 'Gece Testi Turu')->first()?->duration_nights);
    }
}
