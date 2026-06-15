<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneAnalytics extends Command
{
    protected $signature = 'app:prune-analytics';

    protected $description = 'Analitik tablolarındaki eski ham satırları siler (yaşam-boyu toplamlar tours.views_count/clicks_count sayaçlarında korunur).';

    /**
     * Tablo → [zaman kolonu, retention gün]. Pencereli sorguların en genişi
     * 30 gün (dashboard grafikleri); retention'lar bunun çok üstünde.
     * ai_search_logs ML eğitim verisi için 90 gün tutulur.
     */
    private const RETENTION = [
        'tour_views' => ['viewed_at', 180],
        'tour_clicks' => ['clicked_at', 180],
        'ai_search_logs' => ['created_at', 90],
        'price_histories' => ['recorded_at', 365],
    ];

    public function handle(): int
    {
        foreach (self::RETENTION as $table => [$column, $days]) {
            $cutoff = now()->subDays($days);
            $total = 0;

            // Uzun lock'lardan kaçınmak için parça parça sil
            do {
                $deleted = DB::table($table)
                    ->where($column, '<', $cutoff)
                    ->limit(5000)
                    ->delete();

                $total += $deleted;
            } while ($deleted > 0);

            $this->line($table.': '.$total.' satır silindi (retention '.$days.' gün).');
        }

        $this->info('Analitik pruning tamamlandı.');

        return self::SUCCESS;
    }
}
