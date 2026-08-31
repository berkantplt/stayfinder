<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\AiSearchLog;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Tour;
use App\Models\TourView;
use App\Support\DestinationFilter;
use App\Support\LandingSlug;
use App\Support\TourComparison;
use App\Support\TurkishCities;
use App\Support\TurkishMonths;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TourController extends Controller
{
    public function index(Request $request)
    {
        // Tek facet ile daraltılmış liste artık kendi düz adresinde yaşıyor
        // (/kultur-turlari, /kapadokya-turlari). Eski query-string adresi 301
        // ile oraya taşınır — hem index edilmiş bağlantılar korunur hem aynı
        // içerik iki adreste yaşamaz.
        //
        // ÇOKLU filtrede yönlendirme YAPILMAZ: "?category=x&min_price=y" bir
        // landing sayfası değil, kullanıcının kurduğu bir filtre; yönlendirmek
        // seçimini silerdi. O kombinasyonlar zaten noindex alıyor (App\Support\Seo).
        if ($redirect = $this->canonicalLandingRedirect($request)) {
            return $redirect;
        }

        $query = Tour::with('agency', 'category', 'dates')
            // Kart üstündeki puan rozeti (tour_grid): tur başına ayrı sorgu yerine tek çekim
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->active()
            ->whereHas('agency', fn ($q) => $q->active());

        // Filters
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        // Kelime sınırlı eşleşme: "Fethiye" seçimi "Ölüdeniz, Fethiye" turunu da
        // bulur, ama "Kas" seçimi "Kastamonu"yu getirmez (bkz. DestinationFilter).
        if ($request->filled('destination')) {
            DestinationFilter::apply($query, $request->destination);
        }

        // Kalkış şehrim: kalkış şehri VEYA duraklarından biri eşleşen turlar.
        // Şehir bilgisi girilmemiş turlar bu filtrede gizlenir (scopeDepartsFrom).
        if ($request->filled('departure_city')) {
            $query->departsFrom($request->departure_city);
        }

        // Filtre değerleri TL — kur-normalize price_try ile karşılaştırılır
        if ($request->filled('min_price')) {
            $query->where('price_try', '>=', $request->min_price);
        }
        if ($request->filled('max_price')) {
            $query->where('price_try', '<=', $request->max_price);
        }

        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }

        if ($request->filled('category')) {
            $cat = Category::where('slug', $request->category)->first();
            if ($cat) {
                $catIds = collect([$cat->id])->merge($cat->children()->pluck('id'));
                $query->whereIn('category_id', $catIds);
            }
        }

        // Yurt içi / yurt dışı: mobil kategori kısayolları bu parametreyi kullanır
        if (in_array($request->yurt, ['ic', 'dis'], true)) {
            $query->where('is_international', $request->yurt === 'dis');
        }

        // Vize: kaynak tours.requires_visa (ana sayfa filtre barıyla AYNI kolon).
        // DestinationProfile şehir listesi KULLANILMAZ — ölçümde Paris ve
        // Yunanistan "vizesiz" çıkıyordu, gerekçe HomeController'da yazılı.
        // İşaretlenmemiş tur hiçbir yöne girmez.
        if (in_array($request->visa, ['vizesiz', 'vizeli'], true)) {
            $query->where('requires_visa', $request->visa === 'vizeli');
        }

        if ($request->filled('min_days')) {
            $query->where('duration_days', '>=', $request->min_days);
        }
        if ($request->filled('max_days')) {
            $query->where('duration_days', '<=', $request->max_days);
        }

        if ($request->filled('date_start')) {
            $query->where(function ($q) use ($request) {
                $q->where('departure_date', '>=', $request->date_start)
                    ->orWhereHas('dates', fn ($dq) => $dq->where('departure_date', '>=', $request->date_start));
            });
        }

        if ($request->filled('date_end')) {
            $query->where(function ($q) use ($request) {
                // If a tour overlaps or starts before this date
                $q->where('departure_date', '<=', $request->date_end)
                    ->orWhereHas('dates', fn ($dq) => $dq->where('departure_date', '<=', $request->date_end));
            });
        }

        // Sort
        $sort = $request->input('sort', 'price_asc');

        if (in_array($sort, ['popular', 'reviews'])) {
            $query->withCount(['clicks', 'views', 'reviews']);
        }

        match ($sort) {
            'price_desc' => $query->orderByDesc('price_try'),
            'date' => $query->orderBy('departure_date'),
            'newest' => $query->orderByDesc('created_at'),
            'popular' => $query->orderByRaw('(clicks_count + views_count) DESC')->orderByDesc('reviews_count'),
            'reviews' => $query->orderByDesc('reviews_count')->orderByDesc('id'),
            default => $query->orderBy('price_try'),
        };

        $tours = $query->paginate(12)->withQueryString();

        // DISTINCT ham dizge DEĞİL: "Kapadokya, Nevşehir" listeden kalkar, yerine
        // "Kapadokya" ve "Nevşehir" ayrı ayrı ve seçilebilir olarak gelir.
        $destinations = DestinationFilter::vocabulary(
            Tour::active()->whereHas('agency', fn ($q) => $q->active())
        );
        $agencies = Agency::active()->orderBy('name')->get();
        $categories = Category::active()->parents()->with('children')->orderBy('sort_order')->get();
        $departureCities = TurkishCities::all();

        $activeCategory = $request->filled('category')
            ? Category::where('slug', $request->category)->first()
            : null;

        $activeDestination = $request->filled('destination') ? (string) $request->destination : null;

        return view('tours.index', compact(
            'tours', 'destinations', 'agencies', 'categories', 'departureCities',
            'activeCategory', 'activeDestination'
        ));
    }

    /**
     * "?category=x" veya "?destination=y" TEK BAŞINA geldiyse, o facet'in düz
     * landing adresine 301. Başka parametre varsa null döner (filtre korunur).
     */
    private function canonicalLandingRedirect(Request $request): ?RedirectResponse
    {
        $params = collect($request->query())
            ->reject(fn ($v, $k) => $k === 'page' || $v === '' || $v === null);

        if ($params->count() !== 1) {
            return null;
        }

        $key = (string) $params->keys()->first();
        $value = (string) $params->first();

        if ($key === 'category') {
            $category = Category::where('slug', $value)->first();

            return $category ? redirect(LandingSlug::urlForCategory($category), 301) : null;
        }

        if ($key === 'destination') {
            $destination = Destination::where('name', $value)->first();

            return $destination ? redirect(LandingSlug::urlForDestination($destination), 301) : null;
        }

        return null;
    }

    public function show(Request $request, Tour $tour)
    {
        abort_unless($tour->isPubliclyVisible() && $tour->agency?->is_active, 404);

        // SEO: tek kanonik adres slug'dır. Eski /turlar/{id} bağlantıları ve ID
        // taşıyan iç bağlantılar 301 ile slug adresine taşınır — index edilmiş
        // eski URL'lerin biriktirdiği değer yeni adrese aktarılır.
        // route('tour') bağlama sonrası modeli döner; URL'de ne yazdığını
        // originalParameter verir.
        $routeValue = (string) $request->route()?->originalParameter('tour', '');
        if (! empty($tour->slug) && $routeValue !== $tour->slug) {
            $target = route('tours.show', $tour);
            if ($query = $request->getQueryString()) {
                $target .= '?'.$query;
            }

            return redirect($target, 301);
        }

        $aiContext = $this->captureAiSelection($request, $tour);
        $tour->load('agency', 'dates', 'category');

        // Record view
        $sessionId = session()->getId();
        $recentKey = 'tour_view_'.$tour->id.'_'.$sessionId;

        if (! cache()->has($recentKey)) {
            TourView::create([
                'tour_id' => $tour->id,
                'session_id' => $sessionId,
                'user_id' => auth()->id(),
                'viewed_at' => now(),
            ]);
            // Yaşam-boyu sayaç: ham tour_views satırları retention ile silinse de toplam
            // korunur. Query builder: model event'leri ve updated_at tetiklenmesin.
            DB::table('tours')->where('id', $tour->id)->increment('views_count');
            cache()->put($recentKey, true, now()->addHour());
        }

        // Track in session for recently viewed display
        $recentlyViewed = session()->get('recently_viewed', []);
        $recentlyViewed = array_filter($recentlyViewed, fn ($id) => $id !== $tour->id);
        array_unshift($recentlyViewed, $tour->id);
        session()->put('recently_viewed', array_slice($recentlyViewed, 0, 6));

        // Get same tour name from other agencies
        $otherOffers = Tour::with('agency')
            ->active()
            ->where('title', $tour->title)
            ->where('id', '!=', $tour->id)
            ->whereHas('agency', fn ($q) => $q->active())
            ->orderBy('price_try')
            ->get();

        // Similar tours — turun şehirlerinden HERHANGİ biri eşleşsin. Tam eşleşme
        // kullanıldığında "Ölüdeniz, Fethiye" gibi turlar hiç benzer tur bulamıyordu;
        // yalnız ilk şehre bakmak da yetmez (Ölüdeniz'de komşu yok, Fethiye'de var).
        $sehirler = DestinationFilter::splitCities((string) $tour->destination)
            ?: [(string) $tour->destination];

        $similarTours = DestinationFilter::apply(
            Tour::with('agency')->active(),
            $sehirler
        )
            ->where('id', '!=', $tour->id)
            ->whereNotIn('id', $otherOffers->pluck('id'))
            ->orderBy('price_try')
            ->limit(4)
            ->get();

        $reviews = $tour->reviews()->with('user')->get();
        $avgRating = $reviews->avg('rating') ? round($reviews->avg('rating'), 1) : null;
        $userReview = auth()->check()
            ? $reviews->firstWhere('user_id', auth()->id())
            : null;

        // Price history (last 30 days)
        $priceHistory = $tour->priceHistories()
            ->where('recorded_at', '>=', now()->subDays(30))
            ->orderBy('recorded_at')
            ->get();

        $priceLabels = $priceHistory->pluck('recorded_at')->map(fn ($d) => $d->format('d M'))->values();
        $priceData = $priceHistory->pluck('price')->values();

        return view('tours.show', compact(
            'tour', 'otherOffers', 'similarTours', 'reviews', 'avgRating', 'userReview',
            'priceLabels', 'priceData', 'aiContext'
        ));
    }

    /**
     * AI aramadan gelen tıklamayı loglar VE tur sayfasında gösterilecek
     * "danışman barı" bağlamını döner — kullanıcı 5 sekme açsa da aradığı
     * kriterlerle bu turun ilişkisini görür, sohbete dönüş kopmaz.
     *
     * @return array{query: string, compatibility: ?float, checks: array<int, string>}|null
     */
    private function captureAiSelection(Request $request, Tour $tour): ?array
    {
        if (! $request->filled('ai_log_id')) {
            return null;
        }

        $log = AiSearchLog::find((int) $request->query('ai_log_id'));
        if (! $log) {
            return null;
        }

        // Basic ownership checks: user and/or session should match.
        if ($log->user_id !== null && $log->user_id !== auth()->id()) {
            return null;
        }

        $sessionId = $request->session()->getId();
        if ($log->session_id && $log->session_id !== $sessionId) {
            return null;
        }

        $resultIds = is_array($log->result_tour_ids)
            ? array_map('intval', $log->result_tour_ids)
            : [];
        if (! empty($resultIds) && ! in_array((int) $tour->id, $resultIds, true)) {
            return null;
        }

        $aiContext = $this->buildAiContextBar($log, $tour);

        if ($log->selected_tour_id) {
            return $aiContext; // tıklama zaten kayıtlı — barı yine göster
        }

        $rank = (int) $request->query('ai_rank', 0);
        $log->update([
            'selected_tour_id' => $tour->id,
            'selected_rank' => $rank > 0 ? $rank : null,
            'selected_at' => now(),
        ]);

        return $aiContext;
    }

    /**
     * Danışman barı içeriği: aramadaki kriterler + bu turun onlarla ilişkisi
     * (✅/⚠️ işaretleri) — tamamen logdan/DB'den, LLM'siz.
     *
     * @return array{query: string, compatibility: ?float, checks: array<int, string>}
     */
    private function buildAiContextBar(AiSearchLog $log, Tour $tour): array
    {
        $intent = (array) ($log->intent ?? []);
        $scoreEntry = collect($log->result_scores ?? [])->firstWhere('tour_id', $tour->id);
        $monthNames = TurkishMonths::NAMES;

        $checks = [];

        if (! empty($intent['max_budget'])) {
            $inBudget = (float) ($tour->price_try ?? $tour->price) <= (int) $intent['max_budget'];
            $checks[] = ($inBudget ? '✅' : '⚠️').' '.number_format((int) $intent['max_budget'], 0, ',', '.').' TL bütçe'.($inBudget ? '' : ' (üstünde)');
        }
        if (! empty($intent['preferred_month'])) {
            $month = (int) $intent['preferred_month'];
            $tour->loadMissing('dates');
            $hasMonth = $tour->dates->contains(fn ($d) => $d->departure_date
                && (int) $d->departure_date->format('n') === $month
                && $d->departure_date->isFuture())
                || ($tour->departure_date && (int) $tour->departure_date->format('n') === $month);
            $checks[] = ($hasMonth ? '✅' : '⚠️').' '.($monthNames[$month] ?? '').' kalkışı';
        }
        if (($intent['is_international'] ?? null) !== null) {
            $checks[] = '✅ '.($tour->is_international ? 'yurt dışı' : 'yurt içi');
        }
        if (! empty($intent['preferred_min_days']) || ! empty($intent['preferred_max_days'])) {
            $checks[] = '📅 '.$tour->duration_days.' gün';
        }

        return [
            'query' => (string) $log->raw_query,
            'compatibility' => $scoreEntry['compatibility_score'] ?? null,
            'checks' => $checks,
        ];
    }

    /** Görsel tutarlılık sınırı: üçten fazla kolon telefonda okunmuyor. */
    private const KARSILASTIRMA_LIMITI = 3;

    public function compare(Request $request)
    {
        // Sanitizasyon: gelen ids kullanıcı girdisi. Pozitif tamsayıya indirgenir,
        // tekrarlar atılır, limit uygulanır.
        $ids = collect((array) $request->input('ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->take(self::KARSILASTIRMA_LIMITI)
            ->values();

        if ($ids->isEmpty()) {
            return redirect()->route('tours.index')->with('error', 'Karşılaştırılacak tur bulunamadı.');
        }

        $tours = Tour::whereIn('id', $ids)
            ->with(['agency', 'category', 'dates' => function ($q) {
                $q->orderBy('departure_date');
            }])
            ->active()
            ->get()
            // whereIn sonucu DB sırasında döner. Kullanıcı turları bir sırayla
            // seçti; kolonlar o sırayı korumazsa "soldaki hangisiydi" kayboluyor.
            ->sortBy(fn (Tour $tour) => $ids->search($tour->id))
            ->values();

        if ($tours->count() < 2) {
            return redirect()->route('tours.index')->with('error', 'Karşılaştırma yapmak için en az 2 aktif tur seçmelisiniz.');
        }

        $karsilastirma = TourComparison::build($tours);

        return view('tours.compare', compact('tours', 'karsilastirma'));
    }
}
