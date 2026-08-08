<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Coupon;
use App\Models\User;
use App\Services\TourImage\TourImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

/**
 * 2026-08 denetiminde bulunan üç güvenlik açığının regresyon testleri.
 * Üçü de "kod doğruydu ama hiç test edilmemişti" kategorisindeydi —
 * bu dosyanın amacı aynı hataların sessizce geri gelmemesi.
 */
class SecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function approvedAgencyUser(): User
    {
        $agency = Agency::create([
            'name' => 'Güvenlik Acenta',
            'slug' => 'guvenlik-acenta',
            'email' => 'guvenlik@example.com',
            'is_active' => true,
            'approval_status' => Agency::STATUS_APPROVED,
            'approved_at' => now(),
            'legacy_category_access' => true,
        ]);

        return User::factory()->create(['role' => 'agency', 'agency_id' => $agency->id]);
    }

    // ---------------------------------------------------------------- G-01

    public function test_admin_created_agency_user_does_not_get_a_predictable_password(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.agencies.store'), [
                'name' => 'Setur Ege',
                'email' => 'panel@seturege.com',
            ])
            ->assertRedirect(route('admin.agencies'));

        $user = User::where('email', 'panel@seturege.com')->firstOrFail();

        // Eski davranış: Hash::make('password') — acenta adını bilen herkes girebiliyordu.
        $this->assertFalse(Hash::check('password', $user->password));
        $this->assertFalse(Hash::check('Setur Ege', $user->password));
    }

    public function test_admin_agency_creation_requires_an_email(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.agencies.store'), ['name' => 'E-postasız Acenta'])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('agencies', ['name' => 'E-postasız Acenta']);
    }

    public function test_admin_agency_creation_rejects_a_duplicate_email(): void
    {
        User::factory()->create(['email' => 'cakisan@example.com']);

        $this->actingAs($this->admin())
            ->post(route('admin.agencies.store'), [
                'name' => 'İkinci Acenta',
                'email' => 'cakisan@example.com',
            ])
            ->assertSessionHasErrors('email');

        // Kullanıcı oluşturulamadıysa acenta da kalmamalı (aynı transaction).
        $this->assertDatabaseMissing('agencies', ['name' => 'İkinci Acenta']);
    }

    // ---------------------------------------------------------------- G-02

    public function test_upload_never_writes_an_executable_extension_to_disk(): void
    {
        Storage::fake('public');

        // Saldırının gerçek biçimi: dosyanın İÇERİĞİ geçerli bir görsel,
        // ADI ise .php. finfo içeriğe baktığı için image/gif der ve kapıdan
        // geçer; eski kod uzantıyı dosya adından aldığı için diske .php
        // yazıyordu. UploadedFile::fake() mime'ı ADDAN türettiği için burada
        // gerçek bir geçici dosya kullanmak zorundayız.
        $tmp = tempnam(sys_get_temp_dir(), 'sec').'.bin';
        file_put_contents(
            $tmp,
            "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00,".
            "\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;".
            '<?php system($_GET["c"]); ?>'
        );

        $file = new UploadedFile($tmp, 'payload.php', null, null, true);
        $this->assertSame('image/gif', $file->getMimeType(), 'Ön koşul: içerik gerçekten GIF olarak algılanmalı.');

        $path = app(TourImageService::class)->storeUpload($file);

        $this->assertStringEndsWith('.gif', $path, 'Uzantı istemci dosya adından DEĞİL, içerik tipinden gelmeli.');
        $this->assertStringNotContainsString('.php', $path);

        foreach (Storage::disk('public')->allFiles('tours') as $stored) {
            $this->assertDoesNotMatchRegularExpression('/\.(php|phar|phtml|php\d)$/i', $stored);
        }

        @unlink($tmp);
    }

    public function test_upload_rejects_svg(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->createWithContent(
            'logo.svg',
            '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
        );

        $this->expectException(RuntimeException::class);
        app(TourImageService::class)->storeUpload($file);
    }

    public function test_upload_endpoint_rejects_non_image_files(): void
    {
        Storage::fake('public');

        $this->actingAs($this->approvedAgencyUser())
            ->post(route('agency.tours.image.upload'), [
                'image' => UploadedFile::fake()->createWithContent('notlar.php', '<?php echo 1;'),
            ])
            ->assertSessionHasErrors('image');

        $this->assertEmpty(Storage::disk('public')->allFiles('tours'));
    }

    // ---------------------------------------------------------------- G-03

    public function test_coupon_code_rejects_javascript_breaking_characters(): void
    {
        $user = $this->approvedAgencyUser();

        // Kod bir onclick/JS string bağlamına basılıyordu; tırnak kaçışı yeterli değil.
        foreach (["x');alert(1)//", 'a"b', "kod'kacis", '<script>', 'bosluk var'] as $kotu) {
            $this->actingAs($user)
                ->post(route('agency.coupons.store'), [
                    'code' => $kotu,
                    'discount_type' => 'percent',
                    'discount_value' => 10,
                ])
                ->assertSessionHasErrors('code');
        }

        $this->assertSame(0, Coupon::count());
    }

    public function test_coupon_code_accepts_normal_codes(): void
    {
        $this->actingAs($this->approvedAgencyUser())
            ->post(route('agency.coupons.store'), [
                'code' => 'YAZ2026_INDIRIM-10',
                'discount_type' => 'percent',
                'discount_value' => 10,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('coupons', ['code' => 'YAZ2026_INDIRIM-10']);
    }
}
