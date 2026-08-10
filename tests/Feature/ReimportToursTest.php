<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\MissingTransportNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Toplu yeniden içe aktarma ve eksik-ulaşım bildirimi.
 *
 * Komut ÜZERİNE YAZDIĞI için buradaki en önemli bekçi, boş dönen alanın mevcut
 * iyi veriyi ezmemesi: içe aktarma yarım başarılı olduğunda açıklamayı silmek
 * hiç çalışmamasından kötü.
 */
class ReimportToursTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->agency = Agency::create([
            'name' => 'Aktarım Acenta',
            'slug' => 'aktarim-acenta',
            'email' => 'aktarim@example.com',
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
            'title' => 'Eski Başlık',
            'destination' => 'Kapadokya',
            'description' => 'Elle yazılmış açıklama',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'tour_url' => 'https://1.1.1.1/tur',
            'is_active' => true,
        ], $ek));
    }

    private function fakeKaynak(array $llm, string $html = '<html><body><p>Tur sayfası</p></body></html>'): void
    {
        Http::fake(['*' => Http::response($html, 200, ['Content-Type' => 'text/html'])]);
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => json_encode($llm)],
                ]],
            ]),
        ]);
    }

    public function test_ulasim_bilgisi_yazilir(): void
    {
        $tur = $this->tur();
        $this->fakeKaynak(['title' => 'Yeni Başlık', 'transport_type' => 'otobus']);

        $this->artisan('app:reimport-tours', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('otobus', $tur->fresh()->transport_type);
    }

    public function test_bos_donen_alan_mevcut_veriyi_ezmez(): void
    {
        $tur = $this->tur(['description' => 'Elle yazılmış açıklama']);
        // İçe aktarma açıklamayı bulamadı (null döndü)
        $this->fakeKaynak(['title' => 'Yeni Başlık', 'transport_type' => 'ucak', 'description' => null]);

        $this->artisan('app:reimport-tours', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('Elle yazılmış açıklama', $tur->fresh()->description);
        $this->assertSame('ucak', $tur->fresh()->transport_type);
    }

    public function test_kuru_calisma_hicbir_sey_yazmaz(): void
    {
        $tur = $this->tur();
        $this->fakeKaynak(['title' => 'Yeni Başlık', 'transport_type' => 'otobus']);

        $this->artisan('app:reimport-tours', ['--dry' => true, '--sleep' => 0])->assertSuccessful();

        $this->assertNull($tur->fresh()->transport_type);
        $this->assertSame('Eski Başlık', $tur->fresh()->title);
    }

    public function test_ulasimi_olan_tur_atlanir(): void
    {
        $tur = $this->tur(['transport_type' => 'otobus', 'title' => 'Dokunulmamalı']);
        $this->fakeKaynak(['title' => 'Yeni Başlık', 'transport_type' => 'ucak']);

        $this->artisan('app:reimport-tours', ['--sleep' => 0])->assertSuccessful();

        // Yeniden çalıştırılabilirlik: zaten dolu olan tur tekrar çekilmez.
        $this->assertSame('Dokunulmamalı', $tur->fresh()->title);
        $this->assertSame('otobus', $tur->fresh()->transport_type);
    }

    public function test_force_ile_ulasimi_olan_tur_da_islenir(): void
    {
        $tur = $this->tur(['transport_type' => 'otobus']);
        $this->fakeKaynak(['title' => 'Yeni Başlık', 'transport_type' => 'ucak']);

        $this->artisan('app:reimport-tours', ['--force' => true, '--sleep' => 0])->assertSuccessful();

        $this->assertSame('ucak', $tur->fresh()->transport_type);
    }

    public function test_url_siz_tur_hic_islenmez(): void
    {
        $tur = $this->tur(['tour_url' => null, 'title' => 'URL yok']);
        $this->fakeKaynak(['title' => 'Yeni Başlık', 'transport_type' => 'otobus']);

        $this->artisan('app:reimport-tours', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('URL yok', $tur->fresh()->title);
        $this->assertNull($tur->fresh()->transport_type);
    }

    public function test_ice_aktarma_hatasi_turu_bozmaz(): void
    {
        $tur = $this->tur(['title' => 'Bozulmamalı']);
        Http::fake(['*' => Http::response('sayfa yok', 404)]);

        $this->artisan('app:reimport-tours', ['--sleep' => 0])->assertSuccessful();

        $this->assertSame('Bozulmamalı', $tur->fresh()->title);
        $this->assertNull($tur->fresh()->transport_type);
    }

    // ---- Bildirim ----

    public function test_ulasimi_eksik_turu_olan_acentaya_bildirim_gider(): void
    {
        Notification::fake();
        $this->tur(['tour_url' => null]);
        $user = User::create([
            'name' => 'Acenta', 'email' => 'bildirim@example.com',
            'password' => bcrypt('sifre12345'), 'role' => 'agency',
            'agency_id' => $this->agency->id,
        ]);

        $this->artisan('app:notify-missing-transport')->assertSuccessful();

        Notification::assertSentTo($user, MissingTransportNotification::class);
    }

    public function test_ulasimi_tam_acentaya_bildirim_gitmez(): void
    {
        Notification::fake();
        $this->tur(['transport_type' => 'otobus']);
        $user = User::create([
            'name' => 'Acenta', 'email' => 'tam@example.com',
            'password' => bcrypt('sifre12345'), 'role' => 'agency',
            'agency_id' => $this->agency->id,
        ]);

        $this->artisan('app:notify-missing-transport')->assertSuccessful();

        Notification::assertNotSentTo($user, MissingTransportNotification::class);
    }

    public function test_bildirim_acenta_basina_tek_tanedir(): void
    {
        Notification::fake();
        $this->tur(['tour_url' => null, 'title' => 'Tur A']);
        $this->tur(['tour_url' => null, 'title' => 'Tur B']);
        $this->tur(['tour_url' => null, 'title' => 'Tur C']);
        $user = User::create([
            'name' => 'Acenta', 'email' => 'tek@example.com',
            'password' => bcrypt('sifre12345'), 'role' => 'agency',
            'agency_id' => $this->agency->id,
        ]);

        $this->artisan('app:notify-missing-transport')->assertSuccessful();

        // 3 tur eksik ama 1 bildirim — panel bildirimle dolmasın.
        Notification::assertSentToTimes($user, MissingTransportNotification::class, 1);
    }
}
