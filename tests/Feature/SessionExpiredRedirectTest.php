<?php

namespace Tests\Feature;

use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * 419 (oturum/CSRF zaman aşımı) artık soğuk hata sayfası değil: normal
 * isteklerde mesajla giriş sayfasına yönlendirir, AJAX'ta 419 JSON döner.
 */
class SessionExpiredRedirectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // CSRF middleware'i test ortamında kendini atladığından, handler'ı
        // istisnayı bizzat fırlatan geçici bir route ile tetikliyoruz.
        Route::post('/_test-419', function () {
            throw new TokenMismatchException('CSRF token mismatch.');
        })->middleware('web');
    }

    public function test_expired_session_redirects_to_login_with_message(): void
    {
        $this->post('/_test-419')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('session');
    }

    public function test_ajax_request_still_gets_419_json(): void
    {
        $this->postJson('/_test-419')
            ->assertStatus(419)
            ->assertJsonPath('message', 'Oturum süresi doldu. Sayfayı yenileyip tekrar deneyin.');
    }
}
