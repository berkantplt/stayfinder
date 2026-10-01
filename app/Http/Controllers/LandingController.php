<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Destination;
use App\Models\Tour;
use App\Support\CategoryHero;
use App\Support\DestinationFilter;
use App\Support\LandingFilter;
use App\Support\LandingProfile;
use App\Support\LandingSlug;
use App\Support\LandingStats;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Düz landing sayfaları: /kapadokya-turlari, /kultur-turlari
 *
 * Rakip taramasının en net bulgusu: kategori ve destinasyon sayfaları query
 * string ile değil, tek düz yol segmentiyle sunuluyor. İncelenen 9 sitenin
 * hiçbiri "/turlar?kategori=x" kalıbını kullanmıyor.
 *
 * Envanteri biten sayfa KAPATILMAZ. Gruppal'ın /kayak-turlari sayfası Ağustos'ta
 * 0 ürün listeliyor ama H1, metin, SSS ve breadcrumb'ıyla canlı duruyor; 404'e
 * düşürmek o adresin biriktirdiği değeri çöpe atardı.
 *
 * Sayfa üç katman: hero (admin'in kategori banner'ı — App\Support\CategoryHero),
 * sayfanın kendi envanterinden türeyen filtreler (App\Support\LandingFilter) ve
 * veri-tabanlı SEO blokları (App\Support\LandingStats). İstatistik ve facet'ler
 * her zaman FİLTRELENMEMİŞ kümeden gelir: kullanıcı daraltınca sayfa metni ve
 * seçenekler değişmez, yalnız liste değişir.
 */
class LandingController extends Controller
{
    public function show(Request $request, string $slug)
    {
        $resolved = LandingSlug::resolve($slug);

        abort_if($resolved === null, 404);

        $model = $resolved['model'];

        // Tek kanonik adres: "deniz-tekne" ile gelen istek "deniz-tekne-turlari"ye
        // 301 döner. Aynı içeriğin iki adreste yaşamasını engeller.
        $canonical = $resolved['type'] === 'category'
            ? LandingSlug::forCategory($model)
            : LandingSlug::forDestination($model);

        if ($slug !== $canonical) {
            return redirect('/'.$canonical, 301);
        }

        return $resolved['type'] === 'category'
            ? $this->category($request, $model)
            : $this->destination($request, $model);
    }

    private function category(Request $request, Category $category)
    {
        // Üst kategori seçilince torunları da listelenir — kullanıcı "Kültür
        // Turları"na tıklayınca alt kırılımdaki turları da görmeli.
        $ids = collect([$category->id])->merge($category->children()->pluck('id'));

        $base = Tour::query()
            ->active()
            ->whereHas('agency', fn ($q) => $q->active())
            ->whereIn('category_id', $ids);

        return $this->render($request, $category, 'category', $base, [
            // Kategori bir şehir değil; şehir profili yalnız destinasyonlarda.
            'profil' => null,
            'altKategoriler' => $category->children()->active()->orderBy('sort_order')->get(),
        ]);
    }

    private function destination(Request $request, Destination $destination)
    {
        $base = DestinationFilter::apply(
            Tour::query()->active()->whereHas('agency', fn ($q) => $q->active()),
            $destination->name
        );

        return $this->render($request, $destination, 'destination', $base, [
            // Şehir bilgisi mevcut DestinationProfile'dan gelir — 55 şehir için
            // zaten üretilmiş, yeni LLM çağrısı yok.
            'profil' => LandingProfile::forName($destination->name),
            'altKategoriler' => collect(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function render(Request $request, Category|Destination $model, string $tur, Builder $base, array $extra)
    {
        $filtre = LandingFilter::parse($request->query());

        $sorgu = LandingFilter::apply((clone $base)->with('agency'), $filtre);
        $tours = LandingFilter::sort($sorgu, $filtre['sirala'])
            ->paginate(24)
            ->withQueryString();

        // Facet'ler ve istatistikler SAYFALANMAMIŞ + FİLTRELENMEMİŞ kümeden:
        // 2. sayfada ve daraltılmış listede de aynı seçenekler, aynı rakamlar.
        $facets = LandingFilter::facets($base);
        $stats = LandingStats::build($base);

        $hero = $model instanceof Category
            ? CategoryHero::forCategory($model, $facets['ilkGorsel'])
            : CategoryHero::forDestination($model, $facets['ilkGorsel']);

        return view('landing.show', $extra + [
            'model' => $model,
            'tur' => $tur,
            'tours' => $tours,
            'stats' => $stats,
            'facets' => $facets,
            'filtre' => $filtre,
            'filtreAktif' => LandingFilter::isActive($filtre),
            'filtreSayisi' => LandingFilter::count($filtre),
            'hero' => $hero,
            'toplamTur' => $stats['turSayisi'] ?? 0,
            'breadcrumb' => [
                ['name' => 'Turlar', 'url' => route('tours.index')],
                ['name' => LandingSlug::heading($model)],
            ],
        ]);
    }
}
