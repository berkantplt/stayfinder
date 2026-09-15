<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\EmailChangeVerificationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * D5 — E-posta değişikliği: yeni adres onaylanana kadar bekler (eski adres
 * geçerli), yeni adrese imzalı 60 dk bağlantı gider, onayda email_verified_at
 * yenilenir; bozuk imza/hash 403; iptal ve yeniden gönderme.
 */
class ProfileEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, string $email): array
    {
        return ['name' => $user->name, 'email' => $email];
    }

    public function test_eposta_degisikligi_onay_bekler_ve_yeni_adrese_baglanti_gider(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'eski@example.com', 'role' => User::ROLE_VISITOR]);

        $this->actingAs($user)->put(route('profile.update'), $this->payload($user, 'Yeni@Example.com'))
            ->assertRedirect(route('profile.edit'));

        $user->refresh();
        $this->assertSame('eski@example.com', $user->email, 'eski adres onaya kadar geçerli kalır');
        $this->assertSame('yeni@example.com', $user->pending_email);
        $this->assertNotNull($user->pending_email_requested_at);

        Notification::assertSentOnDemand(EmailChangeVerificationNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'yeni@example.com');
    }

    public function test_imzali_baglanti_adresi_tasir_ve_dogrular(): void
    {
        $user = User::factory()->create(['email' => 'eski@example.com', 'email_verified_at' => null, 'role' => User::ROLE_VISITOR]);
        $user->forceFill(['pending_email' => 'yeni@example.com', 'pending_email_requested_at' => now()])->save();

        $url = URL::temporarySignedRoute('profile.email.verify', now()->addMinutes(60), ['user' => $user->id, 'hash' => sha1('yeni@example.com')]);

        // Oturumsuz (başka cihaz) açılış: login sayfasına yönlendirir, adres değişmiştir
        $this->get($url)->assertRedirect(route('login'));

        $user->refresh();
        $this->assertSame('yeni@example.com', $user->email);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNull($user->pending_email);
    }

    public function test_bozuk_hash_ve_suresi_gecmis_baglanti_reddedilir(): void
    {
        $user = User::factory()->create(['email' => 'eski@example.com', 'role' => User::ROLE_VISITOR]);
        $user->forceFill(['pending_email' => 'yeni@example.com', 'pending_email_requested_at' => now()])->save();

        $bozuk = URL::temporarySignedRoute('profile.email.verify', now()->addMinutes(60), ['user' => $user->id, 'hash' => sha1('baska@example.com')]);
        $this->get($bozuk)->assertForbidden();

        $gecerli = URL::temporarySignedRoute('profile.email.verify', now()->addMinutes(60), ['user' => $user->id, 'hash' => sha1('yeni@example.com')]);
        $this->travel(61)->minutes();
        $this->get($gecerli)->assertForbidden();

        $this->assertSame('eski@example.com', $user->fresh()->email);
    }

    public function test_iptal_ve_yeniden_gonderme(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'eski@example.com', 'role' => User::ROLE_VISITOR]);
        $user->forceFill(['pending_email' => 'yeni@example.com', 'pending_email_requested_at' => now()])->save();

        $this->actingAs($user)->post(route('profile.email.resend'))->assertRedirect(route('profile.edit'));
        Notification::assertSentOnDemandTimes(EmailChangeVerificationNotification::class, 1);

        $this->actingAs($user)->get(route('profile.edit'))->assertOk()->assertSee('yeni@example.com')->assertSee('onay bekliyor');

        $this->actingAs($user)->delete(route('profile.email.cancel'))->assertRedirect(route('profile.edit'));
        $this->assertNull($user->fresh()->pending_email);
    }

    public function test_ayni_adres_bekleyen_degisiklik_yaratmaz(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'eski@example.com', 'role' => User::ROLE_VISITOR]);

        $this->actingAs($user)->put(route('profile.update'), $this->payload($user, 'eski@example.com'))
            ->assertRedirect(route('profile.show'));

        $this->assertNull($user->fresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_baska_hesaba_ait_adres_onayda_reddedilir(): void
    {
        User::factory()->create(['email' => 'yeni@example.com']);
        $user = User::factory()->create(['email' => 'eski@example.com', 'role' => User::ROLE_VISITOR]);
        $user->forceFill(['pending_email' => 'yeni@example.com', 'pending_email_requested_at' => now()])->save();

        $url = URL::temporarySignedRoute('profile.email.verify', now()->addMinutes(60), ['user' => $user->id, 'hash' => sha1('yeni@example.com')]);
        $this->get($url)->assertRedirect(route('login'))->assertSessionHasErrors('email');

        $this->assertSame('eski@example.com', $user->fresh()->email);
    }
}
