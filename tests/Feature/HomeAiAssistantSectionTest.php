<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ana sayfa AI asistan bölümü bekçileri (2026-08-28 tasarımı): Keşif Rehberi
 * kartı ai.discovery_enabled'a, Tur Danışmanı kartı ChatV2Visibility (balonla
 * ORTAK kural) üzerinden ai.chat_v2_enabled'a bağlıdır. Assertion'lar hasım
 * incelemesi sonrası bilerek karta/balona ÖZGÜ imzalar kullanır: nav/footer
 * linkleri veya kartın kendi onclick'i yüzünden totolojik geçen genel metin
 * aramalarına güvenilmez (id="cv2-trigger" yalnız layout balonunda, href+class
 * imzası yalnız kart CTA'sında geçer).
 */
class HomeAiAssistantSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_kesif_rehberi_karti_bayrak_acikken_gorunur(): void
    {
        config(['ai.discovery_enabled' => true, 'ai.chat_v2_enabled' => false]);

        $r = $this->get(route('home'))->assertOk();
        $r->assertSee('AI asistanlarımız yardımcı olsun');
        $r->assertSee('Keşif Rehberi AI');
        // Kart yeni sayfaya götürmez: pencereyi açan buton + pencerenin kendisi
        // sayfada olmalı (kullanıcı kararı — işlem yatay dialog içinde yapılır)
        $r->assertSee('id="dgm-ac"', false);
        $r->assertSee('id="dg-modal"', false);
        $r->assertSee('Planı Oluştur'); // pencere içi gönder butonu
        $r->assertSee('images/ai/kesif-rehberi-ai.webp', false);
    }

    public function test_kesif_kapali_danisman_acikken_pencere_render_edilmez(): void
    {
        config(['ai.discovery_enabled' => false, 'ai.chat_v2_enabled' => true]);

        $r = $this->get(route('home'))->assertOk();
        $r->assertDontSee('id="dg-modal"', false);
        $r->assertDontSee('id="dgm-ac"', false);
    }

    public function test_danisman_karti_v2_bayragi_kapaliyken_gorunmez(): void
    {
        config(['ai.discovery_enabled' => true, 'ai.chat_v2_enabled' => false]);

        $r = $this->get(route('home'))->assertOk();
        $r->assertDontSee('Tur Danışmanı AI');
        $r->assertDontSee('Sohbete Başla');
        $r->assertDontSee('id="cv2-trigger"', false); // balon da yok
    }

    public function test_yalniz_danisman_acikken_bolum_danisman_kartiyla_gelir(): void
    {
        // discovery default'u true olduğu için açıkça kapatılır — "||" dalı
        // ancak böyle gerçekten test edilir (mutasyon analizi bulgusu)
        config(['ai.discovery_enabled' => false, 'ai.chat_v2_enabled' => true]);

        $r = $this->get(route('home'))->assertOk();
        $r->assertSee('AI asistanlarımız yardımcı olsun');
        $r->assertDontSee('Keşif Rehberi AI');
        $r->assertSee('Tur Danışmanı AI');
        $r->assertSee('Sohbete Başla');
        // Balonun KENDİSİ sayfada (kartın onclick'indeki 'cv2-trigger' metni değil)
        $r->assertSee('id="cv2-trigger"', false);
        // Karttan açılış orta-pencere kablolaması: buton + cv2-orta sınıfı
        $r->assertSee('id="cv2-karttan-ac"', false);
        $r->assertSee('cv2-orta', false);
    }

    public function test_iki_bayrak_da_kapaliyken_bolum_hic_yok(): void
    {
        config(['ai.discovery_enabled' => false, 'ai.chat_v2_enabled' => false]);

        $r = $this->get(route('home'))->assertOk();
        $r->assertDontSee('AI asistanlarımız yardımcı olsun');
        $r->assertDontSee('ai-hub-wrap', false);
        $r->assertDontSee('dg-modal', false);
    }

    public function test_giris_yapmis_musteri_hem_karti_hem_balonu_gorur(): void
    {
        config(['ai.discovery_enabled' => true, 'ai.chat_v2_enabled' => true]);
        $musteri = User::factory()->create(['role' => 'visitor']);

        $r = $this->actingAs($musteri)->get(route('home'))->assertOk();
        $r->assertSee('Tur Danışmanı AI');
        $r->assertSee('id="cv2-trigger"', false);
    }

    public function test_admin_roller_danisman_kartini_ve_balonu_gormez_kesif_kartini_gorur(): void
    {
        config(['ai.discovery_enabled' => true, 'ai.chat_v2_enabled' => true]);

        foreach (['admin', 'super_admin', 'superadmin'] as $rol) {
            $kullanici = User::factory()->create(['role' => $rol]);

            $r = $this->actingAs($kullanici)->get(route('home'))->assertOk();
            // Pozitif çapa: bölüm admin'e KAYBOLMAZ, yalnız danışman kartı gizlenir
            $r->assertSee('AI asistanlarımız yardımcı olsun');
            $r->assertSee('Keşif Rehberi AI');
            $r->assertDontSee('Tur Danışmanı AI');
            $r->assertDontSee('id="cv2-trigger"', false); // balon da render edilmez
        }
    }

    public function test_karakter_gorselleri_diskte_mevcut(): void
    {
        // Path typo'su veya commit'e girmeyen asset 404 görsel üretir; suite yakalasın
        self::assertFileExists(public_path('images/ai/kesif-rehberi-ai.webp'));
        self::assertFileExists(public_path('images/ai/tur-danismani-ai.webp'));
    }
}
