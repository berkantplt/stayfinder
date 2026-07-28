<?php

namespace App\Services\Matching;

use Illuminate\Support\Facades\File;

/**
 * B işi (brif §4-5): test cevaplarını kullanıcı vektörüne (0-100) ve
 * ağırlıklara çevirir. Arketip/persona YOK — şıklar boyutlara doğrudan
 * değer yazar; "fark etmez" boyutu eşleştirmeden tamamen çıkarır.
 * Sonuç OTURUM bazlıdır, kalıcı profile yazılmaz (kapsam kısıtı).
 */
class QuizEvaluator
{
    public const QUIZ_VERSION = 'v1';

    private static ?array $quizCache = null;

    public static function definition(): array
    {
        return self::$quizCache ??= json_decode(
            File::get(resource_path('quiz/'.self::QUIZ_VERSION.'.json')),
            true,
            flags: JSON_THROW_ON_ERROR
        );
    }

    /**
     * @param  array<string, mixed>  $answers  q1..q7 → şık index; q8 → index dizisi (≤2)
     * @return array{degerler: array<string,float>, agirliklar: array<string,float>, filtre: array<string,mixed>}
     */
    public function evaluate(array $answers): array
    {
        $quiz = self::definition();
        $degerler = [];        // boyut → [yazılan 0-100 değerler] (tutarlılık için liste)
        $kapali = [];          // "fark etmez" ile devre dışı boyutlar
        $filtre = [];

        foreach ($quiz['tercih_sorulari'] as $soru) {
            // Sayısal string de kabul edilir: 'integer' doğrulaması "3" gibi
            // değerleri geçirir ve katı is_int() kontrolü cevabı sessizce yutardı.
            $idx = $answers[$soru['key']] ?? null;
            $secenek = is_numeric($idx) ? ($soru['secenekler'][(int) $idx] ?? null) : null;
            if (! $secenek) {
                continue; // cevaplanmamış soru: yazdığı boyutlar değersiz kalır
            }

            foreach ($secenek['agirliksizlastir'] ?? [] as $d) {
                $kapali[$d] = true;
            }
            foreach ($secenek['yazar'] ?? [] as $d => $v) {
                $degerler[$d][] = (float) $v;
            }
            foreach ($secenek['filtre'] ?? [] as $k => $v) {
                $filtre[$k] = $v;
            }
        }

        // 8. soru: en fazla 2 seçim, ağırlık çarpanı (brif §4.3)
        $beyanlar = [];
        $onem = $quiz['onem_sorusu'];
        foreach (array_slice((array) ($answers[$onem['key']] ?? []), 0, $onem['max_secim']) as $i) {
            $boyut = $onem['secenekler'][(int) $i]['boyut'] ?? null;
            if ($boyut) {
                $beyanlar[$boyut] = true;
            }
        }

        // Nihai değer: aynı boyuta birden çok soru yazdıysa ortalama;
        // tutarlılık cezası ayrışma > eşikse (brif §5)
        $bounds = Rubric::weightBounds();
        $kullanici = [];
        $agirliklar = [];

        foreach (Rubric::dimensions() as $d) {
            if (isset($kapali[$d]) || empty($degerler[$d])) {
                $agirliklar[$d] = 0.0;
                continue;
            }

            $liste = $degerler[$d];
            $kullanici[$d] = round(array_sum($liste) / count($liste), 2);

            $ucluk = abs($kullanici[$d] - 50) / 50;
            $beyan = isset($beyanlar[$d]) ? $bounds['beyan_carpani'] : 1.0;
            $tutarlilik = (count($liste) > 1 && (max($liste) - min($liste)) > $bounds['tutarsizlik_esigi'])
                ? $bounds['tutarsizlik_carpani'] : 1.0;

            $agirliklar[$d] = max($bounds['min'], min($bounds['max'], $ucluk * $beyan * $tutarlilik));
        }

        // Normalizasyon: sıfır olmayan ağırlıkların toplamı = aktif boyut sayısı
        $aktif = array_keys(array_filter($agirliklar, fn ($w) => $w > 0));
        $toplam = array_sum(array_intersect_key($agirliklar, array_flip($aktif)));
        if ($toplam > 0) {
            $olcek = count($aktif) / $toplam;
            foreach ($aktif as $d) {
                $agirliklar[$d] *= $olcek;
            }
        }

        return ['degerler' => $kullanici, 'agirliklar' => $agirliklar, 'filtre' => $filtre];
    }
}
