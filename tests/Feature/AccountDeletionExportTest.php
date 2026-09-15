<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * D4 — KVKK: verilerimi indir (JSON), hesabımı sil (şifre onayı + 30 gün
 * bekleme + giriş iptal eder + anonimleştirme komutu), admin komutu.
 */
class AccountDeletionExportTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();
        $this->agency = Agency::create([
            'name' => 'KVKK Acenta', 'slug' => 'kvkk-acenta', 'email' => 'kvkk@example.com',
            'is_active' => true, 'approval_status' => Agency::STATUS_APPROVED, 'approved_at' => now(), 'legacy_category_access' => true,
        ]);
    }

    private function musteri(array $attrs = []): User
    {
        return User::factory()->create(array_merge(['role' => User::ROLE_VISITOR, 'password' => Hash::make('sifre-123456')], $attrs));
    }

    private function tur(): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id, 'title' => 'Veri Turu', 'slug' => 'veri-turu-'.uniqid(), 'destination' => 'Kapadokya',
            'description' => 'x', 'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3,
            'departure_date' => today()->addDays(20), 'is_active' => true,
        ]);
    }

    public function test_verilerim_json_olarak_iner(): void
    {
        $user = $this->musteri(['name' => 'Veri Sahibi', 'email' => 'veri@example.com']);
        $tour = $this->tur();
        $user->favoriteTours()->attach($tour->id);
        Review::create(['user_id' => $user->id, 'tour_id' => $tour->id, 'rating' => 5, 'comment' => 'Muhteşem bir deneyimdi.']);

        $response = $this->actingAs($user)->get(route('profile.data-export'))->assertOk();
        $response->assertHeader('Content-Disposition', 'attachment; filename="turxtur-verilerim-'.now()->format('Y-m-d').'.json"');
        $response->assertJsonPath('profil.email', 'veri@example.com')
            ->assertJsonPath('favoriler.0.tur', 'Veri Turu')
            ->assertJsonPath('yorumlar.0.yorum', 'Muhteşem bir deneyimdi.')
            ->assertJsonStructure(['kayitli_aramalar', 'kupon_kullanimlari', 'bildirimler', 'ai_aramalari', 'kesif_rehberleri']);
    }

    public function test_silme_talebi_sifre_ister_oturumu_kapatir(): void
    {
        $user = $this->musteri();

        $this->actingAs($user)->from(route('profile.security'))
            ->post(route('profile.delete'), ['password' => 'yanlis', 'onay' => '1'])
            ->assertSessionHasErrors('password');
        $this->assertNull($user->fresh()->deletion_requested_at);

        $this->actingAs($user)->post(route('profile.delete'), ['password' => 'sifre-123456', 'onay' => '1'])
            ->assertRedirect(route('login'))
            ->assertSessionHas('success');

        $this->assertGuest();
        $this->assertNotNull($user->fresh()->deletion_requested_at);
    }

    public function test_bekleme_suresinde_giris_talebi_iptal_eder(): void
    {
        $user = $this->musteri(['email' => 'geri@example.com']);
        $user->forceFill(['deletion_requested_at' => now()->subDays(5)])->save();

        $this->post(route('login.post'), ['email' => 'geri@example.com', 'password' => 'sifre-123456'])
            ->assertSessionHas('success');

        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->deletion_requested_at);
    }

    public function test_acenta_kullanicisi_bu_yoldan_silinemez(): void
    {
        $agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $this->agency->id, 'password' => Hash::make('sifre-123456')]);

        $this->actingAs($agencyUser)->post(route('profile.delete'), ['password' => 'sifre-123456', 'onay' => '1'])
            ->assertForbidden();
    }

    public function test_purge_komutu_30_gunu_dolanlari_anonimlestirir(): void
    {
        $tour = $this->tur();
        $dolmus = $this->musteri(['name' => 'Ali Veli', 'email' => 'ali@example.com', 'phone' => '05551112233', 'city' => 'İstanbul']);
        $dolmus->forceFill(['deletion_requested_at' => now()->subDays(31)])->save();
        $dolmus->favoriteTours()->attach($tour->id);
        Review::create(['user_id' => $dolmus->id, 'tour_id' => $tour->id, 'rating' => 3, 'comment' => 'Kişisel yorum.']);

        $taze = $this->musteri(['email' => 'taze@example.com']);
        $taze->forceFill(['deletion_requested_at' => now()->subDays(10)])->save();

        $this->artisan('users:purge-deleted')->assertSuccessful();

        $dolmus->refresh();
        $this->assertSame('Silinmiş Kullanıcı', $dolmus->name);
        $this->assertSame('silinmis-'.$dolmus->id.'@anonim.invalid', $dolmus->email);
        $this->assertNull($dolmus->phone);
        $this->assertNull($dolmus->city);
        $this->assertNotNull($dolmus->anonymized_at);
        $this->assertSame(0, $dolmus->favoriteTours()->count());
        $this->assertSame(0, Review::withTrashed()->where('user_id', $dolmus->id)->count());

        $this->assertSame('taze@example.com', $taze->fresh()->email, '10 günlük talep bekler');

        // İkinci koşu aynı hesabı tekrar ele almaz
        $this->artisan('users:purge-deleted')->expectsOutput('Süresi dolmuş silme talebi yok.');
    }

    public function test_admin_komutu_talep_baslatir_ve_iptal_eder(): void
    {
        $user = $this->musteri(['email' => 'kvkk-talep@example.com']);

        $this->artisan('users:request-deletion', ['email' => 'kvkk-talep@example.com'])->assertSuccessful();
        $this->assertNotNull($user->fresh()->deletion_requested_at);

        $this->artisan('users:request-deletion', ['email' => 'kvkk-talep@example.com', '--cancel' => true])->assertSuccessful();
        $this->assertNull($user->fresh()->deletion_requested_at);

        $agencyUser = User::factory()->create(['role' => 'agency', 'agency_id' => $this->agency->id, 'email' => 'ac@example.com']);
        $this->artisan('users:request-deletion', ['email' => 'ac@example.com'])->assertFailed();
    }
}
