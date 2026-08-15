<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Destination;
use App\Models\Tour;
use App\Support\DestinationFilter;
use App\Support\LandingProfile;
use App\Support\LandingSlug;
use App\Support\LandingStats;
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

        $tours = (clone $base)
            ->with('agency')
            ->orderBy('price_try')
            ->paginate(24)
            ->withQueryString();

        return view('landing.show', [
            'model' => $category,
            'tur' => 'category',
            'tours' => $tours,
            // İstatistikler SAYFALANMAMIŞ kümeden: 2. sayfada da aynı rakamlar.
            'stats' => LandingStats::build($base),
            // Kategori bir şehir değil; şehir profili yalnız destinasyonlarda.
            'profil' => null,
            'altKategoriler' => $category->children()->active()->orderBy('sort_order')->get(),
            'breadcrumb' => [
                ['name' => 'Turlar', 'url' => route('tours.index')],
                ['name' => LandingSlug::heading($category)],
            ],
        ]);
    }

    private function destination(Request $request, Destination $destination)
    {
        $base = DestinationFilter::apply(
            Tour::query()->active()->whereHas('agency', fn ($q) => $q->active()),
            $destination->name
        );

        $tours = (clone $base)
            ->with('agency')
            ->orderBy('price_try')
            ->paginate(24)
            ->withQueryString();

        return view('landing.show', [
            'model' => $destination,
            'tur' => 'destination',
            'tours' => $tours,
            'stats' => LandingStats::build($base),
            // Şehir bilgisi mevcut DestinationProfile'dan gelir — 55 şehir için
            // zaten üretilmiş, yeni LLM çağrısı yok.
            'profil' => LandingProfile::forName($destination->name),
            'altKategoriler' => collect(),
            'breadcrumb' => [
                ['name' => 'Turlar', 'url' => route('tours.index')],
                ['name' => LandingSlug::heading($destination)],
            ],
        ]);
    }
}
