<?php

namespace App\Support;

use App\Models\PriceHistory;

/**
 * Kart rozetleri için fiyat düşüşü: turun son 30 gündeki son iki fiyat
 * kaydından düşüş yüzdesi (tur_id => %düşüş). Ana sayfa ve /turlar aynı
 * hesabı kullanır; eskiden yalnız ana sayfada vardı, listede rozet hiç
 * basılmıyordu.
 *
 * @param  iterable<int>  $tourIds
 * @return array<int, int>
 */
final class PriceDrops
{
    public static function last30Days(iterable $tourIds): array
    {
        $ids = collect($tourIds)->map(fn ($id) => (int) $id)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return [];
        }

        $drops = [];
        $histories = PriceHistory::whereIn('tour_id', $ids)
            ->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('tour_id');

        foreach ($histories as $tourId => $rows) {
            if ($rows->count() < 2) {
                continue;
            }
            $last = (float) $rows[0]->price;
            $prev = (float) $rows[1]->price;
            if ($prev > 0 && $last < $prev) {
                $drops[(int) $tourId] = (int) round((1 - $last / $prev) * 100);
            }
        }

        return array_filter($drops);
    }
}
