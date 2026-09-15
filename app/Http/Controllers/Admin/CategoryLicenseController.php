<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\Tour;
use App\Support\CategoryLicensing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryLicenseController extends Controller
{
    public function index()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        return view('admin.category-licenses.index', [
            'stats' => $this->stats(),
            'topDemandCategories' => $this->topDemandCategories($this->enrichedCategories()),
            'trendData' => $this->trendData(),
        ]);
    }

    public function pricing()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        return view('admin.category-licenses.pricing', [
            // Fiyat yalnızca alt kategorilerde belirlenir; üst kategoriler fiyatsız gruptur
            'categories' => $this->enrichedCategories()->whereNotNull('parent_id')->values(),
            'stats' => $this->stats(),
            'extraSlotReady' => CategoryLicensing::slotSchemaReady(),
        ]);
    }

    public function access()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        return view('admin.category-licenses.access', [
            'activeSubscriptions' => AgencyCategorySubscription::active()
                ->with(['agency', 'category.parent'])
                ->orderBy('expires_at')
                ->get(),
            'legacyAgencies' => $this->legacyAgencies(),
            'stats' => $this->stats(),
        ]);
    }

    public function orders()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        $orders = AgencyCategoryOrder::with(['agency', 'items.category'])
            ->orderByDesc('purchased_at')
            ->paginate(20);

        $allOrders = AgencyCategoryOrder::query()->get(['status', 'subtotal', 'purchased_at', 'paid_at']);
        $lastThirtyDays = now()->subDays(30);

        // CİRO yalnızca ödenmiş (PAID) siparişlerden hesaplanır — pending/failed/
        // cancelled sipariş "gelir" sayılmaz (şişkin ciro raporlanmaz). Tarih
        // filtresi için ödemenin gerçekleştiği an (paid_at) kullanılır.
        $paidOrders = $allOrders->where('status', AgencyCategoryOrder::STATUS_PAID);

        $orderStats = [
            'total_orders' => $allOrders->count(),
            'total_revenue' => round($paidOrders->sum(fn ($order) => (float) $order->subtotal), 2),
            'last_30_days_orders' => $paidOrders->filter(fn ($order) => $order->paid_at && $order->paid_at->greaterThanOrEqualTo($lastThirtyDays))->count(),
            'last_30_days_revenue' => round(
                $paidOrders
                    ->filter(fn ($order) => $order->paid_at && $order->paid_at->greaterThanOrEqualTo($lastThirtyDays))
                    ->sum(fn ($order) => (float) $order->subtotal),
                2
            ),
        ];

        return view('admin.category-licenses.orders', compact('orders', 'orderStats'));
    }

    public function updatePricing(Request $request, Category $category)
    {
        if ($redirect = $this->redirectIfSchemaMissing(true)) {
            return $redirect;
        }

        if ($category->parent_id === null) {
            return redirect()
                ->route('admin.category-licenses.pricing')
                ->withErrors('Üst kategoriler fiyatlandırılmaz. Fiyat yalnızca alt kategorilerde belirlenir.');
        }

        $slotReady = CategoryLicensing::slotSchemaReady();

        $rules = ['monthly_price' => 'required|numeric|min:0'];

        if ($slotReady) {
            $rules['extra_tour_price'] = 'required|numeric|min:0';
        }

        $validated = $request->validate($rules);

        $category->update([
            'monthly_price' => round((float) $validated['monthly_price'], 2),
        ] + ($slotReady ? [
            'extra_tour_price' => round((float) $validated['extra_tour_price'], 2),
        ] : []));

        return redirect()
            ->route('admin.category-licenses.pricing')
            ->with('success', $category->name.' için '.($slotReady ? 'aylık ücret ve ekstra tur fiyatı' : 'aylık kategori ücreti').' güncellendi.');
    }

    private function redirectIfSchemaMissing(bool $back = false)
    {
        if (! CategoryLicensing::schemaReady()) {
            return $back
                ? back()->withErrors('Kategori yetkilendirme altyapısı henüz veritabanına uygulanmamış.')
                : redirect()
                    ->route('admin.dashboard')
                    ->withErrors('Kategori yetkilendirme altyapısı henüz veritabanına uygulanmamış. Önce migration çalıştırılmalı.');
        }

        return null;
    }

    /**
     * B8 — Eski buildSharedData() üç ekranda (genel bakış / tarife / erişim) koşulsuz
     * tam çalışıyordu: tüm aktif abonelikler + tüm legacy acentalar + tüm kategoriler
     * + 6 aylık siparişler belleğe; tarife sayfası bunun yalnız kategori listesini,
     * erişim sayfası yalnız abonelik/legacy listesini kullanıyordu. Artık her ekran
     * yalnız ihtiyacını çağırır: özet sayılar SQL aggregate, trend 1 saat önbellekte
     * (ödeme kesinleşince CategoryOrderFinalizer anahtarı siler).
     */
    private function stats(): array
    {
        $actualMonthlyRevenue = round((float) AgencyCategorySubscription::active()->sum('monthly_price'), 2);
        $activeCount = AgencyCategorySubscription::active()->count();

        // Legacy talep: kategori başına kaç geçiş erişimli acenta aktif tur yayınlıyor;
        // hipotetik aylık değer = acenta sayısı × kategori tarifesi (portföy değeri).
        $legacyDemand = $this->legacyDemandByCategory();
        $prices = $legacyDemand->isEmpty()
            ? collect()
            : Category::whereIn('id', $legacyDemand->keys())->pluck('monthly_price', 'id');
        $legacyDemandValue = 0.0;
        foreach ($legacyDemand as $categoryId => $row) {
            $legacyDemandValue += (int) $row->agency_count * (float) ($prices[$categoryId] ?? 0);
        }
        $legacyDemandValue = round($legacyDemandValue, 2);

        return [
            'actual_monthly_revenue' => $actualMonthlyRevenue,
            'portfolio_monthly_value' => round($actualMonthlyRevenue + $legacyDemandValue, 2),
            'active_subscriptions' => $activeCount,
            'combined_demand' => $activeCount + (int) $legacyDemand->sum('agency_count'),
            'legacy_agencies' => Agency::where('legacy_category_access', true)->count(),
            'legacy_active_tours' => Tour::where('is_active', true)
                ->whereHas('agency', fn ($q) => $q->where('legacy_category_access', true))
                ->count(),
            // B7: yalnız ödenmiş — pending/failed/cancelled "sipariş" sayılmaz
            'total_orders' => AgencyCategoryOrder::where('status', AgencyCategoryOrder::STATUS_PAID)->count(),
            'category_count' => Category::count(),
            // Eski hesapla aynı: fiyatsız (null) kategoriler 0 sayılarak ortalama
            'average_monthly_price' => round((float) Category::query()->value(DB::raw('AVG(COALESCE(monthly_price, 0))')), 2),
        ];
    }

    /** category_id => {agency_count, active_tours_count} — geçiş erişimli acentaların aktif turları. */
    private function legacyDemandByCategory()
    {
        return Tour::query()
            ->selectRaw('category_id, COUNT(DISTINCT agency_id) as agency_count, COUNT(*) as active_tours_count')
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->whereHas('agency', fn ($query) => $query->where('legacy_category_access', true))
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');
    }

    /** Tarife + genel bakış talep sıralaması için zenginleştirilmiş kategori listesi (alan adları korunur). */
    private function enrichedCategories()
    {
        $legacyDemandByCategory = $this->legacyDemandByCategory();

        $orderTotalsByCategory = AgencyCategoryOrderItem::query()
            ->selectRaw('category_id, COUNT(*) as order_items_count, COALESCE(SUM(unit_price), 0) as gross_total')
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        // Kategori başına aktif abonelik geliri SQL'de (eskiden tüm satırlar belleğe alınıp gruplanıyordu)
        $subscriptionRevenueByCategory = AgencyCategorySubscription::active()
            ->selectRaw('category_id, COALESCE(SUM(monthly_price), 0) as revenue')
            ->groupBy('category_id')
            ->pluck('revenue', 'category_id');

        return Category::with('parent')
            ->withCount([
                'agencyCategorySubscriptions as active_subscriptions_count' => fn ($query) => $query->active(),
                'tours as active_tours_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) use ($legacyDemandByCategory, $orderTotalsByCategory, $subscriptionRevenueByCategory) {
                $legacyDemand = $legacyDemandByCategory->get($category->id);
                $orderTotals = $orderTotalsByCategory->get($category->id);
                $actualMonthlyRevenue = round((float) ($subscriptionRevenueByCategory[$category->id] ?? 0), 2);
                $legacyDemandCount = (int) data_get($legacyDemand, 'agency_count', 0);

                $category->legacy_demand_count = $legacyDemandCount;
                $category->legacy_active_tours_count = (int) data_get($legacyDemand, 'active_tours_count', 0);
                $category->combined_demand_count = (int) $category->active_subscriptions_count + $legacyDemandCount;
                $category->actual_monthly_revenue = $actualMonthlyRevenue;
                $category->legacy_monthly_value = round($legacyDemandCount * (float) $category->monthly_price, 2);
                $category->portfolio_monthly_value = round($actualMonthlyRevenue + $category->legacy_monthly_value, 2);
                $category->order_items_count = (int) data_get($orderTotals, 'order_items_count', 0);
                $category->gross_order_total = (float) data_get($orderTotals, 'gross_total', 0);

                return $category;
            });
    }

    private function topDemandCategories($categories)
    {
        return $categories
            ->sort(function (Category $left, Category $right) {
                return ($right->combined_demand_count <=> $left->combined_demand_count)
                    ?: ($right->portfolio_monthly_value <=> $left->portfolio_monthly_value)
                    ?: strcmp($left->name, $right->name);
            })
            ->take(10)
            ->values();
    }

    /** Geçiş erişimli acentalar + kullandıkları kategori sayısı (erişim ekranı). */
    private function legacyAgencies()
    {
        $legacyCategoryUsageByAgency = Tour::query()
            ->selectRaw('agency_id, COUNT(DISTINCT category_id) as used_categories_count')
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->whereHas('agency', fn ($query) => $query->where('legacy_category_access', true))
            ->groupBy('agency_id')
            ->get()
            ->keyBy('agency_id');

        return Agency::where('legacy_category_access', true)
            ->withCount([
                'tours as active_tours_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Agency $agency) use ($legacyCategoryUsageByAgency) {
                $agency->used_categories_count = (int) data_get($legacyCategoryUsageByAgency->get($agency->id), 'used_categories_count', 0);

                return $agency;
            });
    }

    /** 6 aylık eğri 1 saat önbellekte; ödeme kesinleşince finalizer anahtarı siler. */
    private function trendData(): array
    {
        return cache()->remember(CategoryLicensing::ADMIN_TREND_CACHE_KEY, 3600, fn () => $this->buildTrendData());
    }

    /**
     * B7 — Son 6 ayın gelir/sipariş/aktivasyon eğrisi. YALNIZ ödenmiş (PAID)
     * siparişler, ödemenin gerçekleştiği ay (paid_at) esasıyla. Eskiden status
     * filtresi yoktu ve purchased_at kullanılıyordu: ödeme ekranını açıp yarıda
     * bırakan acentanın pending siparişi (purchased_at = now()) "gelir"e giriyor,
     * aynı modülün Siparişler ekranıyla çelişiyordu. Aktivasyon sayısı yalnız
     * lisans kalemleri (ekstra tur hakkı bir kategori aktivasyonu değildir).
     */
    private function buildTrendData(): array
    {
        $periodStart = now()->subMonths(5)->startOfMonth();

        $recentOrders = AgencyCategoryOrder::query()
            ->where('status', AgencyCategoryOrder::STATUS_PAID)
            ->whereNotNull('paid_at')
            ->where('paid_at', '>=', $periodStart)
            ->get(['id', 'subtotal', 'paid_at']);

        // item_type sütunu ayrı migration'la geldi (slotSchemaReady); şema yoksa
        // sütunu SEÇME — MySQL "Unknown column" ile 3 admin ekranını düşürürdü.
        // isExtraSlot() null'u lisans sayar, reject her iki durumda güvenli.
        $slotReady = CategoryLicensing::slotSchemaReady();
        $recentOrderItems = AgencyCategoryOrderItem::query()
            ->whereIn('order_id', $recentOrders->pluck('id'))
            ->get($slotReady ? ['order_id', 'item_type'] : ['order_id'])
            ->reject(fn (AgencyCategoryOrderItem $item) => $item->isExtraSlot());

        $paidMonthByOrderId = $recentOrders->mapWithKeys(fn (AgencyCategoryOrder $order) => [$order->id => $order->paid_at->format('Y-m')]);

        $ordersByMonth = $recentOrders->groupBy(fn (AgencyCategoryOrder $order) => $order->paid_at->format('Y-m'));
        $orderItemsByMonth = $recentOrderItems->groupBy(fn (AgencyCategoryOrderItem $item) => $paidMonthByOrderId[$item->order_id]);

        $labels = [];
        $revenueData = [];
        $ordersData = [];
        $activationData = [];

        for ($monthOffset = 5; $monthOffset >= 0; $monthOffset--) {
            $date = now()->subMonths($monthOffset);
            $monthKey = $date->format('Y-m');
            $labels[] = $date->format('m/Y');
            $revenueData[] = round(
                (float) ($ordersByMonth->get($monthKey)?->sum(fn (AgencyCategoryOrder $order) => (float) $order->subtotal) ?? 0),
                2
            );
            $ordersData[] = $ordersByMonth->get($monthKey)?->count() ?? 0;
            $activationData[] = $orderItemsByMonth->get($monthKey)?->count() ?? 0;
        }

        return [
            'labels' => $labels,
            'revenueData' => $revenueData,
            'ordersData' => $ordersData,
            'activationData' => $activationData,
        ];
    }
}
