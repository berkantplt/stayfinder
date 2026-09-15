<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D6 — Şifre değişince diğer cihazlardaki oturumlar kapanır: logoutOtherDevices
 * + oturum kimliği yenileme + AuthenticateSession middleware'i web grubunda.
 */
class ProfilePasswordChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_sifre_degisince_diger_oturumlar_dusurulur(): void
    {
        $user = User::factory()->create(['password' => Hash::make('eski-sifre-123')]);

        // AuthenticateSession ilk istekte eski şifrenin izini oturuma yazar
        $this->actingAs($user)->get(route('profile.edit'))->assertOk();
        $before = session('password_hash_web');
        $this->assertNotNull($before);

        $response = $this->actingAs($user)->put(route('profile.password'), [
            'current_password' => 'eski-sifre-123',
            'password' => 'yeni-sifre-456',
            'password_confirmation' => 'yeni-sifre-456',
        ]);

        $response->assertRedirect(route('profile.security'))
            ->assertSessionHas('success', 'Şifreniz güncellendi; diğer cihazlardaki oturumlarınız kapatıldı.');

        $this->assertTrue(Hash::check('yeni-sifre-456', $user->fresh()->password));
        // Bu oturumdaki iz yeni şifreye göre yenilenir; eski izi taşıyan diğer
        // oturumlar AuthenticateSession tarafından bir sonraki istekte düşürülür.
        $after = session('password_hash_web');
        $this->assertNotNull($after);
        $this->assertNotSame($before, $after);
    }

    public function test_yanlis_mevcut_sifre_reddedilir(): void
    {
        $user = User::factory()->create(['password' => Hash::make('eski-sifre-123')]);

        $this->actingAs($user)->from(route('profile.edit'))->put(route('profile.password'), [
            'current_password' => 'yanlis',
            'password' => 'yeni-sifre-456',
            'password_confirmation' => 'yeni-sifre-456',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('eski-sifre-123', $user->fresh()->password));
    }

    public function test_authenticate_session_middleware_web_grubunda(): void
    {
        $web = app(\Illuminate\Contracts\Http\Kernel::class)->getMiddlewareGroups()['web'];

        $this->assertContains(AuthenticateSession::class, $web);
    }
}
