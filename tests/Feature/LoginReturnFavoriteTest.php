<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Giriş / kayıt sonrası bağlam: ziyaretçi kalbe basıp girişe gittiğinde
 * aynı tura döner ve favorisi tamamlanır; yalnız yerel yol kabul edilir.
 */
class LoginReturnFavoriteTest extends TestCase
{
    use RefreshDatabase;

    private Tour $tour;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $agency = Agency::create([
            'name' => 'Dönüş Acenta',
            'slug' => 'donus-acenta',
            'email' => 'donus@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
        $this->tour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Kapadokya Turu',
            'destination' => 'Kapadokya',
            'description' => 'Test',
            'price' => 4499,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(30),
            'is_active' => true,
        ]);
    }

    private function makeVisitor(): User
    {
        return User::create([
            'name' => 'Ziyaretçi',
            'email' => 'ziyaretci@example.com',
            'password' => Hash::make('sifre1234'),
            'role' => 'visitor',
        ]);
    }

    public function test_giris_sonrasi_ayni_tura_doner_ve_favori_eklenir(): void
    {
        $user = $this->makeVisitor();
        $next = '/turlar/'.$this->tour->slug;

        $response = $this->post(route('login.post'), [
            'email' => $user->email,
            'password' => 'sifre1234',
            'next' => $next,
            'favori' => $this->tour->id,
        ]);

        $response->assertRedirect($next);
        $this->assertTrue($user->fresh()->hasFavorited($this->tour));
    }

    public function test_giris_sayfasi_gizli_alanlari_tasir(): void
    {
        $this->get(route('login', ['next' => '/turlar/kapadokya-turu', 'favori' => $this->tour->id]))
            ->assertOk()
            ->assertSee('name="next" value="/turlar/kapadokya-turu"', false)
            ->assertSee('name="favori" value="'.$this->tour->id.'"', false);
    }

    public function test_dis_adres_ve_protokol_goreli_next_reddedilir(): void
    {
        $user = $this->makeVisitor();

        $this->post(route('login.post'), [
            'email' => $user->email, 'password' => 'sifre1234', 'next' => 'https://kotu.example',
        ])->assertRedirect(route('home'));

        $this->post(route('login.post'), [
            'email' => $user->email, 'password' => 'sifre1234', 'next' => '//kotu.example/x',
        ])->assertRedirect(route('home'));

        $this->get(route('login', ['next' => 'https://kotu.example']))
            ->assertOk()
            ->assertSee('name="next" value=""', false);
    }

    public function test_gorunmez_tur_favoriye_eklenmez_ama_yonlendirme_calisir(): void
    {
        $user = $this->makeVisitor();
        $this->tour->update(['is_active' => false]);

        $this->post(route('login.post'), [
            'email' => $user->email, 'password' => 'sifre1234', 'next' => '/turlar', 'favori' => $this->tour->id,
        ])->assertRedirect('/turlar');

        $this->assertFalse($user->fresh()->hasFavorited($this->tour));
    }

    public function test_kayit_sonrasi_da_tura_doner_ve_favori_eklenir(): void
    {
        $next = '/turlar/'.$this->tour->slug;

        $this->post(route('register.post'), [
            'account_type' => 'visitor',
            'name' => 'Yeni Üye',
            'email' => 'yeni@example.com',
            'password' => 'sifre1234',
            'password_confirmation' => 'sifre1234',
            'next' => $next,
            'favori' => $this->tour->id,
        ])->assertRedirect($next);

        $this->assertTrue(User::where('email', 'yeni@example.com')->first()->hasFavorited($this->tour));
    }
}
