<?php

namespace App\Services\Discovery;

use App\Models\DiscoveryGuide;
use App\Models\Tour;
use App\Support\DestinationFilter;
use Illuminate\Support\Collection;

/**
 * "Bu destinasyondaki turları keşfet" bölümünün TEK kaynağı turXtur veritabanı:
 * AI hiçbir zaman tur önermez/uyduramaz. Eşleştirme kademeli:
 *   1) Rehberin bağlandığı destinasyon kaydının adı
 *   2) Kullanıcının girdiği destinasyon metni
 *   3) AI'ın doğrulanmış related_destination_keywords listesi (kontrollü)
 * İlk sonuç veren kademe kullanılır; hepsi DestinationFilter'ın kelime sınırlı
 * eşleşmesinden geçer ("Roma" → "Romanya" tuzağı kapalı).
 */
class RelatedTourService
{
    private const LIMIT = 8;

    /** @return Collection<int, Tour> */
    public function forGuide(DiscoveryGuide $guide): Collection
    {
        $kademeler = [];

        if ($guide->destination) {
            $kademeler[] = [$guide->destination->name];
        }

        $kademeler[] = [$guide->destination_input];

        $keywords = collect((array) ($guide->guide_payload['related_destination_keywords'] ?? []))
            ->filter(fn ($k) => is_string($k) && mb_strlen(trim($k), 'UTF-8') >= 3)
            ->map(fn ($k) => trim($k))
            ->take(5)
            ->all();
        if ($keywords !== []) {
            $kademeler[] = $keywords;
        }

        foreach ($kademeler as $sehirler) {
            $tours = $this->query($sehirler);
            if ($tours->isNotEmpty()) {
                return $tours;
            }
        }

        return collect();
    }

    /**
     * @param  array<int, string>  $cities
     * @return Collection<int, Tour>
     */
    private function query(array $cities): Collection
    {
        // scopeActive lisans + acenta aktifliğini içerir; kart partial'ı için
        // agency + puan agregasyonları tek sorguda gelir (N+1 yok).
        return DestinationFilter::apply(Tour::query()->active(), $cities)
            ->with(['agency', 'category'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->orderBy('price_try')
            ->take(self::LIMIT)
            ->get();
    }
}
