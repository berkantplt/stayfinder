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

class CategoryLicenseController extends Controller
{
    public function index()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        $data = $this->buildSharedData();

        return view('admin.category-licenses.index', [
            'stats' => $data['stats'],
            'topDemandCategories' => $data['topDemandCategories'],
            'trendData' => $data['trendData'],
        ]);
    }

    public function pricing()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        $data = $this->buildSharedData();

        return view('admin.category-licenses.pricing', [
            // Fiyat yalnızca alt kategorilerde belirlenir; üst kategoriler fiyatsız gruptur
            'categories' => $data['categories']->whereNotNull('parent_id')->values(),
            'stats' => $data['stats'],
        ]);
    }

    public function access()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        $data = $this->buildSharedData();

        return view('admin.category-licenses.access', [
            'activeSubscriptions' => $data['activeSubscriptions'],
            'legacyAgencies' => $data['legacyAgencies'],
            'stats' => $data['stats'],
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

        $validated = $request->validate([
            'monthly_price' => 'required|numeric|min:0',
        ]);

        $category->update([
            'monthly_price' => round((float) $validated['monthly_price'], 2),
        ]);

        return redirect()
            ->route('admin.category-licenses.pricing')
            ->with('success', $category->name.' için aylık kategori ücreti güncellendi.');
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

    private function buildSharedData(): array
    {
        $activeSubscriptions = AgencyCategorySubscription::active()
            ->with(['agency', 'category.parent'])
            ->orderBy('expires_at')
            ->get();

        $legacyCategoryUsageByAgency = Tour::query()
            ->selectRaw('agency_id, COUNT(DISTINCT category_id) as used_categories_count')
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->whereHas('agency', fn ($query) => $query->where('legacy_category_access', true))
            ->groupBy('agency_id')
            ->get()
            ->keyBy('agency_id');

        $legacyAgencies = Agency::where('legacy_category_access', true)
            ->withCount([
                'tours as active_tours_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('name')
            ->get()
            ->map(function (Agency $agency) use ($legacyCategoryUsageByAgency) {
                $agency->used_categories_count = (int) data_get(
                    $legacyCategoryUsageByAgency->get($agency->id),
                    'used_categories_count',
                    0
                );

                return $agency;
            });

        $legacyDemandByCategory = Tour::query()
            ->selectRaw('category_id, COUNT(DISTINCT agency_id) as agency_count, COUNT(*) as active_tours_count')
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->whereHas('agency', fn ($query) => $query->where('legacy_category_access', true))
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $orderTotalsByCategory = AgencyCategoryOrderItem::query()
            ->selectRaw('category_id, COUNT(*) as order_items_count, COALESCE(SUM(unit_price), 0) as gross_total')
            ->whereNotNull('category_id')
            ->groupBy('category_id')
            ->get()
            ->keyBy('category_id');

        $subscriptionRevenueByCategory = $activeSubscriptions
            ->groupBy('category_id')
            ->map(fn ($subscriptions) => round($subscriptions->sum(fn ($subscription) => (float) $subscription->monthly_price), 2));

        $categories = Category::with('parent')
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
                $actualMonthlyRevenue = (float) ($subscriptionRevenueByCategory->get($category->id) ?? 0);
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

        $topDemandCategories = $categories
            ->sort(function (Category $left, Category $right) {
                return ($right->combined_demand_count <=> $left->combined_demand_count)
                    ?: ($right->portfolio_monthly_value <=> $left->portfolio_monthly_value)
                    ?: strcmp($left->name, $right->name);
            })
            ->take(10)
            ->values();

        $actualMonthlyRevenue = round($activeSubscriptions->sum(fn ($subscription) => (float) $subscription->monthly_price), 2);
        $legacyDemandValue = round($categories->sum('legacy_monthly_value'), 2);

        $stats = [
            'actual_monthly_revenue' => $actualMonthlyRevenue,
            'portfolio_monthly_value' => round($actualMonthlyRevenue + $legacyDemandValue, 2),
            'active_subscriptions' => $activeSubscriptions->count(),
            'combined_demand' => (int) $categories->sum('combined_demand_count'),
            'legacy_agencies' => $legacyAgencies->count(),
            'legacy_active_tours' => (int) $legacyAgencies->sum('active_tours_count'),
            'total_orders' => AgencyCategoryOrder::count(),
            'category_count' => $categories->count(),
            'average_monthly_price' => round((float) $categories->avg(fn (Category $category) => (float) $category->monthly_price), 2),
        ];

        return [
            'activeSubscriptions' => $activeSubscriptions,
            'legacyAgencies' => $legacyAgencies,
            'categories' => $categories,
            'topDemandCategories' => $topDemandCategories,
            'trendData' => $this->buildTrendData(),
            'stats' => $stats,
        ];
    }

    private function buildTrendData(): array
    {
        $periodStart = now()->subMonths(5)->startOfMonth();

        $recentOrders = AgencyCategoryOrder::query()
            ->whereNotNull('purchased_at')
            ->where('purchased_at', '>=', $periodStart)
            ->get(['subtotal', 'purchased_at']);

        $recentOrderItems = AgencyCategoryOrderItem::query()
            ->where('created_at', '>=', $periodStart)
            ->get(['created_at']);

        $ordersByMonth = $recentOrders->groupBy(fn (AgencyCategoryOrder $order) => $order->purchased_at?->format('Y-m'));
        $orderItemsByMonth = $recentOrderItems->groupBy(fn (AgencyCategoryOrderItem $item) => $item->created_at?->format('Y-m'));

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
