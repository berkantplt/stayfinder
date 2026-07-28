<?php

namespace App\Services\Chat\Eval;

use App\Models\Tour;
use App\Services\Chat\ChatAgent;
use App\Services\Chat\ConversationState;

/**
 * Bir altın senaryoyu bota koşturur ve DETERMİNİSTİK ihlalleri toplar.
 *
 * Denetim raporunun uyarısı: assert'ler prose üzerinde değil ARAÇ ÇAĞRI DİZİSİ
 * üzerinde olmalı. Bu yüzden buradaki kontroller izden ve katalogdan beslenir;
 * niteliksel değerlendirme ayrıca EvalJudge'a bırakılır.
 */
class ScenarioRunner
{
    public function __construct(private readonly ChatAgent $agent) {}

    /**
     * @param  array{ad: string, kullanici_mesajlari: string[]}  $senaryo
     * @return array{ad: string, turlar: array, ihlaller: string[], transkript: string}
     */
    public function run(array $senaryo): array
    {
        $gecmis = [];
        $durum = new ConversationState();
        $turlar = [];
        $ihlaller = [];

        foreach ($senaryo['kullanici_mesajlari'] ?? [] as $mesaj) {
            $sonuc = $this->agent->handle($mesaj, $gecmis, $durum);
            $durum = $sonuc['durum'];

            $gecmis[] = ['role' => 'user', 'content' => $mesaj];
            $gecmis[] = ['role' => 'assistant', 'content' => $sonuc['metin']];

            $turlar[] = [
                'kullanici' => $mesaj,
                'bot' => $sonuc['metin'],
                'iz' => $sonuc['iz'],
                'kartlar' => array_column($sonuc['turlar'], 'title'),
                'dusurulen' => $sonuc['dusurulen'],
            ];

            $ihlaller = array_merge($ihlaller, $this->determistikIhlaller($sonuc));
        }

        return [
            'ad' => $senaryo['ad'] ?? '(isimsiz)',
            'turlar' => $turlar,
            'ihlaller' => array_values(array_unique($ihlaller)),
            'transkript' => $this->transkript($turlar),
        ];
    }

    /**
     * Modelin niyetinden bağımsız, koddan ölçülebilen ihlaller.
     *
     * @return string[]
     */
    private function determistikIhlaller(array $sonuc): array
    {
        $ihlaller = [];

        // 1) Doğrulayıcı cümle düşürdüyse model uydurma sayı yazmayı DENEMİŞ
        if ($sonuc['dusurulen'] !== []) {
            $ihlaller[] = 'Uydurma sayı denemesi (doğrulayıcı düşürdü): "'
                .mb_substr($sonuc['dusurulen'][0], 0, 90, 'UTF-8').'"';
        }

        // 2) Kanıtsız boyut doldurma denemesi (kanıt disiplini ihlali)
        foreach ($sonuc['iz'] as $adim) {
            if (! empty($adim['olculemeyen_boyutlar'])) {
                $ihlaller[] = 'Kanıtsız boyut doldurma denemesi: '.implode(', ', $adim['olculemeyen_boyutlar']);
            }
        }

        // 3) Cevapta katalogda OLMAYAN tur adı geçiyor mu (doğrulayıcının
        //    kapsamadığı kontrol — burada katalog gerçeğiyle kıyaslanır)
        foreach ($this->uydurmaTurAdlari($sonuc['metin']) as $ad) {
            $ihlaller[] = 'Katalogda olmayan tur adı: "'.$ad.'"';
        }

        // 4) Kart gösterilmemesine rağmen tur adı sayması / tersi
        if ($sonuc['turlar'] === [] && preg_match('/\b(bu tur|şu tur|önerdiğim tur)\b/iu', $sonuc['metin'])) {
            $ihlaller[] = 'Kart yokken belirli bir tura atıf yapıyor';
        }

        return $ihlaller;
    }

    /**
     * Metinde tırnak içinde geçen ve katalogda karşılığı olmayan tur adları.
     * Yalnız tırnaklı ifadelere bakılır — serbest cümledeki her büyük harfli
     * öbeği tur adı saymak yanlış-pozitif üretirdi (şehir adları, tatil tipleri).
     *
     * @return string[]
     */
    private function uydurmaTurAdlari(string $metin): array
    {
        if (! preg_match_all('/["“”«]([^"“”«»]{8,80})["“”»]/u', $metin, $m)) {
            return [];
        }

        $sonuc = [];
        foreach (array_unique($m[1]) as $aday) {
            $aday = trim($aday);
            // "tur" geçmiyorsa tur adı iddiası değildir (alıntı olabilir)
            if (! preg_match('/\btur(u|lar|ları)?\b/iu', $aday)) {
                continue;
            }
            $varMi = Tour::query()
                ->whereRaw('LOWER(title) LIKE ?', ['%'.mb_strtolower($aday, 'UTF-8').'%'])
                ->exists();
            if (! $varMi) {
                $sonuc[] = $aday;
            }
        }

        return $sonuc;
    }

    private function transkript(array $turlar): string
    {
        $satirlar = [];
        foreach ($turlar as $t) {
            $satirlar[] = 'KULLANICI: '.$t['kullanici'];
            $araclar = array_map(
                fn ($a) => $a['arac'].'('.json_encode($a['args'], JSON_UNESCAPED_UNICODE).') → '
                    .($a['hata'] ?? ($a['tur_sayisi'].' tur')),
                $t['iz']
            );
            if ($araclar !== []) {
                $satirlar[] = '[ARAÇLAR] '.implode(' | ', $araclar);
            }
            $satirlar[] = 'BOT: '.$t['bot'];
            if ($t['kartlar'] !== []) {
                $satirlar[] = '[GÖSTERİLEN KARTLAR] '.implode(', ', $t['kartlar']);
            }
        }

        return implode("\n", $satirlar);
    }
}
