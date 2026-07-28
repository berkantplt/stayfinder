<?php

namespace App\Services\Matching;

use App\Models\Tour;
use App\Models\TourRubricScore;
use Illuminate\Support\Collection;

/**
 * Brif §2 + §6: sert filtreler → asimetrik cezalı eşleştirme → sonuç kuralları.
 *
 * - tavan boyutta tur kullanıcıyı AŞARSA ×2.5, altında ×0.4 (30 isteyene 80'lik
 *   trekking tatili mahveder; tersi sadece biraz sıkıcıdır). taban tersi (konfor).
 * - value null olan boyut o tur için tamamen devre dışı (ne ceza ne avantaj).
 * - İlk 3 gösterilir, %60 tabanı altı "önerilen" sayılmaz, doga_sehir bandından
 *   en fazla 2 tur, eşitlik deterministik kırılır (en yakın kalkış → id).
 *
 * Uyarlama notları (dosya-tabanlı brif → Laravel/DB):
 * - Eşitlik kırma brif'te marj/kontenjan ister; katalogda bu alanlar yok →
 *   deterministik vekil: en yakın kalkış tarihi, sonra id. Rastgele kırılmaz.
 * - Çocuk yaş sınırı ve erişilebilirlik testte toplanır ama tur tarafında
 *   alan olmadığı için v1'de uygulanamaz (katalog alanı eklenince devreye girer).
 */
class TourMatcher
{
    /**
     * @param  array{degerler: array<string,float>, agirliklar: array<string,float>, filtre: array<string,mixed>}  $profil
     * @return array{tours: array, relaxation_notes: string[], below_floor: bool}
     */
    public function match(array $profil, array $baglam = []): array
    {
        $rules = Rubric::resultRules();
        $notlar = [];

        // Yalnız YAYINLANABİLİR puanı olan turlar aday olabilir: needs_review
        // işaretliler editör onayına kadar canlıda kullanılmaz (brif §3.5) ve
        // aday sayımı da bu küme üzerinden yapılır (gevşetme tetiği doğru çalışsın).
        $scores = TourRubricScore::where('rubric_version', Rubric::VERSION)
            ->where('review_status', '!=', TourRubricScore::STATUS_NEEDS_REVIEW)
            ->get()
            ->keyBy('tour_id');

        [$adaylar, $notlar] = $this->hardFilter($baglam, $rules, $notlar, $scores->keys()->all());

        $puanli = $adaylar->map(function (Tour $tour) use ($scores, $profil) {
            $rubricScore = $scores->get($tour->id);
            $skor = $rubricScore ? $this->skor($rubricScore, $profil['degerler'], $profil['agirliklar']) : null;
            if ($skor === null) {
                return null; // puanlanmamış tur önerilmez
            }
            $tour->match_score = $skor;
            $tour->rubric = $rubricScore;

            return $tour;
        })->filter()->values();

        // Eşitlik kırma: skor ↓, en yakın kalkış ↑, id ↑ (deterministik)
        $sirali = $puanli->sort(function ($a, $b) {
            if ($b->match_score !== $a->match_score) {
                return $b->match_score <=> $a->match_score;
            }
            $aDate = $a->departure_date?->timestamp ?? PHP_INT_MAX;
            $bDate = $b->departure_date?->timestamp ?? PHP_INT_MAX;

            return $aDate !== $bDate ? $aDate <=> $bDate : $a->id <=> $b->id;
        })->values();

        // %60 tabanı (brif §6): taban altı turlar "önerilen" diye gösterilmez.
        // Taban üstü hiç yoksa en yakınlar gösterilir ve below_floor işaretlenir.
        $tabanUstu = $sirali->filter(fn ($t) => $t->match_score >= $rules['min_score'])->values();
        $belowFloor = $tabanUstu->isEmpty();
        $havuz = $belowFloor ? $sirali : $tabanUstu;

        // Çeşitlilik: ilk 3'te aynı doga_sehir bandından en fazla 2
        $secilen = $this->applyDiversity($havuz, $rules);

        return [
            'tours' => $secilen->map(fn ($t) => $this->card($t, $profil, $baglam))->all(),
            'relaxation_notes' => $notlar,
            'below_floor' => $belowFloor && $secilen->isNotEmpty(),
        ];
    }

    /** Brif §6 formülü birebir. Aktif ağırlık yoksa null. */
    public function skor(TourRubricScore $rubricScore, array $kullanici, array $agirliklar): ?int
    {
        $penalty = Rubric::penalty();
        $cezaToplam = 0.0;
        $agirlikToplam = 0.0;

        foreach (Rubric::dimensions() as $d) {
            $w = $agirliklar[$d] ?? 0.0;
            if ($w <= 0) {
                continue;
            }
            $turDeger = $rubricScore->value100($d);
            if ($turDeger === null) {
                continue; // LLM kanıt bulamamış → boyut devre dışı
            }

            $fark = $turDeger - ($kullanici[$d] ?? 50);
            $katsayi = match (Rubric::type($d)) {
                'tavan' => $fark > 0 ? $penalty['tavan_asim'] : $penalty['tavan_alti'],
                'taban' => $fark < 0 ? $penalty['taban_alti'] : $penalty['taban_ustu'],
                default => $penalty['mesafe'],
            };

            $cezaToplam += $w * $katsayi * abs($fark);
            $agirlikToplam += $w;
        }

        if ($agirlikToplam == 0) {
            return null;
        }

        return (int) max(0, round(100 - ($cezaToplam / $agirlikToplam) * $penalty['olcek']));
    }

    /**
     * Gerekçe cümlesi (brif §6): en küçük mutlak farklı 2 boyut (örtüşme) +
     * en büyük ağırlıklı cezalı 1 boyut (sapma). Şablon tabanlı — LLM yok.
     */
    public function reason(TourRubricScore $rubricScore, array $kullanici, array $agirliklar): string
    {
        $penalty = Rubric::penalty();
        $farklar = [];
        foreach (Rubric::dimensions() as $d) {
            if (($agirliklar[$d] ?? 0) <= 0) {
                continue;
            }
            $turDeger = $rubricScore->value100($d);
            if ($turDeger === null) {
                continue;
            }
            $fark = $turDeger - ($kullanici[$d] ?? 50);
            $katsayi = match (Rubric::type($d)) {
                'tavan' => $fark > 0 ? $penalty['tavan_asim'] : $penalty['tavan_alti'],
                'taban' => $fark < 0 ? $penalty['taban_alti'] : $penalty['taban_ustu'],
                default => 1.0,
            };
            $farklar[$d] = ['fark' => $fark, 'ceza' => ($agirliklar[$d] ?? 0) * $katsayi * abs($fark)];
        }
        if ($farklar === []) {
            return '';
        }

        uasort($farklar, fn ($a, $b) => abs($a['fark']) <=> abs($b['fark']));
        $ortusme = array_slice(array_keys($farklar), 0, 2);

        uasort($farklar, fn ($a, $b) => $b['ceza'] <=> $a['ceza']);
        $sapmaKey = array_key_first($farklar);
        $sapma = $farklar[$sapmaKey];

        $parcalar = array_map(fn ($d) => self::ORTUSME_METIN[$d] ?? Rubric::label($d), $ortusme);
        $cumle = implode(' ve ', $parcalar).' isteğinle örtüşüyor.';

        if (abs($sapma['fark']) >= 15 && ! in_array($sapmaKey, $ortusme, true)) {
            $yon = $sapma['fark'] > 0 ? 'yuksek' : 'dusuk';
            $cumle .= ' '.(self::SAPMA_METIN[$sapmaKey][$yon] ?? Rubric::label($sapmaKey).' beklediğinden farklı.');
        }

        return $cumle;
    }

    private const ORTUSME_METIN = [
        'tempo' => 'Tempo', 'fiziksel' => 'Fiziksel seviye', 'kultur' => 'Kültür yoğunluğu',
        'doga_sehir' => 'Doğa-şehir dengesi', 'adrenalin' => 'Heyecan dozu', 'gastronomi' => 'Gastronomi ağırlığı',
        'sosyallik' => 'Grup yapısı', 'konfor' => 'Konfor seviyesi', 'yapilandirilmislik' => 'Program esnekliği',
        'kalabaliklik' => 'Rota sakinliği',
    ];

    private const SAPMA_METIN = [
        'tempo' => ['yuksek' => 'Program beklediğinden bir tık yoğun.', 'dusuk' => 'Program beklediğinden daha rahat.'],
        'fiziksel' => ['yuksek' => 'Fiziksel olarak beklediğinden zorlayıcı.', 'dusuk' => 'Fiziksel olarak oldukça hafif.'],
        'kultur' => ['yuksek' => 'Kültür programı beklediğinden yoğun.', 'dusuk' => 'Kültürel içerik beklediğinden az.'],
        'doga_sehir' => ['yuksek' => 'Beklediğinden daha doğa ağırlıklı.', 'dusuk' => 'Beklediğinden daha şehir ağırlıklı.'],
        'adrenalin' => ['yuksek' => 'Heyecan dozu beklediğinin üstünde.', 'dusuk' => 'Adrenalin kısmı beklediğinden sakin.'],
        'gastronomi' => ['yuksek' => 'Yemek programı beklediğinden yoğun.', 'dusuk' => 'Gastronomi vurgusu beklediğinden az.'],
        'sosyallik' => ['yuksek' => 'Grup beklediğinden kalabalık.', 'dusuk' => 'Beklediğinden daha bireysel bir program.'],
        'konfor' => ['yuksek' => 'Konfor beklediğinin üstünde.', 'dusuk' => 'Konfor seviyesi beklediğinden bir tık sade.'],
        'yapilandirilmislik' => ['yuksek' => 'Program beklediğinden planlı.', 'dusuk' => 'Program beklediğinden serbest.'],
        'kalabaliklik' => ['yuksek' => 'Rota beklediğinden turistik.', 'dusuk' => 'Rota beklediğinden tenha.'],
    ];

    /**
     * @param  int[]  $puanliTurIds  yalnız yayınlanabilir puanı olan turlar
     * @return array{0: Collection<int, Tour>, 1: string[]}
     */
    private function hardFilter(array $baglam, array $rules, array $notlar, array $puanliTurIds): array
    {
        if ($puanliTurIds === []) {
            return [collect(), $notlar];
        }

        $build = function (float $butceCarpan, float $gunCarpan) use ($baglam, $puanliTurIds) {
            $today = now()->toDateString();
            // Kolon kısıtı: embedding/search_text/description gibi ağır alanlar
            // eşleştirmede kullanılmaz — her istekte belleğe çekilmesinler.
            $q = Tour::query()
                ->select(['id', 'agency_id', 'title', 'destination', 'price', 'currency', 'price_try',
                    'duration_days', 'image', 'departure_date', 'departure_points', 'is_active'])
                ->with('agency:id,name,is_active')
                ->whereIn('id', $puanliTurIds)
                ->active()
                ->whereHas('agency', fn ($aq) => $aq->active())
                ->where(function ($dq) use ($today) {
                    $dq->whereDate('departure_date', '>=', $today)
                        ->orWhereHas('dates', fn ($d) => $d->whereDate('departure_date', '>=', $today))
                        ->orWhere(fn ($inner) => $inner->whereNull('departure_date')->whereDoesntHave('dates'));
                });

            if (! empty($baglam['aylar'])) {
                $aylar = array_map('intval', (array) $baglam['aylar']);
                // whereMonth: sürücüye göre MONTH()/strftime üretir (SQLite uyumu).
                // Geçmiş kalkışlar sayılmaz — istenen ayda GELECEK kalkışı olmalı.
                $ayKosulu = function ($sub) use ($aylar, $today) {
                    $sub->whereDate('departure_date', '>=', $today)
                        ->where(function ($inner) use ($aylar) {
                            foreach ($aylar as $ay) {
                                $inner->orWhereMonth('departure_date', $ay);
                            }
                        });
                };
                $q->where(function ($mq) use ($ayKosulu) {
                    $mq->where($ayKosulu)->orWhereHas('dates', $ayKosulu);
                });
            }
            if (! empty($baglam['gun_min']) || ! empty($baglam['gun_max'])) {
                $min = max(1, (int) round(($baglam['gun_min'] ?? 1) * (2 - $gunCarpan)));
                $max = (int) round(($baglam['gun_max'] ?? 60) * $gunCarpan);
                $q->whereBetween('duration_days', [$min, $max]);
            }
            if (! empty($baglam['butce_max_try'])) {
                $q->where('price_try', '<=', ((float) $baglam['butce_max_try']) * $butceCarpan);
            }

            $sonuc = $q->get();

            // Kalkış şehri PHP tarafında elenir: SQL LOWER() ile PHP mb_strtolower
            // Türkçe 'İ' harfinde ayrışıyor (U+0130 → "i̇" iki kod noktası) ve
            // "İstanbul" araması hiçbir zaman eşleşmiyordu.
            if (! empty($baglam['kalkis_sehri'])) {
                $sehir = self::normalizeTr((string) $baglam['kalkis_sehri']);
                if ($sehir !== '') {
                    $sonuc = $sonuc->filter(function (Tour $t) use ($sehir) {
                        $noktalar = $t->departure_points;
                        if (empty($noktalar)) {
                            return true; // kalkış bilgisi yoksa eleme
                        }
                        $metin = self::normalizeTr(is_array($noktalar) ? implode(' ', $noktalar) : (string) $noktalar);

                        return str_contains($metin, $sehir);
                    })->values();
                }
            }

            return $sonuc;
        };

        $adaylar = $build(1.0, 1.0);

        // Brif §2: aday < 3 ise bütçe, sonra süre %20 gevşetilir ve AÇIKÇA söylenir
        if ($adaylar->count() < $rules['min_candidates'] && ! empty($baglam['butce_max_try'])) {
            $adaylar = $build(1 + $rules['relax_step'], 1.0);
            $notlar[] = 'Bütçe kısıtını %20 gevşettim — birebir uyan yeterli tur yoktu.';
        }
        if ($adaylar->count() < $rules['min_candidates'] && (! empty($baglam['gun_min']) || ! empty($baglam['gun_max']))) {
            $adaylar = $build(1 + $rules['relax_step'], 1 + $rules['relax_step']);
            $notlar[] = 'Süre kısıtını %20 gevşettim.';
        }

        return [$adaylar, $notlar];
    }

    private function applyDiversity(Collection $sirali, array $rules): Collection
    {
        $band = function (Tour $t) use ($rules) {
            $v = $t->rubric?->value100($rules['diversity_axis']);
            if ($v === null) {
                return 'bilinmiyor';
            }

            return $v < 34 ? 'dusuk' : ($v > 66 ? 'yuksek' : 'orta');
        };

        $secilen = collect();
        $bandSayac = [];
        foreach ($sirali as $tour) {
            $b = $band($tour);
            if ($b !== 'bilinmiyor' && ($bandSayac[$b] ?? 0) >= $rules['diversity_max_same_band']) {
                continue;
            }
            $secilen->push($tour);
            $bandSayac[$b] = ($bandSayac[$b] ?? 0) + 1;
            if ($secilen->count() >= $rules['top_n']) {
                break;
            }
        }
        // Çeşitlilik yüzünden 3 dolmadıysa atlananlarla tamamla
        if ($secilen->count() < $rules['top_n']) {
            foreach ($sirali as $tour) {
                if (! $secilen->contains('id', $tour->id)) {
                    $secilen->push($tour);
                    if ($secilen->count() >= $rules['top_n']) {
                        break;
                    }
                }
            }
        }

        return $secilen;
    }

    /**
     * Türkçe-güvenli karşılaştırma normalizasyonu: 'İ' → 'i' (mb_strtolower'ın
     * ürettiği birleşik nokta U+0307 temizlenir), 'I' → 'ı'.
     */
    private static function normalizeTr(string $metin): string
    {
        $metin = str_replace(['İ', 'I'], ['i', 'ı'], trim($metin));
        $metin = mb_strtolower($metin, 'UTF-8');

        return str_replace("\u{0307}", '', $metin);
    }

    private function card(Tour $tour, array $profil, array $baglam = []): array
    {
        // Bütçe gevşetildiyse kullanıcıya dürüstçe işaretlenir
        $butce = (float) ($baglam['butce_max_try'] ?? 0);
        $overBudget = $butce > 0 && (float) ($tour->price_try ?? $tour->price) > $butce;

        return [
            'id' => $tour->id,
            'title' => $tour->title,
            'destination' => $tour->destination,
            'price' => $tour->price,
            'currency' => $tour->currency,
            'duration_days' => $tour->duration_days,
            'image' => $tour->image,
            'url' => route('tours.show', $tour->id),
            'agency_name' => $tour->agency?->name,
            'compatibility_score' => $tour->match_score / 100,
            'match_percent' => $tour->match_score,
            'over_budget' => $overBudget,
            'reason' => $this->reason($tour->rubric, $profil['degerler'], $profil['agirliklar']),
            'next_departure' => optional($tour->departure_date)->format('Y-m-d'),
            'flex_date' => null,
        ];
    }
}
