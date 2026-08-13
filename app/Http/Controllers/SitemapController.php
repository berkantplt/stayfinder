<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Destination;
use App\Models\Post;
use App\Models\Tour;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Sitemap index + bölüm haritaları.
 *
 * Tek dosya yerine bölümlere ayrıldı:
 *   - Google Search Console her bölümün indexlenme oranını AYRI raporlar.
 *     "Turların %90'ı indexlendi ama blogun %10'u" gibi teşhis ancak böyle mümkün.
 *   - Bölüm başına 50.000 URL / 50 MB sınırı vardır; chunk'lama şimdiden hazır.
 *   - Sık değişen bölüm (turlar) sık, sabit bölüm (kurumsal sayfalar) seyrek
 *     taranır; hepsi tek dosyada olsa bu ayrım kaybolurdu.
 *
 * Eklenenler: acenta sayfaları ve kurumsal/legal sayfalar (eski sürümde hiç yoktu),
 * statik girdilere lastmod, turlara görsel etiketi (Google Görseller trafiği).
 */
class SitemapController extends Controller
{
    /** Bir bölüm haritasına konacak azami URL sayısı (spec sınırı 50.000). */
    private const CHUNK = 10000;

    private const CACHE_HOURS = 6;

    /** @var array<string, string> bölüm anahtarı => URL parçası */
    private const SECTIONS = [
        'sayfalar' => 'sayfalar',
        'turlar' => 'turlar',
        'destinasyonlar' => 'destinasyonlar',
        'acentalar' => 'acentalar',
        'blog' => 'blog',
    ];

    /**
     * /sitemap.xml — bölüm haritalarını listeleyen index.
     */
    public function index(): Response
    {
        $xml = cache()->remember('sitemap_index_v2', now()->addHours(self::CACHE_HOURS), function () {
            $sections = [];

            foreach (self::SECTIONS as $key => $slug) {
                $pages = max(1, (int) ceil($this->countFor($key) / self::CHUNK));

                for ($page = 1; $page <= $pages; $page++) {
                    $sections[] = [
                        'loc' => route('sitemap.section', ['section' => $slug])
                            .($page > 1 ? '?sayfa='.$page : ''),
                        'lastmod' => $this->lastModifiedFor($key),
                    ];
                }
            }

            return view('sitemap.index', ['sections' => $sections])->render();
        });

        return $this->xml($xml);
    }

    /**
     * /sitemap-{bolum}.xml — tek bölümün URL listesi.
     */
    public function section(string $section): Response
    {
        abort_unless(in_array($section, self::SECTIONS, true), 404);

        $page = max(1, (int) request()->integer('sayfa', 1));
        $cacheKey = "sitemap_section_v2_{$section}_{$page}";

        $xml = cache()->remember($cacheKey, now()->addHours(self::CACHE_HOURS), function () use ($section, $page) {
            return view('sitemap.urlset', [
                'urls' => $this->urlsFor($section, $page),
            ])->render();
        });

        return $this->xml($xml);
    }

    /**
     * @return array<int, array{loc: string, lastmod?: string, changefreq?: string, priority?: string, images?: array<int, string>}>
     */
    private function urlsFor(string $section, int $page): array
    {
        return match ($section) {
            'sayfalar' => $this->staticPages(),
            'turlar' => $this->tours($page),
            'destinasyonlar' => $this->destinations($page),
            'acentalar' => $this->agencies($page),
            'blog' => $this->posts($page),
            default => [],
        };
    }

    /**
     * Kurumsal ve liste sayfaları. Eski sitemap'te bunların çoğu yoktu; lastmod
     * da yoktu — Google her taramada "değişmiş mi?" diye baştan indiriyordu.
     *
     * @return array<int, array<string, mixed>>
     */
    private function staticPages(): array
    {
        $deployedAt = $this->deploymentDate();

        $pages = [
            ['route' => 'home', 'changefreq' => 'daily', 'priority' => '1.0'],
            ['route' => 'tours.index', 'changefreq' => 'daily', 'priority' => '0.9'],
            ['route' => 'blog.index', 'changefreq' => 'weekly', 'priority' => '0.7'],
            ['route' => 'recreation.quiz.definition', 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['route' => 'legal.nasil-calisir', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['route' => 'legal.iletisim', 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['route' => 'legal.siralama', 'changefreq' => 'monthly', 'priority' => '0.4'],
            ['route' => 'legal.gizlilik', 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['route' => 'legal.kvkk', 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['route' => 'legal.cerez', 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['route' => 'legal.kosullar', 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        $urls = [];

        foreach ($pages as $page) {
            // Rota kapalıysa (ör. quiz askıya alınırsa) sitemap'e 404 koymayalım.
            if (! app('router')->has($page['route'])) {
                continue;
            }

            $urls[] = [
                'loc' => route($page['route']),
                'lastmod' => $deployedAt,
                'changefreq' => $page['changefreq'],
                'priority' => $page['priority'],
            ];
        }

        return $urls;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function tours(int $page): array
    {
        $urls = [];

        // cursor(): satırlar tek tek akar, 50.000 tur da olsa bellek sabit kalır.
        // (chunk() burada kullanılamaz — kendi limit/offset'ini uygulayıp
        // forPage ile kurulan sayfalamayı ezer.)
        $tours = $this->activeTours()
            ->select(['id', 'slug', 'title', 'image', 'updated_at'])
            ->orderBy('id')
            ->forPage($page, self::CHUNK)
            ->cursor();

        foreach ($tours as $tour) {
            $urls[] = [
                'loc' => route('tours.show', $tour),
                'lastmod' => $tour->updated_at?->toAtomString(),
                'changefreq' => 'daily',
                'priority' => '0.8',
                // Görsel etiketi: tur fotoğrafları Google Görseller'de
                // aranabilir hale gelir, ek bir trafik kanalı açar.
                'images' => $tour->image ? [$this->absolute($tour->image)] : [],
                'image_title' => $tour->title,
            ];
        }

        return $urls;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function destinations(int $page): array
    {
        return Destination::query()
            ->when(
                $this->hasColumn('destinations', 'is_active'),
                fn ($q) => $q->where('is_active', true)
            )
            ->orderBy('id')
            ->forPage($page, self::CHUNK)
            ->get()
            ->map(fn (Destination $destination) => [
                'loc' => route('destinations.show', $destination),
                'lastmod' => $destination->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.7',
                'images' => $destination->image ? [$this->absolute($destination->image)] : [],
                'image_title' => $destination->name,
            ])
            ->all();
    }

    /**
     * Acenta sayfaları eski sitemap'te HİÇ yoktu ve onlara link veren bir
     * liste sayfası da yok — yalnız tur sayfalarından erişiliyorlar.
     *
     * @return array<int, array<string, mixed>>
     */
    private function agencies(int $page): array
    {
        return Agency::active()
            ->whereHas('tours', fn ($q) => $q->active())
            ->orderBy('id')
            ->forPage($page, self::CHUNK)
            ->get()
            ->map(fn (Agency $agency) => [
                'loc' => route('agencies.show', $agency),
                'lastmod' => $agency->updated_at?->toAtomString(),
                'changefreq' => 'weekly',
                'priority' => '0.6',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function posts(int $page): array
    {
        // Yayınlanmamış yazı sitemap'e girerse Google'a 404 bildirilmiş olur
        // (PostController yayınlanmamışta abort(404) veriyor).
        return Post::query()
            ->where('is_published', true)
            ->orderBy('id')
            ->forPage($page, self::CHUNK)
            ->get()
            ->map(fn (Post $post) => [
                'loc' => route('blog.show', $post),
                'lastmod' => $post->updated_at?->toAtomString(),
                'changefreq' => 'monthly',
                'priority' => '0.6',
                'images' => $post->image ? [$this->absolute($post->image)] : [],
                'image_title' => $post->title,
            ])
            ->all();
    }

    private function countFor(string $section): int
    {
        return match ($section) {
            'sayfalar' => count($this->staticPages()),
            'turlar' => $this->activeTours()->count(),
            'destinasyonlar' => Destination::count(),
            'acentalar' => Agency::active()->whereHas('tours', fn ($q) => $q->active())->count(),
            'blog' => Post::where('is_published', true)->count(),
            default => 0,
        };
    }

    private function lastModifiedFor(string $section): ?string
    {
        $latest = match ($section) {
            'turlar' => $this->activeTours()->max('updated_at'),
            'destinasyonlar' => Destination::max('updated_at'),
            'acentalar' => Agency::active()->max('updated_at'),
            'blog' => Post::where('is_published', true)->max('updated_at'),
            default => null,
        };

        if ($latest === null) {
            return $this->deploymentDate();
        }

        return Carbon::parse($latest)->toAtomString();
    }

    /**
     * Turun görünürlüğü: aktif tur + aktif acenta. Pasif acentanın turları
     * sitede 404 veriyor; sitemap'e konursa Google'a kırık adres bildirilir.
     */
    private function activeTours()
    {
        return Tour::active()->whereHas('agency', fn ($q) => $q->active());
    }

    /**
     * Görsel alanı bazen tam URL (Unsplash), bazen yerel yol tutuyor.
     */
    private function absolute(string $path): string
    {
        return str_starts_with($path, 'http://') || str_starts_with($path, 'https://')
            ? $path
            : url($path);
    }

    private function hasColumn(string $table, string $column): bool
    {
        return cache()->remember(
            "schema_has_{$table}_{$column}",
            now()->addDay(),
            fn () => \Illuminate\Support\Facades\Schema::hasColumn($table, $column)
        );
    }

    /**
     * Statik sayfalar için lastmod. Dosya tarihi yerine sabit bir dağıtım
     * damgası: her istekte değişen bir lastmod, Google'ın güvenini düşürür.
     */
    private function deploymentDate(): string
    {
        return Carbon::createFromTimestamp(
            @filemtime(base_path('composer.lock')) ?: time()
        )->toAtomString();
    }

    private function xml(string $xml): Response
    {
        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Robots-Tag' => 'noindex',
        ]);
    }
}
