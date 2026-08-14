<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Hero'daki beyaz perde banner başına ayarlanır (banners.white_veil).
 *
 * Neden test: perde koyu başlığın OKUNURLUĞUNU sağlıyor. Ana sayfa ile admin
 * önizlemesi ayrı formül kullanırsa admin'in gördüğü ile sitede çıkan görüntü
 * sessizce ayrışır — ikisi de Banner::VEIL_STOPS'tan türemek zorunda.
 */
class BannerWhiteVeilTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function banner(int $veil = 100): Banner
    {
        return Banner::create([
            'title' => 'Kapadokya',
            'image' => 'banners/test.jpg',
            'blur' => 0,
            'darkness' => 12,
            'white_veil' => $veil,
            'sort_order' => 0,
        ]);
    }

    public function test_perde_degradesi_degerle_olceklenir(): void
    {
        // 100 → tasarımın aynısı
        $this->assertStringContainsString('rgba(255,255,255,0.94) 0%', Banner::veilGradient(100));

        // 50 → tüm duraklar yarıya iner
        $yarim = Banner::veilGradient(50);
        $this->assertStringContainsString('rgba(255,255,255,0.47) 0%', $yarim);
        $this->assertStringContainsString('rgba(255,255,255,0.43) 24%', $yarim);

        // 0 → perde yok (hepsi saydam)
        $this->assertStringContainsString('rgba(255,255,255,0) 0%', Banner::veilGradient(0));
    }

    public function test_aralik_disi_deger_kirpilir(): void
    {
        $this->assertSame(Banner::veilGradient(100), Banner::veilGradient(180));
        $this->assertSame(Banner::veilGradient(0), Banner::veilGradient(-40));
    }

    public function test_ana_sayfa_her_slayta_kendi_perdesini_basar(): void
    {
        $this->banner(35);

        $this->get(route('home'))
            ->assertOk()
            // 0.94 * 0.35 = 0.329
            ->assertSee('rgba(255,255,255,0.329) 0%', false);
    }

    public function test_admin_perdeyi_guncelleyebilir(): void
    {
        $banner = $this->banner(100);

        $this->actingAs($this->admin())
            ->put(route('admin.banners.update', $banner), [
                'title' => $banner->title,
                'blur' => 0,
                'darkness' => 12,
                'white_veil' => 45,
                'sort_order' => 0,
            ])
            ->assertRedirect(route('admin.banners.index'));

        $this->assertSame(45, $banner->fresh()->white_veil);
    }

    public function test_gecersiz_perde_degeri_reddedilir(): void
    {
        $banner = $this->banner(100);

        $this->actingAs($this->admin())
            ->put(route('admin.banners.update', $banner), [
                'title' => $banner->title,
                'white_veil' => 140,
            ])
            ->assertSessionHasErrors('white_veil');

        $this->assertSame(100, $banner->fresh()->white_veil);
    }

    public function test_yeni_banner_varsayilan_perdeyle_gelir(): void
    {
        Storage::fake('public');

        $this->actingAs($this->admin())
            ->post(route('admin.banners.store'), [
                'title' => 'Yeni Banner',
                'image' => UploadedFile::fake()->image('hero.jpg'),
            ])
            ->assertRedirect(route('admin.banners.index'));

        $this->assertSame(100, Banner::where('title', 'Yeni Banner')->first()->white_veil);
    }

    public function test_admin_sayfasi_perde_kaydiricisini_gosterir(): void
    {
        $this->banner(70);

        $this->actingAs($this->admin())
            ->get(route('admin.banners.index'))
            ->assertOk()
            ->assertSee('Beyaz Perde')
            ->assertSee('name="white_veil"', false)
            // Önizleme, ana sayfayla aynı degradeyi basmalı
            ->assertSee(Banner::veilGradient(70), false);
    }
}
