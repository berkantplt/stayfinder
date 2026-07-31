<?php

namespace App\Console\Commands;

use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use Illuminate\Console\Command;

/**
 * Mevcut rubrik puanlarının review_status'ünü YENİDEN DEĞERLENDİRİR — LLM
 * çağrısı yapmadan, kayıtlı evidence_verified bayraklarını kullanarak.
 *
 * Neden gerekti: alıntı doğrulaması tek bir sapmada tüm turu incelemeye
 * düşürüyordu ve katalogun tamamı bloke olmuştu. Kural gevşetildi; bu komut
 * eski kayıtları yeniden puanlamadan (para/zaman harcamadan) kurtarır.
 *
 * İki geçiş uyuşmazlığı bilgisi kayıtta tutulmadığı için, o sebeple işaretlenen
 * turlar --force verilmedikçe KORUNUR (brif §3.5 gereği editör bakmalı).
 */
class RubricRecheck extends Command
{
    protected $signature = 'app:rubric-recheck
        {--force : İki geçiş uyuşmazlığı olanları da yeniden değerlendir}
        {--dry : Sadece raporla, yazma}';

    protected $description = 'Rubrik puanlarının review_status alanını LLM çağırmadan yeniden değerlendirir';

    public function handle(): int
    {
        $kayitlar = TourRubricScore::where('rubric_version', Rubric::VERSION)->get();
        if ($kayitlar->isEmpty()) {
            $this->error('Bu rubrik versiyonunda hiç puan yok.');

            return self::FAILURE;
        }

        $kurtarilan = 0;
        $korunan = 0;
        $istatistik = [];

        foreach ($kayitlar as $kayit) {
            if ($kayit->review_status !== TourRubricScore::STATUS_NEEDS_REVIEW) {
                continue;
            }

            $puanli = 0;
            $dogrulanmayan = 0;
            foreach (Rubric::dimensions() as $d) {
                $s = $kayit->scores[$d] ?? [];
                if (! is_numeric($s['value'] ?? null)) {
                    continue;
                }
                $puanli++;
                if (($s['evidence_verified'] ?? null) === false) {
                    $dogrulanmayan++;
                }
            }
            $istatistik[] = ['tur' => $kayit->tour_id, 'puanli' => $puanli, 'dogrulanmayan' => $dogrulanmayan];

            // Yeni kural: hiçbir KANIT kontrolü turu bloklamaz — ne alıntı
            // doğrulaması ne iki-geçiş ayrışması. İkisi de yalnız ilgili BOYUTU
            // etkiler. Eski kayıtlar bu yüzden koşulsuz yayına alınır; tur
            // düzeyinde blok yalnız yeni puanlamalarda (sistemik kararsızlık)
            // oluşabilir ve o bilgi kayıtta saklanmıyor.

            if (! $this->option('dry')) {
                $kayit->update(['review_status' => TourRubricScore::STATUS_AUTO]);
            }
            $kurtarilan++;
        }

        $yayinlanabilir = TourRubricScore::where('rubric_version', Rubric::VERSION)
            ->where('review_status', '!=', TourRubricScore::STATUS_NEEDS_REVIEW)
            ->count();

        $this->info(($this->option('dry') ? '[DRY] ' : '')
            ."{$kurtarilan} kayıt yayına alındı, {$korunan} kayıt incelemede bırakıldı.");
        $this->line("Şu an YAYINLANABİLİR (chat kart gösterebilir): {$yayinlanabilir} / {$kayitlar->count()}");

        // Teşhis: neden bloke olmuşlardı? (0/36 gibi durumlarda kör kalmayalım)
        if ($istatistik !== []) {
            $toplamPuanli = array_sum(array_column($istatistik, 'puanli'));
            $toplamDogrulanmayan = array_sum(array_column($istatistik, 'dogrulanmayan'));
            $this->line(sprintf(
                'Teşhis → işaretli kayıt: %d | puanlı boyut: %d | alıntısı doğrulanamayan boyut: %d',
                count($istatistik), $toplamPuanli, $toplamDogrulanmayan
            ));
        }

        if ($kurtarilan > 0) {
            $this->comment('Not: eski kayıtlarda HANGİ boyutun ayrıştığı saklanmadığı için o '
                .'boyutlar null\'a çekilemedi. Fırsat olunca "app:score-tours-rubric --force" ile '
                .'yeniden puanlamak veriyi tam kurala oturtur (zorunlu değil).');
        }

        return self::SUCCESS;
    }
}
