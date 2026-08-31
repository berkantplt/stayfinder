<?php

namespace App\Services\AiSearch;

use App\Models\AiSearchLog;
use App\Models\Tour;
use App\Services\KnowledgeService;
use App\Support\OpenAiChatParams;
use App\Support\TurkishMonths;
use App\Support\TurkishText;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * AI arama motoru: niyet çıkarımı + hibrit skorlama + gevşetme merdiveni +
 * yorum/rerank/bağlamsal cevap üretimi. Gövdeler AiSearchController'dan davranış
 * birebir korunarak taşındı; controller yalnızca HTTP uçlarını tutar.
 */
class TourSearchService
{
    /**
     * Sonuçlarda göstermeye değer minimum uyumluluk skoru.
     * Bu skorun altındaki turlar kullanıcının niyeti ile yeterince eşleşmiyor sayılır.
     */
    public const COMPATIBILITY_THRESHOLD = 0.51;

    /** Sohbette gösterilen tur sayısı — en isabetli N; kalanı "tümünü gör" sayfasında. */
    public const CHAT_RESULT_LIMIT = 7;

    public function __construct(
        private readonly IntentHeuristics $intentHeuristics,
        private readonly TourScorer $tourScorer,
        private readonly HybridRanker $hybridRanker,
        private readonly DestinationProfileService $destinationProfiles,
        private readonly DestinationKnowledgeService $destinationKnowledge,
        private readonly QueryEmbeddingCache $embeddingCache,
        private readonly AiSearchPrompts $prompts,
    ) {
    }

    /**
     * @param  array<string, mixed>|null  $previousIntent  Önceki turun merge'lenmiş niyet JSON'u
     * @param  bool  $skipComment  true ise AI yorum üretilmez (streaming endpoint kendi
     *                             `streamComment` ile akıtacak); return'de aiComment=null + _comment_context dolu
     */
    public function performAiSearch(Request $request, string $query, ?array $previousIntent = null, bool $skipComment = false, array $excludeTourIds = []): ?array
    {
        $query = trim($query);
        if ($query === '') {
            return null;
        }

        // Meta anahtarlar (_clarifications, _model, _pending_context) LLM promptuna,
        // cache anahtarına ve merge'e sızmasın — yalnızca gerçek niyet alanları bağlamdır.
        if (! empty($previousIntent)) {
            $previousIntent = array_filter(
                $previousIntent,
                fn ($key) => ! str_starts_with((string) $key, '_'),
                ARRAY_FILTER_USE_KEY
            );
        }

        try {
            $startedAt = microtime(true);

            // 1. Niyet Analizi (LLM)
            $systemPrompt = $this->prompts->intentSystemPrompt($previousIntent);

            // Niyet önbelleği: aynı sorgu + aynı önceki-niyet bağlamı deterministik
            // olarak aynı intent'i üretir — popüler sorgular ("vizesiz turlar" vb.)
            // 24 saat boyunca API'ye gitmeden cevaplanır.
            $intentModel = config('ai.intent_model', 'gpt-5.4-mini');
            // Cache anahtarı normalize sorgudan: "Kapadokya Turu" ile "kapadokya turu"
            // aynı 24 saatlik girdiye düşer → isabet artar, intent model maliyeti düşer
            $intentCacheKey = 'ai:intent:'.md5($intentModel.'|'.TurkishText::normalize($query).'|'.json_encode($previousIntent ?? []));

            // Cache::remember DEĞİL: parse hatasında boş [] 24 saat cache'lenip
            // sorguyu "niyetsiz" duruma kilitlerdi. Önce üret, GEÇERLİYSE (boş
            // değil) cache'e yaz; boşsa cache'leme, bir sonraki denemede taze koşsun.
            $analysis = Cache::get($intentCacheKey);
            if (! is_array($analysis) || $analysis === []) {
                // intent JSON'u kompakt — 600 token kaçak uzun çıktıya tavan
                $analysisResponse = OpenAI::chat()->create(OpenAiChatParams::json($intentModel, [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $this->wrapUserInputSafely($query)],
                ], 600));

                $analysis = json_decode($analysisResponse->choices[0]->message->content, true) ?: [];
                if ($analysis !== []) {
                    Cache::put($intentCacheKey, $analysis, 86400);
                }
            }

            // A/B test ve maliyet analizi için: hangi modelin çıkardığı log'a yazılsın
            $analysis['_model'] = $intentModel;

            // Önceki niyetle merge — yeni cevap null bıraktıysa eski değer korunur.
            // İSTİSNA: kullanıcının BU mesajda kaldırdığı kısıtlar (cleared_fields)
            // geri yüklenmez — "bütçe fark etmez artık" gerçekten bütçeyi kaldırır.
            $clearedFields = array_values(array_filter((array) ($analysis['cleared_fields'] ?? []), 'is_string'));
            unset($analysis['cleared_fields']);
            if (! empty($previousIntent)) {
                foreach ($previousIntent as $key => $value) {
                    if (in_array($key, $clearedFields, true)) {
                        continue;
                    }
                    if (! array_key_exists($key, $analysis) || $analysis[$key] === null) {
                        $analysis[$key] = $value;
                    }
                }
            }

            $maxBudget = isset($analysis['max_budget']) ? (int) $analysis['max_budget'] : null;

            // Bütçe para birimi: "kişi başı 1000 euro" 1000 TL sanılmasın — sorguda
            // döviz geçiyorsa TL'ye çevir ve intent'e TL olarak yaz (sonraki turlarda
            // merge edilen değer de doğru kalsın).
            if ($maxBudget !== null && $maxBudget > 0) {
                $converted = $this->intentHeuristics->convertBudgetToTry($maxBudget, $query);
                if ($converted !== $maxBudget) {
                    $maxBudget = $converted;
                    $analysis['max_budget'] = $converted;
                }
            }

            // Heuristic'ler kullanıcının açık ifadelerini yakalar (ör. "yurt dışı", "doğa istiyorum").
            // GPT yumuşak/yanlış cevap verebiliyor; kullanıcının açık ifadesi varsa GPT'yi OVERRIDE et.
            $isInternational = $this->intentHeuristics->detectInternationalIntent($query)
                ?? $this->intentHeuristics->toNullableBool($analysis['is_international'] ?? null);

            $requiresVisa = $this->intentHeuristics->toNullableBool($analysis['requires_visa'] ?? null);
            $minDays = isset($analysis['preferred_min_days']) ? (int) $analysis['preferred_min_days'] : null;
            $maxDays = isset($analysis['preferred_max_days']) ? (int) $analysis['preferred_max_days'] : null;
            $preferredMonth = $this->intentHeuristics->extractPreferredMonth($analysis, $query);

            $wantsNature = $this->intentHeuristics->detectNatureIntent($query)
                ?? $this->intentHeuristics->toNullableBool($analysis['wants_nature'] ?? null);

            $avoidCrowdedCity = $this->intentHeuristics->detectEscapeCityIntent($query)
                ?? $this->intentHeuristics->toNullableBool($analysis['avoid_crowded_city'] ?? null);

            $wantsLively = $this->intentHeuristics->detectLivelyIntent($query)
                ?? $this->intentHeuristics->toNullableBool($analysis['wants_lively'] ?? null);
            $preferredDestination = $this->intentHeuristics->extractPreferredDestination($analysis, $query);
            $excludedDestinations = $this->intentHeuristics->extractExcludedDestinations($analysis, $query);

            // Kullanıcı yurt içi/dışı YÖNÜNÜ değiştirdiyse ("aslında yurt içi olsun"),
            // önceki yönden miras kalan ve bu mesajda anılmayan destinasyon taşınmaz —
            // yoksa is_international=false + destination LIKE '%Avrupa%' gibi imkânsız
            // filtre birleşimi sessizce 0 sonuç üretiyordu.
            $previousDirection = isset($previousIntent['is_international'])
                ? $this->intentHeuristics->toNullableBool($previousIntent['is_international'])
                : null;
            if ($isInternational !== null && $previousDirection !== null && $isInternational !== $previousDirection) {
                $normalizedUserQuery = TurkishText::normalize($query);
                if ($preferredDestination !== null && ! $this->intentHeuristics->queryMentionsDestination($normalizedUserQuery, $preferredDestination)) {
                    $preferredDestination = null;
                }
                $excludedDestinations = array_values(array_filter(
                    $excludedDestinations,
                    fn ($dest) => $this->intentHeuristics->queryMentionsDestination($normalizedUserQuery, (string) $dest)
                ));
            }

            if ($preferredDestination !== null && $this->tourScorer->destinationInList($preferredDestination, $excludedDestinations)) {
                $preferredDestination = null;
            }
            $cleanQuery = trim((string) ($analysis['search_query'] ?? $query));
            if ($cleanQuery === '') {
                $cleanQuery = $query;
            }
            if ($preferredDestination !== null) {
                $normalizedCleanQuery = TurkishText::normalize($cleanQuery);
                $normalizedPreferredDestination = TurkishText::normalize($preferredDestination);
                if (! str_contains($normalizedCleanQuery, $normalizedPreferredDestination)) {
                    $cleanQuery = trim($cleanQuery.' '.$preferredDestination);
                }
            }

            // Uygulanan (heuristik + LLM birleşimi) değerleri intent'e GERİ yaz —
            // conversation'a persist edilen niyet, uygulanan filtrelerle birebir aynı
            // olsun. Aksi halde heuristikten gelen filtre (ör. "vizesiz" → yurt dışı)
            // sonraki turda sessizce düşüyordu.
            $analysis['max_budget'] = $maxBudget;
            $analysis['is_international'] = $isInternational;
            $analysis['requires_visa'] = $requiresVisa;
            $analysis['preferred_min_days'] = $minDays;
            $analysis['preferred_max_days'] = $maxDays;
            $analysis['preferred_month'] = $preferredMonth;
            $analysis['wants_nature'] = $wantsNature;
            $analysis['avoid_crowded_city'] = $avoidCrowdedCity;
            $analysis['wants_lively'] = $wantsLively;
            $analysis['preferred_destination'] = $preferredDestination;
            $analysis['exclude_destinations'] = $excludedDestinations;
            $analysis['search_query'] = $cleanQuery;

            // 2. Vektör Oluşturma (önbellekli — tekrar eden sorgular API'ye gitmez).
            // Sorgu genişletmesi eklenir: "balayı" gibi hiçbir tur metninde geçmeyen
            // ifadeler, çağrışımlarıyla (romantik/çift/sakin) tur temsilleriyle buluşur.
            $expandedQuery = trim((string) ($analysis['expanded_query'] ?? ''));
            $embeddingInput = $expandedQuery !== ''
                ? mb_substr($cleanQuery.' '.$expandedQuery, 0, 400)
                : $cleanQuery;
            $queryVector = $this->embeddingCache->vector($embeddingInput);

            // 3.4. Negatif feedback memory: kullanıcı son 24 saatte reddettiği turları
            // havuzdan çıkarır + reddedilen turların ortalama embedding'i ile cosine
            // similarity yüksek olanlara penalty uygulanır.
            $rejectedIds = $this->collectRejectedTourIds($request);
            $rejectionAvgEmbedding = $this->hybridRanker->computeRejectionAvgEmbedding($rejectedIds);

            // Girişli kullanıcının tercih profili vektörü (gece ai:build-user-profiles
            // üretir) — geçmiş beğenilere yakın turlara küçük kişiselleştirme bonusu
            $profileVector = null;
            $preference = $request->user()?->ai_preference;
            if (is_array($preference) && ! empty($preference['embedding']) && is_array($preference['embedding'])) {
                $profileVector = $preference['embedding'];
            }

            // 3. Veritabanı Filtreleme + 0-SONUÇ GEVŞETME MERDİVENİ:
            // birebir sonuç yoksa körce "bulamadım" demek yerine kademeli gevşetilir
            // (eşik altı en iyi 3 → destinasyonu bırak → bütçe tavanını bırak) ve
            // nedeni relaxation_note ile kullanıcıya açıklanır.
            $relaxMonth = false;
            $relaxDestination = false;
            $relaxBudget = false;
            $relaxAll = false;
            $relaxationNote = null;
            $results = collect();
            $rankedTours = collect();
            $candidateCount = 0;

            for ($relaxPass = 0; $relaxPass < 5; $relaxPass++) {

            $toursQuery = Tour::whereNotNull('embedding')
                ->active()
                ->whereHas('agency', fn ($agencyQuery) => $agencyQuery->active());

            // Geçmişte kalmış turlar aday olamaz: en az bir GELECEK kalkışı olan
            // (veya hiç tarih girilmemiş) turlar havuzda kalır.
            $toursQuery->where(function ($dateQuery) {
                $today = now()->toDateString();
                $dateQuery->whereDate('departure_date', '>=', $today)
                    ->orWhereHas('dates', fn ($d) => $d->whereDate('departure_date', '>=', $today))
                    ->orWhere(fn ($inner) => $inner->whereNull('departure_date')->whereDoesntHave('dates'));
            });

            // "Beğenmedim, başka öner" akışı: önceki turda gösterilen turlar dışlanır
            if (! empty($excludeTourIds)) {
                $toursQuery->whereNotIn('id', $excludeTourIds);
            }
            if (! $relaxBudget && $maxBudget && $maxBudget > 0) {
                // Soft upper bound: budget üstünü tamamen dışlamadan aday havuzunu daralt.
                // Kullanıcının bütçesi TL — kur-normalize price_try ile karşılaştır.
                $toursQuery->where('price_try', '<=', $maxBudget * 1.8);
            }
            // Yurt içi/dışı YÖNÜ asla gevşetilmez: "yurt dışı istiyorum" diyen
            // kullanıcıya yurt içi tur önermek güveni bitirir — yönde sonuç yoksa
            // dürüstçe bulunamadı denir.
            if ($isInternational !== null) {
                $toursQuery->where('is_international', $isInternational);
            }
            // Vize üç durumlu: belirtilmemiş (null) turlar vize YÖNÜ istenen
            // aramada elenir. SQL'de NULL zaten hiçbir değere eşit olmadığı için
            // bu kendiliğinden oluyor — bilinçli karar olduğu buraya yazıldı:
            // "vizesiz tur" arayana vize durumu bilinmeyen tur gösterilmez.
            if ($requiresVisa !== null) {
                $toursQuery->where('requires_visa', $requiresVisa);
            }
            if (! $relaxAll && $minDays && $minDays > 0) {
                $toursQuery->where('duration_days', '>=', max(1, $minDays - 1));
            }
            if (! $relaxAll && $maxDays && $maxDays > 0) {
                $toursQuery->where('duration_days', '<=', $maxDays + 1);
            }
            // Ay tercihi SERT filtre: "Eylül'de" diyen kullanıcıya o ayda kalkışı
            // olmayan tur gösterilmez (yumuşak 0.06 skor sessizce sızdırıyordu).
            // Envanterde o ay yoksa gevşetme basamağı nedeni söyleyerek açar.
            if (! $relaxMonth && ! $relaxAll && $preferredMonth !== null) {
                $today = now()->toDateString();
                $toursQuery->where(function ($mq) use ($preferredMonth, $today) {
                    $mq->whereHas('dates', fn ($d) => $d->whereMonth('departure_date', $preferredMonth)->whereDate('departure_date', '>=', $today))
                        ->orWhere(fn ($q) => $q->whereMonth('departure_date', $preferredMonth)->whereDate('departure_date', '>=', $today));
                });
            }
            if (! $relaxDestination && $preferredDestination !== null) {
                $this->tourScorer->applyDestinationConstraint($toursQuery, $preferredDestination);
            }
            if (! empty($excludedDestinations)) {
                $this->tourScorer->applyExcludedDestinationsConstraint($toursQuery, $excludedDestinations);
            }
            if (! empty($rejectedIds)) {
                $toursQuery->whereNotIn('id', $rejectedIds);
            }

            // 3.5. Memory-efficient pre-filter: tüm aday embedding'leri yerine
            // cursor ile sadece id+embedding stream et, top-K cosine ID'lerini bul,
            // sonra sadece top-K için full hydrate. 2000+ turda memory'i ~25MB → ~1MB.
            $candidateCount = (clone $toursQuery)->count();
            $tours = $this->hybridRanker->topKByCosine($toursQuery, $queryVector, 100, $cleanQuery);

            // 4. Hibrit skor: semantic + kullanıcı niyeti odaklı kriterler
            $rankedTours = $tours->map(function ($tour) use ($queryVector, $maxBudget, $isInternational, $requiresVisa, $minDays, $maxDays, $wantsNature, $avoidCrowdedCity, $wantsLively, $preferredMonth, $preferredDestination, $excludedDestinations, $rejectionAvgEmbedding, $profileVector) {
                // Pre-computed similarity varsa onu kullan (topKByCosine attach etti),
                // yoksa fallback olarak yeniden hesapla.
                $semanticScore = $tour->similarity ?? $this->hybridRanker->cosineSimilarity($queryVector, $tour->embedding);

                // Anahtar kelime kanalından güçlü gelen turlar (birebir ifade eşleşmesi)
                // salt cosine düşük diye 0.51 eşiğinin altında kalmasın
                if (($tour->keyword_rank ?? null) !== null) {
                    $semanticScore = max($semanticScore, $tour->keyword_rank <= 3 ? 0.66 : 0.56);
                }
                $budgetScore = $this->tourScorer->scoreBudget((float) ($tour->price_try ?? $tour->price), $maxBudget);
                $internationalScore = $this->tourScorer->scoreExactBool($tour->is_international, $isInternational);
                $visaScore = $this->tourScorer->scoreExactBool($tour->requires_visa, $requiresVisa);
                $durationScore = $this->tourScorer->scoreDuration((int) $tour->duration_days, $minDays, $maxDays);
                $natureScore = $this->tourScorer->scoreNatureFit(
                    (string) $tour->title,
                    (string) $tour->destination,
                    (string) ($tour->description ?? ''),
                    (string) ($tour->included ?? ''),
                    $wantsNature,
                    $avoidCrowdedCity
                );
                $cityEscapeScore = $this->tourScorer->scoreCityEscape((string) $tour->destination, $avoidCrowdedCity);
                $livelyScore = $this->tourScorer->scoreLivelyFit(
                    (string) $tour->title,
                    (string) $tour->destination,
                    (string) ($tour->description ?? ''),
                    (string) ($tour->included ?? ''),
                    $wantsLively,
                    $avoidCrowdedCity,
                    $tour->pace_score !== null ? (float) $tour->pace_score : null
                );
                $destinationScore = $this->tourScorer->scoreDestinationMatch(
                    $tour,
                    $preferredDestination,
                    $excludedDestinations
                );
                $monthScore = $this->tourScorer->scoreMonthForTour($tour, $preferredMonth);

                $tour->similarity = $semanticScore;
                // Replay/kalibrasyon için TÜM eksen skorları saklanır (loglara yazılır)
                $tour->budget_score = $budgetScore;
                $tour->international_score = $internationalScore;
                $tour->visa_score = $visaScore;
                $tour->duration_score = $durationScore;
                $tour->nature_score = $natureScore;
                $tour->city_escape_score = $cityEscapeScore;
                $tour->lively_score = $livelyScore;
                $tour->destination_score = $destinationScore;
                $tour->month_score = $monthScore;
                $tour->compatibility_score = $this->tourScorer->computeCompatibilityScore(
                    [
                        'semantic' => $semanticScore,
                        'budget' => $budgetScore,
                        'international' => $internationalScore,
                        'visa' => $visaScore,
                        'duration' => $durationScore,
                        'nature' => $natureScore,
                        'city_escape' => $cityEscapeScore,
                        'lively' => $livelyScore,
                        'month' => $monthScore,
                        'destination' => $destinationScore,
                    ],
                    [
                        'max_budget' => $maxBudget,
                        'is_international' => $isInternational,
                        'requires_visa' => $requiresVisa,
                        'preferred_min_days' => $minDays,
                        'preferred_max_days' => $maxDays,
                        'wants_nature' => $wantsNature,
                        'avoid_crowded_city' => $avoidCrowdedCity,
                        'wants_lively' => $wantsLively,
                        'preferred_month' => $preferredMonth,
                        'preferred_destination' => $preferredDestination,
                        'excluded_destinations' => $excludedDestinations,
                    ]
                );

                // Destinasyon profilinden seasonal bonus:
                // turun departure ay'ı destinasyonun "best_months" listesindeyse +0.05;
                // "crowded_months"'taysa ve kullanıcı kalabalıktan kaçınmak istiyorsa -0.05.
                $destProfile = $this->destinationProfiles
                    ->get((string) $tour->destination);

                $tour->seasonal_bonus = 0.0;
                $tour->destination_summary = $destProfile['summary'] ?? null;

                if ($tour->departure_date) {
                    $tourMonth = (int) $tour->departure_date->format('n');

                    if (! empty($destProfile['best_months']) && in_array($tourMonth, $destProfile['best_months'], true)) {
                        $tour->seasonal_bonus = 0.05;
                    }

                    if ($avoidCrowdedCity === true
                        && ! empty($destProfile['crowded_months'])
                        && in_array($tourMonth, $destProfile['crowded_months'], true)
                    ) {
                        $tour->seasonal_bonus -= 0.05;
                    }
                }

                // Vibe tag eşleşmesi: kullanıcının niyetiyle destinasyonun vibe_tags'i
                // arasında deterministik bonus/penalty. Embedding'in yumuşak yakaladığı
                // sinyali güçlendirir.
                $tour->vibe_score = $this->tourScorer->scoreVibeMatch(
                    $wantsNature,
                    $wantsLively,
                    $avoidCrowdedCity,
                    $destProfile['vibe_tags'] ?? null
                );

                // Negatif feedback penalty: kullanıcının son 24 saat reddettikleriyle
                // embedding olarak benzeyen turları cezalandır. Reddedilen yoksa 0.
                $tour->rejection_penalty = 0.0;
                if ($rejectionAvgEmbedding !== null && ! empty($tour->embedding)) {
                    $simToRejected = $this->hybridRanker->cosineSimilarity($tour->embedding, $rejectionAvgEmbedding);
                    if ($simToRejected > 0.7) {
                        $tour->rejection_penalty = -0.15;
                    }
                }

                // Kişisel tercih bonusu: kullanıcının geçmişte tıkladığı/favorilediği
                // turların ortalama vektörüne yakın turlara küçük artı (±0.04 sınırlı)
                $tour->profile_bonus = 0.0;
                if ($profileVector !== null && ! empty($tour->embedding)
                    && $this->hybridRanker->cosineSimilarity($tour->embedding, $profileVector) > 0.75) {
                    $tour->profile_bonus = 0.04;
                }

                $tour->compatibility_score = max(0.0, min(1.0,
                    (float) $tour->compatibility_score
                    + $tour->seasonal_bonus
                    + $tour->vibe_score
                    + $tour->rejection_penalty
                    + $tour->profile_bonus
                    // "Gösterildi ama tıklanmadı" öğrenimi (gece hesaplanır, ±0.03 sınırlı)
                    + max(-0.03, min(0.03, (float) ($tour->ai_ctr_bonus ?? 0)))
                ));

                return $tour;
            })->sortByDesc('compatibility_score')->values();

            if ($avoidCrowdedCity === true) {
                $nonCrowded = $rankedTours->reject(fn ($tour) => $this->tourScorer->isCrowdedCity((string) $tour->destination))->values();
                $crowded = $rankedTours->filter(fn ($tour) => $this->tourScorer->isCrowdedCity((string) $tour->destination))->values();
                if ($nonCrowded->isNotEmpty()) {
                    $rankedTours = $nonCrowded->concat($crowded)->values();
                }
            }

            if (! $relaxDestination && $preferredDestination !== null) {
                $destinationMatched = $rankedTours
                    ->filter(fn ($tour) => $this->tourScorer->matchesRequestedDestination($tour, $preferredDestination))
                    ->values();
                $rankedTours = $destinationMatched;
            }

            if (! empty($excludedDestinations)) {
                $rankedTours = $rankedTours
                    ->reject(fn ($tour) => $this->tourScorer->matchesAnyDestination($tour, $excludedDestinations))
                    ->values();
            }

            // Eşik filtresi: sadece %51 ve üzeri uyumluluk skoruna sahip turlar gösterilir.
            // Sıralama zaten descending; tüm geçerli turlar büyükten küçüğe sırayla döner.
            $results = $rankedTours
                ->filter(fn ($tour) => (float) $tour->compatibility_score >= self::COMPATIBILITY_THRESHOLD)
                ->values();

            if ($results->isNotEmpty()) {
                break;
            }

            // Aday var ama hiçbiri eşiği geçemedi → en iyi 3'ü "yakın eşleşme" olarak göster
            if ($rankedTours->isNotEmpty()) {
                $results = $rankedTours->take(3)->values();
                $relaxationNote = 'Kriterlerine birebir uyan tur bulamadım — en yakın seçenekleri gösteriyorum.';
                break;
            }

            // Hiç aday yok: önce ayı, sonra destinasyonu, sonra bütçe tavanını gevşet
            if (! $relaxMonth && $preferredMonth !== null) {
                $relaxMonth = true;
                $monthNames = TurkishMonths::NAMES;
                $relaxationNote = ($monthNames[$preferredMonth] ?? 'İstediğin ay').' kalkışlı uygun tur bulamadım — diğer aylardaki benzer seçenekleri gösteriyorum.';

                continue;
            }
            if (! $relaxDestination && $preferredDestination !== null) {
                $relaxDestination = true;
                $relaxationNote = '"'.$preferredDestination.'" için uygun tur bulamadım — diğer kriterlerine uyan alternatifleri gösteriyorum.';

                continue;
            }
            if (! $relaxBudget && $maxBudget && $maxBudget > 0) {
                $relaxBudget = true;
                $relaxationNote = 'Bu bütçeye uyan tur bulamadım — bütçenin üzerindeki seçenekleri de dahil ettim.';

                continue;
            }

            // SON BASAMAK: süre ve miras kriterler de bırakılır, salt semantik
            // yakınlıkla en iyi seçenekler getirilir. Yurt içi/dışı YÖNÜ ve vize
            // şartı korunur (yön ihlali önermek kullanıcıyı çıldırtıyor) — yön
            // uyuşan tek tur bile varsa kullanıcı çıplak "bulunamadı" görmez.
            if (! $relaxAll) {
                $relaxAll = true;
                $relaxDestination = true;
                $relaxBudget = true;
                $relaxationNote = 'Kriterlerinin tamamına birebir uyan tur bulamadım — sana en yakın seçenekleri gösteriyorum.';

                continue;
            }

            $relaxationNote = null; // havuzda hiç aktif tur yok → gerçek 0 sonuç

            break;

            } // gevşetme merdiveni sonu

            // Eşiğin ALTINDA kalan adayların kısa dökümü — acenta talep radarının
            // "turun neden kaçırdı" içgörüsünün ham verisi (loglanır)
            $nearMisses = $rankedTours
                ->filter(fn ($t) => (float) $t->compatibility_score < self::COMPATIBILITY_THRESHOLD)
                ->take(10)
                ->map(fn ($t) => [
                    'tour_id' => $t->id,
                    'agency_id' => $t->agency_id,
                    'score' => round((float) $t->compatibility_score, 4),
                    'weakest' => $this->weakestAxis($t),
                ])
                ->values()
                ->all();

            // 4.5. LLM re-ranker: en iyi 15 adayı mini model niyete göre yeniden
            // puanlar — "yaşlı annemle rahat tempolu" gibi nüansları program
            // metninden okur. Hata/kapalıysa hibrit sıra aynen kalır.
            if (config('ai.rerank_enabled', true) && $results->count() >= 3) {
                $results = $this->rerankResults($results, $analysis, $query);
            }

            // 5. RAG (Bilgi Bankası) Entegrasyonu
            $knowledgeService = new KnowledgeService;
            $relevantChunks = $knowledgeService->findRelevantChunks($query);
            $knowledgeContext = $knowledgeService->buildContext($relevantChunks);

            // Gevşetme yapıldıysa yorum LLM'i bunu kullanıcıya açıklasın — sonuçlar
            // neden kriterlerin birebir karşılığı değil, sebepsiz görünmesin.
            if ($relaxationNote !== null) {
                $knowledgeContext .= "\n\nARAMA NOTU: ".$relaxationNote.' Yorumunda bu durumu kullanıcıya kısaca ve kibarca belirt.';
            }

            // Yolcu profili: yorum tonu kişiye uysun (balayı çifti ≠ bebekli aile)
            $profileLabels = [
                'balayi' => 'balayı çifti', 'aile_bebek' => 'bebekli aile', 'aile_cocuk' => 'çocuklu aile',
                'arkadas_grubu' => 'arkadaş grubu', 'tek_basina' => 'tek başına gezgin', 'ciftler' => 'çift',
            ];
            $travelerProfile = $profileLabels[$analysis['traveler_profile'] ?? ''] ?? null;
            $occasion = trim((string) ($analysis['occasion'] ?? ''));
            if ($travelerProfile !== null || $occasion !== '') {
                $knowledgeContext .= "\n\nKULLANICI PROFİLİ: ".trim(($travelerProfile ?? '').' '.($occasion !== '' ? '('.str_replace('_', ' ', $occasion).')' : ''))
                    .' — yorumunun tonunu ve vurgusunu bu profile uydur (ör. balayı çiftine romantik/butik vurgusu, bebekli aileye rahatlık/tempo vurgusu). Uygunsa kısa bir tebrik/iyi dilek ekle.';
            }

            // Sohbette yalnızca EN İSABETLİ 7 tur gösterilir (results zaten uyum
            // skoruna göre sıralı); eşleşmelerin TAMAMI log'a yazılır ve
            // "tüm eşleşen turları gör" sayfasından erişilir.
            $totalMatches = $results->count();
            $displayResults = $results->take(self::CHAT_RESULT_LIMIT)->values();

            // 6. Akıllı, "Mekan Sahibi" Yorumu (RAG + Turlar) — yorum yalnızca
            // GÖSTERİLEN turlardan bahseder (liste dışı tur önerme kuralıyla tutarlı)
            // Streaming endpoint $skipComment=true geçer, kendi streamComment'i çağırır
            if ($totalMatches > self::CHAT_RESULT_LIMIT) {
                $knowledgeContext .= "\n\nEK BİLGİ: Toplam {$totalMatches} eşleşen tur bulundu; kullanıcıya en isabetli ".self::CHAT_RESULT_LIMIT." tanesi gösteriliyor ve tamamını görebileceği bağlantı sunuluyor.";
            }
            $aiComment = $skipComment
                ? null
                : $this->buildAiComment($query, $displayResults, $knowledgeContext);

            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            // Sadece frontend'in ihtiyacı olan alanları döndür (embedding hariç)
            $cleanResults = $displayResults->map(function ($tour, $index) use ($maxBudget, $preferredMonth, $preferredDestination, $wantsNature, $wantsLively, $avoidCrowdedCity) {
                return [
                    'id' => $tour->id,
                    'title' => $tour->title,
                    'slug' => $tour->slug,
                    'url' => route('tours.show', $tour),
                    'destination' => $tour->destination,
                    'price' => $tour->price,
                    'currency' => $tour->currency,
                    'duration_days' => $tour->duration_days,
                    'duration_label' => $tour->duration_label,
                    'departure_date' => $tour->departure_date,
                    'image' => $tour->image,
                    'rank' => $index + 1,
                    // Bütçe belirtildiyse ve tur üstündeyse işaretle — kullanıcıya
                    // "bütçe üstü" bilgisi yapısal olarak iletilir (sessiz sürpriz olmasın)
                    'over_budget' => ($maxBudget && $maxBudget > 0)
                        ? ((float) ($tour->price_try ?? $tour->price) > $maxBudget)
                        : false,
                    // "Neden bu tur?" — skor bileşenlerinden deterministik tek cümle
                    // (LLM'siz: 7 kart × LLM çağrısı maliyet tuzağı olurdu)
                    'reason' => $tour->rerank_reason
                        ?? $this->buildTourReason($tour, $maxBudget, $preferredMonth, $preferredDestination, $wantsNature, $wantsLively, $avoidCrowdedCity),
                    // En yakın gelecek kalkış — kullanıcı kartta tarihi hemen görsün
                    'next_departure' => $this->nextDepartureLabel($tour),
                    // Bütçe kurtarıcı: tur bütçe üstüyse ama başka bir tarihte bütçeye
                    // giren fiyat varsa çip olarak sun ("19 Eki — 34.500 TL bütçende")
                    'flex_date' => ($maxBudget && $maxBudget > 0)
                        ? $this->findBudgetFriendlyDate($tour, $maxBudget)
                        : null,
                    'similarity' => round((float) $tour->similarity, 6),
                    'compatibility_score' => round((float) $tour->compatibility_score, 6),
                    ...$this->roundedScoreBreakdown($tour),
                ];
            });

            $appliedFilters = $this->appliedFilters(
                $maxBudget, $isInternational, $requiresVisa, $minDays, $maxDays,
                $preferredMonth, $wantsNature, $avoidCrowdedCity, $wantsLively,
                $preferredDestination, $excludedDestinations
            );

            // 6. Eğitim/veri toplama logu
            $log = $this->logSearch($request, $query, $cleanQuery, $analysis, $appliedFilters, $relaxationNote, $candidateCount, $nearMisses, $results, $latencyMs);

            return [
                'results' => $cleanResults,
                'aiComment' => $aiComment,
                'log_id' => $log->id,
                'relaxation_note' => $relaxationNote,
                'total_matches' => $totalMatches,
                'all_results_url' => $totalMatches > self::CHAT_RESULT_LIMIT
                    ? route('ai.search.results', $log)
                    : null,
                'intent' => $analysis,
                'applied_filters' => $appliedFilters,
                'latency_ms' => $latencyMs,
                '_comment_context' => [
                    'query' => $query,
                    'results' => $displayResults, // gösterilen turlar (yorum yalnızca bunlardan bahseder)
                    'knowledge_context' => $knowledgeContext,
                ],
            ];

        } catch (\Throwable $e) {
            // \Throwable: alt katmandaki fatal Error'lar (ör. rerank) da graceful
            // fallback'e düşsün, tüm aramayı patlatmasın.
            Log::error('[AiSearch] performAiSearch hata: '.$e->getMessage());

            return ['error' => 'Arama sırasında bir sorun oluştu, lütfen tekrar deneyin.'];
        }
    }

    /**
     * Uygulanan (heuristik + LLM birleşimi) filtre haritası — hem dönüş payload'ı
     * hem arama logu aynı diziden beslenir (önceden iki el yazımı kopyaydı).
     *
     * @return array<string, mixed>
     */
    private function appliedFilters(
        ?int $maxBudget,
        ?bool $isInternational,
        ?bool $requiresVisa,
        ?int $minDays,
        ?int $maxDays,
        ?int $preferredMonth,
        ?bool $wantsNature,
        ?bool $avoidCrowdedCity,
        ?bool $wantsLively,
        ?string $preferredDestination,
        array $excludedDestinations
    ): array {
        return [
            'max_budget' => $maxBudget,
            'is_international' => $isInternational,
            'requires_visa' => $requiresVisa,
            'preferred_min_days' => $minDays,
            'preferred_max_days' => $maxDays,
            'preferred_month' => $preferredMonth,
            'wants_nature' => $wantsNature,
            'avoid_crowded_city' => $avoidCrowdedCity,
            'wants_lively' => $wantsLively,
            'preferred_destination' => $preferredDestination,
            'exclude_destinations' => $excludedDestinations,
        ];
    }

    /**
     * Skor eksenlerinin round(...,6) serileştirmesi — kart payload'ı ve log
     * result_scores aynı bloktan beslenir (önceden iki el yazımı kopyaydı).
     * Anahtar sırası her iki kullanımda da eski çıktıyla birebir aynıdır.
     *
     * @return array<string, float>
     */
    private function roundedScoreBreakdown($tour): array
    {
        return [
            'nature_score' => round((float) ($tour->nature_score ?? 1.0), 6),
            'city_escape_score' => round((float) ($tour->city_escape_score ?? 1.0), 6),
            'lively_score' => round((float) ($tour->lively_score ?? 1.0), 6),
            'destination_score' => round((float) ($tour->destination_score ?? 1.0), 6),
            'month_score' => round((float) ($tour->month_score ?? 1.0), 6),
            'vibe_score' => round((float) ($tour->vibe_score ?? 0.0), 6),
            'seasonal_bonus' => round((float) ($tour->seasonal_bonus ?? 0.0), 6),
            'rejection_penalty' => round((float) ($tour->rejection_penalty ?? 0.0), 6),
        ];
    }

    /**
     * Eğitim/veri toplama logu — AiSearchLog payload kurulumu tek yerde.
     *
     * @param  array<string, mixed>  $analysis
     * @param  array<string, mixed>  $appliedFilters
     * @param  array<int, array<string, mixed>>  $nearMisses
     */
    private function logSearch(
        Request $request,
        string $query,
        string $cleanQuery,
        array $analysis,
        array $appliedFilters,
        ?string $relaxationNote,
        int $candidateCount,
        array $nearMisses,
        Collection $results,
        int $latencyMs
    ): AiSearchLog {
        return AiSearchLog::create([
            'user_id' => $request->user()?->id,
            'session_id' => $request->session()->getId(),
            'raw_query' => $query,
            'normalized_query' => $cleanQuery,
            'intent' => $analysis,
            'applied_filters' => array_merge($appliedFilters, [
                'relaxation' => $relaxationNote, // kalite raporu: gevşetilmiş aramalar
            ]),
            'candidate_count' => $candidateCount,
            'near_misses' => $nearMisses ?: null,
            // TAM eşleşme listesi loglanır (sohbette 7'si gösterilse bile):
            // "tüm eşleşen turlar" sayfası ve eğitim verisi bunu kullanır
            'result_tour_ids' => $results->pluck('id')->values()->all(),
            'result_scores' => $results->map(function ($tour, $index) {
                return [
                    'tour_id' => $tour->id,
                    'rank' => $index + 1,
                    'compatibility_score' => round((float) $tour->compatibility_score, 6),
                    'semantic_score' => round((float) $tour->similarity, 6),
                    'budget_score' => round((float) ($tour->budget_score ?? 1.0), 6),
                    'international_score' => round((float) ($tour->international_score ?? 1.0), 6),
                    'visa_score' => round((float) ($tour->visa_score ?? 1.0), 6),
                    'duration_score' => round((float) ($tour->duration_score ?? 1.0), 6),
                    ...$this->roundedScoreBreakdown($tour),
                ];
            })->values()->all(),
            'latency_ms' => $latencyMs,
        ]);
    }

    /**
     * Mevcut user/session'ın son 24 saat içinde reddettiği tur ID'lerini toplar.
     * AiSearchLog.rejected_tour_ids JSON formatında objects (tour_id, reason, at)
     * veya plain ID array içerebilir; rejectedTourIds() helper'ı ikisini de handle eder.
     *
     * @return array<int, int>
     */
    public function collectRejectedTourIds(Request $request): array
    {
        $userId = $request->user()?->id;
        $sessionId = $request->session()->getId();

        $query = AiSearchLog::query()
            ->whereNotNull('rejected_tour_ids')
            ->where('rejected_at', '>=', now()->subDay());

        if ($userId !== null) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', $sessionId)->whereNull('user_id');
        }

        return $query->get(['rejected_tour_ids'])
            ->flatMap(fn (AiSearchLog $log) => $log->rejectedTourIds())
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Kullanıcı girdisini LLM'e güvenli şekilde verir.
     * - <USER_QUERY> tag'i içine sarar (system prompt bu tag'i "veri" olarak işaret eder).
     * - Tag delimiter'larını ASCII surrogate'larla değiştirir (kullanıcı kendi tag'ini açamaz).
     * - Çok uzun girdileri keser (token bombing'e karşı).
     *
     * Public: helper'ın test edilebilmesi ve gerekirse başka servislerden çağrılabilmesi için.
     */
    public function wrapUserInputSafely(string $input, int $maxLength = 1000): string
    {
        $sanitized = strtr($input, [
            '<' => '‹',
            '>' => '›',
        ]);

        $sanitized = mb_substr($sanitized, 0, $maxLength);

        return '<USER_QUERY>'.$sanitized.'</USER_QUERY>';
    }

    public function buildAiComment(string $query, Collection $results, string $context): string
    {
        try {
            [$systemPrompt, $userContent] = $this->buildCommentPromptParts($query, $results, $context);

            $response = OpenAI::chat()->create(OpenAiChatParams::text(config('ai.comment_model', 'gpt-4o-mini'), [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ], 300));

            return $response->choices[0]->message->content;

        } catch (\Throwable $e) {
            Log::error('[TourSearchService] Yorum oluşturma hatası: '.$e->getMessage());

            return 'Şu an senin için en iyi seçenekleri araştırıyorum. İşte bulduğum turlar:';
        }
    }

    /**
     * LLM re-ranker: eşiği geçen ilk 15 adayı kompakt kartlarla gpt-4o-mini'ye
     * verir, 0-10 uygunluk puanı + tek cümle gerekçe alır. Nihai skor =
     * 0.65×hibrit + 0.35×(puan/10). 24 sa önbellekli; hatada sıra değişmez.
     */
    public function rerankResults(Collection $results, array $analysis, string $query): Collection
    {
        $top = $results->take(15)->values();
        $rest = $results->slice(15)->values();

        $cards = $top->map(fn ($t) => [
            'id' => $t->id,
            'baslik' => $t->title,
            'destinasyon' => $t->destination,
            'fiyat' => (int) $t->price.' '.$t->currency,
            'gun' => (int) $t->duration_days,
            'ozet' => Str::limit((string) ($t->description ?? ''), 150),
            'program' => Str::limit(collect(is_array($t->itinerary) ? $t->itinerary : [])->pluck('title')->implode(' / '), 120),
        ])->all();

        $intentSummary = json_encode(array_filter([
            'butce_tl' => $analysis['max_budget'] ?? null,
            'ay' => $analysis['preferred_month'] ?? null,
            'profil' => $analysis['traveler_profile'] ?? null,
            'doga' => $analysis['wants_nature'] ?? null,
            'hareketli' => $analysis['wants_lively'] ?? null,
            'sakin' => $analysis['avoid_crowded_city'] ?? null,
        ], fn ($v) => $v !== null), JSON_UNESCAPED_UNICODE);

        $cacheKey = 'ai:rerank:'.md5(TurkishText::normalize($query).'|'.$intentSummary.'|'.$top->pluck('id')->implode(','));

        try {
            // Cache::remember DEĞİL: parse hatasında boş skor listesi 24 saat
            // cache'lenip rerank'ı kalıcı devre dışı bırakırdı. Boş skor
            // cache'lenmez → sonraki denemede taze puanlanır.
            $scores = Cache::get($cacheKey);
            if (! is_array($scores) || $scores === []) {
                $response = OpenAI::chat()->create(OpenAiChatParams::json(config('ai.router_model', 'gpt-4o-mini'), [
                    ['role' => 'system', 'content' => $this->prompts->rerankSystemPrompt($intentSummary, $cards)],
                    ['role' => 'user', 'content' => $this->wrapUserInputSafely($query)],
                ], 900));

                $data = json_decode($response->choices[0]->message->content ?? '{}', true) ?: [];
                $scores = is_array($data['scores'] ?? null) ? $data['scores'] : [];
                if ($scores !== []) {
                    Cache::put($cacheKey, $scores, 86400);
                }
            }
        } catch (\Throwable $e) {
            Log::info('[AiRerank] hata, hibrit sıra korunuyor', ['message' => $e->getMessage()]);

            return $results;
        }

        if (empty($scores)) {
            return $results;
        }

        $byId = collect($scores)->filter(fn ($s) => isset($s['id']))->keyBy(fn ($s) => (int) $s['id']);

        $reranked = $top->map(function ($t) use ($byId) {
            $s = $byId->get($t->id);
            if ($s !== null) {
                $llmScore = max(0.0, min(10.0, (float) ($s['score'] ?? 5))) / 10;
                $t->compatibility_score = max(0.0, min(1.0, 0.65 * (float) $t->compatibility_score + 0.35 * $llmScore));
                if (! empty($s['reason']) && is_string($s['reason'])) {
                    $t->rerank_reason = Str::limit(trim($s['reason']), 140);
                }
            }

            return $t;
        })->sortByDesc('compatibility_score')->values();

        return $reranked->concat($rest);
    }

    /** Turun en zayıf skor ekseni (acenta radarı: "turun neden kaçırdı"). */
    public function weakestAxis($tour): string
    {
        $axes = array_filter([
            'içerik uyumu' => $tour->similarity ?? null,
            'bütçe' => $tour->budget_score ?? null,
            'süre' => $tour->duration_score ?? null,
            'doğa' => $tour->nature_score ?? null,
            'sakinlik' => $tour->city_escape_score ?? null,
            'hareketlilik' => $tour->lively_score ?? null,
            'ay uygunluğu' => $tour->month_score ?? null,
            'destinasyon' => $tour->destination_score ?? null,
        ], fn ($v) => $v !== null);

        if (empty($axes)) {
            return 'içerik uyumu';
        }
        asort($axes);

        return (string) array_key_first($axes);
    }

    /** Turun en yakın gelecek kalkış tarihi etiketi (kart için, d.m.Y). */
    public function nextDepartureLabel($tour): ?string
    {
        $next = null;
        if ($tour->relationLoaded('dates')) {
            $next = $tour->dates
                ->filter(fn ($d) => $d->departure_date && $d->departure_date->greaterThanOrEqualTo(now()->startOfDay()))
                ->sortBy('departure_date')
                ->first()?->departure_date;
        }
        $next ??= ($tour->departure_date && $tour->departure_date->greaterThanOrEqualTo(now()->startOfDay()))
            ? $tour->departure_date
            : null;

        return $next?->format('d.m.Y');
    }

    /**
     * Bütçe kurtarıcı: turun ana fiyatı bütçe üstündeyken, bütçeye giren
     * GELECEK tarihli bir kalkış varsa onu döner — kullanıcı "tarihinde
     * esnersen bütçene giriyor" çipini görür. Tur zaten bütçedeyse null.
     *
     * @return array{date: string, price: string}|null
     */
    public function findBudgetFriendlyDate($tour, int $maxBudget): ?array
    {
        $mainPrice = (float) ($tour->price_try ?? $tour->price);
        if ($mainPrice <= $maxBudget || ! $tour->relationLoaded('dates')) {
            return null;
        }

        $cheapest = $tour->dates
            ->filter(fn ($d) => $d->departure_date
                && $d->departure_date->greaterThanOrEqualTo(now()->startOfDay())
                && $d->price !== null
                && \App\Models\CurrencyRate::toTry((float) $d->price, $tour->currency) <= $maxBudget)
            ->sortBy(fn ($d) => (float) $d->price)
            ->first();

        if (! $cheapest) {
            return null;
        }

        return [
            'date' => $cheapest->departure_date->format('d.m.Y'),
            'price' => number_format((float) $cheapest->price, 0, ',', '.').' '.$tour->currency_symbol,
        ];
    }

    /**
     * "Neden bu tur?" — kartta gösterilen kişiye özel tek cümle gerekçe.
     * Tamamen deterministik: hesaplanmış skor bileşenlerinden şablonla üretilir.
     */
    public function buildTourReason(
        $tour,
        ?int $maxBudget,
        ?int $preferredMonth,
        ?string $preferredDestination,
        ?bool $wantsNature,
        ?bool $wantsLively,
        ?bool $avoidCrowdedCity
    ): ?string {
        $monthNames = TurkishMonths::NAMES;
        $parts = [];

        if ($preferredDestination !== null && (float) ($tour->destination_score ?? 0) >= 0.99) {
            $parts[] = 'tam istediğin bölgede';
        }
        if ($preferredMonth !== null && (float) ($tour->month_score ?? 0) >= 0.99) {
            $parts[] = ($monthNames[$preferredMonth] ?? '').' kalkışı var';
        }
        if ($maxBudget && $maxBudget > 0 && (float) ($tour->price_try ?? $tour->price) <= $maxBudget) {
            $parts[] = 'bütçene uyuyor';
        }
        if ($wantsNature === true && (float) ($tour->nature_score ?? 0) >= 0.75) {
            $parts[] = 'doğa ağırlıklı';
        }
        if ($avoidCrowdedCity === true && (float) ($tour->city_escape_score ?? 0) >= 0.75) {
            $parts[] = 'kalabalıktan uzak';
        }
        if ($wantsLively === true && (float) ($tour->lively_score ?? 0) >= 0.75) {
            $parts[] = 'hareketli bir programı var';
        }

        if (empty($parts)) {
            return (float) ($tour->similarity ?? 0) >= 0.6 ? 'Anlattıklarına içerik olarak en yakın turlardan.' : null;
        }

        return mb_convert_case(mb_substr(implode(', ', array_slice($parts, 0, 2)), 0, 1), MB_CASE_TITLE, 'UTF-8')
            .mb_substr(implode(', ', array_slice($parts, 0, 2)), 1).'.';
    }

    /**
     * İki-üç turun deterministik karşılaştırma tablosu — sayılar DB'den gelir,
     * LLM üretmez (halüsinasyon sıfır). Frontend 'compare' SSE eventiyle çizer.
     *
     * @param  \Illuminate\Support\Collection  $tours
     * @return array{columns: array, rows: array}
     */
    public function buildComparisonTable($tours): array
    {
        $monthNames = [1 => 'Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'];

        $months = fn ($tour) => $tour->dates
            ->map(fn ($d) => $d->departure_date?->format('n'))
            ->filter()->unique()->sort()
            ->map(fn ($m) => $monthNames[(int) $m])
            ->implode(', ') ?: '—';

        $boarding = fn ($tour) => collect([$tour->departure_city])
            ->merge(is_array($tour->stop_cities) ? $tour->stop_cities : [])
            ->filter()->unique()->implode(', ') ?: '—';

        return [
            'columns' => $tours->map(fn ($t) => [
                'id' => $t->id,
                'title' => $t->title,
                'url' => route('tours.show', $t),
                'image' => $t->image,
            ])->values()->all(),
            'rows' => [
                ['label' => 'Fiyat', 'values' => $tours->map(fn ($t) => number_format((float) $t->price, 0, ',', '.').' '.$t->currency_symbol)->values()->all()],
                ['label' => 'Süre', 'values' => $tours->map(fn ($t) => $t->duration_days.' gün')->values()->all()],
                ['label' => 'Destinasyon', 'values' => $tours->map(fn ($t) => (string) $t->destination)->values()->all()],
                ['label' => 'Kalkış şehirleri', 'values' => $tours->map($boarding)->values()->all()],
                ['label' => 'Kalkış ayları', 'values' => $tours->map($months)->values()->all()],
                ['label' => 'Konaklama', 'values' => $tours->map(fn ($t) => Str::limit((string) ($t->hotel_info ?: '—'), 60))->values()->all()],
                ['label' => 'Program', 'values' => $tours->map(fn ($t) => is_array($t->itinerary) && count($t->itinerary) ? count($t->itinerary).' günlük program' : '—')->values()->all()],
            ],
        ];
    }

    /**
     * Turun LLM'e verilen yapılandırılmış veri fişi — tur-içi soru cevaplama
     * yalnızca bu alanlardan beslenir (RAG'a/embedding'e gitmez, uydurma kapalı).
     */
    public function tourFactSheet(Tour $tour): string
    {
        $tour->loadMissing('agency', 'dates');

        $dates = $tour->dates
            ->filter(fn ($d) => $d->departure_date && $d->departure_date->isFuture())
            ->map(fn ($d) => $d->departure_date->format('d.m.Y'))
            ->take(8)->implode(', ');

        $itinerary = collect(is_array($tour->itinerary) ? $tour->itinerary : [])
            ->map(fn ($day, $i) => ($i + 1).'. Gün — '.($day['title'] ?? '').': '.Str::limit((string) ($day['content'] ?? ''), 400))
            ->implode("\n");

        $campaign = $tour->active_campaign;
        $profileService = $this->destinationProfiles;
        $visa = $profileService->get((string) $tour->destination)['requires_visa_for_tr'] ?? null;
        // Çok şehirli rotada her şehrin KENDİ profili yazılır — substring
        // fallback'in seçtiği tek şehir tüm rotanın karakteri gibi sunulmaz
        $cityProfileLines = collect($profileService->describeCities((string) $tour->destination))
            ->map(fn ($p) => 'Şehir profili ('.$p['city'].'): '.$p['character'].(! empty($p['summary']) ? ' — '.$p['summary'] : ''))
            ->implode("\n");
        $paceLabel = $tour->pace_score !== null
            ? match (true) {
                (float) $tour->pace_score >= 0.66 => 'yüksek (yoğun gezi programı)',
                (float) $tour->pace_score <= 0.35 => 'düşük (bol serbest zaman, dinlenme ağırlıklı)',
                default => 'orta',
            }
            : null;
        $reviewCount = $tour->reviews()->count();

        return implode("\n", array_filter([
            "TUR: {$tour->title}",
            "Acenta: {$tour->agency?->name}",
            "Destinasyon: {$tour->destination}",
            $tour->character_summary ? 'Tur karakteri: '.$tour->character_summary : null,
            $paceLabel !== null ? 'Tempo: '.$paceLabel : null,
            $cityProfileLines !== '' ? $cityProfileLines : null,
            'Fiyat (başlangıç): '.number_format((float) $tour->price, 0, ',', '.').' '.$tour->currency,
            $campaign ? 'KAMPANYA: '.$campaign->label.' — kampanyalı fiyat '.number_format((float) $campaign->discount_price, 0, ',', '.').' '.$tour->currency : null,
            $visa !== null ? 'Vize (TR vatandaşı): '.($visa ? 'gerekli — pasaportun seyahat bitiminden sonra en az 6 ay geçerli olmalı; randevu süreleri uzayabilir, erken başvuru öner' : 'gerekmiyor') : null,
            $reviewCount > 0 ? 'Değerlendirme: '.$tour->avg_rating.'/5 ('.$reviewCount.' yorum)' : null,
            "Süre: {$tour->duration_days} gün",
            $dates !== '' ? "Kalkış tarihleri: {$dates}" : null,
            $tour->departure_points ? 'Kalkış noktaları: '.Str::limit((string) $tour->departure_points, 200) : null,
            $tour->hotel_info ? 'Konaklama: '.Str::limit((string) $tour->hotel_info, 300) : null,
            $tour->included ? 'Fiyata dahil: '.Str::limit((string) $tour->included, 400) : null,
            $tour->excluded ? 'Dahil olmayan: '.Str::limit((string) $tour->excluded, 300) : null,
            $tour->extras ? 'Ekstra aktiviteler: '.Str::limit((string) $tour->extras, 300) : null,
            $tour->cancellation_policy ? 'İptal/iade: '.Str::limit((string) $tour->cancellation_policy, 300) : null,
            $itinerary !== '' ? "GÜN GÜN PROGRAM:\n".Str::limit($itinerary, 4000) : null,
        ]));
    }

    /**
     * Arama-dışı mesajların (tur sorusu / kıyas / site sorusu / sohbet) cevabını
     * gpt-4o-mini ile akıtır — arama pipeline'ı (gpt-4o intent + skorlama) hiç
     * çalışmaz. Cevap yalnızca verilen tur fişleri/bilgi bankasından beslenir.
     *
     * @param  \Illuminate\Support\Collection  $tours
     * @param  \Closure(string): void  $onToken
     */
    public function streamContextualAnswer(string $mode, $tours, string $question, array $intent, \Closure $onToken): string
    {
        $factSheets = '';
        if ($mode === 'tour_question' || $mode === 'compare') {
            $factSheets = $tours->map(fn ($t) => $this->tourFactSheet($t))->implode("\n\n---\n\n");
        }

        $knowledgeContext = '';
        if ($mode === 'site_question') {
            $knowledgeService = new KnowledgeService;
            $chunks = $knowledgeService->findRelevantChunks($question);
            $knowledgeContext = $knowledgeService->buildContext($chunks);
        }

        $systemPrompt = $this->prompts->contextualSystemPrompt($mode, $factSheets, $intent, $knowledgeContext);

        $full = '';
        try {
            $stream = OpenAI::chat()->createStreamed(OpenAiChatParams::text(config('ai.router_model', 'gpt-4o-mini'), [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $this->wrapUserInputSafely($question)],
            ], 400));

            foreach ($stream as $response) {
                $delta = $response->choices[0]->delta->content ?? null;
                if ($delta !== null && $delta !== '') {
                    $full .= $delta;
                    $onToken($delta);
                }
            }
        } catch (\Throwable $e) {
            Log::error('[AiSearch] bağlamsal cevap hatası: '.$e->getMessage());
            if ($full === '') {
                $fallback = 'Şu an cevaplayamadım — sorunu bir daha yazar mısın?';
                $onToken($fallback);

                return $fallback;
            }
        }

        return $full;
    }

    /**
     * AI yorum üretimi için system + user prompt parçalarını üretir.
     * buildAiComment (sync) ve streamComment (streaming) bunu paylaşır.
     *
     * @return array{0: string, 1: string} [systemPrompt, userContent]
     */
    public function buildCommentPromptParts(string $query, Collection $results, string $context): array
    {
        $profileService = $this->destinationProfiles;
        $toursInfo = $results->isNotEmpty()
            ? "Bulunan uygun turlar:\n".$results->map(function ($t) use ($profileService) {
                $line = "- {$t->title} ({$t->destination}): {$t->price} {$t->currency}, {$t->duration_days} gün";
                // Kampanya yalnız GERÇEKTEN varsa yazılır (model kampanya uyduramaz)
                if ($campaign = $t->active_campaign) {
                    $line .= ' | KAMPANYA: '.$campaign->label.' '.number_format((float) $campaign->discount_price, 0, ',', '.').' '.$t->currency;
                }
                // Vize bilgisi destinasyon profilinden (LLM'siz, 5 dk cache'li)
                $visa = $profileService->get((string) $t->destination)['requires_visa_for_tr'] ?? null;
                if ($visa === false) {
                    $line .= ' | vizesiz';
                } elseif ($visa === true) {
                    $line .= ' | vize gerekli';
                }
                // Tur karakteri (tek seferlik job üretimi): "dinlenme turu /
                // eğlence turu" gerekçesini model buradan kurar, uydurmaz
                if (! empty($t->character_summary)) {
                    $line .= ' | KARAKTER: '.$t->character_summary;
                }

                return $line;
            })->implode("\n")
            : 'Uyan aktif bir tur şu an bulunamadı.';

        // Destinasyon profillerinden zengin bağlam (LLM job'ı doldurdukça artar):
        // çok şehirli rotalarda ŞEHİR BAŞINA satır — tek şehrin profili tüm
        // rotaya mal edilmez
        $destinationContext = $results
            ->flatMap(function ($t) use ($profileService) {
                return collect($profileService->describeCities((string) $t->destination, onlyDescribed: false))
                    ->map(function ($entry) {
                        $bits = array_filter([$entry['character'], $entry['summary']]);

                        return $bits !== [] ? "- {$entry['city']}: ".implode(' — ', $bits) : null;
                    });
            })
            ->filter()
            ->unique()
            ->implode("\n");

        // Sitedeki gerçek destinasyon envanteri: model yalnız turumuz olan
        // yerlere yönlendirebilsin (olmayan yere yönlendirme = uydurma)
        $inventoryLine = $this->destinationKnowledge->promptInventoryLine();

        $systemPrompt = $this->prompts->commentSystemPrompt($context, $inventoryLine, $destinationContext, $toursInfo);

        return [$systemPrompt, $this->wrapUserInputSafely($query)];
    }

    /**
     * AI yorumunu streaming olarak üretir. Her chunk geldiğinde $onToken callback çağrılır.
     * Tamamlanmış yorumun full string'ini döndürür.
     *
     * @param  \Closure(string): void  $onToken
     */
    public function streamComment(string $query, Collection $results, string $context, \Closure $onToken): string
    {
        [$systemPrompt, $userContent] = $this->buildCommentPromptParts($query, $results, $context);

        $full = '';

        try {
            $stream = OpenAI::chat()->createStreamed(OpenAiChatParams::text(config('ai.comment_model', 'gpt-4o-mini'), [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userContent],
            ], 300));

            foreach ($stream as $response) {
                $delta = $response->choices[0]->delta->content ?? null;
                if ($delta !== null && $delta !== '') {
                    $full .= $delta;
                    $onToken($delta);
                }
            }
        } catch (\Throwable $e) {
            Log::error('[TourSearchService] Streaming yorum hatası: '.$e->getMessage());

            if ($full === '') {
                $fallback = 'Şu an senin için en iyi seçenekleri araştırıyorum. İşte bulduğum turlar:';
                $onToken($fallback);

                return $fallback;
            }
        }

        return $full;
    }
}
