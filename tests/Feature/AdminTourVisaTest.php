<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vize toplu düzenleme ekranı.
 *
 * Bu ekran "vizesiz turlar" filtresinin TEK veri kaynağını besliyor; buradaki
 * bir hata doğrudan kullanıcıya yanlış vize bilgisi olarak yansır.
 */
class AdminTourVisaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->agency = Agency::create([
            'name' => 'Vize Acenta',
            'slug' => 'vize-acenta-'.uniqid(),
            'email' => uniqid().'@ornek.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    private function tur(array $attrs = []): Tour
    {
        return Tour::create(array_merge([
            'agency_id' => $this->agency->id,
            'title' => 'Tur '.uniqid(),
            'destination' => 'Roma',
            'description' => 'Test',
            'price' => 20000,
            'currency' => 'TRY',
            'duration_days' => 5,
            'departure_date' => today()->addDays(30),
            'is_active' => true,
        ], $attrs));
    }

    public function test_ekran_dort_secenegi_de_kaydeder(): void
    {
        $vizeli = $this->tur();
        $kapida = $this->tur();
        $vizesiz = $this->tur();
        $temizlenen = $this->tur(['requires_visa' => true]);

        $this->actingAs($this->admin)->put(route('admin.tour-visa.update'), [
            'visa' => [
                $vizeli->id => '1',
                $kapida->id => 'kapida',
                $vizesiz->id => '0',
                $temizlenen->id => 'unknown',
            ],
        ])->assertRedirect();

        $this->assertTrue($vizeli->fresh()->requires_visa);
        $this->assertFalse($vizeli->fresh()->visa_on_arrival);

        $this->assertTrue($kapida->fresh()->requires_visa);
        $this->assertTrue($kapida->fresh()->visa_on_arrival);

        $this->assertFalse($vizesiz->fresh()->requires_visa);

        // Yanlış işaretlemeyi geri almak mümkün olmalı — tek yönlü kapı olmasın.
        $this->assertNull($temizlenen->fresh()->requires_visa);
    }

    public function test_gecersiz_deger_reddedilir(): void
    {
        $tur = $this->tur();

        $this->actingAs($this->admin)
            ->put(route('admin.tour-visa.update'), ['visa' => [$tur->id => 'belki']])
            ->assertSessionHasErrors('visa.'.$tur->id);

        $this->assertNull($tur->fresh()->requires_visa);
    }

    public function test_yurtdisi_eksik_filtresi_onceligi_gosterir(): void
    {
        $this->tur(['title' => 'Roma Turu', 'is_international' => true]);
        $this->tur(['title' => 'Kapadokya Turu', 'is_international' => false]);
        $this->tur(['title' => 'Isaretli Roma', 'is_international' => true, 'requires_visa' => true]);

        $yanit = $this->actingAs($this->admin)->get(route('admin.tour-visa', ['durum' => 'yurtdisi']));

        $yanit->assertOk();
        $yanit->assertSee('Roma Turu', false);
        // Yurt içi turda vize alanı anlamsız, listeyi şişirmemeli.
        $yanit->assertDontSee('Kapadokya Turu', false);
        $yanit->assertDontSee('Isaretli Roma', false);
    }

    public function test_admin_olmayan_giremez(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('admin.tour-visa'))
            ->assertForbidden();
    }
}
