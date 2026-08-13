<?php

namespace App\Services\AiSearch;

use App\Http\Controllers\AiSearchController;
use App\Models\AiLead;
use App\Models\AiSearchConversation;
use App\Models\AiSearchMessage;
use App\Models\Favorite;
use App\Models\Tour;
use App\Models\User;
use App\Notifications\NewAiLeadNotification;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

class ConversationService
{
    /** Bir konuşmada en fazla bu kadar netleştirme sorusu sorulur, sonra zorla aramaya geçilir. */
    public const MAX_CLARIFICATIONS = 2;

    /** Aynı conversation'a paralel mesaj çakışmasını önlemek için lock.
     *  TTL, en yavaş yol (intent + embedding + streaming yorum) bitmeden kilit
     *  düşmesin diye beklenen en kötü sürenin üzerinde tutulur. */
    public const LOCK_TTL_SECONDS = 90;
    public const LOCK_BLOCK_SECONDS = 5;

    /**
     * NOTE: Şu an AiSearchController'ın public performAiSearch metodunu çağırıyoruz.
     * TODO: AiSearchController içindeki search logic'ini bağımsız bir TourSearchService'e
     * çıkardığında bu çağrıyı oraya yönlendir.
     */
    public function __construct(
        private readonly AiSearchController $searchController,
        private readonly DestinationKnowledgeService $destinations,
    ) {}

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
     * Tur başlangıcı hazırlığı: sıfırlama isteği, netleştirme turlarında biriken
     * bağlam ve "beğenmedim, başka öner" akışını tek yerde çözer.
     *
     * @return array{0: array<string, mixed>, 1: string, 2: array<int, int>}
     *              [previousIntent, searchQuery, excludeTourIds]
     */
    private function prepareTurn(AiSearchConversation $conversation, string $userMessage): array
    {
        $previousIntent = $conversation->current_intent ?? [];

        // "Baştan başlayalım / yeni arama" → tüm eski niyet ve sayaçlar temizlenir;
        // yapışkan filtrelerden kurtulmanın deterministik yolu.
        if ($this->wantsReset($userMessage)) {
            $previousIntent = [];
        }

        // Netleştirme turlarında verilen bilgiler (_pending_context) kaybolmasın:
        // "Kapadokya" + soruya cevaben "30 bin" → arama "Kapadokya 30 bin" ile yapılır.
        $pending = trim((string) ($previousIntent['_pending_context'] ?? ''));
        $searchQuery = $pending !== '' ? trim($pending.' '.$userMessage) : $userMessage;

        // Yazıyla "beğenmedim, başka öner": önceki sonuçlar dışlanır ve semantik
        // sorgu, anlamsız "beğenmedim" metni yerine önceki aramanın konusuyla kurulur.
        $excludeTourIds = [];
        if ($this->wantsDifferentResults($userMessage)) {
            $excludeTourIds = array_values(array_filter(array_map('intval', (array) ($conversation->last_result_tour_ids ?? []))));
            $previousQuery = trim((string) ($previousIntent['search_query'] ?? ''));
            if ($previousQuery !== '') {
                $searchQuery = trim($previousQuery.' '.$userMessage);
            }
        }

        return [$previousIntent, $searchQuery, $excludeTourIds];
    }

    private function wantsReset(string $userMessage): bool
    {
        $text = $this->normalizeTr($userMessage);

        foreach ([
            'bastan basla', 'bastan alalim', 'yeni arama', 'yeni bir arama',
            'sifirla', 'sifirdan basla', 'oncekileri unut', 'onceki aramayi unut',
            'hepsini unut', 'unut gitsin', 'temiz sayfa',
        ] as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** Kullanıcı insana/acentaya bağlanmak istiyor mu? (LLM'siz kelime kontrolü) */
    private function wantsHumanHandoff(string $userMessage): bool
    {
        $text = $this->normalizeTr($userMessage);

        // NOT: "beni arayın/arasın" artık LEAD yakalama akışına gider (ad+telefon
        // alınıp acentaya kalıcı kayıt düşülür) — buradan bilinçli çıkarıldı.
        foreach ([
            'temsilci', 'yetkiliyle', 'yetkili biri', 'insanla konus', 'gercek biri',
            'acentayla konus', 'acenta ile konus', 'acentayi ara',
            'telefonla gorus', 'whatsapp', 'watsap', 'iletisime gec', 'pazarlik',
        ] as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Sıcak devir kartı: son gösterilen turun acentası + sohbet özetiyle önceden
     * doldurulmuş WhatsApp/telefon bağlantıları. Sonuç yoksa null (devir edilemez).
     *
     * @return array<string, mixed>|null
     */
    private function buildHandoffPayload(AiSearchConversation $conversation): ?array
    {
        $lastIds = array_values(array_filter(array_map('intval', (array) ($conversation->last_result_tour_ids ?? []))));
        if (empty($lastIds)) {
            return null;
        }

        // Görünürlük: bayat last_result_tour_ids içindeki lisansı bitmiş/pasif tur
        // takip sorusunda tam veri fişiyle (fiyat/program) LLM cevabına girmesin.
        $tour = Tour::active()->with('agency')->find($lastIds[0]);
        if (! $tour || ! $tour->agency) {
            return null;
        }

        $phoneDigits = preg_replace('/\D+/', '', (string) $tour->agency->phone);
        if ($phoneDigits === '') {
            return null;
        }
        if (str_starts_with($phoneDigits, '0')) {
            $phoneDigits = '9'.$phoneDigits;
        } elseif (str_starts_with($phoneDigits, '5')) {
            $phoneDigits = '90'.$phoneDigits;
        }

        $monthNames = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        $ci = (array) ($conversation->current_intent ?? []);
        $bits = array_filter([
            ! empty($ci['preferred_month']) ? ($monthNames[(int) $ci['preferred_month']] ?? null).' dönemi' : null,
            ! empty($ci['max_budget']) ? '~'.number_format((int) $ci['max_budget'], 0, ',', '.').' TL bütçe' : null,
            ! empty($ci['traveler_profile']) ? str_replace('_', ' ', (string) $ci['traveler_profile']) : null,
        ]);
        $prefill = 'Merhaba, turXtur\'da "'.$tour->title.'" turunu inceledim.'
            .(! empty($bits) ? ' '.implode(', ', $bits).'.' : '')
            .' Müsaitlik ve detayları öğrenebilir miyim?';

        return [
            'agency_name' => $tour->agency->name,
            'tour_id' => $tour->id,
            'tour_title' => $tour->title,
            'phone' => (string) $tour->agency->phone,
            'phone_link' => 'tel:+'.$phoneDigits,
            'whatsapp_link' => 'https://wa.me/'.$phoneDigits.'?text='.rawurlencode($prefill),
        ];
    }

    /**
     * İnsan devri akışı (streaming): sohbet özeti hazır WhatsApp/telefon kartı
     * basılır, devir conversation'a işlenir (acentaya "AI'dan sıcak lead").
     *
     * @return array{type: string, user: AiSearchMessage, assistant: AiSearchMessage}
     */
    private function respondHandoffStreamed(AiSearchConversation $conversation, string $userMessage, \Closure $emit, array $handoff, ?string $content = null): array
    {
        $content ??= 'Tabii — seni '.$handoff['agency_name'].' ile buluşturayım. "'.$handoff['tour_title'].'" turu için konuşmamızın özetini hazırladım; aşağıdaki bağlantıyla tek dokunuşta iletebilirsin. Telefon: '.$handoff['phone'];

        [$userMsg, $assistantMsg] = DB::transaction(function () use ($conversation, $userMessage, $content, $handoff) {
            $u = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_USER,
                'content' => $userMessage,
            ]);
            $a = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_ASSISTANT,
                'content' => $content,
            ]);
            $conversation->forceFill([
                'handoff_at' => now(),
                'handoff_tour_id' => $handoff['tour_id'],
            ])->save();
            $conversation->update(['last_message_at' => now()]);

            return [$u, $a];
        });

        $emit('search', [
            'conversation_uuid' => $conversation->uuid,
            'user_message' => [
                'id' => $userMsg->id, 'role' => $userMsg->role,
                'content' => $userMsg->content,
                'created_at' => $userMsg->created_at?->toIso8601String(),
            ],
        ]);
        $emit('comment', ['delta' => $content]);
        $emit('handoff', $handoff);
        $emit('done', ['is_clarification' => false, 'assistant_message_id' => $assistantMsg->id]);

        return ['type' => 'handoff', 'user' => $userMsg, 'assistant' => $assistantMsg];
    }

    /** Şikayet / iade / ödeme sorunu — satış modu kapanır, insana yönlendirilir. */
    private function detectComplaint(string $userMessage): bool
    {
        $text = $this->normalizeTr($userMessage);

        foreach ([
            'sikayet', 'magdur', 'rezalet', 'berbat bir', 'dolandir',
            'iade istiyorum', 'param iade', 'ucret iadesi', 'paramizi',
            'iptal etmek istiyorum', 'rezervasyonumu iptal', 'kaydimi iptal',
            'odeme sorunu', 'odeme yapamiyorum', 'odemem gecti',
        ] as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** Geri arama / opsiyon talebi — ad+telefon toplanıp acentaya lead düşülür. */
    private function detectLeadIntent(string $userMessage): ?string
    {
        $text = $this->normalizeTr($userMessage);

        foreach (['opsiyon koy', 'opsiyon yap', 'on kayit', 'yer ayir', 'yer tut', 'rezerve et'] as $pattern) {
            if (str_contains($text, $pattern)) {
                return AiLead::INTENT_OPTION;
            }
        }
        foreach (['beni arayin', 'beni arasin', 'beni arar', 'geri arama', 'geri arayin', 'geri donun', 'geri donus yapin', 'numarami birakayim', 'telefon birakayim'] as $pattern) {
            if (str_contains($text, $pattern)) {
                return AiLead::INTENT_CALLBACK;
            }
        }

        return null;
    }

    /** Fiyat düşünce / yer açılınca haber isteği. */
    private function wantsPriceAlert(string $userMessage): bool
    {
        $text = $this->normalizeTr($userMessage);

        foreach ([
            'fiyat dusunce', 'fiyati dusunce', 'fiyat duserse', 'fiyati duserse',
            'ucuzlayinca', 'ucuzlarsa', 'indirime girince', 'indirime girerse',
            'yer acilinca', 'yer acilirsa', 'kontenjan acilinca',
        ] as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** Bekleyen lead sorusundan vazgeçme kalıpları. */
    private function declinesLeadContact(string $userMessage): bool
    {
        $text = $this->normalizeTr($userMessage);

        foreach (['istemiyorum', 'vazgectim', 'gerek yok', 'gerek kalmadi', 'sonra veririm', 'simdi degil', 'birakalim', 'istemem'] as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /** Mesajdan TR cep telefonu çıkarır (0/+90 önekli 5xx). Bulamazsa null. */
    /**
     * Mesajda arama kriteri/öneri isteği sinyali var mı? Varsa Phase-0d'nin
     * destinasyon kestirmeleri DEVRE DIŞI kalır — "Eylülde 30 bin bütçeyle
     * nereye tur önerirsin?" envanter dökümü değil, kriterli ARAMADIR (intent'e
     * ay+bütçe işlenmeli). Belirsizlikte arama güvenli varsayılandır.
     */
    private function hasSearchCriteriaSignal(string $normalized): bool
    {
        if (preg_match('/\d/', $normalized) === 1) {
            return true; // bütçe / gün sayısı / tarih
        }

        foreach ([
            'oner', 'tavsiye', 'butce', 'balayi', 'aile', 'cocuk', 'arkadas', 'romantik',
            'ocak', 'subat', 'mart', 'nisan', 'mayis', 'haziran',
            'temmuz', 'agustos', 'eylul', 'ekim', 'kasim', 'aralik',
        ] as $signal) {
            if (str_contains($normalized, $signal)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "Nerelere turunuz var?" tarzı envanter sorusu — deterministik listeyle
     * cevaplanır (LLM'siz, her zaman doğru ve eksiksiz). Kriterli öneri
     * istekleri ("balayı için nereye gidebiliriz") bilerek DIŞARIDA: onlar
     * arama pipeline'ında küratörlü sonuç almalı.
     */
    private function wantsDestinationList(string $userMessage): bool
    {
        $normalized = $this->normalizeTr($userMessage);

        if ($this->hasSearchCriteriaSignal($normalized)) {
            return false;
        }

        foreach ([
            'nerelere tur', 'nereye tur',
            'hangi sehirlere', 'hangi ulkelere', 'hangi bolgelere',
            'hangi destinasyon', 'destinasyonlariniz', 'destinasyonlar neler',
            'nereler var', 'nerelere gidiyorsunuz',
        ] as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * "İstanbul nasıl bir şehir? / kalabalık mı? / vize gerekiyor mu?" tarzı,
     * turdan bağımsız destinasyon sorusu: karakter-soru kalıbı + mesajda
     * bilinen bir şehir. Profil verisinden LLM'siz cevaplanır.
     *
     * @return array{city: string, normalized: string, count: int}|null
     */
    private function detectDestinationQuestion(string $userMessage): ?array
    {
        $normalized = $this->normalizeTr($userMessage);

        // "tur" kelimesi geçen mesaj TUR hakkındadır, şehir hakkında değil:
        // "İstanbul turu hakkında bilgi", "Kapadokya turunu anlatır mısın",
        // "İstanbul'da gece hayatı olan tur öner" → router/arama işlesin.
        // Kriter sinyali (bütçe/ay/öner) de aramayı işaret eder.
        if (preg_match('/\btur\p{L}*\b/u', $normalized) === 1
            || str_contains($normalized, 'paket')
            || $this->hasSearchCriteriaSignal($normalized)) {
            return null;
        }

        $hasQuestionPattern = false;
        foreach ([
            'nasil bir yer', 'nasil bir sehir', 'nasil bi yer', 'nasil bi sehir', 'nasildir',
            'kalabalik mi', 'kalabalik midir', 'sakin mi', 'sakin midir',
            'gece hayati', 'eglencesi nasil', 'eglence var mi', 'eglence nasil',
            'ne zaman gidilir', 'hangi ayda gid', 'en iyi ay',
            'vize gerekiyor mu', 'vize gerekli mi', 'vize var mi', 'vize lazim mi',
            'vizesiz mi', 'vize istiyor mu', 'vize isteniyor mu',
            'hakkinda bilgi', 'anlatir misin', 'anlatsana', 'biraz anlat',
        ] as $pattern) {
            if (str_contains($normalized, $pattern)) {
                $hasQuestionPattern = true;
                break;
            }
        }

        if (! $hasQuestionPattern) {
            return null;
        }

        return $this->destinations->findCityInMessage($userMessage);
    }

    private function extractPhone(string $userMessage): ?string
    {
        if (! preg_match('/(?:\+?9\s*0[\s\-.]?)?0?[\s\-.]?(5\d{2})[\s\-.]?(\d{3})[\s\-.]?(\d{2})[\s\-.]?(\d{2})(?!\d)/', $userMessage, $m)) {
            return null;
        }

        return '0'.$m[1].$m[2].$m[3].$m[4];
    }

    /**
     * Basit tek-mesajlık tur: kullanıcı+asistan mesajı kaydedilir, intent
     * yamalanır, (varsa) SSE olayları basılır. Lead/güvenlik akışlarının
     * LLM'siz cevapları bu ortak yoldan döner.
     *
     * @param  array<string, mixed>  $intentPatch  null değerli anahtar intent'ten SİLİNİR
     * @return array{type: string, user: AiSearchMessage, assistant: AiSearchMessage}
     */
    private function respondPlainTurn(AiSearchConversation $conversation, string $userMessage, ?\Closure $emit, string $content, array $intentPatch = [], array $suggestions = []): array
    {
        [$userMsg, $assistantMsg] = DB::transaction(function () use ($conversation, $userMessage, $content, $intentPatch) {
            $u = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_USER,
                'content' => $userMessage,
            ]);
            $a = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_ASSISTANT,
                'content' => $content,
            ]);

            $intent = (array) ($conversation->current_intent ?? []);
            foreach ($intentPatch as $key => $value) {
                if ($value === null) {
                    unset($intent[$key]);
                } else {
                    $intent[$key] = $value;
                }
            }
            $conversation->update([
                'current_intent' => $intent ?: null,
                'last_message_at' => now(),
                'title' => $conversation->title ?: Str::limit($userMessage, 60),
            ]);

            return [$u, $a];
        });

        if ($emit !== null) {
            $emit('search', [
                'conversation_uuid' => $conversation->uuid,
                'user_message' => [
                    'id' => $userMsg->id, 'role' => $userMsg->role,
                    'content' => $userMsg->content,
                    'created_at' => $userMsg->created_at?->toIso8601String(),
                ],
            ]);
            $emit('comment', ['delta' => $content, 'final' => $content]);
            if ($suggestions !== []) {
                $emit('suggestions', ['items' => $suggestions]);
            }
            $emit('done', ['is_clarification' => false, 'assistant_message_id' => $assistantMsg->id]);
        }

        return ['type' => 'plain', 'user' => $userMsg, 'assistant' => $assistantMsg, 'suggestions' => $suggestions];
    }

    /** Plain turu senkron (JSON) cevap zarfına çevirir. */
    private function wrapPlainPayload(array $turn): array
    {
        $payload = [
            'aiComment' => $turn['assistant']->content,
            'results' => [],
            'is_answer' => true,
        ];
        if (! empty($turn['suggestions'])) {
            $payload['suggestions'] = $turn['suggestions'];
        }

        return [
            'user' => $turn['user'],
            'assistant' => $turn['assistant'],
            'payload' => $payload,
        ];
    }

    /**
     * Lead için ad+telefon isteme turu: _awaiting_lead bayrağı kurulur, amaç
     * açıkça söylenir (KVKK: veri yalnız belirtilen amaç için istenir).
     */
    private function askLeadContact(AiSearchConversation $conversation, string $userMessage, ?\Closure $emit, string $intent, ?string $note = null, bool $complaint = false): array
    {
        $lastIds = array_values(array_filter(array_map('intval', (array) ($conversation->last_result_tour_ids ?? []))));
        $tourId = $lastIds[0] ?? null;

        $question = match (true) {
            $complaint => 'Bunu yaşadığın için üzgünüm. Konuyu hemen yetkili arkadaşımıza iletiyorum — sana dönüş yapabilmemiz için ad-soyad ve telefon numaranı yazar mısın? (Örn: Ayşe Yılmaz 0532 123 45 67)',
            $intent === AiLead::INTENT_OPTION => 'Opsiyon talebini iletmem için ad-soyad ve telefon numaranı alabilir miyim? Temsilcimiz onay için seni arayacak. (Örn: Ayşe Yılmaz 0532 123 45 67)',
            $intent === AiLead::INTENT_PRICE_ALERT => 'Fiyat değişince haber verebilmemiz için ad-soyad ve telefon numaranı yazar mısın? (Örn: Ayşe Yılmaz 0532 123 45 67) 🔔',
            default => 'Tabii! Temsilcimizin seni arayabilmesi için ad-soyad ve telefon numaranı yazar mısın? (Örn: Ayşe Yılmaz 0532 123 45 67) 📞',
        };

        return $this->respondPlainTurn($conversation, $userMessage, $emit, $question, [
            '_awaiting_lead' => [
                'intent' => $intent,
                'tour_id' => $tourId,
                'note' => $note,
                'reminded' => false,
            ],
        ]);
    }

    /**
     * _awaiting_lead bekleyen cevabı işler. Lead oluşursa/vazgeçilirse/tekrar
     * sorulursa plain tur döner; null dönerse bayrak temizlenmiştir ve mesaj
     * normal akışta (router/arama) işlenmeye devam eder.
     */
    private function handleAwaitingLead(Request $request, AiSearchConversation $conversation, string $userMessage, ?\Closure $emit): ?array
    {
        $awaiting = (array) (($conversation->current_intent ?? [])['_awaiting_lead'] ?? []);
        if (empty($awaiting['intent'])) {
            return null;
        }

        if ($this->declinesLeadContact($userMessage)) {
            return $this->respondPlainTurn($conversation, $userMessage, $emit,
                'Sorun değil, istediğin an tekrar yazman yeterli 😊 Tatil planına devam edelim mi?',
                ['_awaiting_lead' => null]);
        }

        $phone = $this->extractPhone($userMessage);
        if ($phone === null) {
            if (empty($awaiting['reminded'])) {
                $awaiting['reminded'] = true;

                return $this->respondPlainTurn($conversation, $userMessage, $emit,
                    'Telefon numaranı yakalayamadım — "Ad Soyad 05xx xxx xx xx" şeklinde yazabilir misin? İstemezsen "gerek yok" demen yeterli.',
                    ['_awaiting_lead' => $awaiting]);
            }

            // İkinci denemede de yok: ısrar etme, bayrağı bırak ve mesajı normal işle
            $intent = (array) ($conversation->current_intent ?? []);
            unset($intent['_awaiting_lead']);
            $conversation->update(['current_intent' => $intent ?: null]);
            $conversation->refresh();

            return null;
        }

        // Ad: telefon ve ayraçlar çıkarıldıktan sonra kalan metin
        $name = trim((string) preg_replace('/[\d\+\-\.\(\)]+/', ' ', $userMessage));
        $name = trim((string) preg_replace('/\s{2,}/', ' ', $name));
        if (mb_strlen($name) < 3) {
            $name = $request->user()?->name ?? 'Ad belirtilmedi';
        }
        $name = Str::limit($name, 120, '');

        $tour = ! empty($awaiting['tour_id']) ? Tour::with('agency')->find((int) $awaiting['tour_id']) : null;

        $ci = (array) ($conversation->current_intent ?? []);
        $monthNames = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        $noteBits = array_filter([
            $awaiting['note'] ?? null,
            $tour?->title ? 'Tur: '.$tour->title : null,
            ! empty($ci['preferred_month']) ? 'Ay: '.($monthNames[(int) $ci['preferred_month']] ?? '') : null,
            ! empty($ci['max_budget']) ? 'Bütçe: ~'.number_format((int) $ci['max_budget'], 0, ',', '.').' TL' : null,
            ! empty($ci['traveler_profile']) ? 'Profil: '.str_replace('_', ' ', (string) $ci['traveler_profile']) : null,
        ]);

        $lead = AiLead::create([
            'conversation_id' => $conversation->id,
            'tour_id' => $tour?->id,
            'agency_id' => $tour?->agency_id,
            'name' => $name,
            'phone' => $phone,
            'intent' => (string) $awaiting['intent'],
            'note' => $noteBits !== [] ? Str::limit(implode(' · ', $noteBits), 1000, '') : null,
        ]);

        if ($tour?->agency_id) {
            User::where('agency_id', $tour->agency_id)->get()
                ->each(fn (User $u) => $u->notify(new NewAiLeadNotification($lead)));
        }

        // Fiyat alarmı + girişli kullanıcı: favoriye de ekle ki mevcut fiyat-düşüş
        // bildirimi otomatik devreye girsin.
        if ($awaiting['intent'] === AiLead::INTENT_PRICE_ALERT && $request->user() && $tour) {
            Favorite::firstOrCreate(['user_id' => $request->user()->id, 'tour_id' => $tour->id]);
        }

        $confirm = match ($awaiting['intent']) {
            AiLead::INTENT_OPTION => 'Aldım! '.($tour?->title ? '"'.$tour->title.'" için opsiyon talebini' : 'Opsiyon talebini').' acentaya ilettim — temsilci en kısa sürede seni arayacak. Bu arada başka bir şey bakalım mı?',
            AiLead::INTENT_PRICE_ALERT => 'Tamamdır! '.($tour?->title ? '"'.$tour->title.'" turunda' : 'Bu turda').' fiyat değişirse sana haber vereceğiz 🔔 Başka bir konuda yardımcı olayım mı?',
            default => 'Notunu aldım, teşekkürler! Temsilcimiz en kısa sürede '.$phone.' numarasından sana dönecek. Beklerken başka bir şeye bakalım mı?',
        };

        return $this->respondPlainTurn($conversation, $userMessage, $emit, $confirm, ['_awaiting_lead' => null]);
    }

    /**
     * Fiyat alarmı başlangıcı: girişli + tur bilinen kullanıcıda tek adımda
     * favori+onay; aksi halde telefon istenir.
     */
    private function startPriceAlert(Request $request, AiSearchConversation $conversation, string $userMessage, ?\Closure $emit): array
    {
        $lastIds = array_values(array_filter(array_map('intval', (array) ($conversation->last_result_tour_ids ?? []))));
        $tour = ! empty($lastIds) ? Tour::active()->find($lastIds[0]) : null;

        if ($tour === null) {
            return $this->respondPlainTurn($conversation, $userMessage, $emit,
                'Fiyat alarmı için önce bir tur seçelim 😊 Nasıl bir tatil arıyorsun — birlikte bulalım, sonra alarmı kurarım.');
        }

        if ($request->user()) {
            Favorite::firstOrCreate(['user_id' => $request->user()->id, 'tour_id' => $tour->id]);

            return $this->respondPlainTurn($conversation, $userMessage, $emit,
                'Tamamdır! "'.$tour->title.'" turunu takibe aldım — fiyatı düşerse bildirim göndereceğim 🔔 Başka bir şey bakalım mı?');
        }

        return $this->askLeadContact($conversation, $userMessage, $emit, AiLead::INTENT_PRICE_ALERT);
    }

    private function wantsDifferentResults(string $userMessage): bool
    {
        $text = $this->normalizeTr($userMessage);

        foreach ([
            'begenmedim', 'begenmedik', 'baska oner', 'baska tur', 'baska secenek',
            'baska neler var', 'baska var mi', 'baskalarini goster', 'farkli oner',
            'farkli tur', 'farkli secenek', 'farkli bir sey', 'daha farkli',
            'bunlar olmadi', 'olmadi bunlar', 'bunlari begenmedim', 'degisik bir sey',
        ] as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function normalizeTr(string $text): string
    {
        return strtr(mb_strtolower(trim($text), 'UTF-8'), [
            'ı' => 'i', 'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ö' => 'o', 'ç' => 'c', 'i̇' => 'i',
        ]);
    }

    /**
     * Mesaj yönlendirici: her mesajı arama sanma. Önceki turda sonuç
     * gösterildiyse mini modelle mesajın türü belirlenir — "bu turun oteli
     * nasıl?" sorusu yeni arama + 7 alakasız kart yerine turun kendi verisinden
     * cevaplanır. Belirsizlik/hata → 'search' (mevcut davranış, güvenli varsayılan).
     *
     * @return array{action: string, tour_ids: array<int, int>}
     */
    private function routeMessage(AiSearchConversation $conversation, string $userMessage): array
    {
        $lastIds = array_values(array_filter(array_map('intval', (array) ($conversation->last_result_tour_ids ?? []))));

        // İlk mesaj / henüz sonuç gösterilmemiş → router'a gerek yok (maliyet 0)
        if (empty($lastIds)) {
            return ['action' => 'search', 'tour_ids' => []];
        }

        // Bariz arama/yenileme kalıplarında LLM'e gitme
        if ($this->wantsDifferentResults($userMessage) || $this->wantsReset($userMessage)) {
            return ['action' => 'search', 'tour_ids' => []];
        }

        $shownIds = array_slice($lastIds, 0, AiSearchController::CHAT_RESULT_LIMIT);
        $titles = Tour::whereIn('id', $shownIds)->pluck('title', 'id');
        $numbered = collect($shownIds)
            ->map(fn ($id, $i) => ($i + 1).'. '.($titles[$id] ?? 'Tur'))
            ->implode("\n");

        $recentMessages = AiSearchMessage::where('conversation_id', $conversation->id)
            ->orderByDesc('id')->limit(3)->get()->reverse()
            ->map(fn ($m) => ($m->role === AiSearchMessage::ROLE_USER ? 'Kullanıcı' : 'Asistan').': '.Str::limit((string) $m->content, 160))
            ->implode("\n");

        $system = 'Bir tur arama sohbetinde kullanıcının SON mesajının türünü belirle. SADECE şu JSON\'u dön: {"action": "...", "tour_numbers": [sayılar]}'
            ."\naction değerleri:"
            ."\n- search: yeni tur arama isteği veya kriter değişikliği ('daha ucuz olsun', 'eylülde olsun', yeni bir yer)"
            ."\n- tour_question: GÖSTERİLEN turlardan biri hakkında soru ('otel nasıl', 'programın 3. günü ne', '2. turda neler dahil')"
            ."\n- compare: gösterilen iki+ turu karşılaştırma isteği ('hangisi daha iyi', 'ilkiyle üçüncüyü kıyasla')"
            ."\n- site_question: site/işleyiş sorusu (iptal politikası, ödeme, rezervasyon süreci)"
            ."\n- destination_question: belirli bir şehir/ülke HAKKINDA genel soru — tur değil yer sorulur ('İstanbul nasıl bir şehir', 'Roma kalabalık mı', 'Dubai'de gece hayatı var mı')"
            ."\n- chitchat: selamlaşma/teşekkür/geyik"
            ."\ntour_numbers: bahsedilen turların listedeki numaraları ('ilki'→[1], 'son ikisi'→son iki numara, tur adı geçiyorsa adıyla eşleştir); yoksa []."
            ."\nEmin değilsen action=search."
            ."\n\nGÖSTERİLEN TURLAR:\n".$numbered
            ."\n\nSON MESAJLAR:\n".$recentMessages;

        try {
            $response = OpenAI::chat()->create([
                'model' => config('ai.router_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $this->searchController->wrapUserInputSafely($userMessage, 500)],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 80,
            ]);
            $data = json_decode($response->choices[0]->message->content ?? '{}', true) ?: [];
        } catch (\Throwable $e) {
            Log::info('[AiRouter] hata, search varsayılanına düşüldü', ['message' => $e->getMessage()]);

            return ['action' => 'search', 'tour_ids' => []];
        }

        $action = in_array($data['action'] ?? '', ['search', 'tour_question', 'compare', 'site_question', 'destination_question', 'chitchat'], true)
            ? $data['action']
            : 'search';

        $tourIds = collect((array) ($data['tour_numbers'] ?? []))
            ->map(fn ($n) => $shownIds[(int) $n - 1] ?? null)
            ->filter()
            ->map(fn ($v) => (int) $v)
            ->unique()
            ->values()
            ->all();

        // Tur gerektiren aksiyonda referans çözülemediyse güvenli varsayılanlar
        if ($action === 'tour_question' && empty($tourIds)) {
            $tourIds = [$shownIds[0]];
        }
        if ($action === 'compare') {
            if (count($tourIds) < 2) {
                $tourIds = array_slice($shownIds, 0, 2);
            }
            $tourIds = array_slice($tourIds, 0, 3);
        }

        return ['action' => $action, 'tour_ids' => $tourIds];
    }

    /**
     * respondNonSearchStreamed'in senkron (JSON) karşılığı — sendMessage yolu.
     *
     * @return array{user: AiSearchMessage, assistant: AiSearchMessage, payload: array<string, mixed>}
     */
    private function respondNonSearch(AiSearchConversation $conversation, string $userMessage, array $route): array
    {
        $tours = empty($route['tour_ids'])
            ? collect()
            : Tour::active()->with(['agency', 'dates'])->whereIn('id', $route['tour_ids'])->get();

        $comparison = ($route['action'] === 'compare' && $tours->count() >= 2)
            ? $this->searchController->buildComparisonTable($tours)
            : null;

        $fullContent = $this->searchController->streamContextualAnswer(
            $route['action'],
            $tours,
            $userMessage,
            $conversation->current_intent ?? [],
            function () {} // senkron: delta biriktirilir, akıtılmaz
        );
        if (trim($fullContent) === '') {
            $fullContent = 'Sorunu tam yakalayamadım — bir daha yazar mısın?';
        }

        [$userMsg, $assistantMsg] = DB::transaction(function () use ($conversation, $userMessage, $fullContent) {
            $u = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_USER,
                'content' => $userMessage,
            ]);
            $a = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_ASSISTANT,
                'content' => $fullContent,
            ]);
            $conversation->update([
                'last_message_at' => now(),
                'title' => $conversation->title ?: Str::limit($userMessage, 60),
            ]);

            return [$u, $a];
        });

        return [
            'user' => $userMsg,
            'assistant' => $assistantMsg,
            'payload' => [
                'aiComment' => $fullContent,
                'results' => [],
                'comparison' => $comparison,
                'is_answer' => true, // arama değil, bağlamsal cevap
            ],
        ];
    }

    /**
     * Arama-dışı mesaj akışı (tur sorusu / kıyas / site sorusu / sohbet):
     * arama pipeline'ı hiç çalışmaz; cevap tur fişlerinden/bilgi bankasından
     * topraklanmış şekilde akıtılır. Kıyasta deterministik tablo da basılır.
     *
     * @return array{type: string, user: AiSearchMessage, assistant: AiSearchMessage}
     */
    private function respondNonSearchStreamed(AiSearchConversation $conversation, string $userMessage, \Closure $emit, array $route): array
    {
        [$userMsg, $assistantMsg] = DB::transaction(function () use ($conversation, $userMessage) {
            $u = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_USER,
                'content' => $userMessage,
            ]);
            $a = AiSearchMessage::create([
                'conversation_id' => $conversation->id,
                'role' => AiSearchMessage::ROLE_ASSISTANT,
                'content' => '',
            ]);
            $conversation->update([
                'last_message_at' => now(),
                'title' => $conversation->title ?: Str::limit($userMessage, 60),
            ]);

            return [$u, $a];
        });

        $emit('search', [
            'conversation_uuid' => $conversation->uuid,
            'user_message' => [
                'id' => $userMsg->id,
                'role' => $userMsg->role,
                'content' => $userMsg->content,
                'created_at' => $userMsg->created_at?->toIso8601String(),
            ],
        ]);

        $tours = empty($route['tour_ids'])
            ? collect()
            : Tour::active()->with(['agency', 'dates'])->whereIn('id', $route['tour_ids'])->get();

        if ($route['action'] === 'compare' && $tours->count() >= 2) {
            $emit('compare', $this->searchController->buildComparisonTable($tours));
        }

        $fullContent = $this->searchController->streamContextualAnswer(
            $route['action'],
            $tours,
            $userMessage,
            $conversation->current_intent ?? [],
            fn (string $delta) => $emit('comment', ['delta' => $delta])
        );

        if (trim($fullContent) === '') {
            $fullContent = 'Sorunu tam yakalayamadım — bir daha yazar mısın?';
        }
        $assistantMsg->update(['content' => $fullContent]);

        // Devam önerileri: yeni yetenekleri kullanıcıya keşfettir
        $suggestions = match ($route['action']) {
            'tour_question' => ['Bu turu diğerleriyle kıyasla', 'Benzer başka turlar öner'],
            'compare' => ['Başka iki turu kıyasla', 'Farklı seçenekler göster'],
            default => [],
        };
        if (! empty($suggestions)) {
            $emit('suggestions', ['items' => $suggestions]);
        }

        $emit('done', [
            'is_clarification' => false,
            'assistant_message_id' => $assistantMsg->id,
        ]);

        return ['type' => $route['action'], 'user' => $userMsg, 'assistant' => $assistantMsg];
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

            // Phase 0-PII: kart/TC maskesi — hassas numara HAM olarak ne DB'ye
            // ne OpenAI'ye gider. Yakalanırsa LLM'siz güvenlik cevabı döner.
            $pii = PiiMasker::mask($userMessage);
            if ($pii['types'] !== []) {
                return $this->wrapPlainPayload($this->respondPlainTurn($conversation, $pii['text'], null,
                    'Güvenliğin için kart veya kimlik numarası gibi bilgileri sohbette işleyemiyorum — mesajındaki numarayı maskeledim. Ödeme yalnızca güvenli rezervasyon sayfasında yapılır. Tatil planına dönelim mi?'));
            }

            // Phase 0-lead-bekleyen: önceki turda ad+telefon istendiyse cevabı işle
            $leadTurn = $this->handleAwaitingLead($request, $conversation, $userMessage, null);
            if ($leadTurn !== null) {
                return $this->wrapPlainPayload($leadTurn);
            }

            // Phase 0-şikayet: satış modu kapanır — devir kartı (emojisiz) ya da
            // geri-arama kaydı ile insana yönlendirilir
            if ($this->detectComplaint($userMessage)) {
                $handoff = $this->buildHandoffPayload($conversation);
                if ($handoff !== null) {
                    $turn = $this->respondHandoffStreamed($conversation, $userMessage, function () {}, $handoff,
                        'Bunu yaşadığın için üzgünüm. Seni hemen '.$handoff['agency_name'].' ile buluşturuyorum; konuşmamızın özetini de ilettim. Telefon: '.$handoff['phone']);

                    return [
                        'user' => $turn['user'],
                        'assistant' => $turn['assistant'],
                        'payload' => ['aiComment' => $turn['assistant']->content, 'results' => [], 'handoff' => $handoff],
                    ];
                }
                $turn = $this->askLeadContact($conversation, $userMessage, null, AiLead::INTENT_CALLBACK, 'Şikayet/İade talebi', complaint: true);

                return $this->wrapPlainPayload($turn);
            }

            // İnsan devri (streaming yoldakiyle aynı) — açıkça acenta/temsilci
            // isteyen mesajda devir, genel "beni arayın" lead'inden ÖNCE gelir
            if ($this->wantsHumanHandoff($userMessage)) {
                $handoff = $this->buildHandoffPayload($conversation);
                if ($handoff !== null) {
                    $turn = $this->respondHandoffStreamed($conversation, $userMessage, function () {}, $handoff);

                    return [
                        'user' => $turn['user'],
                        'assistant' => $turn['assistant'],
                        'payload' => [
                            'aiComment' => $turn['assistant']->content,
                            'results' => [],
                            'handoff' => $handoff,
                        ],
                    ];
                }
            }

            // Phase 0c: fiyat alarmı / geri arama-opsiyon lead'i (LLM'siz)
            if ($this->wantsPriceAlert($userMessage)) {
                return $this->wrapPlainPayload($this->startPriceAlert($request, $conversation, $userMessage, null));
            }
            if (($leadIntent = $this->detectLeadIntent($userMessage)) !== null) {
                return $this->wrapPlainPayload($this->askLeadContact($conversation, $userMessage, null, $leadIntent));
            }

            // Phase 0d: destinasyon bilgisi (streaming yoldakiyle aynı) —
            // envanter listesi / şehir karakteri LLM'siz cevaplanır
            if ($this->wantsDestinationList($userMessage)) {
                $answer = $this->destinations->answerInventoryQuestion();

                return $this->wrapPlainPayload($this->respondPlainTurn($conversation, $userMessage, null, $answer['text'], [], $answer['suggestions']));
            }
            if (($cityMatch = $this->detectDestinationQuestion($userMessage)) !== null) {
                $answer = $this->destinations->answerCityQuestion($cityMatch);

                return $this->wrapPlainPayload($this->respondPlainTurn($conversation, $userMessage, null, $answer['text'], [], $answer['suggestions']));
            }

            // Mesaj yönlendirici (streaming yoldakiyle aynı): arama-dışı mesajlar
            // arama pipeline'ına girmeden yanıtlanır
            $route = $this->routeMessage($conversation, $userMessage);
            if ($route['action'] === 'destination_question') {
                $cityMatch = $this->destinations->findCityInMessage($userMessage);
                if ($cityMatch !== null) {
                    $answer = $this->destinations->answerCityQuestion($cityMatch);

                    return $this->wrapPlainPayload($this->respondPlainTurn($conversation, $userMessage, null, $answer['text'], [], $answer['suggestions']));
                }
                $route = ['action' => 'search', 'tour_ids' => []];
            }
            if ($route['action'] !== 'search') {
                return $this->respondNonSearch($conversation, $userMessage, $route);
            }

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

            [$previousIntent, $searchQuery, $excludeTourIds] = $this->prepareTurn($conversation, $userMessage);
            $clarificationsAsked = (int) ($previousIntent['_clarifications'] ?? 0);

            // 1. Niyet eksikse, aramadan önce kullanıcıya bir netleştirme sorusu sor.
            // Sinyal kontrolü birleşik bağlamla yapılır — kullanıcının önceki turda
            // verdiği bilgi ("Kapadokya") tekrar sorulmaz.
            if ($clarificationsAsked < self::MAX_CLARIFICATIONS) {
                $question = $this->maybeAskClarification($searchQuery, $previousIntent);

                // Aynı soru tekrar oluştuysa kullanıcının cevabı yeni eksen
                // eklememiş demektir — tekrarlamak yerine eldekiyle ara
                if ($question !== null && $this->isSameAsLastAssistantQuestion($conversation, $question)) {
                    $question = null;
                }

                if ($question !== null) {
                    $assistantMsg = AiSearchMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => AiSearchMessage::ROLE_ASSISTANT,
                        'content' => $question,
                    ]);

                    $nextIntent = array_merge($previousIntent, [
                        '_clarifications' => $clarificationsAsked + 1,
                        // Soru turlarında verilen bilgi kaybolmasın — arama anında
                        // birleşik sorgu olarak kullanılacak
                        '_pending_context' => Str::limit($searchQuery, 500, ''),
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
            $searchResult = $this->searchController->performAiSearch($request, $searchQuery, $previousIntent, excludeTourIds: $excludeTourIds);

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
            // Başarılı arama: soru hakkı yenilenir (yeni konuya geçince tekrar
            // sorulabilsin) ve biriken soru-cevap bağlamı intent'e taşındığı için düşülür.
            unset($mergedIntent['_pending_context']);
            $mergedIntent['_clarifications'] = 0;

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
                    'relaxation_note' => $searchResult['relaxation_note'] ?? null,
                    'total_matches' => $searchResult['total_matches'] ?? null,
                    'all_results_url' => $searchResult['all_results_url'] ?? null,
                ],
            ];
        });
    }

    /**
     * Streaming versiyon — emit closure ile event akıtır.
     * Akış: 'search' → ('tours' → 'comment'+ → 'done') | ('comment' → 'done') | 'error'
     *
     * Atomic kural: DB::transaction içinde HİÇBİR emit yok. Yazımlar bittikten sonra
     * emit + streaming yapılır. Final content streaming sonrası ayrı UPDATE ile yazılır.
     *
     * @param  \Closure(string, mixed): void  $emit
     * @return array{type: string, user: AiSearchMessage, assistant: AiSearchMessage}
     * @throws LockTimeoutException Lock alınamadığında
     */
    public function respondStreamed(Request $request, AiSearchConversation $conversation, string $userMessage, \Closure $emit): array
    {
        $userMessage = trim($userMessage);
        if ($userMessage === '') {
            throw new \InvalidArgumentException('Boş mesaj gönderilemez.');
        }

        $lock = Cache::lock('ai_conv_lock:' . $conversation->id, self::LOCK_TTL_SECONDS);

        return $lock->block(self::LOCK_BLOCK_SECONDS, function () use ($request, $conversation, $userMessage, $emit) {
            $conversation->refresh();

            // Phase 0-PII: kart/TC maskesi — hassas numara HAM olarak ne DB'ye
            // ne OpenAI'ye gider. Yakalanırsa LLM'siz güvenlik cevabı döner.
            $pii = PiiMasker::mask($userMessage);
            if ($pii['types'] !== []) {
                return $this->respondPlainTurn($conversation, $pii['text'], $emit,
                    'Güvenliğin için kart veya kimlik numarası gibi bilgileri sohbette işleyemiyorum — mesajındaki numarayı maskeledim. Ödeme yalnızca güvenli rezervasyon sayfasında yapılır. Tatil planına dönelim mi?');
            }

            // Phase 0-lead-bekleyen: önceki turda ad+telefon istendiyse cevabı işle
            $leadTurn = $this->handleAwaitingLead($request, $conversation, $userMessage, $emit);
            if ($leadTurn !== null) {
                return $leadTurn;
            }

            // Phase 0-şikayet: satış modu kapanır — devir kartı (emojisiz) ya da
            // geri-arama kaydı ile insana yönlendirilir
            if ($this->detectComplaint($userMessage)) {
                $handoff = $this->buildHandoffPayload($conversation);
                if ($handoff !== null) {
                    $turn = $this->respondHandoffStreamed($conversation, $userMessage, $emit, $handoff,
                        'Bunu yaşadığın için üzgünüm. Seni hemen '.$handoff['agency_name'].' ile buluşturuyorum; konuşmamızın özetini de ilettim. Telefon: '.$handoff['phone']);

                    return $turn;
                }
                $turn = $this->askLeadContact($conversation, $userMessage, $emit, AiLead::INTENT_CALLBACK, 'Şikayet/İade talebi', complaint: true);

                return $turn;
            }

            // Phase 0a: insan devri — kullanıcı acenta/temsilci istiyorsa sohbet
            // özetiyle dolu WhatsApp/telefon köprüsü kur (LLM'siz)
            if ($this->wantsHumanHandoff($userMessage)) {
                $handoff = $this->buildHandoffPayload($conversation);
                if ($handoff !== null) {
                    return $this->respondHandoffStreamed($conversation, $userMessage, $emit, $handoff);
                }
            }

            // Phase 0c: fiyat alarmı / geri arama-opsiyon lead'i (LLM'siz)
            if ($this->wantsPriceAlert($userMessage)) {
                return $this->startPriceAlert($request, $conversation, $userMessage, $emit);
            }
            if (($leadIntent = $this->detectLeadIntent($userMessage)) !== null) {
                return $this->askLeadContact($conversation, $userMessage, $emit, $leadIntent);
            }

            // Phase 0d: destinasyon bilgisi — envanter ("nerelere turunuz var")
            // ve şehir karakteri ("İstanbul nasıl bir şehir") LLM'siz, gerçek
            // envanter + profil verisinden cevaplanır
            if ($this->wantsDestinationList($userMessage)) {
                $answer = $this->destinations->answerInventoryQuestion();

                return $this->respondPlainTurn($conversation, $userMessage, $emit, $answer['text'], [], $answer['suggestions']);
            }
            if (($cityMatch = $this->detectDestinationQuestion($userMessage)) !== null) {
                $answer = $this->destinations->answerCityQuestion($cityMatch);

                return $this->respondPlainTurn($conversation, $userMessage, $emit, $answer['text'], [], $answer['suggestions']);
            }

            // Phase 0b: mesaj yönlendirici — arama-dışı mesajlar (tur sorusu/kıyas/
            // site sorusu/sohbet) arama pipeline'ına girmeden ucuz yoldan yanıtlanır
            $route = $this->routeMessage($conversation, $userMessage);
            if ($route['action'] === 'destination_question') {
                // Router regex'in kaçırdığı ifadeyi yakaladı; şehir çözülürse
                // aynı deterministik cevap, çözülemezse güvenli varsayılan (arama)
                $cityMatch = $this->destinations->findCityInMessage($userMessage);
                if ($cityMatch !== null) {
                    $answer = $this->destinations->answerCityQuestion($cityMatch);

                    return $this->respondPlainTurn($conversation, $userMessage, $emit, $answer['text'], [], $answer['suggestions']);
                }
                $route = ['action' => 'search', 'tour_ids' => []];
            }
            if ($route['action'] !== 'search') {
                return $this->respondNonSearchStreamed($conversation, $userMessage, $emit, $route);
            }

            // Phase 1: DB transaction içinde atomic yazımlar + arama
            $turnState = DB::transaction(function () use ($request, $conversation, $userMessage) {
                $userMsg = AiSearchMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => AiSearchMessage::ROLE_USER,
                    'content' => $userMessage,
                ]);

                [$previousIntent, $searchQuery, $excludeTourIds] = $this->prepareTurn($conversation, $userMessage);
                $clarificationsAsked = (int) ($previousIntent['_clarifications'] ?? 0);

                // 1a. Clarification check (birleşik bağlamla — önceki turda verilen
                // bilgi tekrar sorulmaz)
                if ($clarificationsAsked < self::MAX_CLARIFICATIONS) {
                    $question = $this->maybeAskClarification($searchQuery, $previousIntent);

                    // Aynı soru tekrar oluştuysa tekrarlamak yerine eldekiyle ara
                    if ($question !== null && $this->isSameAsLastAssistantQuestion($conversation, $question)) {
                        $question = null;
                    }

                    if ($question !== null) {
                        $assistantMsg = AiSearchMessage::create([
                            'conversation_id' => $conversation->id,
                            'role' => AiSearchMessage::ROLE_ASSISTANT,
                            'content' => $question,
                        ]);

                        $conversation->update([
                            'current_intent' => array_merge($previousIntent, [
                                '_clarifications' => $clarificationsAsked + 1,
                                '_pending_context' => Str::limit($searchQuery, 500, ''),
                            ]),
                            'last_message_at' => now(),
                            'title' => $conversation->title ?: Str::limit($userMessage, 60),
                        ]);

                        return [
                            'type' => 'clarification',
                            'user' => $userMsg,
                            'assistant' => $assistantMsg,
                            'question' => $question,
                        ];
                    }
                }

                // 1b. Search (skipComment: streamComment dışarıda akıtacak)
                $searchResult = $this->searchController->performAiSearch(
                    $request,
                    $searchQuery,
                    $previousIntent,
                    skipComment: true,
                    excludeTourIds: $excludeTourIds
                );

                if (!is_array($searchResult) || isset($searchResult['error'])) {
                    $errorMessage = $searchResult['error'] ?? 'Arama başarısız.';
                    $assistantMsg = AiSearchMessage::create([
                        'conversation_id' => $conversation->id,
                        'role' => AiSearchMessage::ROLE_ASSISTANT,
                        'content' => 'Üzgünüm, arama sırasında bir sorun oldu: ' . $errorMessage,
                    ]);

                    return [
                        'type' => 'error',
                        'user' => $userMsg,
                        'assistant' => $assistantMsg,
                        'error' => $errorMessage,
                    ];
                }

                // Placeholder assistant message — content streaming sonrası UPDATE edilecek
                $resultIds = collect($searchResult['results'])->pluck('id')->all();
                $resultScores = collect($searchResult['results'])->map(fn($r) => [
                    'tour_id' => $r['id'],
                    'rank' => $r['rank'],
                    'compatibility_score' => $r['compatibility_score'] ?? null,
                ])->all();

                $assistantMsg = AiSearchMessage::create([
                    'conversation_id' => $conversation->id,
                    'role' => AiSearchMessage::ROLE_ASSISTANT,
                    'content' => '',
                    'intent_snapshot' => $searchResult['intent'] ?? null,
                    'result_tour_ids' => $resultIds,
                    'result_scores' => $resultScores,
                    'latency_ms' => $searchResult['latency_ms'] ?? null,
                ]);

                $mergedIntent = $searchResult['intent'] ?? $previousIntent;
                // Başarılı arama: soru hakkı yenilenir, biriken bağlam düşülür
                unset($mergedIntent['_pending_context']);
                $mergedIntent['_clarifications'] = 0;

                $conversation->update([
                    'current_intent' => $mergedIntent,
                    'last_result_tour_ids' => $resultIds,
                    'last_message_at' => now(),
                    'title' => $conversation->title ?: Str::limit($userMessage, 60),
                ]);

                return [
                    'type' => 'search',
                    'user' => $userMsg,
                    'assistant' => $assistantMsg,
                    'searchResult' => $searchResult,
                ];
            });

            // Phase 2: DB transaction TAMAM. Şimdi emit + streaming yapılabilir.
            $emit('search', [
                'conversation_uuid' => $conversation->uuid,
                'user_message' => [
                    'id' => $turnState['user']->id,
                    'role' => $turnState['user']->role,
                    'content' => $turnState['user']->content,
                    'created_at' => $turnState['user']->created_at?->toIso8601String(),
                ],
            ]);

            if ($turnState['type'] === 'clarification') {
                // Tek atışta soru — streaming çağrısı yok
                $emit('comment', ['delta' => $turnState['question'], 'final' => $turnState['question']]);
                $emit('done', [
                    'is_clarification' => true,
                    'assistant_message_id' => $turnState['assistant']->id,
                ]);

                return $turnState;
            }

            if ($turnState['type'] === 'error') {
                $emit('error', ['message' => $turnState['error']]);
                $emit('done', ['is_error' => true]);

                return $turnState;
            }

            // Search akışı: tur kartları + streaming yorum
            // log_id reject button'unun çalışması için tours event'ine ekleniyor
            $emit('tours', [
                'log_id' => $turnState['searchResult']['log_id'] ?? null,
                'relaxation_note' => $turnState['searchResult']['relaxation_note'] ?? null,
                'total_matches' => $turnState['searchResult']['total_matches'] ?? null,
                'all_results_url' => $turnState['searchResult']['all_results_url'] ?? null,
                'items' => $this->mapToursToCards($turnState['searchResult']['results']),
            ]);

            $ctx = $turnState['searchResult']['_comment_context'];
            $fullContent = $this->searchController->streamComment(
                $ctx['query'],
                $ctx['results'],
                $ctx['knowledge_context'],
                $ctx['preferred_destination'],
                function (string $delta) use ($emit) {
                    $emit('comment', ['delta' => $delta]);
                }
            );

            // Streaming tamamlandı — asistan mesajının final content'ini DB'ye yaz (atomik update).
            // Akış hiç içerik üretmeden koptuysa placeholder boş kalmasın.
            if (trim($fullContent) === '') {
                $fullContent = 'Sonuçlar yukarıda — detaylara kartlardan ulaşabilirsin.';
            }
            $turnState['assistant']->update(['content' => $fullContent]);

            // Devam önerisi çipleri: kıyas ve tur-içi soru yeteneklerini keşfettirir
            $shown = collect($turnState['searchResult']['results'] ?? []);
            $chips = [];
            if ($shown->count() >= 2) {
                $chips[] = 'İlk iki turu kıyasla';
            }
            if ($shown->count() >= 1) {
                $chips[] = '1. turun programını anlat';
            }
            if ($shown->contains(fn ($r) => (bool) (is_array($r) ? ($r['over_budget'] ?? false) : ($r->over_budget ?? false)))) {
                $chips[] = 'Sadece bütçeme uyanları göster';
            }
            if (! empty($chips)) {
                $emit('suggestions', ['items' => array_slice($chips, 0, 3)]);
            }

            $emit('done', [
                'is_clarification' => false,
                'assistant_message_id' => $turnState['assistant']->id,
                'log_id' => $turnState['searchResult']['log_id'] ?? null,
                'applied_filters' => $turnState['searchResult']['applied_filters'] ?? [],
            ]);

            return $turnState;
        });
    }

    /**
     * cleanResults formatındaki dizilerden frontend tur kartlarına dönüştürür.
     * (Eloquent collection'dan dönüşen 'results' alanı içinde Tour modelleri var.)
     */
    private function mapToursToCards(\Illuminate\Support\Collection $results): array
    {
        $tourIds = $results->pluck('id')->filter()->values()->all();
        $tours = empty($tourIds)
            ? collect()
            : \App\Models\Tour::with('agency')->whereIn('id', $tourIds)->get()->keyBy('id');

        return $results->map(function ($r) use ($tours) {
            $tour = $tours->get(is_object($r) ? $r->id : ($r['id'] ?? null));

            $get = fn(string $key) => is_object($r) ? ($r->{$key} ?? null) : ($r[$key] ?? null);

            return [
                'id' => $get('id'),
                'title' => $get('title'),
                'destination' => $get('destination'),
                'price' => $get('price'),
                'currency' => $get('currency'),
                'duration_days' => $get('duration_days'),
                'image' => $get('image'),
                // Slug varsa onunla: ID ile kurulan adres 301 ile slug'a yönleniyor.
                'url' => route('tours.show', $tour ?? $get('id')),
                'agency_name' => $tour?->agency?->name,
                'compatibility_score' => $get('compatibility_score'),
                'over_budget' => (bool) $get('over_budget'),
                'reason' => $get('reason'),
                'next_departure' => $get('next_departure'),
                'flex_date' => $get('flex_date'),
            ];
        })->values()->all();
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
        // "Farketmez / önemli değil / sen seç" → kullanıcı kısıt vermek istemiyor.
        // Soruyu tekrarlamak saçma diyaloglar üretir; eldekiyle arama yapılır.
        if ($this->userDismissesClarification($userMessage)) {
            return null;
        }

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

    /**
     * Kullanıcı netleştirme sorusunu geçiştiriyor mu? ("farketmez", "sen seç",
     * "hepsi olur"...) — bu cevaba yeni soru sormak diyaloğu kilitler.
     */
    private function userDismissesClarification(string $userMessage): bool
    {
        $text = $this->normalizeTr($userMessage);

        foreach ([
            'farketmez', 'fark etmez', 'farketmiyor', 'fark etmiyor', 'farketmes',
            'onemli degil', 'onemi yok', 'hepsi olur', 'her sey olur', 'herhangi',
            'sen sec', 'sen oner', 'sen karar', 'sana birakiyorum', 'size birakiyorum',
            'ne olursa', 'bilmiyorum sen', 'bilmem sen', 'olsun yeter', 'yeter ki',
        ] as $pattern) {
            if (str_contains($text, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Üretilen netleştirme sorusu, asistanın SON mesajıyla birebir aynıysa true —
     * kullanıcının cevabı yeni eksen eklememiş demektir; aynı soruyu tekrarlamak
     * yerine eldekiyle arama yapılmalı.
     */
    private function isSameAsLastAssistantQuestion(AiSearchConversation $conversation, string $question): bool
    {
        $last = AiSearchMessage::where('conversation_id', $conversation->id)
            ->where('role', AiSearchMessage::ROLE_ASSISTANT)
            ->orderByDesc('id')
            ->value('content');

        return $last !== null && trim($last) === trim($question);
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
