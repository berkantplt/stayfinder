<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bireysel kayıt (/kayit) ile acenta başvurusu (/acenta-kayit) ayrı sayfa ve ayrı uç.
 * Eskiden tek formda Bireysel/Acenta anahtarı vardı; footer "Acenta Ol"
 * /kayit?type=agency'ye düşüyordu (2026-10-08'de ayrıldı).
 */
class KayitAyrimiTest extends TestCase
{
    use RefreshDatabase;

    public function test_acenta_kayit_sayfasi_kendi_formuyla_acilir(): void
    {
        $html = $this->get('/acenta-kayit')->assertOk()->getContent();

        $this->assertStringContainsString('Acenta başvurusu', $html);
        $this->assertStringContainsString('name="agency_name"', $html);
        $this->assertStringContainsString('action="'.route('agency.register.post').'"', $html);
        // Hesap türü anahtarı ve bireysel kayıt ucu bu sayfada yok
        $this->assertStringNotContainsString('data-account-type', $html);
        $this->assertStringNotContainsString('action="'.route('register.post').'"', $html);
        // Sosyal kayıt yalnız bireysel hesap içindir
        $this->assertStringNotContainsString('data-social-auth', $html);
    }

    public function test_bireysel_kayit_sayfasinda_acenta_alani_yok(): void
    {
        $html = $this->get('/kayit')->assertOk()->getContent();

        $this->assertStringContainsString('Hesabını oluştur', $html);
        $this->assertStringContainsString('action="'.route('register.post').'"', $html);
        $this->assertStringNotContainsString('name="agency_name"', $html);
        $this->assertStringNotContainsString('data-account-type', $html);
        // Acentaya geçiş bağlantısı var
        $this->assertStringContainsString('href="'.route('agency.register').'"', $html);
    }

    public function test_footer_acenta_ol_acenta_kayit_sayfasina_gider(): void
    {
        $html = $this->get('/giris')->assertOk()->getContent();

        $this->assertSame('/acenta-kayit', parse_url(route('agency.register'), PHP_URL_PATH));
        $this->assertStringContainsString('href="'.route('agency.register').'" class="ftr-btn ftr-btn-primary"', $html);
        $this->assertStringContainsString('Acenta Ol', $html);
    }

    public function test_eski_type_agency_baglantisi_acenta_sayfasina_yonlenir(): void
    {
        $this->get('/kayit?type=agency')->assertRedirect(route('agency.register'));
    }

    public function test_bireysel_kayit_ucu_account_type_agency_gonderilse_de_acenta_acmaz(): void
    {
        $response = $this->post(route('register.post'), [
            'account_type' => 'agency',
            'agency_name' => 'Sizma Travel',
            'name' => 'Ali Veli',
            'email' => 'ali@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect();
        $this->assertSame(0, Agency::count());
        $user = User::firstOrFail();
        $this->assertSame(User::ROLE_VISITOR, $user->role);
        $this->assertNull($user->agency_id);
        $this->assertAuthenticatedAs($user);
    }

    public function test_acenta_ucu_eksik_acenta_adini_reddeder(): void
    {
        $response = $this->from('/acenta-kayit')->post(route('agency.register.post'), [
            'name' => 'Ali Veli',
            'email' => 'ali@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect('/acenta-kayit');
        $response->assertSessionHasErrors('agency_name');
        $this->assertSame(0, Agency::count());
        $this->assertSame(0, User::count());
    }
}
