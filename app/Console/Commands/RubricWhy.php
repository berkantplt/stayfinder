<?php

namespace App\Console\Commands;

use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use App\Services\Matching\TourMatcher;
use Illuminate\Console\Command;

/**
 * Tek turun bir tercih profiline karşı skorunu boyut boyut açar.
 *
 * Neden gerekti: "şu tur bana tam uyuyor, neden listede yok?" sorusuna
 * eşleştiricinin içini görmeden cevap verilemiyordu. Bu komut hangi eksende
 * kaç puan kaybedildiğini gösterir — LLM çağırmaz, hiçbir şey yazmaz.
 *
 * Kırılganlık notu: dağılım tablosu TourMatcher::skor() formülünü yeniden
 * kurar. Sapma sessizce oluşmasın diye toplam, gerçek skorla karşılaştırılır
 * ve tutmazsa uyarı basılır.
 */
class RubricWhy extends Command
{
    protected $signature = 'app:rubric-why
        {tur : Tur ID}
        {--profil= : boyut=deger çiftleri, virgülle (örn: tempo=20,kalabaliklik=20,doga_sehir=70)}
        {--onemli= : kullanıcının vurguladığı boyutlar, virgülle}
        {--liste : Aynı profille katalogda ilk 10 turu da sırala}';

    protected $description = 'Bir turun rubrik skorunu boyut boyut açıklar (salt okuma)';

    public function handle(TourMatcher $matcher): int
    {
        $tur = Tour::find((int) $this->argument('tur'));
        if (! $tur) {
            $this->error('Tur bulunamadı.');

            return self::FAILURE;
        }

        $degerler = $this->profiliCoz((string) $this->option('profil'));
        if ($degerler === []) {
            $this->error('--profil boş. Örnek: --profil="tempo=20,kalabaliklik=20,doga_sehir=70"');

            return self::FAILURE;
        }

        $onemli = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('onemli')))));
        $agirliklar = $this->agirliklar($degerler, $onemli);

        $puan = TourRubricScore::where('tour_id', $tur->id)
            ->where('rubric_version', Rubric::VERSION)
            ->first();

        $this->line('');
        $this->info("#{$tur->id} {$tur->title}");
        $this->line("   {$tur->destination} · {$tur->duration_days} gün · ".($tur->is_active ? 'aktif' : 'PASİF'));

        if (! $puan) {
            $this->error('Bu turun rubrik puanı yok — eşleştirmeye hiç giremez. (app:score-tours-rubric)');

            return self::FAILURE;
        }
        if ($puan->review_status === TourRubricScore::STATUS_NEEDS_REVIEW) {
            $this->warn('review_status = needs_review → tur canlı eşleştirmede KULLANILMIYOR.');
        }

        $ceza = Rubric::penalty();
        $satirlar = [];
        $cezaToplam = 0.0;
        $agirlikToplam = 0.0;
        $olculemeyen = [];

        foreach ($degerler as $d => $kullaniciDeger) {
            $w = $agirliklar[$d] ?? 0.0;
            $turDeger = $puan->value100($d);

            if ($turDeger === null) {
                $olculemeyen[] = $d;
                $satirlar[] = [Rubric::label($d), (int) $kullaniciDeger, '— (ölçülmemiş)', number_format($w, 2), '—', '—'];

                continue;
            }

            $fark = $turDeger - $kullaniciDeger;
            $katsayi = match (Rubric::type($d)) {
                'tavan' => $fark > 0 ? $ceza['tavan_asim'] : $ceza['tavan_alti'],
                'taban' => $fark < 0 ? $ceza['taban_alti'] : $ceza['taban_ustu'],
                default => $ceza['mesafe'],
            };
            $katki = $w * $katsayi * abs($fark);
            $cezaToplam += $katki;
            $agirlikToplam += $w;

            $satirlar[] = [
                Rubric::label($d),
                (int) $kullaniciDeger,
                (int) $turDeger,
                number_format($w, 2),
                '×'.number_format($katsayi, 1),
                number_format($katki, 1),
            ];
        }

        $this->line('');
        $this->table(['Boyut', 'Sen', 'Tur', 'Ağırlık', 'Ceza kat.', 'Kayıp'], $satirlar);

        $gercek = $matcher->skor($puan, $degerler, $agirliklar);
        if ($agirlikToplam <= 0) {
            $this->error('Ölçülebilen hiçbir boyut yok → skor null, tur listeye giremez.');

            return self::SUCCESS;
        }

        $hesap = (int) max(0, round(100 - ($cezaToplam / $agirlikToplam) * $ceza['olcek']));
        $esik = Rubric::resultRules()['min_score'];

        $this->line('  Ortalama ağırlıklı sapma : '.number_format($cezaToplam / $agirlikToplam, 1).' puan');
        $this->line('  Ölçek çarpanı            : ×'.$ceza['olcek']);
        $this->line('  SKOR                     : '.$hesap.'   (eşik '.$esik.' → '.($hesap >= $esik ? 'GEÇER' : 'ELENİR').')');

        if ($gercek !== $hesap) {
            $this->warn("UYARI: TourMatcher::skor() {$gercek} döndürdü, tablo {$hesap} hesapladı — formüller ayrışmış.");
        }
        if ($olculemeyen !== []) {
            $this->warn('Ölçülemeyen boyutlar skora HİÇ girmiyor: '.implode(', ', array_map(fn ($d) => Rubric::label($d), $olculemeyen))
                .' — bu turu, aynı eksende kötü puan almış turlara karşı avantajlı kılar.');
        }

        if ($this->option('liste')) {
            $this->line('');
            $this->info('Aynı profille katalogda ilk 10:');
            $sonuc = $matcher->match(
                ['degerler' => $degerler, 'agirliklar' => $agirliklar],
                ['top_n' => 10, 'cesitlilik' => false],
            );
            $sira = [];
            foreach ($sonuc['tours'] as $i => $t) {
                $sira[] = [$i + 1, $t['id'], mb_substr((string) $t['title'], 0, 52, 'UTF-8'), $t['match_percent'] ?? '—'];
            }
            $this->table(['#', 'ID', 'Tur', 'Skor'], $sira);
            $this->line('  Eşleşen toplam aday: '.$sonuc['toplam_eslesme']);
        }

        return self::SUCCESS;
    }

    /** @return array<string, float> */
    private function profiliCoz(string $ham): array
    {
        $degerler = [];
        foreach (explode(',', $ham) as $parca) {
            if (! str_contains($parca, '=')) {
                continue;
            }
            [$d, $v] = array_map('trim', explode('=', $parca, 2));
            if (in_array($d, Rubric::dimensions(), true) && is_numeric($v)) {
                $degerler[$d] = max(0.0, min(100.0, (float) $v));
            } elseif ($d !== '') {
                $this->warn("Bilinmeyen boyut atlandı: {$d}");
            }
        }

        return $degerler;
    }

    /**
     * LlmProfileBuilder ile aynı formül. Burada kanıt disiplini yok: CLI'de
     * profili zaten operatör elle veriyor, doğrulanacak bir alıntı yok.
     *
     * @return array<string, float>
     */
    private function agirliklar(array $degerler, array $onemli): array
    {
        $bounds = Rubric::weightBounds();
        $agirliklar = [];
        foreach ($degerler as $d => $v) {
            $ucluk = abs($v - 50) / 50;
            $beyan = in_array($d, $onemli, true) ? $bounds['beyan_carpani'] : 1.0;
            $agirliklar[$d] = max($bounds['min'], min($bounds['max'], $ucluk * $beyan));
        }

        return $agirliklar;
    }
}
