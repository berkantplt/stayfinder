<?php

namespace App\Services;

use App\Models\DestinationProfile;
use App\Support\DestinationClassifier;

/**
 * Turun yurt içi/dışı bayrağını iki kaynakla çözer.
 *
 * 1) Küratörlü statik liste (DestinationClassifier) — hızlı, kesin, DB'siz.
 * 2) Bilinmeyende destinasyon profilindeki ülke bilgisi.
 *
 * İkinci kaynak neden şart: statik liste kaçınılmaz olarak eksik kalır ve
 * eksiklik SESSİZ bir hataya dönüşüyordu — sınıflandırılamayan destinasyonda
 * bayrağa dokunulmuyor, kolon varsayılanı false olduğu için tur "yurt içi"
 * sayılıyordu. Böylece Rovaniemi turu "yurt içinde olsun" diyen kullanıcıya
 * çıkabiliyordu. Liste büyütmek tek başına bunu çözmez; kuyruk hep uzar.
 */
class DestinationOriginResolver
{
    /** Profil ülkesi bu değerlerden biriyse yurt içi sayılır. */
    private const TURKIYE = ['turkiye', 'turkey', 'turkiye cumhuriyeti', 'tr'];

    /** true=yurt dışı, false=yurt içi, null=hâlâ belirlenemedi (bayrağa dokunma). */
    public function isInternational(?string $destination): ?bool
    {
        $statik = DestinationClassifier::isInternational($destination);
        if ($statik !== null) {
            return $statik;
        }

        return $this->profilUlkesinden($destination);
    }

    /**
     * Destinasyon parçalarının profillerine bakar. Statik listeyle AYNI kural:
     * parçalardan biri yurt dışıysa tur yurt dışıdır (karma rota pratikte
     * yurt dışı turudur).
     */
    private function profilUlkesinden(?string $destination): ?bool
    {
        $parcalar = DestinationProfile::splitCities((string) $destination);
        if ($parcalar === []) {
            return null;
        }

        $profiller = DestinationProfile::query()
            ->whereIn('normalized_city', array_map(
                fn (string $p) => DestinationProfile::normalize($p),
                $parcalar,
            ))
            // Zenginleşmemiş profilde ülke null; "bilinmiyor"u "Türkiye" sanmayalım
            ->whereNotNull('country')
            ->pluck('country');

        if ($profiller->isEmpty()) {
            return null;
        }

        $yurtIciBulundu = false;
        foreach ($profiller as $ulke) {
            if ($this->turkiyeMi((string) $ulke)) {
                $yurtIciBulundu = true;

                continue;
            }

            return true;
        }

        return $yurtIciBulundu ? false : null;
    }

    private function turkiyeMi(string $ulke): bool
    {
        $normalize = DestinationProfile::normalize($ulke);

        return in_array($normalize, self::TURKIYE, true);
    }
}
