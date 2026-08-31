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
     * @param  array{degerler: array<string,float>, agirliklar: array<string,float>, filtre: array<string,mixed>}  $profil
     * @return array{tours: array, relaxation_notes: string[], below_floor: bool}
     */
    public function match(array $profil, array $baglam = []): array
    {
        $rules = Rubric::resultRules();

        // Yalnız YAYINLANABİLİR puanı olan turlar aday olabilir: needs_review
        // işaretliler editör onayına kadar canlıda kullanılmaz (brif §3.5) ve
        // aday sayımı da bu küme üzerinden yapılır (gevşetme tetiği doğru çalışsın).
        $scores = TourRubricScore::where('rubric_version', Rubric::VERSION)
            ->where('review_status', '!=', TourRubricScore::STATUS_NEEDS_REVIEW)
            ->get()
            ->keyBy('tour_id');

        [$adaylar, $notlar] = $this->hardFilter($baglam, $rules, [], $scores->keys()->all());

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
        // Taban üstü hiç yoksa "en yakınlar" gösterilir — ama HER ŞEY değil:
        // %25'lik bir tur "en yakın" bile sayılmaz, göstermek güveni bitirir.
        // min_display_score altındakiler taban altı modda da elenir.
        $tabanUstu = $sirali->filter(fn ($t) => $t->match_score >= $rules['min_score'])->values();
        $belowFloor = $tabanUstu->isEmpty();
        $havuz = $belowFloor
            ? $sirali->filter(fn ($t) => $t->match_score >= ($rules['min_display_score'] ?? 40))->values()
            : $tabanUstu;

        // Zaten gösterilmiş turlar dışlanır ("diğerleri" görünümü). Konum atlamak
        // yerine KİMLİK dışlamak şart: çeşitlilik kuralı sıralamayı değiştirdiği
        // için "ilk 5'i atla" bazı turların hiç görünmemesine yol açardı.
        if (! empty($baglam['haric'])) {
            $haric = array_map('intval', (array) $baglam['haric']);
            $havuz = $havuz->reject(fn ($t) => in_array($t->id, $haric, true))->values();
        }

        // Çeşitlilik yalnız KÜRATÖRLÜ ilk listede uygulanır. Genişletilmiş
        // listede kapatılır: "en fazla 2" kuralı 20'lik listeyi 6-8 turda
        // tıkardı ve kullanıcı zaten "hepsini göster" demiş oluyor.
        // Taban altı modda liste KISA tutulur: zayıf eşleşmeleri 5'e tamamlamak
        // "bunlar sana uygun" izlenimi verir; 3 tanesi dürüst bir "en yakınlar".
        $topN = max(1, (int) ($baglam['top_n'] ?? ($belowFloor ? ($rules['below_floor_n'] ?? 3) : $rules['top_n'])));
        $secilen = ($baglam['cesitlilik'] ?? true)
            ? $this->applyDiversity($havuz, ['top_n' => $topN] + $rules)
            : $havuz->take($topN);

        return [
            'tours' => $secilen->map(fn ($t) => $this->card($t, $profil, $baglam, $belowFloor))->all(),
            // AYRI liste, kasıtlı: brif §6 "taban altı turlar ÖNERİLEN diye
            // gösterilmez" diyor, o yüzden bunlar tours'a karışmaz. Ama eşiği
            // geçen 1-2 tur varken listeyi orada kesmek de kullanıcıya katalogu
            // boş gösteriyordu — "tam uymuyor ama en yakını bu" dürüst orta yol.
            'yakin_turlar' => $this->yakinTurlar($sirali, $secilen, $tabanUstu, $profil, $baglam, $rules, $belowFloor),
            // Katalogda yayınlanabilir puanı olan tur sayısı: 0 ise sistem HAZIR
            // DEĞİL demektir, "uyan tur yok" ile karıştırılmamalı
            'katalog_puanli_tur' => $scores->count(),
            // Sert filtreleri geçip puanlanabilen TÜM adaylar — "diğerleri"
            // butonunun kaç tur daha olduğunu söyleyebilmesi için
            'toplam_eslesme' => $sirali->count(),
            'relaxation_notes' => $notlar,
            'kapsam' => $this->kapsam($secilen, $profil),
            'karsilanmayan' => $this->karsilanmayan($secilen, $profil),
            'sor' => $this->sorulacakSoru($secilen, $baglam),
            'below_floor' => $belowFloor && $secilen->isNotEmpty(),
        ];
    }

    /**
     * "Tam uymuyor ama en yakını" turları — yalnız önerilen liste ince kaldığında.
     *
     * Taban altı modda ÜRETİLMEZ: orada zaten gösterilen her şey eşik altı,
     * ikinci bir "yakınlar" listesi aynı şeyi iki kez söylemek olur.
     *
     * @return array<int, array<string, mixed>>
     */
    private function yakinTurlar(
        Collection $sirali,
        Collection $secilen,
        Collection $tabanUstu,
        array $profil,
        array $baglam,
        array $rules,
        bool $belowFloor,
    ): array {
        $hedef = (int) ($rules['min_candidates'] ?? 3);

        // Ölçüt HAVUZ, gösterilen kart sayısı değil: çağıran top_n=1 verdiğinde
        // (ör. "diğerleri" görünümünün son sayfası) eşik üstü tur bol olsa bile
        // liste ince görünür ve boşuna zayıf tur eklenirdi.
        if ($belowFloor || $tabanUstu->count() >= $hedef || $secilen->count() >= $hedef) {
            return [];
        }

        $secilenIdler = $secilen->pluck('id')->all();
        $haric = array_map('intval', (array) ($baglam['haric'] ?? []));

        return $sirali
            ->filter(fn ($t) => $t->match_score < $rules['min_score']
                && $t->match_score >= ($rules['min_display_score'] ?? 40)
                && ! in_array($t->id, $secilenIdler, true)
                && ! in_array($t->id, $haric, true))
            ->take($hedef - $secilen->count())
            // belowFloor=true ⇒ uyum rozeti basılmaz; 'yakin' bayrağı arayüzün
            // bunları ayrı çerçevede göstermesi için
            ->map(fn ($t) => ['yakin' => true] + $this->card($t, $profil, $baglam, true))
            ->values()
            ->all();
    }

    /**
     * Profilsiz liste: tercih vektörü çıkarılamadığında dürüst geri çekilme.
     *
     * Rubrik puanı KULLANILMAZ (ağırlık yokken skor null döner, match() boş
     * liste verir). Yalnız sert filtreleri geçen turlar, kalkışı en yakın olan
     * başta olmak üzere listelenir. Kartlar taban-altı modda basılır: "%X uyumlu"
     * rozeti çıkmaz — çünkü ortada ölçülmüş bir uyum yok.
     *
     * @return array{tours: array, toplam_eslesme: int}
     */
    public function listele(array $baglam = [], int $limit = 5): array
    {
        // Rubrik puanı ŞART DEĞİL: bu liste puanı kullanmıyor, kalkış tarihine göre
        // sıralıyor. Puan şartı koyulduğunda katalog henüz puanlanmamışsa sohbet
        // hiçbir tur gösteremiyordu — kullanıcı somut kısıt verse bile.
        [$adaylar] = $this->hardFilter($baglam, Rubric::resultRules(), [], null);

        $sirali = $adaylar->sort(function (Tour $a, Tour $b) {
            $aDate = $a->departure_date?->timestamp ?? PHP_INT_MAX;
            $bDate = $b->departure_date?->timestamp ?? PHP_INT_MAX;

            return $aDate !== $bDate ? $aDate <=> $bDate : $a->id <=> $b->id;
        })->values();

        $bosProfil = ['degerler' => [], 'agirliklar' => []];

        return [
            'tours' => $sirali->take(max(1, $limit))
                ->map(fn (Tour $t) => $this->card($t, $bosProfil, $baglam, true))->all(),
            'toplam_eslesme' => $sirali->count(),
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
            $katsayi = $this->cezaKatsayisi($d, $fark, $penalty);

            $cezaToplam += $w * $katsayi * abs($fark);
            $agirlikToplam += $w;
        }

        if ($agirlikToplam == 0) {
            return null;
        }

        return (int) max(0, round(100 - ($cezaToplam / $agirlikToplam) * $penalty['olcek']));
    }

    /**
     * Asimetrik ceza katsayısı (brif §6): tavan boyutta aşım ×2.5 / altı ×0.4,
     * taban boyutta tersi, mesafe boyutunda düz katsayı (v1.json'da 1.0).
     * skor() ve reason() AYNI katsayıyı kullanır — iki kopya ayrışmasın.
     */
    private function cezaKatsayisi(string $d, float $fark, array $penalty): float
    {
        return match (Rubric::type($d)) {
            'tavan' => $fark > 0 ? $penalty['tavan_asim'] : $penalty['tavan_alti'],
            'taban' => $fark < 0 ? $penalty['taban_alti'] : $penalty['taban_ustu'],
            default => $penalty['mesafe'],
        };
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
            $katsayi = $this->cezaKatsayisi($d, $fark, $penalty);
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

    /**
     * @param  array<int, int>|null  $puanliTurIds  null = rubrik puanı ŞARTI YOK
     *                                              (profilsiz listeleme), [] = puanlı tur yok
     * @return array{0: Collection<int, Tour>, 1: string[]}
     */
    private function hardFilter(array $baglam, array $rules, array $notlar, ?array $puanliTurIds): array
    {
        if ($puanliTurIds === []) {
            return [collect(), $notlar];
        }

        $build = function (float $butceCarpan, float $gunCarpan) use ($baglam, $puanliTurIds) {
            $today = now()->toDateString();
            // Kolon kısıtı: embedding/search_text/description gibi ağır alanlar
            // eşleştirmede kullanılmaz — her istekte belleğe çekilmesinler.
            $q = Tour::query()
                // slug ŞART: rota anahtarı artık slug (route('tours.show', $tour)).
                // Seçilmezse kart URL'i üretilirken UrlGenerationException atar.
                ->select(['id', 'slug', 'agency_id', 'title', 'destination', 'price', 'currency', 'price_try',
                    'duration_days', 'duration_nights', 'image', 'departure_date', 'departure_points', 'is_active'])
                ->with('agency:id,name,is_active')
                // null: profilsiz listeleme — rubrik puanı aranmaz. Bu liste zaten
                // puanı KULLANMIYOR (kalkış tarihine göre sıralanıyor); puan şartı
                // koymak, katalog puanlanmadan sohbetin hiçbir tur gösterememesine
                // yol açıyordu.
                ->when($puanliTurIds !== null, fn ($qq) => $qq->whereIn('id', $puanliTurIds))
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
            if (isset($baglam['yurt_disi']) && $baglam['yurt_disi'] !== null) {
                $q->where('is_international', (bool) $baglam['yurt_disi']);
            }

            $sonuc = $q->get();

            // Destinasyon da PHP tarafında elenir (aynı Türkçe 'İ' gerekçesi):
            // "İzmir" araması SQL LOWER() ile hiçbir zaman eşleşmezdi.
            if (! empty($baglam['destinasyon'])) {
                $dest = self::normalizeTr((string) $baglam['destinasyon']);
                if ($dest !== '') {
                    $sonuc = $sonuc->filter(
                        fn (Tour $t) => str_contains(self::normalizeTr((string) $t->destination), $dest)
                    )->values();
                }
            }

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

        // Bant kuralı yalnız BİRBİRİNE YAKIN puanlar arasında uygulanır. Eskiden
        // puan farkına bakılmıyordu: aynı bantta 3. sıradaki %85'lik tur atlanıp
        // yerine başka banttan %60'lık tur geliyordu — kullanıcı "neden bu geldi"
        // diye haklı olarak soruyordu. Artık yerine geçecek tur belirgin ölçüde
        // kötüyse çeşitlilik feda edilir, puan kazanır.
        $gap = (float) ($rules['diversity_score_gap'] ?? 15);
        $liste = $sirali->values();

        $secilen = collect();
        $bandSayac = [];
        foreach ($liste as $i => $tour) {
            $b = $band($tour);
            if ($b !== 'bilinmiyor' && ($bandSayac[$b] ?? 0) >= $rules['diversity_max_same_band']) {
                // Bu turun yerine gelebilecek en iyi "başka bant" adayı
                $alternatif = null;
                foreach ($liste->slice($i + 1) as $sonraki) {
                    $sb = $band($sonraki);
                    if ($sb === 'bilinmiyor' || ($bandSayac[$sb] ?? 0) < $rules['diversity_max_same_band']) {
                        $alternatif = $sonraki;
                        break;
                    }
                }
                // Alternatif yeterince iyiyse bant kuralı uygulanır, değilse ezilir
                if ($alternatif !== null && $alternatif->match_score >= $tour->match_score - $gap) {
                    continue;
                }
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
     * Ölçülebilen ağırlık oranı (0-1). Kullanıcının önemsediği boyutların ne
     * kadarı turda gerçekten ölçülebilmiş? Düşükse skor ŞİŞMİŞ demektir:
     * formül aktif ağırlığa böldüğü için 2 boyut aktifken ortalama bir tur bile
     * %90+ alır ve "%92 uyumlu" rozeti kullanıcıya yalan söyler.
     */
    private function kapsam(Collection $secilen, array $profil): float
    {
        $istenen = array_sum(array_filter($profil['agirliklar'] ?? []));
        if ($istenen <= 0 || $secilen->isEmpty()) {
            return 0.0;
        }

        $olculen = 0.0;
        foreach ($profil['agirliklar'] as $d => $w) {
            if ($w > 0 && $secilen->first()->rubric?->value100($d) !== null) {
                $olculen += $w;
            }
        }

        return round($olculen / $istenen, 2);
    }

    /**
     * Karşılanamayan istekler: kullanıcının önemsediği ama gösterilen turların
     * HİÇBİRİNDE tutmayan boyutlar. Şartname madde 3'teki "böyle bir turum yok
     * ama…" köprüsünün tek makine-okunur kaynağı — model bunu tahminle kurarsa
     * negatif yönde uydurma yapmış olur.
     *
     * @return string[] boyut anahtarları
     */
    private function karsilanmayan(Collection $secilen, array $profil): array
    {
        if ($secilen->isEmpty()) {
            return array_keys(array_filter($profil['agirliklar'] ?? []));
        }

        $sonuc = [];
        foreach (($profil['agirliklar'] ?? []) as $d => $w) {
            if ($w <= 0) {
                continue;
            }
            $hedef = $profil['degerler'][$d] ?? null;
            if ($hedef === null) {
                continue;
            }

            // Hiçbir turda 25 puandan yakın değilse o istek karşılanamamıştır
            $enYakin = $secilen
                ->map(fn ($t) => $t->rubric?->value100($d))
                ->filter(fn ($v) => $v !== null)
                ->map(fn ($v) => abs($v - $hedef))
                ->min();

            if ($enYakin === null || $enYakin > 25) {
                $sonuc[] = $d;
            }
        }

        return $sonuc;
    }

    /**
     * Soru sorma kararı MODELDEN alınır, veriden türetilir (şartname madde 8).
     * Model "sorayım mı" diye karar verirse ya boğar ya hiç sormaz; tutarlılık
     * olmaz. Burada soru sonuç dağılımından doğar: fiyatlar çok ayrışıyorsa
     * bütçe sorulur, aday havuzu çok genişse tarih sorulur.
     */
    private function sorulacakSoru(Collection $secilen, array $baglam): ?string
    {
        if ($secilen->count() < 2) {
            return null;
        }

        if (empty($baglam['butce_max_try'])) {
            $fiyatlar = $secilen->map(fn ($t) => (float) ($t->price_try ?? $t->price))->filter()->values();
            if ($fiyatlar->count() >= 2 && $fiyatlar->min() > 0 && $fiyatlar->max() / $fiyatlar->min() >= 2.0) {
                return 'butce';
            }
        }

        if (empty($baglam['aylar'])) {
            return 'ay';
        }

        return null;
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

    private function card(Tour $tour, array $profil, array $baglam = [], bool $belowFloor = false): array
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
            'duration_label' => $tour->duration_label,
            'image' => $tour->image,
            'url' => route('tours.show', $tour),
            'agency_name' => $tour->agency?->name,
            // Taban altı modda uyum rozeti BASILMAZ: "%25 Uyumlu" yazısı zayıf
            // eşleşmeyi öneri gibi gösterip güveni bitiriyor. Kart yine görünür
            // ama "en yakınlar" çerçevesinde, rakamsız.
            'compatibility_score' => $belowFloor ? null : $tour->match_score / 100,
            'match_percent' => $tour->match_score,
            'over_budget' => $overBudget,
            // Profilsiz listede rubrik nesnesi yüklenmez; gerekçe de üretilemez
            'reason' => $tour->rubric ? $this->reason($tour->rubric, $profil['degerler'], $profil['agirliklar']) : '',
            'next_departure' => optional($tour->departure_date)->format('Y-m-d'),
            'flex_date' => null,
        ];
    }
}
