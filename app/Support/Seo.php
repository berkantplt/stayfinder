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
        // category ve destination BİLEREK YOK: artık düz landing adreslerinde
        // yaşıyorlar (/kultur-turlari, /kapadokya-turlari) ve tek başlarına
        // geldiklerinde 301 ile oraya taşınıyorlar (TourController::index).
        // departure_city henüz düz adrese taşınmadı — Faz 3'ün ikinci yarısı.
        'tours.index' => ['departure_city'],
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

        // noindex olan sayfa BAŞKA bir adrese kanonik vermemeli: Google
        // "indexleme" ile "bu değil şu" sinyallerini çelişkili bulur ve ikisini
        // birden yok sayabilir. Böyle sayfalar kendilerine kanonik verir;
        // yalnız izleme parametreleri temizlenir.
        if (self::robots($request) !== null) {
            $params = [];
            foreach ($request->query() as $key => $value) {
                if (! in_array($key, self::TRACKING_PARAMS, true) && is_string($value) && $value !== '') {
                    $params[$key] = $value;
                }
            }
            ksort($params);

            return $params === [] ? $base : $base.'?'.http_build_query($params);
        }

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
     * Başlıkta kullanılacak yıl damgası.
     *
     * Rakip taramasında 8 sayfadan 6'sında title'da yıl var ("Kapadokya Turu
     * Fiyatları 2026") — ama SSC Tur'unki hâlâ elle yazılmış "2025" taşıyor ve
     * bayat görünüyor. Bu yüzden yıl ASLA elle yazılmaz, buradan gelir.
     *
     * Kasım–Aralık'ta bir sonraki yıla geçilir: tur satışı öne çalışır, aralıkta
     * "2026 fiyatları" yazan bir sayfa satılan üründen geri kalır.
     */
    public static function year(): int
    {
        $now = now();

        return (int) $now->year + ($now->month >= 11 ? 1 : 0);
    }

    /**
     * Adın "tur" ekinden arındırılmış gövdesi.
     *
     * Kategori adları zaten "Kültür Turları" biçiminde geliyor; sonuna bir
     * "Turları" daha eklenince "Kültür Turları Turları" çıkıyordu. Destinasyon
     * adları ise ("Kapadokya") eki taşımıyor. Gövde ikisini ortak paydaya çeker.
     */
    public static function stem(string $name): string
    {
        $name = trim($name);

        // Uzundan kısaya: "Turları" önce denenmeli, yoksa "Tur" onu kırpar.
        foreach (['Turları', 'Turlari', 'Turlar', 'Turu', 'Tur'] as $suffix) {
            if (mb_strtolower(mb_substr($name, -mb_strlen($suffix))) === mb_strtolower($suffix)) {
                $stripped = trim(mb_substr($name, 0, -mb_strlen($suffix)));

                // "Turlar" gibi tek başına ek olan adı boşaltma.
                if ($stripped !== '') {
                    return $stripped;
                }
            }
        }

        return $name;
    }

    /**
     * Liste sayfasının görünür başlığı: "Kapadokya Turları", "Kültür Turları".
     */
    public static function listingHeading(string $name): string
    {
        return self::stem($name).' Turları';
    }

    /**
     * Liste sayfası başlığı — rakip ölçümüyle aynı kalıp:
     * "{Gövde} Turları | {Gövde} Turu Fiyatları {YIL} — turXtur"
     *
     * Ölçülen rakip title'ları 40–63 karakter bandında. Uzun adlarda sınır
     * aşılmasın diye önce marka soneki, sonra ikinci tamlama düşürülür —
     * SERP'te kesilen başlık ("...ve Fiyatları 2026| Prontotour") hem çirkin
     * hem anahtar kelimeyi boşa harcıyor.
     */
    public static function listingTitle(string $name): string
    {
        $stem = self::stem($name);
        $heading = $stem.' Turları';
        $year = self::year();
        $brand = (string) config('app.name', 'turXtur');

        $full = "{$heading} | {$stem} Turu Fiyatları {$year} — {$brand}";
        if (mb_strlen($full) <= 62) {
            return $full;
        }

        $short = "{$heading} | {$stem} Turu Fiyatları {$year}";
        if (mb_strlen($short) <= 62) {
            return $short;
        }

        return "{$heading} Fiyatları {$year} — {$brand}";
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
