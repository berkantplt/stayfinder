<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_is_throttled_after_five_attempts_for_same_email(): void
    {
        User::factory()->create(['email' => 'hedef@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), [
                'email' => 'hedef@example.com',
                'password' => 'yanlis-sifre-'.$i,
            ])->assertRedirect(); // başarısız giriş → back()
        }

        $this->post(route('login.post'), [
            'email' => 'hedef@example.com',
            'password' => 'yanlis-sifre-6',
        ])->assertStatus(429);
    }

    public function test_different_email_is_not_blocked_by_another_accounts_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('login.post'), [
                'email' => 'kurban@example.com',
                'password' => 'yanlis',
            ]);
        }

        // Farklı e-posta kendi sayacında — IP limiti (20) içinde hâlâ izinli
        $this->post(route('login.post'), [
            'email' => 'baska@example.com',
            'password' => 'yanlis',
        ])->assertRedirect();
    }

    public function test_register_is_throttled_after_six_attempts_per_ip(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post(route('register.post'), []); // validation hatası da sayaca girer
        }

        $this->post(route('register.post'), [])->assertStatus(429);
    }

    public function test_registration_rejects_password_shorter_than_eight_characters(): void
    {
        $this->post(route('register.post'), [
            'account_type' => 'visitor',
            'name' => 'Kısa Şifre',
            'email' => 'kisa@example.com',
            'password' => 'kisa123',
            'password_confirmation' => 'kisa123',
        ])->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('users', ['email' => 'kisa@example.com']);
    }

    public function test_registration_accepts_eight_character_password(): void
    {
        $this->post(route('register.post'), [
            'account_type' => 'visitor',
            'name' => 'Geçerli Şifre',
            'email' => 'gecerli8@example.com',
            'password' => 'tam8kark',
            'password_confirmation' => 'tam8kark',
        ])->assertRedirect(route('home'));

        $this->assertDatabaseHas('users', ['email' => 'gecerli8@example.com']);
    }
}
