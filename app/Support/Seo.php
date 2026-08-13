<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Kanonik adres ve indexleme kararları.
 *
 * SORUN: Filtre barı sınırsız URL üretiyor —
 * /turlar?category=x&min_price=1000&max_price=5000&sort=price_desc&date_start=...
 * Bu kombinasyonların sayısı kombinatoryal büyür. Google her birini ayrı sayfa
 * sanıp tarar; hepsi de aynı 92 turun farklı dizilişi olduğu için:
 *   1) tarama bütçesi (crawl budget) boşa yanar, gerçek tur sayfaları geç taranır,
 *   2) birbirinin kopyası yüzlerce sayfa "yinelenen içerik" sinyali üretir.
 *
 * ÇÖZÜM — iki katman:
 *   canonical() : izleme parametrelerini ve indexlenmeyecek filtreleri atar,
 *                 geriye kalan tek kanonik adresi üretir.
 *   robots()    : hangi kombinasyonun indexleneceğine karar verir.
 *
 * KURAL: yalnız TEK facet indexlenir (/turlar?category=kultur-turlari).
 * İki ve fazlası "noindex,follow" alır — taranır, linkleri izlenir, ama
 * indexlenmez. Bu kombinasyonlar Faz 3'te gerçek içerikli landing page'lere
 * dönüştürülecek; o zaman kendi adresleriyle indexlenirler.
 */
class Seo
{
    /**
     * Rota bazında indexlenmeye değer tekil filtreler. Buradakiler kanonik
     * adreste korunur; listede olmayan her filtre kanonikten düşer.
     *
     * @var array<string, array<int, string>>
     */
    private const INDEXABLE_FACETS = [
        'tours.index' => ['category', 'destination', 'departure_city'],
    ];

    /**
     * Kanonik adreste asla yer almayan parametreler. Reklam/analitik etiketleri
     * içeriği değiştirmez; kanonikte kalırlarsa aynı sayfa onlarca adres olur.
     *
     * @var array<int, string>
     */
    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id',
        'gclid', 'gbraid', 'wbraid', 'fbclid', 'msclkid', 'yclid', 'ttclid', 'igshid',
        'ref', 'referrer', 'source',
    ];

    /**
     * Sayfanın tek doğru adresi. Sayfalama korunur (page=2 kendine kanonik verir,
     * aksi halde Google 2. sayfadaki turları hiç görmez); page=1 düşer.
     */
    public static function canonical(?Request $request = null): string
    {
        $request ??= request();
        $base = $request->url();

        $allowed = self::indexableFacets($request);
        $params = [];

        foreach ($allowed as $key) {
            $value = $request->query($key);
            if (is_string($value) && $value !== '') {
                $params[$key] = $value;
            }
        }

        $page = (int) $request->query('page', 1);
        if ($page > 1) {
            $params['page'] = $page;
        }

        ksort($params);

        return $params === [] ? $base : $base.'?'.http_build_query($params);
    }

    /**
     * robots meta içeriği; null ise etiket hiç basılmaz (varsayılan: indexle).
     */
    public static function robots(?Request $request = null): ?string
    {
        $request ??= request();

        $allowed = self::indexableFacets($request);

        // Facet tanımı olmayan sayfalar (tur detayı, blog, destinasyon) her
        // zaman indexlenir — onlarda filtre kombinasyonu sorunu yok.
        if ($allowed === []) {
            return null;
        }

        $active = [];
        foreach ($request->query() as $key => $value) {
            if (in_array($key, self::TRACKING_PARAMS, true) || $key === 'page') {
                continue;
            }
            if (is_string($value) && $value !== '') {
                $active[] = $key;
            }
        }

        // Hiç filtre yok → temiz liste sayfası, indexlenir.
        if ($active === []) {
            return null;
        }

        // Tek ve indexlenebilir bir facet → indexlenir (/turlar?category=...).
        if (count($active) === 1 && in_array($active[0], $allowed, true)) {
            return null;
        }

        // Geri kalan her şey: sıralama, fiyat/tarih aralığı, serbest arama ve
        // çoklu facet kombinasyonları. Taransın, linkleri izlensin, indexlenmesin.
        return 'noindex,follow';
    }

    /**
     * "?page=1" adresini temiz adrese indirger. Sayfalayıcı ilk sayfa için hep
     * page=1 üretir; kanonik ise page=1'i düşürüyor. rel="prev" bu ikisinin
     * arasında kalmasın diye aynı biçime çekilir.
     */
    public static function withoutFirstPage(?string $url): ?string
    {
        if ($url === null || $url === '') {
            return $url;
        }

        $parts = parse_url($url);
        parse_str($parts['query'] ?? '', $query);

        if ((int) ($query['page'] ?? 0) !== 1) {
            return $url;
        }

        unset($query['page']);

        $base = (isset($parts['scheme'], $parts['host']) ? $parts['scheme'].'://'.$parts['host'] : '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '');

        return $query === [] ? $base : $base.'?'.http_build_query($query);
    }

    /**
     * @return array<int, string>
     */
    private static function indexableFacets(Request $request): array
    {
        $route = $request->route()?->getName();

        return $route !== null ? (self::INDEXABLE_FACETS[$route] ?? []) : [];
    }
}
