<?php

namespace App\Services\AiSearch;

use App\Models\DestinationProfile;
use App\Models\Tour;
use Illuminate\Support\Facades\Cache;

/**
 * Sitedeki destinasyon envanteri + destinasyon sorularına LLM'siz deterministik
 * cevaplar. "Nerelere turunuz var?" ve "İstanbul nasıl bir şehir?" akışlarının
 * tek veri kaynağı: aktif (lisans-görünür) turlardan şehir bazlı envanter +
 * destination_profiles karakter verisi.
 */
class DestinationKnowledgeService
{
    public const INVENTORY_CACHE_KEY = 'ai_dest_inventory_v1';

    private const CACHE_TTL_HOURS = 6;

    /** Envanter cevabında listelenen maksimum şehir sayısı. */
    private const LIST_LIMIT = 25;

    public function __construct(private readonly DestinationProfileService $profiles) {}

    /**
     * Şehir bazlı envanter: aktif + satılabilir turların destinasyonları tekil
     * şehirlere bölünür ("Paris, Roma" iki şehre sayılır). TourObserver + TourDate
     * kancaları tur/tarih değişince cache'i düşürür.
     *
     * @return array<string, array{city: string, count: int}> normalize edilmiş ada göre
     */
    public function inventory(): array
    {
        return $this->cachedInventory()['cities'];
    }

    /**
     * @return array{cities: array<string, array{city: string, count: int}>, total: int}
     */
    private function cachedInventory(): array
    {
        return Cache::remember(self::INVENTORY_CACHE_KEY, now()->addHours(self::CACHE_TTL_HOURS), function () {
            $destinations = Tour::query()
                ->active()
                ->whereNotNull('destination')
                ->where('destination', '!=', '')
                // Satılabilirlik: gelecek tekil kalkış VEYA tarih listesinde gelecek
                // kalkış VEYA hiç tarih bilgisi olmayan tur (her-gün/sürekli turlar —
                // tarihi yok diye "turumuz yok" yalanı söylenmez). Yalnız TÜM
                // tarihleri geçmişte kalan turlar dışarıda kalır.
                ->where(function ($q) {
                    $q->whereDate('departure_date', '>=', now()->toDateString())
                        ->orWhereHas('dates', fn ($d) => $d->whereDate('departure_date', '>=', now()->toDateString()))
                        ->orWhere(function ($undated) {
                            $undated->whereNull('departure_date')->whereDoesntHave('dates');
                        });
                })
                ->pluck('destination');

            $cities = [];
            foreach ($destinations as $destination) {
                foreach (DestinationProfile::splitCities((string) $destination) as $city) {
                    $key = DestinationProfile::normalize($city);
                    if (! isset($cities[$key])) {
                        $cities[$key] = ['city' => $city, 'count' => 0];
                    }
                    $cities[$key]['count']++;
                }
            }

            uasort($cities, fn ($a, $b) => $b['count'] <=> $a['count']);

            // Toplam, şehir listesiyle AYNI anda ve AYNI filtreden sayılır —
            // taze sorgu + bayat cache karışımı çelişen sayılar üretmesin
            return ['cities' => $cities, 'total' => $destinations->count()];
        });
    }

    public static function flushInventory(): void
    {
        Cache::forget(self::INVENTORY_CACHE_KEY);
    }

    /**
     * "Nerelere turunuz var?" için deterministik cevap + devam çipleri.
     *
     * @return array{text: string, suggestions: array<int, string>}
     */
    public function answerInventoryQuestion(): array
    {
        $cached = $this->cachedInventory();
        $inventory = $cached['cities'];

        if ($inventory === []) {
            return [
                'text' => 'Şu an aktif tur envanterimiz güncelleniyor — birazdan tekrar sorabilir ya da bana nasıl bir tatil istediğini anlatabilirsin, uygun seçenek çıktığında birlikte bakarız.',
                'suggestions' => [],
            ];
        }

        $totalTours = $cached['total'];
        $listed = array_slice(array_values($inventory), 0, self::LIST_LIMIT);
        $rest = count($inventory) - count($listed);

        $lines = collect($listed)
            ->map(fn ($row) => $row['city'].' ('.$row['count'].' tur)')
            ->implode(', ');

        $text = 'Şu an '.count($inventory).' destinasyonda toplam '.$totalTours." aktif turumuz var 🌍\n"
            .$lines
            .($rest > 0 ? " ve {$rest} destinasyon daha." : '.')
            ."\nHangisine bakmak istersin? Bütçeni ve tarihini de söylersen sana en uygununu seçeyim.";

        $suggestions = collect($listed)
            ->take(4)
            ->map(fn ($row) => $row['city'].' turlarını göster')
            ->all();

        return ['text' => $text, 'suggestions' => $suggestions];
    }

    /**
     * Mesajda envanter veya profil tablosunda bilinen bir şehir arar.
     * Kelime sınırlı arama + Türkçe ek toleransı ("İstanbul'un", "Roma'ya").
     *
     * @return array{city: string, normalized: string, count: int}|null
     */
    public function findCityInMessage(string $message): ?array
    {
        $normalizedMessage = DestinationProfile::normalize($message);
        if ($normalizedMessage === '') {
            return null;
        }

        // Önce envanterdeki şehirler (uzun ad önce — "new york" > "york")
        $candidates = collect($this->inventory())
            ->map(fn ($row, $key) => ['city' => $row['city'], 'normalized' => $key, 'count' => $row['count']])
            ->values();

        // Envanterde olmayan ama profili olan şehirler de tanınsın ("Bodrum'a
        // turunuz var mı?" → dürüst "şu an yok" cevabı verebilmek için)
        $profileCities = DestinationProfile::query()
            ->whereNotIn('normalized_city', $candidates->pluck('normalized'))
            ->pluck('city', 'normalized_city')
            ->map(fn ($city, $key) => ['city' => $city, 'normalized' => $key, 'count' => 0])
            ->values();

        // Ek toleransı YALNIZ Türkçe çekim ekleri: serbest \p{L}{0,6} "Romanya"yı
        // Roma, "Vancouver"ı Van sanıyordu. Apostroflu ekler serbest ("Roma'ya"),
        // apostrofsuz yalnız bilinen ek kalıpları (istanbula, romada, romanin).
        $suffix = "(?:'\\p{L}{1,6}|y?[aeiu]n?|n[aeiu]n?|d[ae](?:ki|n)?|t[ae](?:ki|n)?|yl[ae])?";

        $found = $candidates->concat($profileCities)
            ->sortByDesc(fn ($row) => mb_strlen($row['normalized'], 'UTF-8'))
            ->first(function ($row) use ($normalizedMessage, $suffix) {
                if (mb_strlen($row['normalized'], 'UTF-8') < 3) {
                    return false;
                }

                return preg_match('/\b'.preg_quote($row['normalized'], '/').$suffix.'\b/u', $normalizedMessage) === 1;
            });

        return $found ?: null;
    }

    /**
     * "X nasıl bir şehir?" için deterministik, profil-verisinden cevap.
     * Profil zenginleşmemişse uydurmak yerine dürüstçe söyler.
     *
     * @param  array{city: string, normalized: string, count: int}  $match
     * @return array{text: string, suggestions: array<int, string>}
     */
    public function answerCityQuestion(array $match): array
    {
        $profile = $this->profiles->get($match['city']);
        $city = $match['city'];
        $count = $match['count'];

        $inventoryLine = $count > 0
            ? "Şu an {$city} için {$count} aktif turumuz var."
            : "Şu an {$city} için aktif turumuz yok — istersen benzer bir destinasyon önerebilirim.";

        $description = DestinationProfileService::describeProfile($profile);
        if ($description === null) {
            // Profil henüz zenginleşmedi (yeni şehir): uydurma niteleyici yazma
            return [
                'text' => "{$city} için detaylı şehir profilim henüz hazır değil — kısa süre içinde tamamlanacak. {$inventoryLine}",
                'suggestions' => $count > 0 ? ["{$city} turlarını göster"] : [],
            ];
        }

        // ucfirst çok baytlı harfleri ('ç') büyütemez — mb ile ilk harf
        $bits = [mb_strtoupper(mb_substr($description, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($description, 1, null, 'UTF-8').'.'];

        if (! empty($profile['summary'])) {
            $bits[] = $profile['summary'];
        }

        if (! empty($profile['best_months'])) {
            $months = collect($profile['best_months'])
                ->map(fn ($m) => DestinationProfileService::MONTH_NAMES_TR[(int) $m] ?? null)
                ->filter()
                ->implode(', ');
            if ($months !== '') {
                $bits[] = 'Ziyaret için en iyi aylar: '.$months.'.';
            }
        }

        if (($profile['requires_visa_for_tr'] ?? null) === false) {
            $bits[] = 'Türk vatandaşları için vize gerekmiyor.';
        } elseif (($profile['requires_visa_for_tr'] ?? null) === true) {
            $bits[] = 'Türk vatandaşları için vize gerekiyor — başvuruya erken başlamakta fayda var.';
        }

        $bits[] = $inventoryLine;

        $text = ($profile['country'] && $count === 0
                ? "{$city} ({$profile['country']}): "
                : "{$city}: ")
            .implode(' ', $bits);

        $suggestions = $count > 0
            ? ["{$city} turlarını göster", "{$city} için en uygun ay hangisi?"]
            : ['Benzer destinasyonları öner'];

        return ['text' => $text, 'suggestions' => $suggestions];
    }

    /**
     * Yorum LLM'inin system promptuna giren kompakt envanter satırı — model
     * yalnız gerçekten turumuz olan yerlere yönlendirebilsin.
     */
    public function promptInventoryLine(): ?string
    {
        $inventory = $this->inventory();
        if ($inventory === []) {
            return null;
        }

        $names = collect($inventory)->take(40)->pluck('city')->implode(', ');
        $rest = count($inventory) - min(count($inventory), 40);

        return $names.($rest > 0 ? " (+{$rest} destinasyon)" : '');
    }
}
