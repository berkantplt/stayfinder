<?php

namespace App\Services\Chat;

use App\Services\Matching\Rubric;

/**
 * Modelin serbest metinden çıkardığı boyut değerlerini TourMatcher profiline
 * çevirir. QuizEvaluator'ın ağırlık türetme kurallarının sohbet karşılığı.
 *
 * KANIT DİSİPLİNİ (kritik): model her boyut için kullanıcının KENDİ
 * cümlesinden alıntı vermek zorunda. Alıntı konuşma transkriptinde gerçekten
 * geçmiyorsa o boyut DÜŞÜRÜLÜR. Tur tarafında uyguladığımız "kanıt yoksa null"
 * kuralının kullanıcı tarafındaki karşılığı — yoksa model 10 boyutun 10'unu da
 * doldurur ve "sessiz sakin tatil" cümlesinden gastronomi puanı üretir.
 */
class LlmProfileBuilder
{
    /**
     * @param  array<string, mixed>  $boyutlar  {boyut: {deger: 0-100, kanit: "kullanıcının cümlesi"}}
     * @param  string[]  $onemli  kullanıcının vurguladığı boyutlar (ağırlık çarpanı)
     * @param  string  $transkript  kanıt doğrulaması için konuşmanın kullanıcı tarafı
     * @return array{degerler: array<string,float>, agirliklar: array<string,float>, dusurulen: string[]}
     */
    public function build(array $boyutlar, array $onemli = [], string $transkript = ''): array
    {
        $bounds = Rubric::weightBounds();
        $transkriptNorm = self::normalize($transkript);

        $degerler = [];
        $agirliklar = [];
        $dusurulen = [];

        foreach (Rubric::dimensions() as $d) {
            $agirliklar[$d] = 0.0;

            $girdi = $boyutlar[$d] ?? null;
            if (! is_array($girdi) || ! is_numeric($girdi['deger'] ?? null)) {
                continue;
            }

            // Kanıt zorunlu ve transkriptte geçmeli. Transkript verilmediyse
            // (birim test / API kullanımı) doğrulama atlanır ama kanıt yine şart.
            // Kanıt anlamlı uzunlukta olmalı: tek harf/kelime alıntı transkriptte
            // her zaman geçer ve disiplini pratikte atlatırdı
            $kanit = is_string($girdi['kanit'] ?? null) ? trim($girdi['kanit']) : '';
            if (mb_strlen($kanit, 'UTF-8') < 8 || ! str_contains($kanit, ' ')) {
                $dusurulen[] = $d;

                continue;
            }
            if ($transkript !== '' && ! str_contains($transkriptNorm, self::normalize($kanit))) {
                $dusurulen[] = $d;

                continue;
            }

            $deger = max(0.0, min(100.0, (float) $girdi['deger']));
            $degerler[$d] = round($deger, 2);

            // QuizEvaluator ile aynı formül: uçluk × beyan, clamp(min, max).
            // Tutarlılık çarpanı yok — sohbette tek kaynak var, çelişki olamaz.
            $ucluk = abs($deger - 50) / 50;
            $beyan = in_array($d, $onemli, true) ? $bounds['beyan_carpani'] : 1.0;

            $agirliklar[$d] = max($bounds['min'], min($bounds['max'], $ucluk * $beyan));
        }

        return ['degerler' => $degerler, 'agirliklar' => $agirliklar, 'dusurulen' => $dusurulen];
    }

    /** Kanıt karşılaştırması için gevşek normalizasyon (boşluk/büyük harf toleransı). */
    private static function normalize(string $metin): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($metin), 'UTF-8')) ?? '';
    }
}
