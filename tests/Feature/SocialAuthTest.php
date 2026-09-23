<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\SocialAccount;
use App\Models\Tour;
use App\Models\User;
use App\Services\Account\AccountDeletionService;
use App\Support\SocialIntent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

/**
 * Google / Apple ile giriş, kayıt ve hesap bağlama.
 *
 * Sağlayıcı çağrıları mock'lanır: testler ağa çıkmaz, Socialite'ın döndürdüğü
 * kullanıcıyı biz kurgularız.
 */
class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        config([
            'services.google.client_id' => 'test-google-id',
            'services.google.client_secret' => 'test-google-secret',
            'services.google.redirect' => 'https://turxtur.test/giris/google/callback',
            'services.apple.client_id' => 'com.turxtur.web',
            'services.apple.private_key' => 'test-apple-key',
            'services.apple.redirect' => 'https://turxtur.test/giris/apple/callback',
        ]);
    }

    /** Sağlayıcıdan dönen kullanıcıyı kurgular. */
    private function fakeSocialiteUser(string $id, ?string $email, ?string $name = 'Ali Veli', array $raw = ['email_verified' => true], string $provider = 'google'): void
    {
        $socialiteUser = Mockery::mock(\Laravel\Socialite\Contracts\User::class);
        $socialiteUser->shouldReceive('getId')->andReturn($id);
        $socialiteUser->shouldReceive('getName')->andReturn($name);
        $socialiteUser->shouldReceive('getEmail')->andReturn($email);
        $socialiteUser->shouldReceive('getAvatar')->andReturn('https://lh3.googleusercontent.com/avatar');
        $socialiteUser->shouldReceive('getRaw')->andReturn($raw);

        $driver = Mockery::mock();
        $driver->shouldReceive('user')->andReturn($socialiteUser);
        // Apple sürücüsü stateless()->cookieNonce() zinciriyle çağrılır
        $driver->shouldReceive('stateless')->andReturnSelf();
        $driver->shouldReceive('cookieNonce')->andReturnSelf();

        Socialite::shouldReceive('driver')->with($provider)->andReturn($driver);
    }

    /** Callback'e giderken taşınan bağlam çerezi. */
    private function withIntent(string $provider, ?string $next = null, ?int $favori = null, ?int $link = null): static
    {
        return $this->withCookie(SocialIntent::COOKIE, json_encode([
            'p' => $provider,
            'next' => $next,
            'favori' => $favori,
            'link' => $link,
        ]));
    }

    public function test_google_ile_yeni_ziyaretci_hesabi_acilir(): void
    {
        $this->fakeSocialiteUser('google-1', 'yeni@example.com');

        $response = $this->withIntent('google')->get(route('social.callback', 'google'));

        $response->assertRedirect(route('home'));

        $user = User::where('email', 'yeni@example.com')->firstOrFail();
        $this->assertTrue($user->isCustomer(), 'Sosyal kayıt daima ziyaretçi rolü açmalı');
        $this->assertNotNull($user->email_verified_at, 'Doğrulanmış adres tekrar doğrulatılmaz');
        $this->assertNull($user->password_set_at, 'Kullanıcı henüz kendi şifresini belirlemedi');
        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-1',
        ]);
    }

    public function test_ikinci_giriste_yeni_kullanici_olusmaz(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);
        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-2',
        ]);

        // Sağlayıcı e-postayı değiştirmiş olsa bile eşleştirme provider_user_id ile
        $this->fakeSocialiteUser('google-2', 'degisti@example.com');

        $this->withIntent('google')->get(route('social.callback', 'google'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
    }

    public function test_dogrulanmis_eposta_mevcut_hesaba_baglanir(): void
    {
        $user = User::factory()->create([
            'email' => 'mevcut@example.com',
            'role' => User::ROLE_VISITOR,
        ]);

        $this->fakeSocialiteUser('google-3', 'mevcut@example.com');

        $this->withIntent('google')->get(route('social.callback', 'google'));

        $this->assertAuthenticatedAs($user);
        $this->assertSame(1, User::count());
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_user_id' => 'google-3']);
    }

    public function test_dogrulanmamis_eposta_mevcut_hesaba_baglanmaz(): void
    {
        User::factory()->create(['email' => 'kurban@example.com', 'role' => User::ROLE_VISITOR]);

        $this->fakeSocialiteUser('google-4', 'kurban@example.com', raw: ['email_verified' => false]);

        $response = $this->withIntent('google')->get(route('social.callback', 'google'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_eposta_gelmezse_hesap_acilmaz(): void
    {
        $this->fakeSocialiteUser('google-5', null);

        $response = $this->withIntent('google')->get(route('social.callback', 'google'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('social');
        $this->assertGuest();
        $this->assertSame(0, User::count());
    }

    public function test_apple_email_verified_metni_dogrulanmis_sayilir(): void
    {
        // Apple kimlik jetonunda bu alanı çoğu kez "true" METNİ olarak gönderir
        $this->fakeSocialiteUser('apple-1', 'apple@example.com', raw: ['email_verified' => 'true'], provider: 'apple');

        $this->withIntent('apple')->post(route('social.callback', 'apple'));

        $user = User::where('email', 'apple@example.com')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_bekleyen_favori_ve_donus_yolu_korunur(): void
    {
        $agency = Agency::create([
            'name' => 'Sosyal Acenta',
            'slug' => 'sosyal-acenta',
            'email' => 'sosyal@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);
        $tour = Tour::create([
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

        $this->fakeSocialiteUser('google-6', 'kalp@example.com');

        $response = $this->withIntent('google', next: '/turlar/'.$tour->slug, favori: $tour->id)
            ->get(route('social.callback', 'google'));

        $response->assertRedirect('/turlar/'.$tour->slug);

        $user = User::where('email', 'kalp@example.com')->firstOrFail();
        $this->assertDatabaseHas('favorites', ['user_id' => $user->id, 'tour_id' => $tour->id]);
    }

    public function test_yapilandirilmamis_saglayici_404(): void
    {
        config(['services.google.client_id' => null]);

        $this->get(route('social.redirect', 'google'))->assertNotFound();
    }

    public function test_tanimsiz_saglayici_route_ile_reddedilir(): void
    {
        $this->get('/giris/facebook')->assertNotFound();
    }

    public function test_giris_sayfasinda_butonlar_gorunur(): void
    {
        $response = $this->get(route('login'));

        $response->assertSee('Google ile devam et');
        $response->assertSee('Apple ile devam et');
    }

    public function test_anahtari_olmayan_saglayicinin_butonu_basilmaz(): void
    {
        config(['services.apple.client_id' => null, 'services.apple.private_key' => null]);

        $response = $this->get(route('login'));

        $response->assertSee('Google ile devam et');
        $response->assertDontSee('Apple ile devam et');
    }

    public function test_sifresiz_kullanici_tek_baglantisini_kaldiramaz(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR, 'password_set_at' => null]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-7']);

        $response = $this->actingAs($user)->delete(route('social.destroy', 'google'));

        $response->assertSessionHasErrors('social');
        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_sifresi_olan_kullanici_baglantisini_kaldirabilir(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-8']);

        $this->actingAs($user)->delete(route('social.destroy', 'google'));

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_sifresiz_kullanici_mevcut_sifre_sorulmadan_sifre_belirler(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR, 'password_set_at' => null]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-9']);

        $response = $this->actingAs($user)->put(route('profile.password'), [
            'password' => 'yeni-sifre-123',
            'password_confirmation' => 'yeni-sifre-123',
        ]);

        $response->assertRedirect(route('profile.security'));
        $this->assertNotNull($user->fresh()->password_set_at);
        $this->assertTrue(Hash::check('yeni-sifre-123', $user->fresh()->password));
    }

    public function test_sosyal_hesabi_olmayan_kullanicida_mevcut_sifre_zorunlu_kalir(): void
    {
        // password_set_at boş olsa bile: sosyal hesabı yoksa mevcut şifre sorulur
        $user = User::factory()->create(['role' => User::ROLE_VISITOR, 'password_set_at' => null]);

        $response = $this->actingAs($user)->put(route('profile.password'), [
            'password' => 'yeni-sifre-123',
            'password_confirmation' => 'yeni-sifre-123',
        ]);

        $response->assertSessionHasErrors('current_password');
    }

    public function test_anonimlestirme_sosyal_baglantilari_siler(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-10']);

        app(AccountDeletionService::class)->anonymize($user);

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_sifresiz_kullanici_hesabini_eposta_onayiyla_silebilir(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VISITOR,
            'email' => 'silinecek@example.com',
            'password_set_at' => null,
        ]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-11']);

        $response = $this->actingAs($user)->post(route('profile.delete'), [
            'eposta_onay' => 'Silinecek@Example.com', // büyük/küçük harf farkı kabul edilir
            'onay' => '1',
        ]);

        $response->assertRedirect(route('login'));
        $this->assertNotNull($user->fresh()->deletion_requested_at);
    }

    public function test_yanlis_eposta_onayiyla_hesap_silinmez(): void
    {
        $user = User::factory()->create([
            'role' => User::ROLE_VISITOR,
            'email' => 'silinecek@example.com',
            'password_set_at' => null,
        ]);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'google', 'provider_user_id' => 'google-12']);

        $response = $this->actingAs($user)->post(route('profile.delete'), [
            'eposta_onay' => 'baska@example.com',
            'onay' => '1',
        ]);

        $response->assertSessionHasErrors('eposta_onay');
        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_profilden_hesap_baglanir(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->fakeSocialiteUser('google-20', 'baglanan@example.com');

        $response = $this->actingAs($user)
            ->withIntent('google', link: $user->id)
            ->get(route('social.callback', 'google'));

        $response->assertRedirect(route('profile.security'));
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_user_id' => 'google-20']);
    }

    public function test_apple_oturumsuz_donuste_baglamayi_cerezden_cozer(): void
    {
        // Apple çapraz site POST ile döner: oturum çerezi gelmez, bağlayan
        // kullanıcı yalnız SocialIntent çerezinden bilinir.
        $user = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->fakeSocialiteUser('apple-20', 'gizli@privaterelay.appleid.com', raw: ['email_verified' => 'true'], provider: 'apple');

        $response = $this->withIntent('apple', link: $user->id)
            ->post(route('social.callback', 'apple'));

        $response->assertRedirect(route('profile.security'));
        $this->assertDatabaseHas('social_accounts', ['user_id' => $user->id, 'provider_user_id' => 'apple-20']);
        // İkinci bir hesap AÇILMAMALI (bağlama modu kaybolsaydı relay adresiyle
        // eşleşme olmaz ve yeni kullanıcı yaratılırdı)
        $this->assertSame(1, User::count());
    }

    public function test_baskasina_bagli_hesap_ikinci_kullaniciya_baglanmaz(): void
    {
        $sahip = User::factory()->create(['role' => User::ROLE_VISITOR]);
        SocialAccount::create(['user_id' => $sahip->id, 'provider' => 'google', 'provider_user_id' => 'google-21']);

        $baskasi = User::factory()->create(['role' => User::ROLE_VISITOR]);

        $this->fakeSocialiteUser('google-21', 'sahip@example.com');

        $response = $this->actingAs($baskasi)
            ->withIntent('google', link: $baskasi->id)
            ->get(route('social.callback', 'google'));

        $response->assertSessionHasErrors('social');
        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertDatabaseHas('social_accounts', ['user_id' => $sahip->id]);
    }

    public function test_yonlendirme_baglami_cereze_yazar(): void
    {
        $driver = Mockery::mock();
        $driver->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($driver);

        $response = $this->get(route('social.redirect', ['provider' => 'google', 'next' => '/turlar/abc', 'favori' => 7]));

        $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
        $response->assertCookie(SocialIntent::COOKIE);
    }

    public function test_disaridan_gelen_next_adresi_reddedilir(): void
    {
        $this->fakeSocialiteUser('google-22', 'acik@example.com');

        // Açık yönlendirme denemesi: yalnız yerel yollar kabul edilir
        $response = $this->withIntent('google', next: 'https://kotu-site.example/phishing')
            ->get(route('social.callback', 'google'));

        $response->assertRedirect(route('home'));
    }
}
