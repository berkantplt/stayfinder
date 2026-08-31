<?php

namespace App\Services\Chat\Tools;

use App\Models\DestinationProfile;
use App\Services\AiSearch\DestinationProfileService;

/**
 * Şehir/destinasyon karakteri (şartname madde 5-6): kalabalıklık, canlılık,
 * en iyi aylar, iklim, vize. "Sakin isteyene kalabalık şehir" önerilmesini
 * engelleyen veri kaynağı.
 */
class SehirBilgisi implements ChatTool
{
    public function __construct(private readonly DestinationProfileService $profiles) {}

    public static function name(): string
    {
        return 'sehir_bilgisi';
    }

    public static function schema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => self::name(),
                'description' => 'Bir şehir/destinasyon hakkında ölçülmüş bilgi getirir: ne kadar '
                    .'kalabalık/canlı, hangi aylar uygun, vize gerekiyor mu. Bir yeri önermeden ÖNCE '
                    .'kullanıcının tarifiyle uyuşuyor mu diye bak. veri_var=false ise o şehir hakkında '
                    .'NİTELEME YAPMA — elimizde ölçüm yok demektir.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'sehir' => ['type' => 'string', 'description' => 'Şehir veya destinasyon adı'],
                    ],
                    'required' => ['sehir'],
                ],
            ],
        ];
    }

    public function run(array $args): array
    {
        $sehir = trim((string) ($args['sehir'] ?? ''));
        if ($sehir === '') {
            return ['veri_var' => false, 'hata' => 'Şehir adı boş.'];
        }

        $p = $this->profiles->get($sehir);

        // Zenginleşmemiş şehirde servis 0.50/0.50 döndürüyor — bayrak olmazsa
        // model bunu "orta yoğunlukta bir şehir" diye okur ve sessizce uydurur.
        $zenginlesmis = ($p['source'] ?? null) !== DestinationProfile::SOURCE_DEFAULT;

        if (! $zenginlesmis) {
            return [
                'sehir' => $sehir,
                'veri_var' => false,
                'not' => 'Bu destinasyon için ölçülmüş profil yok. Kalabalıklık/canlılık/sezon '
                    .'hakkında niteleme yapma; genel coğrafi bilgiyle yetin.',
            ];
        }

        return [
            'sehir' => $sehir,
            'veri_var' => true,
            'ulke' => $p['country'] ?? null,
            'kalabaliklik' => $p['crowd'],      // 0-1, yüksek = turistik/kalabalık
            'canlilik' => $p['lively'],         // 0-1, yüksek = gece hayatı/hareketli
            'en_iyi_aylar' => $p['best_months'] ?? null,
            'kalabalik_aylar' => $p['crowded_months'] ?? null,
            'iklim' => $p['climate_by_month'] ?? null,
            'karakter_etiketleri' => $p['vibe_tags'] ?? null,
            'vize_gerekir_mi' => $p['requires_visa_for_tr'] ?? null,
            'ozet' => $p['summary'] ?? null,
        ];
    }
}
