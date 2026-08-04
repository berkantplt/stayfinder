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
        $kabulEdilen = [];   // boyut => normalize edilmiş kanıt

        foreach (Rubric::dimensions() as $d) {
            $agirliklar[$d] = 0.0;

            $girdi = $boyutlar[$d] ?? null;
            if (! is_array($girdi) || ! is_numeric($girdi['deger'] ?? null)) {
                continue;
            }

            // Kanıt zorunlu ve transkriptte geçmeli. Transkript verilmediyse
            // (birim test / API kullanımı) doğrulama atlanır ama kanıt yine şart.
            //
            // ALT SINIR NEDEN 3: eskiden "≥8 karakter VE boşluk içermeli" idi.
            // Bu kural tek kelimelik ama gayet net cevapları ("deniz-güneş",
            // "lüks", "sakin") elemiyordu; kullanıcı kısa yazınca hiçbir boyut
            // geçmiyor, tur_ara boş dönüyor ve bot aynı soruyu tekrarlıyordu.
            // Uydurmaya karşı asıl siper zaten transkriptte geçme şartı.
            $kanit = is_string($girdi['kanit'] ?? null) ? trim($girdi['kanit']) : '';
            if (mb_strlen($kanit, 'UTF-8') < 3) {
                $dusurulen[] = $d;

                continue;
            }
            if ($transkript !== '' && ! str_contains($transkriptNorm, self::normalize($kanit))) {
                $dusurulen[] = $d;

                continue;
            }

            $deger = max(0.0, min(100.0, (float) $girdi['deger']));
            $degerler[$d] = round($deger, 2);
            $kabulEdilen[$d] = self::normalize($kanit);
        }

        // Ağırlıklar İKİNCİ geçişte: payda yalnız KABUL EDİLEN boyutlardan
        // sayılır. İlk geçişte sayılsaydı, kanıtı transkriptte bulunamayıp
        // düşen bir boyut hayatta kalanın ağırlığını haksız yere yarıya
        // indirirdi.
        $kanitPaydasi = array_count_values($kabulEdilen);

        foreach ($kabulEdilen as $d => $kanitNorm) {
            // QuizEvaluator ile aynı formül: uçluk × beyan, clamp(min, max).
            // Tutarlılık çarpanı yok — sohbette tek kaynak var, çelişki olamaz.
            //
            // PAYLAŞIM: aynı alıntıdan türetilen boyutlar ağırlığı BÖLÜŞÜR.
            // "sessiz sakin" tek ifadesi hem tempo hem kalabaliklik'e yazılınca
            // kullanıcı tek dileği için iki kez ceza kesiliyordu; çok duraklı
            // sahil turları bu yüzden eşiğin altında kalıyordu. Bir cümle,
            // toplamda bir boyut kadar etki etsin.
            $ucluk = abs($degerler[$d] - 50) / 50;
            $beyan = in_array($d, $onemli, true) ? $bounds['beyan_carpani'] : 1.0;
            $pay = max(1, $kanitPaydasi[$kanitNorm] ?? 1);

            $agirliklar[$d] = max($bounds['min'], min($bounds['max'], ($ucluk * $beyan) / $pay));
        }

        return ['degerler' => $degerler, 'agirliklar' => $agirliklar, 'dusurulen' => $dusurulen];
    }

    /** Kanıt karşılaştırması için gevşek normalizasyon (boşluk/büyük harf toleransı). */
    private static function normalize(string $metin): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim($metin), 'UTF-8')) ?? '';
    }
}
