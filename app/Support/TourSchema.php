<?php

namespace App\Support;

use App\Models\Tour;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Tur detay sayfasının yapısal verisi (schema.org).
 *
 * Rakip taramasında (tatilsepeti, tatilbudur, jollytur, setur, gruppal, MNG)
 * ölçülenler ve buradaki karşılıkları:
 *
 *  - 6 sitenin 4'ü günlük programı schema'ya HİÇ taşımıyor  → subTrip[] ile taşınır
 *  - 6 sitenin hiçbirinde FAQPage yok                        → faq() üretir
 *  - tatilsepeti/jolly bir TURA LodgingBusiness basıyor      → TouristTrip kullanılır
 *  - setur priceCurrency="TL" yazıp geçersiz kılmış          → ISO 4217 doğrulanır
 *  - jolly'nin JSON-LD'si fazladan ";" yüzünden parse olmuyor → dizi + json_encode
 *  - tatilbudur description="" ve image=[""] boş bırakmış     → boş alan hiç basılmaz
 *
 * Kural: uydurma alan yok. Veride karşılığı olmayan hiçbir şey basılmaz —
 * eksik alan, yanlış alandan iyidir.
 */
class TourSchema
{
    /**
     * schema.org'un tanıdığı para birimi kodları (ISO 4217). Tour modelindeki
     * desteklenen kurlarla aynı küme; geçersiz değer basmaktansa alan düşer.
     *
     * @var array<int, string>
     */
    private const ISO_CURRENCIES = ['TRY', 'USD', 'EUR', 'GBP', 'AED', 'SAR'];

    /**
     * Tur sayfasının tüm yapısal verisi tek @graph içinde.
     *
     * @return array<string, mixed>
     */
    public static function graph(Tour $tour): array
    {
        $nodes = array_values(array_filter([
            self::touristTrip($tour),
            self::product($tour),
            self::faq($tour),
        ]));

        return [
            '@context' => 'https://schema.org',
            '@graph' => $nodes,
        ];
    }

    /**
     * TouristTrip — turizme özgü tip. Google'ın seyahat sonuçlarında
     * Product'tan daha doğru bir varlık tanımı verir.
     *
     * @return array<string, mixed>
     */
    private static function touristTrip(Tour $tour): array
    {
        $node = [
            '@type' => 'TouristTrip',
            '@id' => route('tours.show', $tour).'#trip',
            'name' => (string) $tour->title,
            'url' => route('tours.show', $tour),
        ];

        if ($description = self::description($tour)) {
            $node['description'] = $description;
        }

        if ($images = self::images($tour)) {
            $node['image'] = $images;
        }

        if ($destination = trim((string) $tour->destination)) {
            $node['itinerary'] = [
                '@type' => 'Place',
                'name' => $destination,
            ];
        }

        // Gün gün program → her gün ayrı Trip. Rakiplerin 4'ünde bu hiç yok.
        if ($subTrips = self::subTrips($tour)) {
            $node['subTrip'] = $subTrips;
        }

        if ($offer = self::offer($tour)) {
            $node['offers'] = $offer;
        }

        if ($agency = $tour->agency) {
            $node['provider'] = [
                '@type' => 'TravelAgency',
                'name' => (string) $agency->name,
                'url' => route('agencies.show', $agency),
            ];
        }

        if ($days = (int) $tour->duration_days) {
            // ISO 8601 süre: 3 gün → P3D
            $node['duration'] = 'P'.$days.'D';
        }

        return $node;
    }

    /**
     * Product — fiyat/teklif zengin sonuçlarının dayandığı tip.
     * TouristTrip ile aynı sayfada, @id ile ayrışarak durur.
     *
     * @return array<string, mixed>
     */
    private static function product(Tour $tour): array
    {
        $node = [
            '@type' => 'Product',
            '@id' => route('tours.show', $tour).'#product',
            'name' => (string) $tour->title,
            'sku' => 'tur-'.$tour->getKey(),
            'url' => route('tours.show', $tour),
        ];

        if ($description = self::description($tour)) {
            $node['description'] = $description;
        }

        if ($images = self::images($tour)) {
            $node['image'] = $images;
        }

        if ($agency = $tour->agency) {
            $node['brand'] = ['@type' => 'Brand', 'name' => (string) $agency->name];
        }

        if ($offer = self::offer($tour)) {
            $node['offers'] = $offer;
        }

        // NOT: aggregateRating ve review BİLEREK basılmıyor. Gerçek yorum
        // olmadan puan basmak Google'ın yapısal veri spam politikası ihlali ve
        // manuel işlem sebebi. Yorum toplama akışı (Faz 4.3) yürüdüğünde açılır.

        return $node;
    }

    /**
     * Teklif. Tarih listesinde farklı fiyatlar varsa AggregateOffer
     * (lowPrice/highPrice), tek fiyat varsa Offer.
     *
     * @return array<string, mixed>|null
     */
    private static function offer(Tour $tour): ?array
    {
        $currency = self::currency($tour);
        if ($currency === null) {
            return null;
        }

        $prices = self::priceCandidates($tour);
        if ($prices === []) {
            return null;
        }

        $base = [
            'priceCurrency' => $currency,
            'availability' => 'https://schema.org/InStock',
            'url' => route('tours.show', $tour),
        ];

        if ($validUntil = self::priceValidUntil($tour)) {
            $base['priceValidUntil'] = $validUntil;
        }

        if ($agency = $tour->agency) {
            $base['seller'] = [
                '@type' => 'TravelAgency',
                'name' => (string) $agency->name,
                'url' => route('agencies.show', $agency),
            ];
        }

        $low = min($prices);
        $high = max($prices);

        if ($high > $low) {
            return $base + [
                '@type' => 'AggregateOffer',
                'lowPrice' => self::money($low),
                'highPrice' => self::money($high),
                'offerCount' => count($prices),
            ];
        }

        return $base + [
            '@type' => 'Offer',
            'price' => self::money($low),
        ];
    }

    /**
     * Turun fiyat adayları: ana fiyat + tarih listesindeki fiyatlar.
     *
     * @return array<int, float>
     */
    private static function priceCandidates(Tour $tour): array
    {
        $prices = [];

        if (($main = (float) $tour->price) > 0) {
            $prices[] = $main;
        }

        // dates ilişkisi yüklenmemişse ek sorgu açmayalım — sayfa zaten
        // load('dates') yapıyor, yapmadığı yerde ana fiyat yeterli.
        if ($tour->relationLoaded('dates')) {
            foreach ($tour->dates as $date) {
                if (($p = (float) $date->price) > 0) {
                    $prices[] = $p;
                }
            }
        }

        return array_values(array_unique($prices));
    }

    /**
     * Fiyatın geçerlilik sonu: bilinen son kalkış tarihi, yoksa dönüş tarihi.
     * Google, priceValidUntil geçmişte kalan teklifleri zengin sonuçtan düşürür;
     * bu yüzden geçmiş bir tarih basmaktansa alan hiç basılmaz.
     */
    private static function priceValidUntil(Tour $tour): ?string
    {
        $candidates = [];

        if ($tour->relationLoaded('dates')) {
            foreach ($tour->dates as $date) {
                if ($date->departure_date) {
                    $candidates[] = Carbon::parse($date->departure_date);
                }
            }
        }

        foreach ([$tour->return_date, $tour->departure_date] as $fallback) {
            if ($fallback) {
                $candidates[] = Carbon::parse($fallback);
            }
        }

        if ($candidates === []) {
            return null;
        }

        $latest = max($candidates);

        return $latest->isFuture() ? $latest->toDateString() : null;
    }

    /**
     * Günlük program → subTrip[]. itinerary biçimi: [{title, content}, ...]
     *
     * @return array<int, array<string, mixed>>
     */
    private static function subTrips(Tour $tour): array
    {
        $itinerary = $tour->itinerary;
        if (! is_array($itinerary) || $itinerary === []) {
            return [];
        }

        $trips = [];

        foreach ($itinerary as $index => $day) {
            if (! is_array($day)) {
                continue;
            }

            $title = trim((string) ($day['title'] ?? ''));
            $content = trim(strip_tags((string) ($day['content'] ?? '')));

            // Başlığı da içeriği de olmayan gün basılmaz.
            if ($title === '' && $content === '') {
                continue;
            }

            $trip = [
                '@type' => 'Trip',
                'name' => $title !== '' ? $title : ($index + 1).'. Gün',
            ];

            if ($content !== '') {
                $trip['description'] = Str::limit($content, 500);
            }

            $trips[] = $trip;
        }

        return $trips;
    }

    /**
     * Liste sayfaları için ItemList → TouristTrip + Offer.
     *
     * Rakip taramasında jollytur ve Prontotour'un liste sayfalarında ürün
     * yapısal verisi HİÇ yok; tatilsepeti 30, tatilbudur 20 basıyor.
     *
     * tatilbudur'un hatası tekrarlanmıyor: o, her ürünün description alanına
     * kategori meta metnini kopyalamış (20 üründe aynı cümle). Burada her turun
     * kendi açıklaması yazılır, yoksa alan hiç basılmaz.
     *
     * @param  iterable<int, Tour>  $tours
     * @return array<string, mixed>|null
     */
    public static function itemList(iterable $tours, string $listUrl): ?array
    {
        $items = [];
        $position = 0;

        foreach ($tours as $tour) {
            if (! $tour instanceof Tour) {
                continue;
            }

            $item = [
                '@type' => 'TouristTrip',
                'name' => (string) $tour->title,
                'url' => route('tours.show', $tour),
            ];

            if ($description = self::description($tour)) {
                $item['description'] = $description;
            }

            if ($images = self::images($tour)) {
                $item['image'] = $images[0];
            }

            if ($offer = self::offer($tour)) {
                // Listede teklif sadeleşir: satıcı ve geçerlilik detayı ürün
                // sayfasında zaten var, listede fiyat + para birimi yeterli.
                $item['offers'] = array_intersect_key($offer, array_flip([
                    '@type', 'price', 'lowPrice', 'highPrice', 'offerCount',
                    'priceCurrency', 'availability', 'url',
                ]));
            }

            if ($agency = $tour->agency) {
                // Pazaryeri farkı: turu SATAN acenta. tatilsepeti bunu basıyor,
                // jollytur ve Prontotour hiç ürün schema'sı basmıyor.
                $item['provider'] = ['@type' => 'TravelAgency', 'name' => (string) $agency->name];
            }

            $items[] = [
                '@type' => 'ListItem',
                'position' => ++$position,
                'item' => $item,
            ];
        }

        if ($items === []) {
            return null;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'url' => $listUrl,
            'numberOfItems' => count($items),
            'itemListElement' => $items,
        ];
    }

    /**
     * Sıkça sorulan sorular — YALNIZCA turun gerçek verisinden.
     *
     * İncelenen 6 rakip sitenin hiçbirinde FAQPage yok; bu bilgi hepsinde düz
     * metne gömülü. Buradaki sorular uydurulmuyor: karşılığı olan alan boşsa
     * o soru hiç sorulmuyor.
     *
     * @return array<string, mixed>|null
     */
    public static function faq(Tour $tour): ?array
    {
        $pairs = [];

        if ($days = (int) $tour->duration_days) {
            $pairs[] = [
                $tour->title.' kaç gün sürüyor?',
                $tour->duration_label.' sürmektedir.'
                    .($tour->departure_date ? ' İlk kalkış tarihi '.$tour->departure_date->translatedFormat('d F Y').'.' : ''),
            ];
        }

        if ($included = self::listFromText($tour->included)) {
            $pairs[] = [
                'Tur fiyatına neler dahil?',
                'Fiyata dahil olanlar: '.implode(', ', $included).'.',
            ];
        }

        if ($excluded = self::listFromText($tour->excluded)) {
            $pairs[] = [
                'Tur fiyatına neler dahil değil?',
                'Fiyata dahil olmayanlar: '.implode(', ', $excluded).'.',
            ];
        }

        if ($tour->is_international) {
            $pairs[] = [
                'Bu tur için vize gerekiyor mu?',
                $tour->requires_visa
                    ? 'Bu yurt dışı turu için vize gerekmektedir. Güncel vize şartlarını turu düzenleyen acentaya danışın.'
                    : 'Tur bilgilerinde vize şartı belirtilmemiştir. Kesin bilgi için turu düzenleyen acentaya danışın.',
            ];
        }

        if ($points = self::listFromText($tour->departure_points)) {
            $pairs[] = [
                'Tur nereden kalkıyor?',
                'Kalkış noktaları: '.implode(', ', $points).'.',
            ];
        }

        if ($policy = trim(strip_tags((string) $tour->cancellation_policy))) {
            $pairs[] = ['İptal ve değişiklik şartları neler?', Str::limit($policy, 600)];
        }

        // Tek soruluk SSS bloğu hem kullanıcıya hem Google'a zayıf görünür.
        if (count($pairs) < 2) {
            return null;
        }

        return [
            '@type' => 'FAQPage',
            '@id' => route('tours.show', $tour).'#faq',
            'mainEntity' => array_map(fn (array $pair) => [
                '@type' => 'Question',
                'name' => $pair[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $pair[1]],
            ], $pairs),
        ];
    }

    /**
     * Serbest metin alanını (satır/virgül ayrılmış) listeye çevirir.
     *
     * @return array<int, string>
     */
    private static function listFromText(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } elseif (is_string($value) && trim($value) !== '') {
            $parts = preg_split('/\r\n|\r|\n|•|;/u', strip_tags($value)) ?: [];
        } else {
            return [];
        }

        $clean = [];
        foreach ($parts as $part) {
            $part = trim(ltrim((string) $part, "-*✓✔ \t"));
            if ($part !== '') {
                $clean[] = $part;
            }
        }

        return array_slice($clean, 0, 12);
    }

    private static function description(Tour $tour): ?string
    {
        $text = trim(strip_tags((string) $tour->description));

        return $text !== '' ? Str::limit($text, 300) : null;
    }

    /**
     * @return array<int, string>
     */
    private static function images(Tour $tour): array
    {
        $paths = [];

        if ($cover = trim((string) $tour->image)) {
            $paths[] = $cover;
        }

        if (is_array($tour->images)) {
            foreach ($tour->images as $image) {
                if (is_string($image) && trim($image) !== '') {
                    $paths[] = trim($image);
                }
            }
        }

        $urls = [];
        foreach (array_unique($paths) as $path) {
            $urls[] = str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
                ? $path
                : url($path);
        }

        return array_slice($urls, 0, 6);
    }

    /**
     * ISO 4217 doğrulaması — setur'un "TL" hatası burada engellenir.
     */
    private static function currency(Tour $tour): ?string
    {
        $code = strtoupper(trim((string) ($tour->currency ?: 'TRY')));

        return in_array($code, self::ISO_CURRENCIES, true) ? $code : null;
    }

    private static function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
