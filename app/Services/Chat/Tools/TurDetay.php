<?php

namespace App\Services\Chat\Tools;

use App\Models\Tour;
use App\Services\AiSearch\DestinationProfileService;
use Illuminate\Support\Str;

/**
 * Tek turun bilgi fişi (kapsam kararı: tur detay soruları).
 *
 * Gövdesi v1'deki AiSearchController::tourFactSheet'ten geldi; orada private
 * olduğu için yeniden kullanılamıyordu. Model burada dönen bilgilerin DIŞINDA
 * tura özel detay uyduramaz (oda özellikleri, manzara, ikram vb.).
 *
 * Vize: yalnız "gerekli/gerekmiyor" döner. Detay alanları (belgeler, ücret,
 * randevu) katalogdan bilerek kaldırılmıştı → prosedür anlatılmaz, acentaya
 * yönlendirilir.
 */
class TurDetay implements ChatTool
{
    public function __construct(private readonly DestinationProfileService $profiles) {}

    public static function name(): string
    {
        return 'tur_detay';
    }

    public static function schema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => self::name(),
                'description' => 'Bir turun tüm detayını getirir: gün gün program, konaklama, '
                    .'fiyata dahil/hariç, iptal koşulu, kalkış tarihleri ve noktaları, vize durumu. '
                    .'Kullanıcı bir tur hakkında soru sorduğunda ("neler dahil", "hangi otel", '
                    .'"iptal edebilir miyim") bu aracı çağır. Buradan dönmeyen hiçbir detayı söyleme.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'tur_id' => ['type' => 'integer', 'description' => 'Daha önce gösterilen turun id değeri'],
                    ],
                    'required' => ['tur_id'],
                ],
            ],
        ];
    }

    public function run(array $args): array
    {
        $tour = Tour::with(['agency', 'dates'])->find((int) ($args['tur_id'] ?? 0));
        if (! $tour || ! $tour->is_active) {
            return ['bulunamadi' => true, 'not' => 'Bu tur artık aktif değil — kullanıcıya dürüstçe söyle.'];
        }

        $dates = $tour->dates
            ->filter(fn ($d) => $d->departure_date && $d->departure_date->isFuture())
            ->map(fn ($d) => $d->departure_date->format('d.m.Y'))
            ->take(8)->values()->all();

        $program = collect(is_array($tour->itinerary) ? $tour->itinerary : [])
            ->map(fn ($day, $i) => [
                'gun' => $i + 1,
                'baslik' => $day['title'] ?? null,
                'icerik' => Str::limit(strip_tags((string) ($day['content'] ?? '')), 600),
            ])->values()->all();

        $campaign = $tour->active_campaign;
        $visa = $this->profiles->get((string) $tour->destination)['requires_visa_for_tr'] ?? null;

        // Çok şehirli rotada her şehrin KENDİ profili — tek şehir tüm rotanın
        // karakteri gibi sunulmasın
        $sehirProfilleri = collect($this->profiles->describeCities((string) $tour->destination))
            ->map(fn ($p) => ['sehir' => $p['city'], 'karakter' => $p['character'], 'ozet' => $p['summary']])
            ->all();

        return [
            'id' => $tour->id,
            'baslik' => $tour->title,
            'acenta' => $tour->agency?->name,
            'destinasyon' => $tour->destination,
            'sure_gun' => $tour->duration_days,
            'sure_metni' => $tour->duration_label,
            'fiyat' => (float) $tour->price,
            'para_birimi' => $tour->currency,
            'kampanya' => $campaign ? ['etiket' => $campaign->label, 'indirimli_fiyat' => (float) $campaign->discount_price] : null,
            'tur_karakteri' => $tour->character_summary,
            'tempo' => $tour->pace_score !== null ? (float) $tour->pace_score : null,
            'sehir_profilleri' => $sehirProfilleri,
            'vize_gerekir_mi' => $visa,
            'vize_notu' => $visa === null ? null : 'Yalnız gereklilik bilgisi var; belge/ücret/randevu detayı için acentaya yönlendir.',
            'kalkis_tarihleri' => $dates,
            'kalkis_noktalari' => $tour->departure_points ? Str::limit(strip_tags((string) $tour->departure_points), 400) : null,
            'konaklama' => $tour->hotel_info ? Str::limit(strip_tags((string) $tour->hotel_info), 600) : null,
            'fiyata_dahil' => $tour->included ? Str::limit(strip_tags((string) $tour->included), 800) : null,
            'dahil_olmayan' => $tour->excluded ? Str::limit(strip_tags((string) $tour->excluded), 500) : null,
            'ekstra_aktiviteler' => $tour->extras ? Str::limit(strip_tags((string) $tour->extras), 500) : null,
            'iptal_iade' => $tour->cancellation_policy ? Str::limit(strip_tags((string) $tour->cancellation_policy), 500) : null,
            'gun_gun_program' => $program,
            'degerlendirme' => $tour->reviews()->count() > 0
                ? ['puan' => $tour->avg_rating, 'yorum_sayisi' => $tour->reviews()->count()]
                : null,
        ];
    }
}
