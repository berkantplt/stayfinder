<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\Category;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\TourView;
use App\Services\TourImage\TourImageService;
use App\Services\Tours\TourPayloadBuilder;
use App\Support\TurkishCities;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class TourController extends Controller
{
    /**
     * Form yükünü kayda çeviren mantık (tarih/fiyat matrisi, galeri, durak şehri,
     * program, URL temizliği) TourPayloadBuilder'da — toplu içe aktarma komutu
     * aynı sınıfı kullanır, panelle birebir sonuç üretir.
     */
    public function __construct(
        private readonly TourImageService $images,
        private readonly TourPayloadBuilder $payload,
    ) {}

    /**
     * Galeri için geçici görsel yükleme (AJAX): dosyayı kaydeder, /storage yolunu
     * döner. Form bu yolu gallery[] gizli alanına ekler; kayıtta sıralı işlenir.
     */
    public function uploadImage(Request $request)
    {
        // 'file' değil 'image' + mimes: ilk kapı burası olmalı.
        // Uzantı artık servis tarafında içerik tipinden türetiliyor
        // (TourImageService::storeUpload), bu kural ikinci savunma hattı.
        $request->validate([
            'image' => 'required|image|mimes:jpg,jpeg,png,webp,avif,gif|max:20480',
        ]);

        try {
            $path = $this->images->storeUpload($request->file('image'));

            return response()->json(['ok' => true, 'path' => $path]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function index()
    {
        $agency = auth()->user()->agency;

        $tours = $agency
            ->tours()
            ->with('category')
            ->withCount(['reviews', 'favoritedBy']) // C18: arşivleme onayında etki sayıları
            ->orderByDesc('created_at')
            ->paginate(15);

        // C9: yayın durumu — "Aktif" ayarı tek başına sitede göründüğü anlamına
        // gelmez (abonelik bitmiş / kategori pasif / acenta pasif). Abonelik
        // id'leri tek sorguda alınır; tur başına ek sorgu atılmaz.
        $subscribedCategoryIds = $this->aboneKategoriIdleri($agency);
        $tours->getCollection()->each(fn (Tour $tour) => $this->listeIcinHazirla($tour, $agency, $subscribedCategoryIds));

        $canCreateTours = $agency->legacy_category_access || count($agency->accessibleCategoryIds()) > 0;

        // A10: arşiv (soft-deleted) — geri alınabilir, 30 gün sonra kalıcı silinir
        $archivedTours = $agency->tours()->onlyTrashed()->orderByDesc('deleted_at')->get();

        return view('agency.tours.index', compact('tours', 'canCreateTours', 'archivedTours'));
    }

    public function create()
    {
        $agency = auth()->user()->agency;
        $categories = $this->resolveAgencyCategoryTree($agency);
        $currencyOptions = Tour::supportedCurrencies();
        $canCreateTours = $agency->legacy_category_access || $categories->isNotEmpty();
        $categorySlotUsage = $this->categorySlotUsageFor($agency);

        return view('agency.tours.create', compact('categories', 'currencyOptions', 'canCreateTours', 'categorySlotUsage'));
    }

    public function show(Tour $tour)
    {
        $this->authorize($tour);
        $tour->load('agency', 'dates');

        $reviews = $tour->reviews()->with('user')->latest()->get();
        $avgRating = $reviews->avg('rating') ? round($reviews->avg('rating'), 1) : null;
        $clickCount = TourClick::where('tour_id', $tour->id)->count();
        $viewCount = TourView::where('tour_id', $tour->id)->count();
        $favoriteCount = $tour->favoritedBy()->count(); // C18: arşivleme onayı için

        return view('agency.tours.show', compact('tour', 'reviews', 'avgRating', 'clickCount', 'viewCount', 'favoriteCount'));
    }

    public function store(Request $request)
    {
        $agency = auth()->user()->agency;

        $validated = $request->validate([
            // C19: üst kategoriler satılmaz ve tur alamaz; pasif alt kategori de seçilemez
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNotNull('parent_id')->where('is_active', true)],
            'title' => 'required|string|max:255',
            'destination' => 'required|string|max:100',
            'departure_city' => ['required', 'string', Rule::in(TurkishCities::all())],
            'stop_cities' => 'nullable|array',
            'stop_cities.*' => ['string', Rule::in(TurkishCities::all())],
            'description' => 'nullable|string',
            'duration_days' => 'required|integer|min:1',
            'duration_nights' => 'nullable|integer|min:0|max:255',
            'transport_type' => ['nullable', Rule::in(array_keys(Tour::TRANSPORT_TYPES))],
            'currency' => ['required', 'string', Rule::in(array_keys(Tour::supportedCurrencies()))],
            'included' => 'nullable|string',
            'excluded' => 'nullable|string',
            'gallery' => 'nullable|array',
            'gallery.*' => 'string',
            'tour_url' => 'nullable|url',
            'departure_points' => 'nullable|string',
            'itinerary' => 'nullable|array',
            'itinerary.*.title' => 'nullable|string|max:255',
            'itinerary.*.content' => 'nullable|string',
            'hotel_info' => 'nullable|string',
            'extras' => 'nullable|string',
            'cancellation_policy' => 'nullable|string',
            'guide_info' => 'nullable|string',
            'frequency' => 'nullable|string|max:255',
            'requires_visa' => 'required|in:0,1,kapida',
        ], [
            'category_id.required' => 'Kategori seçin.',
            'category_id.exists' => 'Yalnızca aktif alt kategoriler seçilebilir; üst kategoriler tur alamaz.',
            'departure_city.required' => 'Kalkış şehrini seçin.',
            'departure_city.in' => 'Geçerli bir kalkış şehri seçin.',
            'requires_visa.required' => 'Vize durumunu işaretleyin: Vizeli, Kapıda vize veya Vizesiz.',
            'requires_visa.in' => 'Vize durumunu işaretleyin: Vizeli, Kapıda vize veya Vizesiz.',
        ]);
        $this->ensureAgencyHasCategoryAccess($agency, (int) $validated['category_id']);
        $this->ensureAgencyHasTourSlot($agency, (int) $validated['category_id']);

        $pricingOptions = $this->payload->pricingOptionsWithDerivedPrices((array) $request->input('pricing_options', []));
        $dates = $this->payload->prepareValidatedDatePrices(
            $pricingOptions,
            (array) $request->input('departure_dates', []),
            $request->input('price'),
            (int) $validated['duration_days']
        );

        unset($validated['gallery']);
        $gallery = $this->payload->processGallery((array) $request->input('gallery', []));
        $validated['images'] = $gallery ?: null;
        $validated['image'] = $gallery[0] ?? null;

        $validated['stop_cities'] = $this->payload->normalizeStopCities($validated['stop_cities'] ?? null, $validated['departure_city']);
        // Yurt içi/dışı bayrağı destinasyondan otomatik türetilir — AI aramanın
        // "yurt dışı" filtresi bu bayrağa dayanır; İspanya turu yurt içi görünmesin
        $classified = app(\App\Services\DestinationOriginResolver::class)->isInternational($validated['destination']);
        if ($classified !== null) {
            $validated['is_international'] = $classified;
        }

        // Vize ÜÇ SEÇENEK, iki kolon (TourPayloadBuilder::visaFlags). 2026-09-01'den
        // beri alan ZORUNLU: "belirtilmemiş" formdan kaydedilemiyor, doğrulama
        // reddediyor; null yalnızca ESKİ kayıtlarda ve admin toplu ekranında kalabilir.
        [$validated['requires_visa'], $validated['visa_on_arrival']] = $this->payload->visaFlags($request->input('requires_visa'));
        $validated['agency_id'] = $agency->id;
        $validated['price'] = $this->payload->resolveBasePrice($dates);
        $primaryDate = $this->payload->resolvePrimaryDate($dates);
        $validated['departure_date'] = $primaryDate['departure_date'];
        $validated['return_date'] = $primaryDate['return_date'];
        $validated['tour_url'] = $this->payload->cleanTourUrl($validated['tour_url'] ?? null);
        $validated['itinerary'] = $this->payload->normalizeItinerary($request->input('itinerary'));
        $validated['pricing_blocks'] = $this->payload->buildPricingBlocks($pricingOptions);

        $tour = Tour::create($validated);
        $this->payload->syncTourDates($tour, $dates);

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur başarıyla eklendi.');
    }

    public function edit(Tour $tour)
    {
        $this->authorize($tour);
        $agency = auth()->user()->agency;
        $categories = $this->resolveAgencyCategoryTree($agency);
        $currencyOptions = Tour::supportedCurrencies();
        // C10: kategorisi hiç olmayan (eski/seeder) tur ile yetkisi bitmiş tur
        // farklı teşhis ister — hasCategoryAccess(null) her zaman false döner,
        // "yetkiniz kalmamış" demek yanıltıcı olurdu.
        $categoryMissing = $tour->category_id === null;
        // C19: eski kayıt üst kategoriye bağlıysa form alt kategori seçtirmeden kaydetmez
        $categoryIsParent = ! $categoryMissing && $tour->category !== null && $tour->category->parent_id === null;
        $currentCategoryAccessible = $categoryMissing || $agency->hasCategoryAccess($tour->category_id);
        $categorySlotUsage = $this->categorySlotUsageFor($agency);

        return view('agency.tours.edit', compact('tour', 'categories', 'currencyOptions', 'currentCategoryAccessible', 'categoryMissing', 'categoryIsParent', 'categorySlotUsage'));
    }

    public function update(Request $request, Tour $tour)
    {
        $this->authorize($tour);
        $agency = auth()->user()->agency;

        $validated = $request->validate([
            // C19: üst kategoriler satılmaz ve tur alamaz; pasif alt kategori de seçilemez
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNotNull('parent_id')->where('is_active', true)],
            'title' => 'required|string|max:255',
            'destination' => 'required|string|max:100',
            'departure_city' => ['required', 'string', Rule::in(TurkishCities::all())],
            'stop_cities' => 'nullable|array',
            'stop_cities.*' => ['string', Rule::in(TurkishCities::all())],
            'description' => 'nullable|string',
            'duration_days' => 'required|integer|min:1',
            'duration_nights' => 'nullable|integer|min:0|max:255',
            'transport_type' => ['nullable', Rule::in(array_keys(Tour::TRANSPORT_TYPES))],
            'currency' => ['required', 'string', Rule::in(array_keys(Tour::supportedCurrencies()))],
            'included' => 'nullable|string',
            'excluded' => 'nullable|string',
            'gallery' => 'nullable|array',
            'gallery.*' => 'string',
            'tour_url' => 'nullable|url',
            'is_active' => 'boolean',
            'departure_points' => 'nullable|string',
            'itinerary' => 'nullable|array',
            'itinerary.*.title' => 'nullable|string|max:255',
            'itinerary.*.content' => 'nullable|string',
            'hotel_info' => 'nullable|string',
            'extras' => 'nullable|string',
            'cancellation_policy' => 'nullable|string',
            'guide_info' => 'nullable|string',
            'frequency' => 'nullable|string|max:255',
            'requires_visa' => 'required|in:0,1,kapida',
        ], [
            'category_id.required' => 'Kategori seçin.',
            'category_id.exists' => 'Yalnızca aktif alt kategoriler seçilebilir; üst kategoriler tur alamaz.',
            'departure_city.required' => 'Kalkış şehrini seçin.',
            'departure_city.in' => 'Geçerli bir kalkış şehri seçin.',
            'requires_visa.required' => 'Vize durumunu işaretleyin: Vizeli, Kapıda vize veya Vizesiz.',
            'requires_visa.in' => 'Vize durumunu işaretleyin: Vizeli, Kapıda vize veya Vizesiz.',
        ]);
        $this->ensureAgencyHasCategoryAccess($agency, (int) $validated['category_id']);

        // Limit yalnızca turu BAŞKA kategoriye taşırken uygulanır — mevcut
        // kategorisinde kalan tur, acenta limit üstünde olsa bile düzenlenebilir
        // (grandfathering: eski turlar silinmez/kilitlenmez).
        if ((int) $validated['category_id'] !== (int) $tour->category_id) {
            $this->ensureAgencyHasTourSlot($agency, (int) $validated['category_id'], $tour);
        }
        $validated['stop_cities'] = $this->payload->normalizeStopCities($validated['stop_cities'] ?? null, $validated['departure_city']);
        // Yurt içi/dışı bayrağı destinasyondan otomatik türetilir (store ile aynı kural)
        $classified = app(\App\Services\DestinationOriginResolver::class)->isInternational($validated['destination']);
        if ($classified !== null) {
            $validated['is_international'] = $classified;
        }

        // Vize ÜÇ SEÇENEK, iki kolon (TourPayloadBuilder::visaFlags). 2026-09-01'den
        // beri alan ZORUNLU: "belirtilmemiş" formdan kaydedilemiyor, doğrulama
        // reddediyor; null yalnızca ESKİ kayıtlarda ve admin toplu ekranında kalabilir.
        [$validated['requires_visa'], $validated['visa_on_arrival']] = $this->payload->visaFlags($request->input('requires_visa'));

        $pricingOptions = $this->payload->pricingOptionsWithDerivedPrices((array) $request->input('pricing_options', []));
        $dates = $this->payload->prepareValidatedDatePrices(
            $pricingOptions,
            (array) $request->input('departure_dates', []),
            $request->input('price'),
            (int) $validated['duration_days']
        );

        unset($validated['gallery']);
        $oldImages = $tour->images ?: ($tour->image ? [$tour->image] : []);
        $gallery = $this->payload->processGallery((array) $request->input('gallery', []));
        $validated['images'] = $gallery ?: null;
        $validated['image'] = $gallery[0] ?? null;
        // Galeriden çıkarılan eski görselleri diskten sil
        foreach (array_diff($oldImages, $gallery) as $removed) {
            $this->images->delete($removed);
        }

        $validated['price'] = $this->payload->resolveBasePrice($dates);
        $primaryDate = $this->payload->resolvePrimaryDate($dates);
        $validated['departure_date'] = $primaryDate['departure_date'];
        $validated['return_date'] = $primaryDate['return_date'];
        $validated['tour_url'] = $this->payload->cleanTourUrl($validated['tour_url'] ?? null);
        $validated['itinerary'] = $this->payload->normalizeItinerary($request->input('itinerary'));
        $validated['pricing_blocks'] = $this->payload->buildPricingBlocks($pricingOptions);
        $tour->update($validated);
        $this->payload->syncTourDates($tour, $dates);

        return redirect()->route('agency.tours.index')
            ->with('success', 'Tur güncellendi.');
    }

    /**
     * A10: soft delete — arşive gider, 30 gün geri alınabilir. Liste sayfası fetch ile
     * JSON ister: arşiv satırının HTML'i + güncel arşiv sayısı döner, sayfa yenilenmez.
     * JS kapalıysa klasik yönlendirme korunur.
     */
    public function destroy(Request $request, Tour $tour)
    {
        $this->authorize($tour);
        $tour->delete();

        $mesaj = 'Tur arşive taşındı. 30 gün içinde "Arşiv" bölümünden geri alabilirsiniz.';

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => $mesaj,
                'arsiv_html' => view('agency.tours._arsiv_row', ['arsiv' => $tour])->render(),
                'arsiv_sayisi' => $this->arsivSayisi($tour->agency),
            ]);
        }

        return redirect()->route('agency.tours.index')->with('success', $mesaj);
    }

    /**
     * A10 — Arşivdeki turu geri alır (rota withTrashed ile bağlar). JSON istenirse
     * liste satırının HTML'i (index ile aynı parça, aynı sayaçlar) + arşiv sayısı döner.
     */
    public function restore(Request $request, Tour $tour)
    {
        $this->authorize($tour);

        if ($tour->trashed()) {
            $tour->restore();
        }

        $mesaj = 'Tur geri alındı.';

        if ($request->expectsJson()) {
            $agency = $tour->agency;
            $tour->load('category')->loadCount(['reviews', 'favoritedBy']);
            $this->listeIcinHazirla($tour, $agency, $this->aboneKategoriIdleri($agency));

            return response()->json([
                'ok' => true,
                'message' => $mesaj,
                'satir_html' => view('agency.tours._row', ['tour' => $tour])->render(),
                'arsiv_sayisi' => $this->arsivSayisi($agency),
            ]);
        }

        return redirect()->route('agency.tours.index')->with('success', $mesaj);
    }

    private function authorize(Tour $tour): void
    {
        if ($tour->agency_id !== auth()->user()->agency_id) {
            abort(403);
        }
    }

    /** C9: abonelik kategori id'leri tek sorguda; legacy erişimde boş liste (kontrol atlanır). */
    private function aboneKategoriIdleri(Agency $agency): array
    {
        return $agency->legacy_category_access
            ? []
            : $agency->activeCategorySubscriptions()->pluck('category_id')->map(fn ($id) => (int) $id)->all();
    }

    /** Liste satırının ihtiyaç duyduğu türetilmiş alanlar (acenta ilişkisi + yayın durumu sebebi). */
    private function listeIcinHazirla(Tour $tour, Agency $agency, array $subscribedCategoryIds): void
    {
        $tour->setRelation('agency', $agency);
        $tour->visibility_issue = $tour->publicVisibilityIssue($subscribedCategoryIds);
    }

    private function arsivSayisi(Agency $agency): int
    {
        return $agency->tours()->onlyTrashed()->count();
    }

    private function ensureAgencyHasCategoryAccess(Agency $agency, int $categoryId): void
    {
        if ($agency->hasCategoryAccess($categoryId)) {
            return;
        }

        throw ValidationException::withMessages([
            'category_id' => 'Bu kategoride tur yayınlamak için önce Kategori Yetkileri sayfasından aylık yetki satın almalısınız.',
        ]);
    }

    /**
     * Kategori aboneliği CategoryLicensing::BASE_TOUR_ALLOWANCE tur hakkı
     * içerir; fazlası için KYM'den ekstra hak satın alınmalı. Limit yalnızca
     * yeni ekleme / kategoriye taşımada uygulanır (mevcut turlar korunur).
     */
    private function ensureAgencyHasTourSlot(Agency $agency, int $categoryId, ?Tour $ignore = null): void
    {
        $limit = $agency->categoryTourLimit($categoryId);

        if ($limit === null) {
            return; // legacy acenta veya slot şeması yok — limitsiz
        }

        $used = $agency->usedCategoryTourSlots($categoryId, $ignore?->id);

        if ($used < $limit) {
            return;
        }

        throw ValidationException::withMessages([
            'category_id' => sprintf(
                'Bu kategorideki tur ekleme hakkınız doldu (%d/%d). Kategori Yetkileri sayfasından bu kategori için ekstra tur hakkı satın alabilirsiniz.',
                $used,
                $limit
            ),
        ]);
    }

    /**
     * Tur formundaki kategori seçiminde "kullanılan/limit" ipucu için kategori
     * başına hak durumu. Legacy veya slot şeması yoksa boş döner (limitsiz).
     *
     * @return array<int, array{used: int, limit: int}>
     */
    private function categorySlotUsageFor(Agency $agency): array
    {
        if (! \App\Support\CategoryLicensing::slotSchemaReady() || $agency->legacy_category_access) {
            return [];
        }

        $usedByCategory = $agency->tours()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as used_count')
            ->groupBy('category_id')
            ->pluck('used_count', 'category_id');

        return $agency->activeCategorySubscriptions()
            ->pluck('extra_tour_slots', 'category_id')
            ->mapWithKeys(fn ($extraSlots, $categoryId) => [
                (int) $categoryId => [
                    'used' => (int) ($usedByCategory[$categoryId] ?? 0),
                    'limit' => \App\Support\CategoryLicensing::BASE_TOUR_ALLOWANCE + (int) $extraSlots,
                ],
            ])
            ->all();
    }

    private function resolveAgencyCategoryTree(Agency $agency)
    {
        $accessibleCategoryIds = $agency->accessibleCategoryIds();

        if (empty($accessibleCategoryIds)) {
            return collect();
        }

        // C19: üst kategori seçilemez; yalnız erişilebilir aktif ALT kategorisi
        // olan üstler optgroup başlığı olarak döner.
        return Category::active()
            ->parents()
            ->whereHas('children', function ($childQuery) use ($accessibleCategoryIds) {
                $childQuery->active()->whereIn('id', $accessibleCategoryIds);
            })
            ->with([
                'children' => function ($childQuery) use ($accessibleCategoryIds) {
                    $childQuery
                        ->active()
                        ->whereIn('id', $accessibleCategoryIds)
                        ->orderBy('sort_order');
                },
            ])
            ->orderBy('sort_order')
            ->get();
    }

}
