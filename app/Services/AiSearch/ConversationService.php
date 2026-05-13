<?php

namespace App\Services\AiSearch;

use App\Http\Controllers\AiSearchController;
use App\Models\AiSearchConversation;
use App\Models\AiSearchMessage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ConversationService
{
    /** Bir konuşmada en fazla bu kadar netleştirme sorusu sorulur, sonra zorla aramaya geçilir. */
    public const MAX_CLARIFICATIONS = 2;

    /** Aynı conversation'a paralel mesaj çakışmasını önlemek için lock. */
    public const LOCK_TTL_SECONDS = 30;
    public const LOCK_BLOCK_SECONDS = 5;

    /**
     * NOTE: Şu an AiSearchController'ın public performAiSearch metodunu çağırıyoruz.
     * TODO: AiSearchController içindeki search logic'ini bağımsız bir TourSearchService'e
     * çıkardığında bu çağrıyı oraya yönlendir.
     */
    public function __construct(private readonly AiSearchController $searchController) {}

    public function startOrLoad(Request $request, ?string $uuid = null): AiSearchConversation
    {
        if ($uuid) {
            $conversation = AiSearchConversation::where('uuid', $uuid)->first();

            if ($conversation && $this->canAccess($request, $conversation)) {
                return $conversation;
            }
        }

        return AiSearchConversation::create([
            'user_id' => $request->user()?->id,
            'session_id' => Str::limit($request->session()->getId(), 64, ''),
            'last_message_at' => now(),
        ]);
    }

    public function canAccess(Request $request, AiSearchConversation $conversation): bool
    {
        if ($conversation->user_id) {
            return $conversation->user_id === $request->user()?->id;
        }

        return $conversation->session_id === Str::limit($request->session()->getId(), 64, '');
    }

    /**
     * Kullanıcı mesajını işler: niyet merge + arama + asistan cevabı kaydeder.
     *
     * Aynı conversation_id'ye paralel istek gelirse atomic lock ile sıraya alınır.
     * Lock alınamazsa LockTimeoutException fırlar — controller bunu 429'a çevirir.
     *
     * @return array{user: AiSearchMessage, assistant: AiSearchMessage, payload: array<string, mixed>}
     * @throws LockTimeoutException Lock alınamadığında
     */
    public function respond(Request $request, AiSearchConversation $conversation, string $userMessage): array
    {
        $userMessage = trim($userMessage);
        if ($userMessage === '') {
            throw new \InvalidArgumentException('Boş mesaj gönderilemez.');
        }

        $lock = Cache::lock('ai_conv_lock:' . $conversation->id, self::LOCK_TTL_SECONDS);

        return $lock->block(self::LOCK_BLOCK_SECONDS, function () use ($request, $conversation, $userMessage) {
            // Lock altında çalışırken conversation'ın güncel halini al — diğer paralel istek
            // tamamlanmış olabilir, intent değişmiş olabilir.
            $conversation->refresh();

            return $this->respondInTransaction($request, $conversation, $userMessage);
        });
    }

    private function respondInTransaction(Request $request, AiSearchConversation $conversation, string $userMessage): array
    {
        return DB::transaction(function () use ($request, $conversation, $userMessage) {
            $userMsg = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_USER,
                'content' => $userMessage,
            ]);

            $previousIntent = $conversation->current_intent ?? [];
            $clarificationsAsked = (int) ($previousIntent['_clarifications'] ?? 0);

            // 1. Niyet eksikse, aramadan önce kullanıcıya bir netleştirme sorusu sor
            if ($clarificationsAsked < self::MAX_CLARIFICATIONS) {
                $question = $this->maybeAskClarification($userMessage, $previousIntent);

                if ($question !== null) {
                    $assistantMsg = AiSearchMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => AiSearchMessage::ROLE_ASSISTANT,
                        'content' => $question,
                    ]);

                    $nextIntent = array_merge($previousIntent, [
                        '_clarifications' => $clarificationsAsked + 1,
                    ]);

                    $conversation->update([
                        'current_intent' => $nextIntent,
                        'last_message_at' => now(),
                        'title' => $conversation->title ?: Str::limit($userMessage, 60),
                    ]);

                    return [
                        'user' => $userMsg,
                        'assistant' => $assistantMsg,
                        'payload' => [
                            'aiComment' => $question,
                            'results' => [],
                            'is_clarification' => true,
                        ],
                    ];
                }
            }

            // 2. Niyet yeterince dolu — arama yap
            $searchResult = $this->searchController->performAiSearch($request, $userMessage, $previousIntent);

            if (!is_array($searchResult) || isset($searchResult['error'])) {
                $errorMessage = $searchResult['error'] ?? 'Arama başarısız.';
                $assistantMsg = AiSearchMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => AiSearchMessage::ROLE_ASSISTANT,
                    'content' => 'Üzgünüm, arama sırasında bir sorun oldu: ' . $errorMessage,
                ]);

                return [
                    'user' => $userMsg,
                    'assistant' => $assistantMsg,
                    'payload' => ['error' => $errorMessage, 'results' => [], 'aiComment' => $assistantMsg->content],
                ];
            }

            $resultIds = collect($searchResult['results'])->pluck('id')->all();
            $resultScores = collect($searchResult['results'])->map(fn($r) => [
                'tour_id' => $r['id'],
                'rank' => $r['rank'],
                'compatibility_score' => $r['compatibility_score'] ?? null,
            ])->all();

            $assistantMsg = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_ASSISTANT,
                'content' => $searchResult['aiComment'] ?? '',
                'intent_snapshot' => $searchResult['intent'] ?? null,
                'result_tour_ids' => $resultIds,
                'result_scores' => $resultScores,
                'latency_ms' => $searchResult['latency_ms'] ?? null,
            ]);

            $mergedIntent = $searchResult['intent'] ?? $previousIntent;
            // _clarifications sayacını koru (debug ve max enforcement için)
            if (isset($previousIntent['_clarifications'])) {
                $mergedIntent['_clarifications'] = $previousIntent['_clarifications'];
            }

            $conversation->update([
                'current_intent' => $mergedIntent,
                'last_result_tour_ids' => $resultIds,
                'last_message_at' => now(),
                'title' => $conversation->title ?: Str::limit($userMessage, 60),
            ]);

            return [
                'user' => $userMsg,
                'assistant' => $assistantMsg,
                'payload' => [
                    'aiComment' => $searchResult['aiComment'] ?? '',
                    'results' => $searchResult['results'],
                    'applied_filters' => $searchResult['applied_filters'] ?? [],
                    'log_id' => $searchResult['log_id'] ?? null,
                ],
            ];
        });
    }

    /**
     * Niyet eksikse Türkçe bir netleştirme sorusu döndürür, yeterliyse null.
     *
     * Tamamen deterministik (LLM kullanmaz). 4 sinyal ekseni: budget, destination,
     * time, vibe. Mesaj + previous intent'in toplamından en az 2 eksen dolmamışsa
     * eksik kalanlardan en kritik olanı sorar. LLM'in tutarsız "yeterli mi" yargısı
     * yerine kuralla yönetmek tutarlılık ve hız kazandırır.
     */
    public function maybeAskClarification(string $userMessage, array $previousIntent): ?string
    {
        $cleanIntent = collect($previousIntent)
            ->reject(fn($_, $key) => str_starts_with($key, '_'))
            ->all();

        $signals = [
            'budget' => $this->hasBudgetSignal($cleanIntent, $userMessage),
            'destination' => $this->hasDestinationSignal($cleanIntent, $userMessage),
            'time' => $this->hasTimeSignal($cleanIntent, $userMessage),
            'vibe' => $this->hasVibeSignal($cleanIntent, $userMessage),
        ];

        $present = array_keys(array_filter($signals));

        // 2+ farklı eksen biliniyorsa arama yapılır
        if (count($present) >= 2) {
            return null;
        }

        // Eksik eksenler — kritiklik sırasına göre tek soruya birleştir
        $missing = array_keys(array_filter($signals, fn($v) => !$v));

        return $this->buildClarificationQuestion($missing, $present);
    }

    private function hasBudgetSignal(array $intent, string $message): bool
    {
        if (!empty($intent['max_budget'])) {
            return true;
        }

        $normalized = mb_strtolower($message, 'UTF-8');

        // "bütçe" / "butce" kelimesi → kullanıcı bütçeden bahsediyor
        if (str_contains($normalized, 'bütçe') || str_contains($normalized, 'butce')) {
            return true;
        }

        // "30K", "30 k", "30 bin" gibi kısa miktar ifadeleri
        if (preg_match('/\d{1,4}\s*(k\b|bin\b)/u', $normalized)) {
            return true;
        }

        // 20000, 30.000, 25,000 gibi 4+ haneli sayılar (genelde bütçe)
        // Yıl olabilir (2025, 2026) — onları dışla.
        if (preg_match_all('/\b(\d{4,})\b/u', $normalized, $matches)) {
            foreach ($matches[1] as $num) {
                $value = (int) $num;
                // 1000-1999 yıl olamaz tipik bütçe; 2000-2099 yıl olabilir
                // Pragmatik: 1000-1999 → bütçe, 2000-2099 → muğlak (atla), 2100+ → bütçe
                if ($value >= 1000 && ($value < 2000 || $value > 2099)) {
                    return true;
                }
            }
        }

        // Para birimi yan yana ya da tek başına
        if (preg_match('/\d{2,}\s*(tl|lira|euro|eur|dolar|usd|gbp|aed|sar)/u', $normalized)) {
            return true;
        }

        if (preg_match('/\b(tl|lira|euro|dolar|usd|eur|gbp)\b/u', $normalized)) {
            return true;
        }

        return false;
    }

    private function hasDestinationSignal(array $intent, string $message): bool
    {
        $intentKeys = ['preferred_destination', 'is_international', 'exclude_destinations', 'requires_visa'];
        foreach ($intentKeys as $key) {
            if (array_key_exists($key, $intent) && $intent[$key] !== null && $intent[$key] !== '' && $intent[$key] !== []) {
                return true;
            }
        }

        $normalized = mb_strtolower($message, 'UTF-8');

        if (preg_match('/(yurt\s?(?:içi|dışı|disi|ici)|avrupa|asya|amerika|afrika|orta\s?doğu|balkan|akdeniz|ege|karadeniz|akdeniz)/u', $normalized)) {
            return true;
        }

        $popularPlaces = [
            'paris', 'roma', 'londra', 'amsterdam', 'venedik', 'barselona', 'prag', 'atina', 'berlin', 'viyana', 'milano', 'floransa',
            'istanbul', 'antalya', 'bodrum', 'kapadokya', 'fethiye', 'marmaris', 'çeşme', 'alanya', 'didim', 'kuşadası', 'izmir', 'ankara',
            'bali', 'maldivler', 'dubai', 'tayland', 'phuket', 'singapur', 'tokyo', 'newyork', 'new york', 'miami', 'las vegas',
            'mısır', 'fas', 'tunus', 'yunan', 'mykonos', 'santorini', 'rodos', 'girit', 'kıbrıs',
        ];
        foreach ($popularPlaces as $place) {
            if (str_contains($normalized, $place)) {
                return true;
            }
        }

        return false;
    }

    private function hasTimeSignal(array $intent, string $message): bool
    {
        $intentKeys = ['preferred_month', 'preferred_min_days', 'preferred_max_days'];
        foreach ($intentKeys as $key) {
            if (!empty($intent[$key])) {
                return true;
            }
        }

        $normalized = mb_strtolower($message, 'UTF-8');

        $timeKeywords = [
            'ocak', 'şubat', 'mart', 'nisan', 'mayıs', 'haziran',
            'temmuz', 'ağustos', 'eylül', 'ekim', 'kasım', 'aralık',
            'yaz', 'kış', 'bahar', 'sonbahar', 'sömestir',
            'haftasonu', 'hafta sonu', 'haftaiçi', 'bayram',
            'önümüzdeki', 'gelecek hafta', 'gelecek ay',
        ];
        foreach ($timeKeywords as $kw) {
            if (str_contains($normalized, $kw)) {
                return true;
            }
        }

        // "5 gün", "3 hafta" vb.
        if (preg_match('/\d+\s*(gün|hafta|ay)/u', $normalized)) {
            return true;
        }

        return false;
    }

    private function hasVibeSignal(array $intent, string $message): bool
    {
        $intentKeys = ['wants_nature', 'avoid_crowded_city', 'wants_lively'];
        foreach ($intentKeys as $key) {
            if (array_key_exists($key, $intent) && $intent[$key] !== null) {
                return true;
            }
        }

        $normalized = mb_strtolower($message, 'UTF-8');

        $vibeKeywords = [
            'plaj', 'doğa', 'kültür', 'tarihi', 'tarih', 'kayak', 'cruise', 'gemi',
            'safari', 'macera', 'lüks', 'romantik', 'balayı',
            'gece hayat', 'eğlence', 'sakin', 'huzurlu', 'kalabalık',
            'şehir turu', 'gezi', 'spa', 'wellness', 'all inclusive',
        ];
        foreach ($vibeKeywords as $kw) {
            if (str_contains($normalized, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $missing  Eksik eksen anahtarları
     * @param  array<int, string>  $present  Bilinen eksenler
     */
    private function buildClarificationQuestion(array $missing, array $present): string
    {
        // Hiçbir şey yok — geniş açılış sorusu
        if (count($present) === 0) {
            return 'Sana uygun bir tatil bulalım. Kısaca anlatır mısın: yurt içi mi yurt dışı mı düşünüyorsun, bütçen ne kadar ve ne zaman gitmek istiyorsun?';
        }

        // 1 eksen biliniyor, en kritik 1-2 eksen sor
        $askParts = [];

        if (in_array('destination', $missing, true)) {
            $askParts[] = 'yurt içi mi yurt dışı mı düşünüyorsun (veya aklında belirli bir yer var mı)';
        }
        if (in_array('budget', $missing, true)) {
            $askParts[] = 'bütçen ne aralıkta';
        }
        if (in_array('time', $missing, true)) {
            $askParts[] = 'ne zaman / kaç günlük bir tatil istiyorsun';
        }
        if (empty($askParts) && in_array('vibe', $missing, true)) {
            $askParts[] = 'nasıl bir tatil — plaj, doğa, kültür mü, yoksa şehir gezisi mi';
        }

        // En fazla 2 soruyu birleştir, daha doğal cümle olsun
        $askParts = array_slice($askParts, 0, 2);

        if (count($askParts) === 1) {
            return 'Kısa bir bilgi: ' . $askParts[0] . '?';
        }

        return 'İki noktayı netleştirelim: ' . implode(' ve ', $askParts) . '?';
    }

    public function recentForUser(Request $request, int $limit = 10): \Illuminate\Support\Collection
    {
        $userId = $request->user()?->id;

        $query = AiSearchConversation::query()
            ->whereNotNull('title')
            ->orderByDesc('last_message_at')
            ->limit($limit);

        if ($userId) {
            $query->where('user_id', $userId);
        } else {
            $query->where('session_id', Str::limit($request->session()->getId(), 64, ''));
        }

        return $query->get();
    }
}
