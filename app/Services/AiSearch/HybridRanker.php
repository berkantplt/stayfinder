<?php

namespace App\Services\AiSearch;

use App\Models\Tour;
use App\Support\Vector;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Hibrit arama sıralayıcısı: vektör (cosine) kanalı + anahtar kelime kanalı
 * RRF füzyonuyla birleşir; negatif feedback merkez vektörü de burada hesaplanır.
 *
 * Metot gövdeleri AiSearchController'dan davranış değişmeden taşındı
 * (spagetti temizliği 3. dalga) — controller thin kalsın.
 */
class HybridRanker
{
    /**
     * Filtered query üzerinden cursor ile ID+embedding stream eder, cosine'a göre
     * en yüksek $topK adayın ID'lerini belirler ve sadece onları full hydrate eder.
     *
     * Sıralama korunur (en yüksek cosine üstte). Pre-computed similarity her tur'a
     * "similarity" attribute olarak attach edilir — sonradan tekrar hesaplama gerekmez.
     *
     * @param  Builder  $query
     * @param  array<int, float>  $queryVector
     * @return Collection<int, Tour>
     */
    public function topKByCosine($query, array $queryVector, int $topK = 100, string $searchQueryText = '')
    {
        $similarities = [];

        // Cursor + select(id, embedding): her iter sadece 12KB memory tutar (1536 float).
        // Iter sonunda tour instance GC edilir, similarity dict'te kalır.
        (clone $query)
            ->select(['id', 'embedding'])
            ->cursor()
            ->each(function ($tour) use (&$similarities, $queryVector) {
                $embedding = $tour->embedding;
                if (empty($embedding)) {
                    return;
                }
                $similarities[$tour->id] = $this->cosineSimilarity($queryVector, $embedding);
            });

        if (empty($similarities)) {
            return collect();
        }

        // HİBRİT FÜZYON (RRF): vektör sırası + anahtar kelime sırası birleşir.
        // Birebir ifade eşleşmeleri ("yüzme molalı") cosine'da geride kalsa bile
        // top-K'ye girer; iki kanalda da iyi olan tur en üste çıkar.
        arsort($similarities);
        $cosineRanks = [];
        $rank = 1;
        foreach (array_keys($similarities) as $id) {
            $cosineRanks[$id] = $rank++;
        }

        $kwRanks = $searchQueryText !== '' ? $this->keywordRanks($query, $searchQueryText) : [];

        $fused = [];
        foreach ($cosineRanks as $id => $cRank) {
            $fused[$id] = 1 / (60 + $cRank) + (isset($kwRanks[$id]) ? 1 / (60 + $kwRanks[$id]) : 0);
        }
        arsort($fused);

        $topIds = array_keys(array_slice($fused, 0, $topK, true));

        // Top-K full hydrate (eager load agency + tarihler — ay skoru tüm kalkışlara bakar)
        $hydrated = Tour::with(['agency', 'dates'])
            ->whereIn('id', $topIds)
            ->get()
            ->keyBy('id');

        // Füzyon sırasını koru; similarity + keyword_rank attach et
        return collect($topIds)
            ->map(function ($id) use ($hydrated, $similarities, $kwRanks) {
                $tour = $hydrated->get($id);
                if ($tour) {
                    $tour->similarity = $similarities[$id];
                    $tour->keyword_rank = $kwRanks[$id] ?? null;
                }

                return $tour;
            })
            ->filter()
            ->values();
    }

    /**
     * Anahtar kelime kanalı: sorgu kelimelerinin search_text'te geçme sıklığına
     * göre aday sıralaması. MySQL'de FULLTEXT (doğal dil), test/sqlite'ta LIKE
     * tabanlı sayım. "Yüzme molalı" gibi birebir ifadeler vektörde sıralamada
     * kaybolsa bile bu kanaldan yüzeye çıkar.
     *
     * @return array<int, int> [tourId => rank] (1'den başlar)
     */
    public function keywordRanks($query, string $searchQueryText, int $topK = 50): array
    {
        $keywords = \App\Support\SearchText::keywords($searchQueryText);
        if (empty($keywords)) {
            return [];
        }

        $builder = (clone $query)->whereNotNull('search_text');

        if (\Illuminate\Support\Facades\DB::getDriverName() === 'mysql') {
            $rows = $builder
                ->selectRaw('id, MATCH(search_text) AGAINST (? IN NATURAL LANGUAGE MODE) AS kw_score', [implode(' ', $keywords)])
                ->havingRaw('kw_score > 0')
                ->orderByDesc('kw_score')
                ->limit($topK)
                ->pluck('kw_score', 'id')
                ->all();
        } else {
            // Fallback (sqlite/test): kelime başına LIKE isabeti say
            $scores = [];
            $builder->select(['id', 'search_text'])->cursor()->each(function ($tour) use (&$scores, $keywords) {
                $hits = 0;
                foreach ($keywords as $kw) {
                    if (str_contains((string) $tour->search_text, $kw)) {
                        $hits++;
                    }
                }
                if ($hits > 0) {
                    $scores[$tour->id] = $hits;
                }
            });
            arsort($scores);
            $rows = array_slice($scores, 0, $topK, true);
        }

        $ranks = [];
        $rank = 1;
        foreach (array_keys($rows) as $id) {
            $ranks[(int) $id] = $rank++;
        }

        return $ranks;
    }

    /**
     * Reddedilen turların embedding'lerinin ortalama vektörü.
     * Null döner: reddedilen yoksa veya hiçbirinin embedding'i yoksa.
     *
     * @param  array<int, int>  $rejectedIds
     * @return array<int, float>|null
     */
    public function computeRejectionAvgEmbedding(array $rejectedIds): ?array
    {
        if (empty($rejectedIds)) {
            return null;
        }

        $embeddings = Tour::whereIn('id', $rejectedIds)
            ->whereNotNull('embedding')
            ->pluck('embedding')
            ->filter(fn ($e) => is_array($e) && ! empty($e))
            ->values()
            ->all();

        if (empty($embeddings)) {
            return null;
        }

        $dim = count($embeddings[0]);
        $sum = array_fill(0, $dim, 0.0);

        foreach ($embeddings as $embedding) {
            if (count($embedding) !== $dim) {
                continue; // safety: skip mismatched-dim embeddings
            }
            foreach ($embedding as $i => $v) {
                $sum[$i] += (float) $v;
            }
        }

        $count = count($embeddings);

        return array_map(fn ($v) => $v / $count, $sum);
    }

    public function cosineSimilarity($vec1, $vec2)
    {
        return Vector::cosine($vec1, $vec2);
    }
}
