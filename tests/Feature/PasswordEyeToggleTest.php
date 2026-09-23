<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Şifre göster/gizle düğmesi: her şifre alanının id'sine bağlı bir göz düğmesi
 * basılır, davranış layout'taki ortak [data-sifre-hedef] dinleyicisindedir.
 */
class PasswordEyeToggleTest extends TestCase
{
    use RefreshDatabase;

    private function assertHerSifreAlanindaGozVar(string $html, int $beklenenAlan): void
    {
        preg_match_all('/<input[^>]*type="password"[^>]*>/', $html, $inputs);
        $this->assertCount($beklenenAlan, $inputs[0]);

        foreach ($inputs[0] as $input) {
            $this->assertMatchesRegularExpression('/\sid="([^"]+)"/', $input, 'Şifre alanının id\'si yok: '.$input);
            preg_match('/\sid="([^"]+)"/', $input, $m);
            $id = $m[1];
            $this->assertSame(1, substr_count($html, ' id="'.$id.'"'), "id tekil değil: {$id}");
            $this->assertSame(1, substr_count($html, 'data-sifre-hedef="'.$id.'"'), "Göz düğmesi yok: {$id}");
        }

        $this->assertStringContainsString('class="goz-ac"', $html);
        $this->assertStringContainsString("closest('[data-sifre-hedef]')", $html);
    }

    public function test_giris_sayfasinda_goz_var(): void
    {
        $this->assertHerSifreAlanindaGozVar($this->get('/giris')->assertOk()->getContent(), 1);
    }

    public function test_kayit_sayfasinda_iki_alanda_goz_var(): void
    {
        $this->assertHerSifreAlanindaGozVar($this->get('/kayit')->assertOk()->getContent(), 2);
    }

    public function test_sifre_sifirlama_sayfasinda_goz_var(): void
    {
        $html = $this->get('/sifre-sifirla/ornek-token?email=a%40b.com')->assertOk()->getContent();
        $this->assertHerSifreAlanindaGozVar($html, 2);
    }

    public function test_hesap_guvenligi_sayfasinda_dort_alanda_goz_var(): void
    {
        // Müşteri (ziyaretçi rolü): mevcut + yeni + tekrar + hesap silme onayı
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);
        $html = $this->actingAs($user)->get(route('profile.security'))->assertOk()->getContent();
        $this->assertHerSifreAlanindaGozVar($html, 4);
    }
}
