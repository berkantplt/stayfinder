<?php

namespace App\Console\Commands;

use App\Models\TourRubricScore;
use App\Services\Chat\Eval\EvalJudge;
use App\Services\Chat\Eval\ScenarioRunner;
use App\Services\Matching\Rubric;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Altın konuşma setini bota koşturur (CHATBOT_V2.md §12).
 *
 * "Yazılacak ilk şey kod değil eval seti" — v1'in sazan sarmalının sebebi
 * iyi/kötüyü ölçen bir şeyin olmamasıydı. Her prompt/model değişikliğinden
 * sonra bu komut koşulur; geçmeyen sürüm yayınlanmaz.
 *
 * DİKKAT: gerçek LLM çağrısı yapar (senaryo başına birkaç tur + hakem).
 */
class ChatEval extends Command
{
    protected $signature = 'app:chat-eval
        {--only= : Yalnız bu numaralı senaryo(lar), virgülle: 3,7}
        {--limit=0 : İlk N senaryo (0 = hepsi)}
        {--no-judge : LLM hakemi atla, yalnız deterministik ihlalleri göster}
        {--json= : Raporu bu dosyaya yaz}';

    protected $description = 'Chatbot v2 altın senaryo setini koşturur ve geçti/kaldı raporu üretir';

    public function handle(ScenarioRunner $runner, EvalJudge $judge): int
    {
        $yol = resource_path('eval/chatbot-v2-senaryolar.json');
        if (! File::exists($yol)) {
            $this->error('Senaryo dosyası yok: '.$yol);

            return self::FAILURE;
        }

        $senaryolar = json_decode(File::get($yol), true)['senaryolar'] ?? [];
        if ($secim = $this->option('only')) {
            $numaralar = array_map('intval', explode(',', $secim));
            $senaryolar = array_values(array_filter($senaryolar, fn ($s) => in_array($s['no'] ?? 0, $numaralar, true)));
        }
        if ($limit = (int) $this->option('limit')) {
            $senaryolar = array_slice($senaryolar, 0, $limit);
        }
        if ($senaryolar === []) {
            $this->error('Koşulacak senaryo bulunamadı.');

            return self::FAILURE;
        }

        // Ön koşul: puanlanmış tur yoksa tur_ara boş döner, sonuçlar anlamsız olur
        $puanli = TourRubricScore::where('rubric_version', Rubric::VERSION)
            ->where('review_status', '!=', TourRubricScore::STATUS_NEEDS_REVIEW)
            ->count();
        if ($puanli === 0) {
            $this->warn('⚠️  Yayınlanabilir rubrik puanı olan tur YOK — tur_ara boş dönecek.');
            $this->warn('   Önce: php artisan app:score-tours-rubric');
            if (! $this->confirm('Yine de devam edilsin mi?', false)) {
                return self::FAILURE;
            }
        } else {
            $this->line("Katalog: {$puanli} puanlanmış tur");
        }

        $this->line(count($senaryolar).' senaryo koşulacak (gerçek LLM çağrısı yapılır).');
        $this->newLine();

        $sonuclar = [];
        $gecen = 0;
        $bar = $this->output->createProgressBar(count($senaryolar));
        $bar->start();

        foreach ($senaryolar as $senaryo) {
            try {
                $kosum = $runner->run($senaryo);
                $karar = $this->option('no-judge')
                    ? ['gecti' => $kosum['ihlaller'] === [], 'gerekce' => $kosum['ihlaller'] === [] ? 'Deterministik ihlal yok' : implode(' | ', $kosum['ihlaller'])]
                    : $judge->judge($senaryo, $kosum['transkript'], $kosum['ihlaller']);
            } catch (\Throwable $e) {
                $kosum = ['transkript' => '', 'ihlaller' => ['çalıştırma hatası']];
                $karar = ['gecti' => false, 'gerekce' => 'Hata: '.$e->getMessage()];
            }

            $gecen += $karar['gecti'] ? 1 : 0;
            $sonuclar[] = [
                'no' => $senaryo['no'] ?? null,
                'ad' => $senaryo['ad'] ?? '',
                'madde' => $senaryo['hangi_madde'] ?? '',
                'gecti' => $karar['gecti'],
                'gerekce' => $karar['gerekce'],
                'ihlaller' => $kosum['ihlaller'],
                'transkript' => $kosum['transkript'],
            ];
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['#', 'Senaryo', 'Sonuç', 'Gerekçe'],
            array_map(fn ($s) => [
                $s['no'],
                mb_substr($s['ad'], 0, 40, 'UTF-8'),
                $s['gecti'] ? '✅' : '❌',
                mb_substr($s['gerekce'], 0, 70, 'UTF-8'),
            ], $sonuclar)
        );

        $toplam = count($sonuclar);
        $oran = $toplam > 0 ? round($gecen / $toplam * 100) : 0;
        $this->newLine();
        $this->line("SONUÇ: {$gecen}/{$toplam} geçti (%{$oran})");

        if ($dosya = $this->option('json')) {
            File::put($dosya, json_encode([
                'model' => config('ai.chat_agent_model'),
                'gecen' => $gecen, 'toplam' => $toplam,
                'sonuclar' => $sonuclar,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->line('Rapor: '.$dosya);
        }

        return $gecen === $toplam ? self::SUCCESS : self::FAILURE;
    }
}
