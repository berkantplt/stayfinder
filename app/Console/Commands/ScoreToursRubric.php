<?php

namespace App\Console\Commands;

use App\Services\Matching\RubricCoverage;
use Illuminate\Console\Command;

/**
 * Rubrik puanı eksik/bayat turları kuyruğa alır. Yeni/güncellenen turları
 * TourObserver otomatik yakalar; bu komut geriye dönük tarama içindir.
 *
 * Durum hesabı ve kuyruğa alma RubricCoverage'da — admin "Tur Puanlama"
 * sayfasıyla aynı sayıları basar.
 */
class ScoreToursRubric extends Command
{
    protected $signature = 'app:score-tours-rubric
        {--all : Pasif turlar dahil}
        {--force : input_hash aynı olsa da yeniden puanla}
        {--limit=0 : En fazla bu kadar tur (0 = sınırsız)}
        {--dry : Sadece sayıyı göster}';

    protected $description = 'Rubrik (v1) puanı eksik veya bayat turlar için ScoreTourRubricJob kuyruğa alır';

    public function handle(RubricCoverage $kapsam): int
    {
        $pasifDahil = (bool) $this->option('all');
        $rapor = $kapsam->rapor($pasifDahil);
        $ozet = $rapor['ozet'];

        // Durum özeti: "kaç tur chat'te kart olarak çıkabilir" sorusunun cevabı.
        // (Sunucuda tinker --execute tırnak sorunları çıkardığı için buraya kondu.)
        $this->line(sprintf(
            'DURUM → %s tur: %d | puanlı: %d | YAYINLANABİLİR (chat kart gösterebilir): %d | editör onayı bekleyen: %d | puansız: %d | bayat: %d | kuyrukta: %d | başarısız: %d',
            $pasifDahil ? 'toplam' : 'aktif',
            $ozet['tur'],
            $ozet['puanli'] + $ozet['bayat'] + $ozet['incelemede'],
            $ozet['yayinlanabilir'],
            $ozet['incelemede'],
            $ozet['puansiz'],
            $ozet['bayat'],
            $ozet['kuyrukta'],
            $ozet['basarisiz'],
        ));

        $ids = $rapor['satirlar']
            ->filter(fn (array $satir) => $this->option('force')
                || in_array($satir['durum'], [RubricCoverage::PUANSIZ, RubricCoverage::BAYAT], true))
            ->map(fn (array $satir) => $satir['tour']->id)
            ->values();

        if ($limit = (int) $this->option('limit')) {
            $ids = $ids->take($limit);
        }

        if ($ids->isEmpty()) {
            $this->info('Puanı eksik/bayat tur yok.');

            return self::SUCCESS;
        }
        if ($this->option('dry')) {
            $this->info($ids->count().' tur kuyruğa alınacaktı (--dry). Not: tur başına 2 LLM geçişi yapılır.');

            return self::SUCCESS;
        }

        $dispatched = $kapsam->kuyrugaAl($ids, (bool) $this->option('force'));

        $this->info($dispatched.' tur rubrik puanlaması için kuyruğa alındı (tur başına 2 geçiş).');

        return self::SUCCESS;
    }
}
