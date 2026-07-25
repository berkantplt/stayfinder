<?php

namespace Tests\Unit;

use App\Models\DestinationProfile;
use App\Services\AiSearch\DestinationProfileService;
use PHPUnit\Framework\TestCase;

/**
 * Destinasyon karakter metni bekçileri: skor→Türkçe niteleyici eşikleri,
 * "asla yanlış veri" kuralı (zenginleşmemiş profil → null) ve çok-şehirli
 * destinasyon bölme kuralı.
 */
class DestinationCharacterTextTest extends TestCase
{
    public function test_kalabalik_etiketi_esikleri(): void
    {
        // Seeder kalibrasyonuyla hizalı: İstanbul 0.98 → çok kalabalık, Rize 0.42 → sakin
        $this->assertSame('çok kalabalık ve turistik', DestinationProfileService::crowdLabel(0.98));
        $this->assertSame('hareketli, turist yoğunluğu yüksek', DestinationProfileService::crowdLabel(0.68));
        $this->assertSame('sakin ve dingin', DestinationProfileService::crowdLabel(0.42));
        $this->assertSame('orta yoğunlukta', DestinationProfileService::crowdLabel(0.50));
    }

    public function test_gece_hayati_etiketi_esikleri(): void
    {
        $this->assertSame('gece hayatı ve eğlence çok hareketli', DestinationProfileService::livelyLabel(0.90));
        $this->assertSame('gece hayatı canlı', DestinationProfileService::livelyLabel(0.62));
        $this->assertSame('gece hayatı sakin, dinlenme ağırlıklı', DestinationProfileService::livelyLabel(0.34));
    }

    public function test_zengin_profil_karakter_metni_uretir(): void
    {
        $text = DestinationProfileService::describeProfile([
            'crowd' => 0.95,
            'lively' => 0.90,
            'source' => DestinationProfile::SOURCE_LLM,
            'vibe_tags' => ['nightlife', 'cultural', 'historical'],
        ]);

        $this->assertStringContainsString('çok kalabalık', $text);
        $this->assertStringContainsString('gece hayatı ve eğlence çok hareketli', $text);
        $this->assertStringContainsString('gece hayatı, kültür, tarih', $text);
    }

    public function test_zenginlesmemis_profil_icin_metin_uretilmez(): void
    {
        // Default (placeholder) profil orta skorlarla döner — "orta yoğunlukta"
        // gibi UYDURMA niteleyici yazılmamalı
        $this->assertNull(DestinationProfileService::describeProfile([
            'crowd' => 0.50,
            'lively' => 0.50,
            'source' => DestinationProfile::SOURCE_DEFAULT,
            'vibe_tags' => null,
        ]));
    }

    public function test_cok_sehirli_destinasyon_bolme(): void
    {
        $this->assertSame(
            ['Paris', 'Roma', 'Floransa'],
            DestinationProfile::splitCities('Paris, Roma ve Floransa')
        );
        // Tekrarlar normalize üzerinden ayıklanır, 3 harften kısa parçalar düşer
        $this->assertSame(['İstanbul'], DestinationProfile::splitCities('İstanbul & istanbul'));
        $this->assertSame([], DestinationProfile::splitCities('  '));
    }
}
