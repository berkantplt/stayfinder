<?php

namespace App\Services\AiSearch;

/**
 * Sohbet mesajlarındaki hassas numaraları (kredi kartı, TC kimlik) maskeleyen
 * güvenlik katmanı. Kullanıcı istemeden yapıştırsa bile bu veriler DB loglarına
 * ve OpenAI'ye HAM olarak asla gitmez (KVKK/PCI hijyeni).
 *
 * Telefon numaraları BİLEREK maskelenmez: geri-arama/lead akışının meşru
 * girdisidir. Ayrım deterministik: TC 11 hanedir ve 0 ile BAŞLAYAMAZ; cep
 * telefonu ise 0 ile başlar (05xx…) ya da +90 önekiyle 12 haneye çıkar —
 * her iki durumda TC deseni eşleşmez. Kart için Luhn, TC için resmi
 * checksum doğrulanır ki rezervasyon kodu / uzun ID'ler yanlışlıkla
 * maskelenmesin.
 */
final class PiiMasker
{
    /**
     * @return array{text: string, types: array<int, string>} types: 'kart' | 'tc'
     */
    public static function mask(string $text): array
    {
        $types = [];

        // Kredi kartı: 13-19 hane, boşluk/tire gruplu olabilir; Luhn şart.
        $masked = preg_replace_callback('/(?<![\d])(?:\d[ \-]?){12,18}\d(?![\d])/', function (array $m) use (&$types) {
            $digits = (string) preg_replace('/\D+/', '', $m[0]);
            $len = strlen($digits);
            if ($len < 13 || $len > 19 || ! self::luhnValid($digits)) {
                return $m[0];
            }
            $types[] = 'kart';

            return '**** **** **** '.substr($digits, -4);
        }, $text);
        $text = $masked ?? $text;

        // TC kimlik: 11 hane, ilk hane 1-9, resmi checksum.
        $masked = preg_replace_callback('/(?<![\d])[1-9]\d{10}(?![\d])/', function (array $m) use (&$types) {
            if (! self::tcValid($m[0])) {
                return $m[0];
            }
            $types[] = 'tc';

            return '*********** (kimlik no maskelendi)';
        }, $text);
        $text = $masked ?? $text;

        return ['text' => $text, 'types' => array_values(array_unique($types))];
    }

    private static function luhnValid(string $digits): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }

    private static function tcValid(string $digits): bool
    {
        if (strlen($digits) !== 11) {
            return false;
        }
        $d = array_map('intval', str_split($digits));
        $odd = $d[0] + $d[2] + $d[4] + $d[6] + $d[8];
        $even = $d[1] + $d[3] + $d[5] + $d[7];
        $check10 = ((($odd * 7) - $even) % 10 + 10) % 10;
        $check11 = array_sum(array_slice($d, 0, 10)) % 10;

        return $d[9] === $check10 && $d[10] === $check11;
    }
}
