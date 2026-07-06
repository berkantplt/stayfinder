<?php

namespace App\Console\Commands;

use App\Models\AiSearchLog;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Girişli kullanıcılar için tercih profili: tıkladığı + favorilediği turların
 * KAYITLI embedding'lerinin ortalaması (yeni API çağrısı yok) + bütçe bandı ve
 * yön oranı. Arama skorlamasında küçük kişiselleştirme bonusu olarak kullanılır.
 */
class BuildUserPreferenceProfiles extends Command
{
    protected $signature = 'ai:build-user-profiles {--days=180}';

    protected $description = 'Kullanıcı tercih profillerini geçmiş etkileşimden üretir.';

    public function handle(): void
    {
        $since = now()->subDays((int) $this->option('days'));
        $built = 0;

        $userIds = AiSearchLog::query()
            ->whereNotNull('user_id')
            ->whereNotNull('selected_tour_id')
            ->where('created_at', '>=', $since)
            ->distinct()
            ->pluck('user_id');

        foreach ($userIds as $userId) {
            $user = User::find($userId);
            if (! $user) {
                continue;
            }

            $clickedIds = AiSearchLog::where('user_id', $userId)
                ->whereNotNull('selected_tour_id')
                ->where('created_at', '>=', $since)
                ->pluck('selected_tour_id');

            $favoriteIds = method_exists($user, 'favorites')
                ? $user->favorites()->pluck('tours.id')
                : collect();

            $tourIds = $clickedIds->merge($favoriteIds)->unique()->values();
            if ($tourIds->count() < 3) {
                continue; // istatistiksel dürüstlük: 3'ten az sinyalle profil kurma
            }

            $tours = Tour::whereIn('id', $tourIds)->whereNotNull('embedding')->get(['id', 'price_try', 'price', 'is_international', 'embedding']);
            if ($tours->count() < 3) {
                continue;
            }

            // Ortalama embedding (kayıtlı vektörlerden — API'siz)
            $dim = count($tours->first()->embedding);
            $mean = array_fill(0, $dim, 0.0);
            foreach ($tours as $tour) {
                foreach ($tour->embedding as $i => $v) {
                    $mean[$i] += (float) $v / $tours->count();
                }
            }

            $prices = $tours->map(fn ($t) => (float) ($t->price_try ?? $t->price))->sort()->values();
            $median = $prices[intdiv($prices->count(), 2)];

            $user->forceFill(['ai_preference' => [
                'embedding' => $mean,
                'budget_median_try' => round($median),
                'international_ratio' => round($tours->avg(fn ($t) => $t->is_international ? 1 : 0), 2),
                'sample_size' => $tours->count(),
                'built_at' => now()->toDateString(),
            ]])->saveQuietly();

            $built++;
        }

        $this->info("Tercih profili üretildi: {$built} kullanıcı.");
    }
}
