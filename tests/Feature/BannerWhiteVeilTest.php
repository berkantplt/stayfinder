<?php

namespace Tests\Feature;

use App\Models\Banner;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\HeroVeil;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Hero'daki beyaz perde: TEK ayar, tüm görsellere uygulanır (2026-08-13
 * kullanıcı kararı — kısa süre banner başına ayarlanabiliyordu).
 *
 * Neden test: perde koyu başlığın OKUNURLUĞUNU sağlıyor. Ana sayfa ile admin
 * önizlemesi ayrı formül kullanırsa admin'in gördüğü ile sitede çıkan görüntü
 * sessizce ayrışır — ikisi de HeroVeil::STOPS'tan türemek zorunda.
 */
class BannerWhiteVeilTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function banner(string $title = 'Kapadokya'): Banner
    {
        return Banner::create([
            'title' => $title,
            'image' => 'banners/test.jpg',
            'blur' => 0,
            'darkness' => 12,
            'sort_order' => 0,
        ]);
    }

    public function test_perde_degradesi_degerle_olceklenir(): void
    {
        // 100 → tasarımın aynısı
        $this->assertStringContainsString('rgba(255,255,255,0.94) 0%', HeroVeil::css(100));

        // 50 → tüm duraklar yarıya iner
        $yarim = HeroVeil::css(50);
        $this->assertStringContainsString('rgba(255,255,255,0.47) 0%', $yarim);
        $this->assertStringContainsString('rgba(255,255,255,0.43) 24%', $yarim);

        // 0 → perde yok (hepsi saydam)
        $this->assertStringContainsString('rgba(255,255,255,0) 0%', HeroVeil::css(0));
    }

    public function test_aralik_disi_deger_kirpilir(): void
    {
        $this->assertSame(HeroVeil::css(100), HeroVeil::css(180));
        $this->assertSame(HeroVeil::css(0), HeroVeil::css(-40));

        HeroVeil::setStrength(500);
        $this->assertSame(100, HeroVeil::strength());
    }

    public function test_ayar_yoksa_varsayilan_kullanilir(): void
    {
        SiteSetting::query()->delete();

        $this->assertSame(HeroVeil::DEFAULT, HeroVeil::strength());
    }

    public function test_ana_sayfa_tek_perde_katmani_basar(): void
    {
        $this->banner('Kapadokya');
        $this->banner('Bodrum');
        HeroVeil::setStrength(35);

        $cevap = $this->get(route('home'))->assertOk();

        // 0.94 * 0.35 = 0.329
        $cevap->assertSee('rgba(255,255,255,0.329) 0%', false);
        // Görsel başına DEĞİL, tek katman
        $this->assertSame(1, substr_count($cevap->getContent(), 'class="hero-veil"'));
    }

    public function test_admin_perdeyi_guncelleyebilir(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.banners.veil'), ['white_veil' => 45])
            ->assertRedirect(route('admin.banners.index'));

        $this->assertSame(45, HeroVeil::strength());
    }

    public function test_gecersiz_perde_degeri_reddedilir(): void
    {
        HeroVeil::setStrength(70);

        $this->actingAs($this->admin())
            ->put(route('admin.banners.veil'), ['white_veil' => 140])
            ->assertSessionHasErrors('white_veil');

        $this->assertSame(70, HeroVeil::strength());
    }

    public function test_banner_kaydetmek_perdeyi_degistirmez(): void
    {
        $banner = $this->banner();
        HeroVeil::setStrength(60);

        $this->actingAs($this->admin())
            ->put(route('admin.banners.update', $banner), [
                'title' => 'Yeni Başlık',
                'blur' => 3,
                'darkness' => 20,
                'sort_order' => 1,
            ])
            ->assertRedirect(route('admin.banners.index'));

        $this->assertSame(60, HeroVeil::strength());
        $this->assertSame('Yeni Başlık', $banner->fresh()->title);
    }

    public function test_admin_sayfasi_tek_kaydirici_gosterir(): void
    {
        $this->banner('Kapadokya');
        $this->banner('Bodrum');
        HeroVeil::setStrength(70);

        $cevap = $this->actingAs($this->admin())
            ->get(route('admin.banners.index'))
            ->assertOk();

        $cevap->assertSee('Beyaz Perde (tüm görseller)');
        // Banner sayısı 2 ama kaydırıcı 1 tane
        $this->assertSame(1, substr_count($cevap->getContent(), 'name="white_veil"'));
        // Önizleme, ana sayfayla aynı degradeyi basmalı
        $cevap->assertSee(HeroVeil::css(70), false);
    }
}
