<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D6 devamı — AuthenticateSession oturumdaki şifre izini kullanıcıyla
 * karşılaştırır. Çıkışta oturum geçersiz kılınmazsa eski kullanıcının izi
 * kalır ve aynı tarayıcıda giriş yapan ikinci kullanıcı ilk istekte düşürülür.
 */
class LogoutSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_cikis_sonrasi_ayni_tarayicida_baska_kullanici_giris_yapabilir(): void
    {
        $ali = User::factory()->create(['email' => 'ali@example.com', 'password' => Hash::make('ali-sifre-123'), 'role' => User::ROLE_VISITOR]);
        $ayse = User::factory()->create(['email' => 'ayse@example.com', 'password' => Hash::make('ayse-sifre-123'), 'role' => User::ROLE_VISITOR]);

        $this->post(route('login.post'), ['email' => 'ali@example.com', 'password' => 'ali-sifre-123']);
        $this->get(route('profile.show'))->assertOk(); // iz oturuma yazılır
        $this->assertAuthenticatedAs($ali);

        $this->post(route('logout'))->assertRedirect();
        $this->assertGuest();

        $this->post(route('login.post'), ['email' => 'ayse@example.com', 'password' => 'ayse-sifre-123']);
        $this->get(route('profile.show'))->assertOk();
        $this->assertAuthenticatedAs($ayse);
    }
}
