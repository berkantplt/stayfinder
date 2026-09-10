<?php

namespace App\Console\Commands;

use App\Models\SavedSearch;
use App\Models\Tour;
use App\Notifications\SavedSearchMatchNotification;
use App\Support\TourListFilter;
use Illuminate\Console\Command;

/**
 * Kayıtlı aramaları günlük tarar: son kontrolden sonra eklenen ve filtreye
 * uyan turlar varsa üyeye tek bildirim (tur başına değil, arama başına).
 * Kontrol zamanı her koşuda güncellenir, aynı tur iki kez bildirilmez.
 */
class CheckSavedSearches extends Command
{
    protected $signature = 'app:check-saved-searches';

    protected $description = 'Kayıtlı aramalara uyan yeni turları bulur ve üyeye bildirim atar';

    public function handle(): int
    {
        $bildirim = 0;

        SavedSearch::with('user')->chunkById(200, function ($aramalar) use (&$bildirim) {
            foreach ($aramalar as $arama) {
                $since = $arama->last_checked_at ?? $arama->created_at;
                $simdi = now();

                $sorgu = Tour::query()->active()
                    ->whereHas('agency', fn ($q) => $q->active())
                    ->where('created_at', '>', $since);
                TourListFilter::apply($sorgu, $arama->params);
                $adet = (clone $sorgu)->count();

                if ($adet > 0 && $arama->user) {
                    $ilk = $sorgu->orderByDesc('created_at')->first();
                    $arama->user->notify(new SavedSearchMatchNotification($arama, $adet, $ilk));
                    $arama->last_notified_at = $simdi;
                    $bildirim++;
                }

                $arama->last_checked_at = $simdi;
                $arama->save();
            }
        });

        $this->info("Kayıtlı arama kontrolü tamam: {$bildirim} bildirim.");

        return self::SUCCESS;
    }
}
