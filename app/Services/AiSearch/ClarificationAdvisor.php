<?php

namespace App\Services\AiSearch;

/**
 * Netleştirme sorusu danışmanı — AiSearchController::searchApi'nin netleştirme
 * akışının TEK SAHİBİ.
 *
 * Tamamen deterministik ve saf metin mantığı: LLM, DB ve oturum bağımlılığı
 * yoktur. 4 sinyal ekseni (budget, destination, time, vibe) mesaj + önceki
 * niyetin toplamından okunur; en az 2 eksen dolmamışsa eksik kalanlardan en
 * kritik olanı soran tek bir Türkçe soru üretilir.
 *
 * Soru sayacı, birleşik bağlam ve "aynı soruyu tekrarlama" kontrolü çağıranın
 * (searchApi → session) sorumluluğundadır; bu sınıf durum tutmaz.
 */
final class ClarificationAdvisor
{
    /** Bir arama oturumunda en fazla bu kadar netleştirme sorusu sorulur, sonra zorla aramaya geçilir. */
    public const MAX_CLARIFICATIONS = 2;

    /**
     * Niyet eksikse Türkçe bir netleştirme sorusu döndürür, yeterliyse null.
     *
     * Tamamen deterministik (LLM kullanmaz). 4 sinyal ekseni: budget, destination,
     * time, vibe. Mesaj + previous intent'in toplamından en az 2 eksen dolmamışsa
     * eksik kalanlardan en kritik olanı sorar. LLM'in tutarsız "yeterli mi" yargısı
     * yerine kuralla yönetmek tutarlılık ve hız kazandırır.
     *
     * @param  array<string, mixed>  $previousIntent
     */
    public function maybeAskClarification(string $userMessage, array $previousIntent): ?string
    {
        // "Farketmez / önemli değil / sen seç" → kullanıcı kısıt vermek istemiyor.
        // Soruyu tekrarlamak saçma diyaloglar üretir; eldekiyle arama yapılır.
        if ($this->userDismissesClarification($userMessage)) {
            return null;
        }

        $cleanIntent = collect($previousIntent)
            ->reject(fn ($_, $key) => str_starts_with($key, '_'))
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
        $missing = array_keys(array_filter($signals, fn ($v) => ! $v));

        return $this->buildClarificationQuestion($missing, $present);
    }

    /**
     * Kullanıcı netleştirme sorusunu geçiştiriyor mu? ("farketmez", "sen seç",
     * "hepsi olur"...) — bu cevaba yeni soru sormak diyaloğu kilitler.
     */
    private function userDismissesClarification(string $userMessage): bool
    {
        return $this->containsAny($userMessage, [
            'farketmez', 'fark etmez', 'farketmiyor', 'fark etmiyor', 'farketmes',
            'onemli degil', 'onemi yok', 'hepsi olur', 'her sey olur', 'herhangi',
            'sen sec', 'sen oner', 'sen karar', 'sana birakiyorum', 'size birakiyorum',
            'ne olursa', 'bilmiyorum sen', 'bilmem sen', 'olsun yeter', 'yeter ki',
        ]);
    }

    /** @param  array<string, mixed>  $intent */
    public function hasBudgetSignal(array $intent, string $message): bool
    {
        if (! empty($intent['max_budget'])) {
            return true;
        }

        $normalized = $this->normalizeTr($message);

        // "bütçe(m/n/yle...)" kelimesi → kullanıcı bütçeden bahsediyor
        if ($this->matchesWord($normalized, 'butce')) {
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

    /** @param  array<string, mixed>  $intent */
    public function hasDestinationSignal(array $intent, string $message): bool
    {
        $intentKeys = ['preferred_destination', 'is_international', 'exclude_destinations', 'requires_visa'];
        foreach ($intentKeys as $key) {
            if (array_key_exists($key, $intent) && $intent[$key] !== null && $intent[$key] !== '' && $intent[$key] !== []) {
                return true;
            }
        }

        $normalized = $this->normalizeTr($message);

        // Yön/bölge ifadeleri — bitişik yazımlar ("yurtdışı", "ortadoğu") da yakalanır
        if ($this->containsAny($normalized, ['yurt ici', 'yurtici', 'yurt disi', 'yurtdisi', 'orta dogu', 'ortadogu', 'new york', 'las vegas'])) {
            return true;
        }

        if ($this->matchesAnyWord($normalized, ['avrupa', 'asya', 'amerika', 'afrika', 'balkan', 'akdeniz', 'ege', 'karadeniz'])) {
            return true;
        }

        // Kısa/tuzaklı adlar yalnız TAM kelime ("balık"→bali, "fasıl"→fas sanılmasın)
        if ($this->matchesAnyWord($normalized, ['bali', 'fas'], 0)) {
            return true;
        }

        return $this->matchesAnyWord($normalized, [
            'paris', 'roma', 'londra', 'amsterdam', 'venedik', 'barselona', 'prag', 'atina', 'berlin', 'viyana', 'milano', 'floransa',
            'istanbul', 'antalya', 'bodrum', 'kapadokya', 'fethiye', 'marmaris', 'cesme', 'alanya', 'didim', 'kusadasi', 'izmir', 'ankara',
            'maldivler', 'dubai', 'tayland', 'phuket', 'singapur', 'tokyo', 'newyork', 'miami',
            'misir', 'tunus', 'yunan', 'yunanistan', 'mykonos', 'santorini', 'rodos', 'girit', 'kibris',
        ]);
    }

    /** @param  array<string, mixed>  $intent */
    public function hasTimeSignal(array $intent, string $message): bool
    {
        $intentKeys = ['preferred_month', 'preferred_min_days', 'preferred_max_days'];
        foreach ($intentKeys as $key) {
            if (! empty($intent[$key])) {
                return true;
            }
        }

        $normalized = $this->normalizeTr($message);

        // Tek kelimelik zaman anahtarları — kelime sınırlı ("nişanlımla"→nisan tuzağı yok)
        if ($this->matchesAnyWord($normalized, [
            'ocak', 'subat', 'mart', 'nisan', 'mayis', 'haziran',
            'temmuz', 'agustos', 'eylul', 'ekim', 'kasim', 'aralik',
            'yaz', 'bahar', 'sonbahar', 'somestir',
            'haftasonu', 'haftaici', 'bayram', 'onumuzdeki',
        ])) {
            return true;
        }

        // 'kış' normalize sonrası 'kişi' ile çakışır ("2 kişilik" kış değildir) — tam kelime
        if ($this->matchesAnyWord($normalized, ['kis', 'kisin'], 0)) {
            return true;
        }

        if ($this->containsAny($normalized, ['hafta sonu', 'gelecek hafta', 'gelecek ay'])) {
            return true;
        }

        // "5 gün", "3 hafta" vb.
        return (bool) preg_match('/\d+\s*(gun|hafta|ay)/u', $normalized);
    }

    /** @param  array<string, mixed>  $intent */
    public function hasVibeSignal(array $intent, string $message): bool
    {
        $intentKeys = ['wants_nature', 'avoid_crowded_city', 'wants_lively'];
        foreach ($intentKeys as $key) {
            if (array_key_exists($key, $intent) && $intent[$key] !== null) {
                return true;
            }
        }

        $normalized = $this->normalizeTr($message);

        // Tek kelimelik anahtarlar — kelime sınırlı ("İspanya"→spa tuzağı yok)
        if ($this->matchesAnyWord($normalized, [
            'plaj', 'doga', 'kultur', 'tarihi', 'tarih', 'kayak', 'cruise', 'gemi',
            'safari', 'macera', 'luks', 'romantik', 'balayi',
            'eglence', 'sakin', 'huzurlu', 'kalabalik',
            'gezi', 'spa', 'wellness',
        ])) {
            return true;
        }

        return $this->containsAny($normalized, ['gece hayat', 'sehir turu', 'all inclusive']);
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
            return 'Kısa bir bilgi: '.$askParts[0].'?';
        }

        return 'İki noktayı netleştirelim: '.implode(' ve ', $askParts).'?';
    }

    /**
     * normalizeTr'den geçmiş metinde kelimeyi KELİME SINIRLI arar — çıplak
     * str_contains'in alt-dizi tuzaklarını önler: "nişanlımla" içindeki 'nisan'
     * ay değildir, "Denizli" içindeki 'deniz' plaj değildir. Türkçe çekim
     * ekleri için sınırlı sonek toleransı vardır ("nisanda", "avrupaya"
     * eşleşir; $maxSuffix=0 tam kelime). AiSearchController::textHasWord ile
     * aynı mantık.
     */
    private function matchesWord(string $normalized, string $word, int $maxSuffix = 4): bool
    {
        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'\p{L}{0,'.$maxSuffix.'}(?![\p{L}\p{N}])/u';

        return preg_match($pattern, $normalized) === 1;
    }

    /**
     * containsAny'nin KELİME SINIRLI karşılığı — matchesWord ile arar.
     *
     * @param  array<int, string>  $words
     */
    private function matchesAnyWord(string $normalized, array $words, int $maxSuffix = 4): bool
    {
        foreach ($words as $word) {
            if ($this->matchesWord($normalized, $word, $maxSuffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Metinde (normalizeTr'den geçirilerek) kalıplardan biri alt-dizi olarak
     * geçiyor mu? LLM'siz kelime-kalıbı dedektörlerinin ortak gövdesi.
     * normalizeTr idempotenttir — zaten normalize edilmiş metin de verilebilir.
     *
     * @param  array<int, string>  $patterns
     */
    private function containsAny(string $text, array $patterns): bool
    {
        $normalized = $this->normalizeTr($text);

        foreach ($patterns as $pattern) {
            if (str_contains($normalized, $pattern)) {
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
}
