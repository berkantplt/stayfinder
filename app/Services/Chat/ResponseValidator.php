<?php

namespace App\Services\Chat;

/**
 * Cevabın araç gerçekleriyle uyumunu deterministik doğrular (karar 1).
 *
 * TASARIM KARARI — neden "modeli sustur" değil: fiyatı/süreyi kartta bırakıp
 * modele hiç sayı söyletmemek pazarlama dilini jenerikleştiriyor ("bu tur tam
 * senin tarif ettiğin his") — v1'in şikayet edilen sesi tam olarak buydu.
 * Bunun yerine model gerçekleri serbestçe kullanır, burada denetlenir.
 *
 * NE DENETLENİR: para mertebesindeki sayılar (uydurulduğunda somut zarar veren).
 * NE DENETLENMEZ: tatil TİPİ ("tarif ettiğin şey villa tatili") — şartname
 * madde 3 bunu açıkça istiyor; naif bir "araç sonucunda yoksa reddet" kuralı
 * villa örneğini yanlış-pozitif olarak öldürürdü. Tarih/saat/süre/yıl da denetim
 * dışı: bunlar rakam yığını olarak okunduğunda sahte "fiyat" üretiyorlardı.
 *
 * İhlalde cümle düşürülür, cevap yeniden üretilmez: tam yeniden üretim hem
 * pahalı hem kullanıcı yazının silinip yeniden yazılmasını görür.
 *
 * BİLİNEN SINIR: izinli sayı havuzu alan-bağımsızdır — A turunun fiyatını B
 * turuna yazmak denetimden geçer. Kart verileri kod tarafından basıldığı için
 * pratik zarar sınırlı; alan bazlı eşleme ileri sürüm işi.
 */
class ResponseValidator
{
    /** Para mertebesi eşiği: altındaki sayılar (süre, kişi, gece) denetlenmez. */
    private const PARA_ESIGI = 1000;

    /** Göreli tolerans: 45.900 için ~±230, ucuz turda orantılı kalır. */
    private const TOLERANS_ORANI = 0.005;

    /**
     * @param  string  $cevap  modelin ürettiği metin
     * @param  array<int, array<string, mixed>>  $aracSonuclari  bu turda dönen ham araç çıktıları
     * @param  float[]  $ekIzinli  hafızadan gelen sayılar (araçsız turda cümle düşmesin)
     * @return array{metin: string, dusurulen: string[]}
     */
    public function temizle(string $cevap, array $aracSonuclari, array $ekIzinli = []): array
    {
        if (trim($cevap) === '') {
            return ['metin' => '', 'dusurulen' => []];
        }

        $izinli = array_merge($this->izinliSayilar($aracSonuclari), array_map('floatval', $ekIzinli));

        // Ayraçları YAKALAYARAK böl: satır sonları ve paragraflar korunsun.
        // Ondalık/sıra sayısından sonra bölme (`3. Gün`) lookbehind ile engellenir.
        $parcalar = preg_split('/(?<=[.!?…])(?<!\d\.)(\s+)/u', $cevap, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parcalar === false || $parcalar === []) {
            // Bozuk UTF-8'de fail-open olmasın: sayı denetimini metnin tamamına uygula
            return $this->ihlalVar($cevap, $izinli)
                ? ['metin' => '', 'dusurulen' => [trim($cevap)]]
                : ['metin' => $cevap, 'dusurulen' => []];
        }

        $kalan = '';
        $dusurulen = [];
        $bekleyenAyrac = '';

        foreach ($parcalar as $i => $parca) {
            // Tek indeksler yakalanan ayraçlar (boşluk/satır sonu)
            if ($i % 2 === 1) {
                $bekleyenAyrac = $parca;

                continue;
            }
            if (trim($parca) === '') {
                continue;
            }

            if ($this->ihlalVar($parca, $izinli)) {
                $dusurulen[] = trim($parca);
                // Düşen cümlenin ayracı da düşsün, çift boşluk kalmasın
                $bekleyenAyrac = '';

                continue;
            }

            $kalan .= ($kalan === '' ? '' : $bekleyenAyrac).$parca;
            $bekleyenAyrac = '';
        }

        return ['metin' => rtrim($kalan), 'dusurulen' => $dusurulen];
    }

    private function ihlalVar(string $cumle, array $izinli): bool
    {
        foreach ($this->cumledekiParaSayilari($cumle) as $sayi) {
            if (! $this->izinliMi($sayi, $izinli)) {
                return true;
            }
        }

        return false;
    }

    private function izinliMi(float $sayi, array $izinli): bool
    {
        foreach ($izinli as $ref) {
            if ($ref <= 0) {
                continue;
            }
            if (abs($sayi - $ref) <= max(1.0, $ref * self::TOLERANS_ORANI)) {
                return true;
            }
            // "46 bin" gibi yuvarlanmış ifade: en yakın bine yuvarlanmış hali
            if ($ref >= self::PARA_ESIGI && abs($sayi - round($ref / 1000) * 1000) < 1.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Araç çıktılarındaki sayılar. String değerler DB biçimindedir ("25000.00"),
     * bu yüzden rakam-sıyırma DEĞİL sayısal ayrıştırma yapılır — sıyırma
     * "25000.00"ı 2500000 yapıp gerçek fiyatı yasaklıyordu.
     *
     * @return float[]
     */
    private function izinliSayilar(array $aracSonuclari): array
    {
        $sayilar = [];

        array_walk_recursive($aracSonuclari, function ($deger) use (&$sayilar) {
            if (is_bool($deger) || $deger === null) {
                return;
            }
            if (is_int($deger) || is_float($deger)) {
                $sayilar[] = (float) $deger;

                return;
            }
            if (is_string($deger)) {
                if (is_numeric($deger)) {
                    $sayilar[] = (float) $deger;   // "25000.00" → 25000.0
                }
                foreach ($this->cumledekiParaSayilari($deger) as $s) {
                    $sayilar[] = $s;               // metin içindeki "45.900 TL"
                }
            }
        });

        return array_values(array_unique($sayilar));
    }

    /**
     * Cümledeki PARA mertebesindeki sayılar. Tarih (28.07.2026), saat (09:30),
     * yıl ve küçük sayılar hariç — bunlar rakam yığını olarak okunduğunda sahte
     * fiyat üretip doğru cümleleri düşürüyordu.
     *
     * @return float[]
     */
    private function cumledekiParaSayilari(string $metin): array
    {
        // Tarih ve saatleri önce maskele
        $temiz = preg_replace('/\b\d{1,2}[.\/]\d{1,2}[.\/]\d{2,4}\b/u', ' ', $metin) ?? $metin;
        $temiz = preg_replace('/\b\d{1,2}:\d{2}\b/u', ' ', $temiz) ?? $temiz;

        $sonuc = [];

        // "46 bin", "1,5 milyon" gibi ifadeler
        if (preg_match_all('/(\d+(?:[.,]\d+)?)\s*(bin|milyon)\b/iu', $temiz, $m, PREG_SET_ORDER)) {
            foreach ($m as $eslesme) {
                $taban = (float) str_replace(',', '.', $eslesme[1]);
                $sonuc[] = $taban * (mb_strtolower($eslesme[2], 'UTF-8') === 'milyon' ? 1000000 : 1000);
            }
            $temiz = preg_replace('/(\d+(?:[.,]\d+)?)\s*(bin|milyon)\b/iu', ' ', $temiz) ?? $temiz;
        }

        if (preg_match_all('/\d[\d.,]*/u', $temiz, $m)) {
            foreach ($m[0] as $ham) {
                $deger = $this->trSayiyaCevir($ham);
                if ($deger === null || $deger < self::PARA_ESIGI) {
                    continue;
                }
                // Ayraçsız 4 haneli yıl (2026) fiyat sayılmaz
                if ($deger >= 1900 && $deger <= 2100 && ! str_contains($ham, '.') && ! str_contains($ham, ',')) {
                    continue;
                }
                $sonuc[] = $deger;
            }
        }

        return array_values(array_unique($sonuc));
    }

    /** Türkçe biçim: nokta binlik ("45.900"), virgül ondalık ("45,5"). */
    private function trSayiyaCevir(string $ham): ?float
    {
        $ham = rtrim($ham, '.,');
        if ($ham === '') {
            return null;
        }

        // 45.900 / 1.234.567 → binlik ayraçlı
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/u', $ham)) {
            return (float) str_replace(',', '.', str_replace('.', '', $ham));
        }
        // 45900,50 → virgül ondalık
        if (preg_match('/^\d+(,\d+)?$/u', $ham)) {
            return (float) str_replace(',', '.', $ham);
        }
        // 25000.00 / 25000 → düz sayı
        if (is_numeric($ham)) {
            return (float) $ham;
        }

        return null;
    }
}
