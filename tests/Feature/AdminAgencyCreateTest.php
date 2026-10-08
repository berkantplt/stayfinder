<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Admin "Yeni Acenta Ekle" (2026-10 yeniden tasarım + isteğe bağlı parola).
 *
 * Parola verilirse: hash'lenir, password_set_at yazılır, bağlantı üretilmez ve
 * parola oturuma YAZILMAZ (B15 korunur). Verilmezse eski bağlantı akışı aynen sürer.
 */
class AdminAgencyCreateTest extends TestCase
{
    use RefreshDatabase;

    private const PAROLA = 'Kx7!mqT2pRv9wZ';

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_create_page_renders_in_the_admin_panel_layout_with_a_password_field(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.agencies.create'))
            ->assertOk()
            ->assertSee('Yeni Acenta Ekle')
            ->assertSee('panel-sidebar-module', false)      // admin kenar çubuğu artık var
            ->assertSee('name="password"', false)
            ->assertSee('data-sifre-hedef="password"', false) // göster/gizle düğmesi
            ->assertSee('Acenta Oluştur')
            ->assertDontSee('Şifre otomatik üretilip');       // eski, yanlış yardım metni
    }

    public function test_admin_can_set_the_agency_password_on_creation(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.agencies.store'), [
                'name' => 'Jolly Tur',
                'email' => 'yonetici@jollytur.com',
                'password' => self::PAROLA,
            ])
            ->assertRedirect(route('admin.agencies'))
            ->assertSessionHas('new_agency_credentials', function (array $creds) {
                return $creds['password_set'] === true
                    && ! array_key_exists('setup_url', $creds)          // bağlantı üretilmez
                    && ! in_array(self::PAROLA, $creds, true);           // parola oturuma yazılmaz
            });

        $user = User::where('email', 'yonetici@jollytur.com')->firstOrFail();
        $this->assertTrue(Hash::check(self::PAROLA, $user->password));
        $this->assertNotNull($user->password_set_at, 'Admin belirledi: hesap "parolası var" sayılmalı');
        $this->assertSame(User::ROLE_AGENCY, $user->role);

        $agency = Agency::where('name', 'Jolly Tur')->firstOrFail();
        $this->assertSame($agency->id, $user->agency_id);
        $this->assertSame(Agency::STATUS_APPROVED, $agency->approval_status);
    }

    public function test_agency_user_can_log_in_with_the_admin_set_password(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.agencies.store'), [
                'name' => 'Giriş Acenta',
                'email' => 'giris@example.com',
                'password' => self::PAROLA,
            ])
            ->assertRedirect(route('admin.agencies'));

        auth()->logout();

        $this->post(route('login.post'), ['email' => 'giris@example.com', 'password' => self::PAROLA])
            ->assertSessionDoesntHaveErrors();

        $this->assertAuthenticatedAs(User::where('email', 'giris@example.com')->firstOrFail());
    }

    public function test_without_a_password_the_setup_link_flow_is_unchanged(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.agencies.store'), [
                'name' => 'Bağlantılı Acenta',
                'email' => 'panel@baglantili.com',
            ])
            ->assertRedirect(route('admin.agencies'))
            ->assertSessionHas('new_agency_credentials', function (array $creds) {
                // Testte posta "array": bağlantı admine gösterilir
                return $creds['password_set'] === false && ! empty($creds['setup_url']);
            });

        $user = User::where('email', 'panel@baglantili.com')->firstOrFail();
        $this->assertNull($user->password_set_at);
    }

    public function test_a_short_password_is_rejected_and_nothing_is_created(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.agencies.store'), [
                'name' => 'Kısa Parola',
                'email' => 'kisa@example.com',
                'password' => '1234567',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseMissing('agencies', ['name' => 'Kısa Parola']);
        $this->assertDatabaseMissing('users', ['email' => 'kisa@example.com']);
    }

    public function test_list_page_tells_the_admin_the_password_is_not_shown(): void
    {
        $this->actingAs($this->admin())
            ->withSession(['new_agency_credentials' => [
                'agency' => 'Jolly Tur', 'email' => 'yonetici@jollytur.com', 'mailed' => false, 'password_set' => true,
            ]])
            ->get(route('admin.agencies'))
            ->assertOk()
            ->assertSee('Parolayı siz belirlediniz')
            ->assertDontSee('Posta hesabı tanımlı olmadığı için');
    }
}
