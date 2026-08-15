<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Landing sayfalarının veri-tabanlı içeriği.
 *
 * NEDEN LLM DEĞİL: buradaki her cümlenin arkasında canlı envanter var. Fiyat
 * değişince metin de değişir, bayatlamaz, uydurmaz ve hiçbir maliyeti yoktur.
 * Editoryal metin (gezilecek yerler, konaklama tavsiyesi) ayrı bir iş — bu
 * sınıf onun yerini almaz, sayfanın omurgasını kurar.
 *
 * ASIL SİLAH: acenta × fiyat karşılaştırması. Rakip taramasında ölçüldü —
 * MNG'nin kendi sayfasında MNG adı 40+ kez, rakip adı 0 kez; Jolly'de jolly
 * 280+ kez, rakip 0 kez geçiyor. Tek acentalı bir site bu tabloyu YAPISAL
 * OLARAK üretemez. Bizim 12 acentamız var.
 */
class LandingStats
{
    /**
     * @param  Builder  $query  Sayfanın tur sorgusu (sayfalanmamış hâli)
     * @return array<string, mixed>
     */
    public static function build(Builder $query): array
    {
        // Tek çekim: aşağıdaki tüm istatistikler aynı koleksiyondan türer.
        $tours = (clone $query)
            ->with('agency:id,name,slug')
            ->get(['id', 'agency_id', 'price', 'price_try', 'currency', 'duration_days', 'duration_nights', 'departure_date']);

        if ($tours->isEmpty()) {
            return ['var' => false];
        }

        $fiyatlar = $tours->pluck('price_try')->filter(fn ($p) => (float) $p > 0)->map(fn ($p) => (float) $p)->sort()->values();

        return [
            'var' => true,
            'turSayisi' => $tours->count(),
            'fiyat' => self::fiyatOzeti($fiyatlar),
            'acentalar' => self::acentaKarsilastirmasi($tours),
            'sureler' => self::sureDagilimi($tours),
            'aylar' => self::ayDagilimi($tours),
        ];
    }

    /**
     * @param  Collection<int, float>  $fiyatlar
     * @return array<string, mixed>|null
     */
    private static function fiyatOzeti(Collection $fiyatlar): ?array
    {
        if ($fiyatlar->isEmpty()) {
            return null;
        }

        // Medyan ortalamadan daha dürüst: tek bir lüks tur ortalamayı şişirip
        // "buradaki turlar pahalı" izlenimi veriyor.
        $adet = $fiyatlar->count();
        $orta = (int) floor(($adet - 1) / 2);
        $medyan = $adet % 2 === 0
            ? ($fiyatlar[$orta] + $fiyatlar[$orta + 1]) / 2
            : $fiyatlar[$orta];

        return [
            'min' => $fiyatlar->first(),
            'max' => $fiyatlar->last(),
            'medyan' => $medyan,
            'adet' => $adet,
        ];
    }

    /**
     * Acenta × fiyat tablosu — sayfanın rakipte olmayan kısmı.
     *
     * @param  Collection<int, \App\Models\Tour>  $tours
     * @return array<int, array<string, mixed>>
     */
    private static function acentaKarsilastirmasi(Collection $tours): array
    {
        return $tours
            ->filter(fn ($t) => $t->agency !== null)
            ->groupBy('agency_id')
            ->map(function (Collection $grup) {
                $fiyatlar = $grup->pluck('price_try')->filter(fn ($p) => (float) $p > 0)->map(fn ($p) => (float) $p);

                return [
                    'acenta' => $grup->first()->agency,
                    'turSayisi' => $grup->count(),
                    'enDusuk' => $fiyatlar->min(),
                    'enYuksek' => $fiyatlar->max(),
                ];
            })
            ->filter(fn ($satir) => $satir['enDusuk'] !== null)
            // En ucuzdan pahalıya: kullanıcının aradığı sıralama bu.
            ->sortBy('enDusuk')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, \App\Models\Tour>  $tours
     * @return array<int, array{etiket: string, adet: int}>
     */
    private static function sureDagilimi(Collection $tours): array
    {
        return $tours
            ->filter(fn ($t) => (int) $t->duration_days > 0)
            ->groupBy(fn ($t) => (int) $t->duration_days)
            ->map(fn (Collection $grup, $gun) => [
                'etiket' => $grup->first()->duration_label,
                'adet' => $grup->count(),
                'gun' => (int) $gun,
            ])
            ->sortBy('gun')
            ->values()
            ->all();
    }

    /**
     * Kalkış ayı dağılımı — "ne zaman gidilir" sorusunun veriden gelen cevabı.
     *
     * @param  Collection<int, \App\Models\Tour>  $tours
     * @return array<int, array{ay: string, adet: int}>
     */
    private static function ayDagilimi(Collection $tours): array
    {
        return $tours
            ->filter(fn ($t) => $t->departure_date !== null)
            ->groupBy(fn ($t) => $t->departure_date->month)
            ->map(fn (Collection $grup, $ay) => [
                'ay' => $grup->first()->departure_date->translatedFormat('F'),
                'adet' => $grup->count(),
                'sira' => (int) $ay,
            ])
            ->sortBy('sira')
            ->values()
            ->all();
    }

    /**
     * Aynı verilerden SSS. Uydurma soru yok — her cevabın arkasında sayı var.
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>|null
     */
    public static function faq(array $stats, string $baslik): ?array
    {
        if (($stats['var'] ?? false) !== true) {
            return null;
        }

        $sorular = [];
        $tl = fn ($n) => number_format((float) $n, 0, ',', '.').' ₺';

        if ($fiyat = $stats['fiyat']) {
            $sorular[] = [
                $baslik.' ne kadar?',
                sprintf(
                    '%s %s ile %s arasında değişiyor; ortanca fiyat %s. Bu rakamlar %d turun karşılaştırmasından geliyor ve fiyatlar değiştikçe güncellenir.',
                    $baslik,
                    $tl($fiyat['min']),
                    $tl($fiyat['max']),
                    $tl($fiyat['medyan']),
                    $fiyat['adet']
                ),
            ];
        }

        if ($acentaSayisi = count($stats['acentalar'])) {
            $adlar = collect($stats['acentalar'])->take(6)->map(fn ($s) => $s['acenta']->name)->implode(', ');
            $sorular[] = [
                $baslik.' hangi acentalarda var?',
                sprintf('Şu anda %d acentanın turu karşılaştırılıyor: %s.', $acentaSayisi, $adlar),
            ];
        }

        if ($sureler = $stats['sureler']) {
            $enYaygin = collect($sureler)->sortByDesc('adet')->first();
            $sorular[] = [
                $baslik.' kaç gün sürüyor?',
                sprintf(
                    'En yaygın süre %s (%d tur). Toplam %d farklı süre seçeneği listeleniyor.',
                    $enYaygin['etiket'],
                    $enYaygin['adet'],
                    count($sureler)
                ),
            ];
        }

        if ($aylar = $stats['aylar']) {
            $enYogun = collect($aylar)->sortByDesc('adet')->first();
            $sorular[] = [
                $baslik.' için en çok hangi aylarda tur var?',
                sprintf(
                    'En çok tur %s ayında (%d tur). Listelenen kalkışlar %s aylarına yayılıyor.',
                    $enYogun['ay'],
                    $enYogun['adet'],
                    collect($aylar)->pluck('ay')->implode(', ')
                ),
            ];
        }

        if (count($sorular) < 2) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn (array $s) => [
                '@type' => 'Question',
                'name' => $s[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $s[1]],
            ], $sorular),
        ];
    }
}
