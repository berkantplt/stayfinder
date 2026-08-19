<?php

namespace App\Support;

use App\Models\DestinationProfile;
use Illuminate\Support\Facades\Cache;

/**
 * Vize bilgisi NET olan şehir listeleri.
 *
 * Kaynak destinasyon profilleridir (chatbot'la aynı, LLM-zenginleştirilmiş).
 * tours.requires_visa KULLANILMAZ: "girilmemiş" ile "vizesiz" ayrılamadığı için
 * yanlış veri satılmış olurdu. Profili olmayan destinasyon = bilinmiyor, filtre
 * dışı kalır (dürüst davranış).
 *
 * Hem ana sayfa filtre barı hem /turlar aynı listeyi kullanır — ayrı hesaplansa
 * iki sayfa aynı seçim için farklı sonuç verirdi.
 */
class VisaCities
{
    /**
     * @return array{vizesiz: array<int, string>, vizeli: array<int, string>}
     */
    public static function lists(): array
    {
        return Cache::remember('home_visa_cities_v1', 300, function () {
            $profiles = DestinationProfile::whereNotNull('requires_visa_for_tr')
                ->get(['city', 'requires_visa_for_tr']);

            return [
                'vizesiz' => $profiles->where('requires_visa_for_tr', false)->pluck('city')->values()->all(),
                'vizeli' => $profiles->where('requires_visa_for_tr', true)->pluck('city')->values()->all(),
            ];
        });
    }
}
