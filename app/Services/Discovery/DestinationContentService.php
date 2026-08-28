<?php

namespace App\Services\Discovery;

use App\Models\Destination;
use App\Models\DestinationProfile;
use App\Support\DestinationFilter;

/**
 * Keşif Rehberi'nin veri önceliği: önce turXtur'un KENDİ verisi (destinasyon
 * kaydı + LLM'le zenginleştirilmiş DestinationProfile), AI açıklaması en sonda.
 * Bu servis o iç veriyi toplar ve hem AI prompt bağlamı hem destination_id
 * eşleşmesi için tek noktadan sunar. Salt okunur: profil yoksa enrichment
 * job'ı TETİKLEMEZ (rehber üretimi profil beklemek zorunda değil).
 */
class DestinationContentService
{
    /**
     * @return array{destination: ?Destination, profile: ?DestinationProfile}
     */
    public function lookup(string $destinationInput): array
    {
        $normalized = DestinationFilter::normalize($destinationInput);
        if ($normalized === '') {
            return ['destination' => null, 'profile' => null];
        }

        // Editöryel destinasyon kaydı: tablo küçük (admin eliyle dolduruluyor),
        // normalize karşılaştırması PHP tarafında — SQL LOWER tuzağına girmeden.
        $destination = Destination::query()->active()->get()
            ->first(fn (Destination $d) => DestinationFilter::normalize($d->name) === $normalized);

        $profile = DestinationProfile::query()
            ->where('normalized_city', DestinationProfile::normalize($destinationInput))
            ->first();

        return ['destination' => $destination, 'profile' => $profile];
    }

    /**
     * AI prompt'una eklenecek Türkçe bağlam bloğu. Yalnız DB'de gerçekten
     * bulunan bilgiler yazılır — boş alan uydurulmaz. Default kaynaklı
     * (placeholder) profil skorları yazılmaz (bkz. DestinationProfileService
     * "asla yanlış veri" kuralı).
     */
    public function promptContext(?Destination $destination, ?DestinationProfile $profile): string
    {
        $satirlar = [];

        if ($destination) {
            $satirlar[] = 'Destinasyon kaydı: '.$destination->name
                .($destination->country ? ' ('.$destination->country.')' : '');
            if ($destination->description) {
                $satirlar[] = 'Editöryel açıklama: '.mb_substr($destination->description, 0, 600, 'UTF-8');
            }
            if ($destination->highlights) {
                $satirlar[] = 'Öne çıkanlar: '.mb_substr((string) $destination->highlights, 0, 400, 'UTF-8');
            }
        }

        if ($profile && $profile->source !== DestinationProfile::SOURCE_DEFAULT) {
            if ($profile->country) {
                $satirlar[] = 'Ülke: '.$profile->country;
            }
            if ($profile->summary) {
                $satirlar[] = 'Şehir özeti: '.$profile->summary;
            }
            if (! empty($profile->vibe_tags)) {
                $satirlar[] = 'Karakter etiketleri: '.implode(', ', (array) $profile->vibe_tags);
            }
            if (! empty($profile->best_months)) {
                $satirlar[] = 'İdeal ziyaret ayları: '.implode(', ', (array) $profile->best_months);
            }
            if ($profile->requires_visa_for_tr !== null) {
                $satirlar[] = 'Türk vatandaşı için vize: '.($profile->requires_visa_for_tr ? 'gerekli' : 'gerekli değil');
            }
        }

        if ($satirlar === []) {
            return '';
        }

        return "SİTE VERİSİ (öncelikli, doğru kabul et):\n- ".implode("\n- ", $satirlar);
    }
}
