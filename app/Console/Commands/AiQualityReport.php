<?php

namespace App\Console\Commands;

use App\Models\AiSearchLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Haftalık AI kalite raporu: kötü cevap imzalarını (0 sonuç, gevşetilmiş
 * aramalar, tıklamasız gösterimler, reddetme yığılmaları) toplar — sorunlar
 * kullanıcı sessizce terk etmeden görünür olsun. LLM'siz, sıfır maliyet.
 */
class AiQualityReport extends Command
{
    protected $signature = 'ai:quality-report {--days=7}';

    protected $description = 'AI arama kalite raporu (0-sonuç, gevşetme, tıklamasız oranı).';

    public function handle(): void
    {
        $since = now()->subDays((int) $this->option('days'));
        $logs = AiSearchLog::where('created_at', '>=', $since)->get();

        if ($logs->isEmpty()) {
            $this->info('Bu dönemde arama yok.');

            return;
        }

        $total = $logs->count();
        $zeroResult = $logs->filter(fn ($l) => empty($l->result_tour_ids));
        $relaxed = $logs->filter(fn ($l) => ! empty(($l->applied_filters ?? [])['relaxation'] ?? null));
        $clicked = $logs->whereNotNull('selected_tour_id');

        $report = [
            'donem_gun' => (int) $this->option('days'),
            'toplam_arama' => $total,
            'sifir_sonuc' => $zeroResult->count(),
            'gevsetilmis' => $relaxed->count(),
            'tiklama_orani' => round($clicked->count() / max(1, $total) * 100, 1).'%',
        ];

        $this->table(array_keys($report), [array_values($report)]);

        // En sorunlu sorgular: 0 sonuç verenlerin ham metinleri (tema tespiti için)
        $worstQueries = $zeroResult->pluck('raw_query')->take(10)->values()->all();
        if (! empty($worstQueries)) {
            $this->comment('0 sonuç veren sorgular (ilk 10):');
            foreach ($worstQueries as $q) {
                $this->line('  - '.$q);
            }
        }

        // Reddetme yığılması
        $rejections = $logs->flatMap(fn ($l) => (array) ($l->rejected_tour_ids ?? []))
            ->map(fn ($r) => is_array($r) ? ($r['reason'] ?? 'belirtilmemiş') : 'belirtilmemiş')
            ->countBy();
        if ($rejections->isNotEmpty()) {
            $this->comment('Reddetme sebepleri: '.$rejections->map(fn ($c, $r) => "$r: $c")->implode(', '));
        }

        $payload = $report + ['sifir_sonuc_sorgular' => $worstQueries, 'olusturma' => now()->toDateTimeString()];
        Cache::put('ai:last_quality_report', $payload, now()->addDays(14));
        Log::info('[AiQualityReport] haftalık rapor', $payload);

        if ($total < 30) {
            $this->comment("Örneklem küçük (n={$total}) — oranları yön göstergesi olarak oku.");
        }
    }
}
