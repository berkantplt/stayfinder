<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Support\CategoryLicensing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CategoryLicenseController extends Controller
{
    private const CART_SESSION_KEY = 'agency_category_license_cart';

    public function index()
    {
        if (!CategoryLicensing::schemaReady()) {
            return redirect()
                ->route('agency.dashboard')
                ->withErrors('Kategori yetkilendirme altyapısı henüz veritabanına uygulanmamış. Önce migration çalıştırılmalı.');
        }

        $agency = auth()->user()->agency;
        $categories = Category::active()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $licensedCategoryIds = $agency->accessibleCategoryIds();
        $cartCategoryIds = collect(session(self::CART_SESSION_KEY, []))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $availableCategories = $agency->legacy_category_access
            ? collect()
            : $categories
                ->whereNotIn('id', $licensedCategoryIds)
                ->whereNotIn('id', $cartCategoryIds->all())
                ->values();

        $cartItems = $categories
            ->whereIn('id', $cartCategoryIds->all())
            ->values();

        $licensedCategories = $agency->legacy_category_access
            ? $categories->map(function (Category $category) use ($agency) {
                return (object) [
                    'category' => $category,
                    'monthly_price' => $category->monthly_price,
                    'started_at' => $agency->created_at,
                    'expires_at' => null,
                    'source' => 'legacy',
                ];
            })
            : $agency->activeCategorySubscriptions()
                ->with('category.parent')
                ->orderBy('expires_at')
                ->get()
                ->map(function (AgencyCategorySubscription $subscription) {
                    return (object) [
                        'category' => $subscription->category,
                        'monthly_price' => $subscription->monthly_price,
                        'started_at' => $subscription->started_at,
                        'expires_at' => $subscription->expires_at,
                        'source' => 'purchase',
                    ];
                });

        $recentOrders = $agency->categoryOrders()
            ->with('items.category')
            ->orderByDesc('purchased_at')
            ->limit(8)
            ->get();

        $cartTotal = $cartItems->sum(fn(Category $category) => (float) $category->monthly_price);

        return view('agency.category-licenses.index', compact(
            'agency',
            'availableCategories',
            'cartItems',
            'cartTotal',
            'licensedCategories',
            'recentOrders'
        ));
    }

    public function addToCart(Request $request)
    {
        if (!CategoryLicensing::schemaReady()) {
            return back()->withErrors('Kategori yetkilendirme altyapısı henüz veritabanına uygulanmamış.');
        }

        $agency = auth()->user()->agency;

        if ($agency->legacy_category_access) {
            return back()->withErrors('Bu acenta için geçiş erişimi tanımlı. Tüm kategoriler zaten açık.');
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $category = Category::active()->findOrFail((int) $validated['category_id']);

        if ($agency->hasCategoryAccess($category)) {
            return back()->withErrors($category->name . ' kategorisi zaten aktif yetkileriniz arasında.');
        }

        $cartCategoryIds = collect(session(self::CART_SESSION_KEY, []))
            ->map(fn($id) => (int) $id)
            ->push($category->id)
            ->unique()
            ->values()
            ->all();

        session([self::CART_SESSION_KEY => $cartCategoryIds]);

        return back()->with('success', $category->name . ' sepetinize eklendi.');
    }

    public function removeFromCart(Category $category)
    {
        if (!CategoryLicensing::schemaReady()) {
            return back()->withErrors('Kategori yetkilendirme altyapısı henüz veritabanına uygulanmamış.');
        }

        $cartCategoryIds = collect(session(self::CART_SESSION_KEY, []))
            ->map(fn($id) => (int) $id)
            ->reject(fn($id) => $id === $category->id)
            ->values()
            ->all();

        session([self::CART_SESSION_KEY => $cartCategoryIds]);

        return back()->with('success', $category->name . ' sepetten çıkarıldı.');
    }

    public function checkout()
    {
        if (!CategoryLicensing::schemaReady()) {
            return back()->withErrors('Kategori yetkilendirme altyapısı henüz veritabanına uygulanmamış.');
        }

        $agency = auth()->user()->agency;

        if ($agency->legacy_category_access) {
            return back()->withErrors('Bu acenta için geçiş erişimi tanımlı. Satın alma işlemi gerekmiyor.');
        }

        $cartCategoryIds = collect(session(self::CART_SESSION_KEY, []))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($cartCategoryIds->isEmpty()) {
            return back()->withErrors('Satın alma yapmadan önce sepetinize en az 1 kategori ekleyin.');
        }

        $categories = Category::active()
            ->whereIn('id', $cartCategoryIds->all())
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            return back()->withErrors('Sepetinizde satın alınabilir kategori bulunamadı.');
        }

        $categories = $categories
            ->reject(fn(Category $category) => $agency->hasCategoryAccess($category))
            ->values();

        if ($categories->isEmpty()) {
            session()->forget(self::CART_SESSION_KEY);

            return back()->withErrors('Sepetteki kategoriler zaten aktif yetkileriniz arasında.');
        }

        DB::transaction(function () use ($agency, $categories) {
            $subtotal = $categories->sum(fn(Category $category) => (float) $category->monthly_price);

            $order = AgencyCategoryOrder::create([
                'agency_id' => $agency->id,
                'order_number' => $this->generateOrderNumber(),
                'billing_cycle' => 'monthly',
                'subtotal' => $subtotal,
                'currency' => 'TRY',
                'status' => AgencyCategoryOrder::STATUS_PAID,
                'purchased_at' => now(),
            ]);

            foreach ($categories as $category) {
                AgencyCategoryOrderItem::create([
                    'order_id' => $order->id,
                    'category_id' => $category->id,
                    'category_name' => $category->name,
                    'unit_price' => $category->monthly_price,
                    'billing_cycle' => 'monthly',
                ]);

                AgencyCategorySubscription::updateOrCreate(
                    [
                        'agency_id' => $agency->id,
                        'category_id' => $category->id,
                    ],
                    [
                        'last_order_id' => $order->id,
                        'monthly_price' => $category->monthly_price,
                        'status' => AgencyCategorySubscription::STATUS_ACTIVE,
                        'started_at' => today(),
                        'expires_at' => today()->addMonth(),
                    ]
                );
            }
        });

        session()->forget(self::CART_SESSION_KEY);

        return redirect()
            ->route('agency.category-licenses.index')
            ->with('success', 'Kategori yetkileri aktif edildi. Aylık faturalama dönemi başlatıldı.');
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'KYM-' . now()->format('Ymd') . '-' . strtoupper(substr((string) md5(uniqid((string) mt_rand(), true)), 0, 6));
        } while (AgencyCategoryOrder::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
