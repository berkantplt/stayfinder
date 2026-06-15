<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_forgot_password_page_renders(): void
    {
        $this->get(route('password.request'))
            ->assertOk()
            ->assertSee('Şifremi Unuttum');
    }

    public function test_login_page_links_to_forgot_password(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee(route('password.request'), false);
    }

    public function test_reset_link_is_sent_to_existing_user(): void
    {
        Notification::fake();

        $user = User::factory()->create(['email' => 'sifirla@example.com']);

        $this->post(route('password.email'), ['email' => 'sifirla@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertSentTo($user, ResetPasswordNotification::class);
        $this->assertDatabaseHas('password_reset_tokens', ['email' => 'sifirla@example.com']);
    }

    public function test_unknown_email_gets_same_response_without_sending(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'yok@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'yok@example.com']);
    }

    public function test_password_can_be_reset_with_valid_token(): void
    {
        $user = User::factory()->create(['email' => 'gecerli@example.com']);
        $token = Password::createToken($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee('Yeni Şifre Belirle');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'yeni-sifre-123',
            'password_confirmation' => 'yeni-sifre-123',
        ])->assertRedirect(route('login'));

        $this->assertTrue(Hash::check('yeni-sifre-123', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
    }

    public function test_invalid_token_does_not_reset_password(): void
    {
        $user = User::factory()->create(['email' => 'sahte@example.com']);
        $oldHash = $user->password;

        $this->post(route('password.update'), [
            'token' => 'gecersiz-token',
            'email' => $user->email,
            'password' => 'yeni-sifre-123',
            'password_confirmation' => 'yeni-sifre-123',
        ])->assertSessionHasErrors('email');

        $this->assertSame($oldHash, $user->fresh()->password);
    }

    public function test_reset_notification_contains_turkish_reset_url(): void
    {
        $user = User::factory()->create();
        $token = Password::createToken($user);

        $mail = (new ResetPasswordNotification($token))->toMail($user);

        $this->assertSame('Şifre Sıfırlama — StayFinder', $mail->subject);
        $this->assertStringContainsString('/sifre-sifirla/'.$token, $mail->actionUrl);
        $this->assertStringContainsString('email='.urlencode($user->email), $mail->actionUrl);
    }
}
