<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Services\Payment\IyzicoService;
use App\Support\CategoryLicensing;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Iyzipay\Model\Status;
use Throwable;

class CategoryLicenseController extends Controller
{
    private const CART_SESSION_KEY = 'agency_category_license_cart';

    public function __construct(private readonly IyzicoService $iyzico) {}

    public function index()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        $agency = $this->currentAgency();
        $categories = Category::active()
            ->with('parent')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $licensedCategoryIds = $agency->accessibleCategoryIds();
        $cartCategoryIds = $this->cartCategoryIds();

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
        if ($redirect = $this->redirectIfSchemaMissing(true)) {
            return $redirect;
        }

        $agency = $this->currentAgency();

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

        $cartCategoryIds = $this->cartCategoryIds()
            ->push($category->id)
            ->unique()
            ->values()
            ->all();

        session([self::CART_SESSION_KEY => $cartCategoryIds]);

        return back()->with('success', $category->name . ' sepetinize eklendi.');
    }

    public function removeFromCart(Category $category)
    {
        if ($redirect = $this->redirectIfSchemaMissing(true)) {
            return $redirect;
        }

        $cartCategoryIds = $this->cartCategoryIds()
            ->reject(fn($id) => $id === $category->id)
            ->values()
            ->all();

        session([self::CART_SESSION_KEY => $cartCategoryIds]);

        return back()->with('success', $category->name . ' sepetten çıkarıldı.');
    }

    public function checkoutForm(Request $request)
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        $agency = $this->currentAgency();

        if ($agency->legacy_category_access) {
            return redirect()
                ->route('agency.category-licenses.index')
                ->withErrors('Bu acenta için geçiş erişimi tanımlı. Satın alma işlemi gerekmiyor.');
        }

        $cartCategories = $this->resolveCartCategoriesFor($agency);

        if ($cartCategories->isEmpty()) {
            return redirect()
                ->route('agency.category-licenses.index')
                ->withErrors('Ödeme için sepetinize en az 1 kategori eklemelisiniz.');
        }

        $cartTotal = $cartCategories->sum(fn(Category $category) => (float) $category->monthly_price);

        return view('agency.category-licenses.checkout', [
            'agency' => $agency,
            'cartCategories' => $cartCategories,
            'cartTotal' => $cartTotal,
            'iyzicoConfigured' => $this->iyzico->isConfigured(),
        ]);
    }

    public function initiatePayment(Request $request)
    {
        if ($redirect = $this->redirectIfSchemaMissing(true)) {
            return $redirect;
        }

        $agency = $this->currentAgency();

        if ($agency->legacy_category_access) {
            return back()->withErrors('Bu acenta için geçiş erişimi tanımlı. Satın alma işlemi gerekmiyor.');
        }

        $cartCategories = $this->resolveCartCategoriesFor($agency);

        if ($cartCategories->isEmpty()) {
            return redirect()
                ->route('agency.category-licenses.index')
                ->withErrors('Sepetinizde satın alınabilir kategori bulunamadı.');
        }

        if (!$this->iyzico->isConfigured()) {
            return back()->withErrors('Ödeme altyapısı henüz yapılandırılmamış. Lütfen yönetici ile iletişime geçin.');
        }

        $buyer = $this->validateBuyer($request);

        try {
            $order = DB::transaction(function () use ($agency, $cartCategories, $buyer) {
                $subtotal = $cartCategories->sum(fn(Category $category) => (float) $category->monthly_price);

                $order = AgencyCategoryOrder::create([
                    'agency_id' => $agency->id,
                    'order_number' => $this->generateOrderNumber(),
                    'billing_cycle' => 'monthly',
                    'subtotal' => $subtotal,
                    'currency' => 'TRY',
                    'status' => AgencyCategoryOrder::STATUS_PENDING,
                    'payment_provider' => AgencyCategoryOrder::PROVIDER_IYZICO,
                    'buyer_type' => $buyer['type'],
                    'buyer_snapshot' => $buyer,
                    'purchased_at' => now(),
                ]);

                foreach ($cartCategories as $category) {
                    AgencyCategoryOrderItem::create([
                        'order_id' => $order->id,
                        'category_id' => $category->id,
                        'category_name' => $category->name,
                        'unit_price' => $category->monthly_price,
                        'billing_cycle' => 'monthly',
                    ]);
                }

                return $order;
            });

            $basketItems = $cartCategories->map(fn(Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'category' => optional($category->parent)->name ?? 'Kategori Yetkisi',
                'price' => $category->monthly_price,
            ])->values()->all();

            $callbackUrl = route('agency.category-licenses.iyzico.callback', ['order' => $order->id]);

            $response = $this->iyzico->initializeCheckoutForm(
                $order,
                $basketItems,
                $buyer,
                $callbackUrl,
                $request->ip() ?? '127.0.0.1'
            );

            $order->update([
                'provider_token' => $response->getToken(),
            ]);

            return view('agency.category-licenses.payment', [
                'order' => $order,
                'checkoutFormContent' => $response->getCheckoutFormContent(),
                'paymentPageUrl' => $response->getPaymentPageUrl(),
            ]);
        } catch (Throwable $e) {
            Log::error('iyzico initiatePayment failed', [
                'agency_id' => $agency->id,
                'message' => $e->getMessage(),
            ]);

            if (isset($order) && $order instanceof AgencyCategoryOrder) {
                $order->update([
                    'status' => AgencyCategoryOrder::STATUS_FAILED,
                    'failure_reason' => $e->getMessage(),
                ]);
            }

            return back()
                ->withInput()
                ->withErrors('Ödeme başlatılamadı: ' . $e->getMessage());
        }
    }

    public function iyzicoCallback(Request $request, AgencyCategoryOrder $order)
    {
        if (!CategoryLicensing::schemaReady()) {
            abort(503, 'Kategori yetkilendirme altyapısı hazır değil.');
        }

        if ($order->payment_provider !== AgencyCategoryOrder::PROVIDER_IYZICO) {
            abort(404);
        }

        $token = (string) $request->input('token');

        if ($token === '' || $token !== (string) $order->provider_token) {
            abort(403, 'Geçersiz ödeme doğrulaması.');
        }

        if ($order->isPaid()) {
            return redirect()->route('agency.category-licenses.payment.result', $order);
        }

        try {
            $checkout = $this->iyzico->retrieveCheckoutForm($token, (string) $order->id);

            if ($checkout->getStatus() === Status::SUCCESS && $checkout->getPaymentStatus() === 'SUCCESS') {
                $this->finalizePaidOrder($order, $checkout->getPaymentId());
            } else {
                $order->update([
                    'status' => AgencyCategoryOrder::STATUS_FAILED,
                    'failure_reason' => $checkout->getErrorMessage()
                        ?: ('Ödeme tamamlanamadı: ' . ($checkout->getPaymentStatus() ?: 'bilinmeyen durum')),
                ]);
            }
        } catch (Throwable $e) {
            Log::error('iyzico callback retrieve failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            $order->update([
                'status' => AgencyCategoryOrder::STATUS_FAILED,
                'failure_reason' => 'Doğrulama hatası: ' . $e->getMessage(),
            ]);
        }

        return redirect()->route('agency.category-licenses.payment.result', $order);
    }

    public function paymentResult(AgencyCategoryOrder $order)
    {
        if ($order->agency_id !== (int) auth()->user()->agency_id) {
            abort(403);
        }

        $order->load('items.category');

        return view('agency.category-licenses.result', compact('order'));
    }

    private function finalizePaidOrder(AgencyCategoryOrder $order, ?string $paymentId): void
    {
        DB::transaction(function () use ($order, $paymentId) {
            $order->update([
                'status' => AgencyCategoryOrder::STATUS_PAID,
                'paid_at' => now(),
                'provider_payment_id' => $paymentId,
                'failure_reason' => null,
            ]);

            $items = $order->items()->with('category')->get();

            foreach ($items as $item) {
                if (!$item->category_id) {
                    continue;
                }

                AgencyCategorySubscription::updateOrCreate(
                    [
                        'agency_id' => $order->agency_id,
                        'category_id' => $item->category_id,
                    ],
                    [
                        'last_order_id' => $order->id,
                        'monthly_price' => $item->unit_price,
                        'status' => AgencyCategorySubscription::STATUS_ACTIVE,
                        'started_at' => today(),
                        'expires_at' => today()->addMonth(),
                    ]
                );
            }

            session()->forget(self::CART_SESSION_KEY);
        });
    }

    private function validateBuyer(Request $request): array
    {
        $rules = [
            'buyer_type' => 'required|in:' . implode(',', [
                AgencyCategoryOrder::BUYER_INDIVIDUAL,
                AgencyCategoryOrder::BUYER_CORPORATE,
            ]),
            'name' => 'required|string|max:80',
            'surname' => 'required|string|max:80',
            'identity_number' => 'required|string|size:11',
            'email' => 'required|email|max:120',
            'gsm' => 'required|string|max:30',
            'address' => 'required|string|max:500',
            'city' => 'required|string|max:60',
            'country' => 'nullable|string|max:60',
            'zip_code' => 'nullable|string|max:20',
        ];

        if ($request->input('buyer_type') === AgencyCategoryOrder::BUYER_CORPORATE) {
            $rules += [
                'company_title' => 'required|string|max:160',
                'tax_number' => 'required|string|max:20',
                'tax_office' => 'required|string|max:120',
            ];
        }

        $validated = $request->validate($rules);

        return array_filter([
            'type' => $validated['buyer_type'],
            'name' => trim($validated['name']),
            'surname' => trim($validated['surname']),
            'identity_number' => trim($validated['identity_number']),
            'email' => trim($validated['email']),
            'gsm' => trim($validated['gsm']),
            'address' => trim($validated['address']),
            'city' => trim($validated['city']),
            'country' => trim($validated['country'] ?? 'Turkey') ?: 'Turkey',
            'zip_code' => trim($validated['zip_code'] ?? ''),
            'company_title' => isset($validated['company_title']) ? trim($validated['company_title']) : null,
            'tax_number' => isset($validated['tax_number']) ? trim($validated['tax_number']) : null,
            'tax_office' => isset($validated['tax_office']) ? trim($validated['tax_office']) : null,
        ], fn($value) => $value !== null && $value !== '');
    }

    private function currentAgency(): Agency
    {
        $agency = auth()->user()?->agency;

        abort_unless($agency instanceof Agency, 403, 'Acenta kaydı bulunamadı.');

        return $agency;
    }

    private function cartCategoryIds()
    {
        return collect(session(self::CART_SESSION_KEY, []))
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return \Illuminate\Support\Collection<int, Category>
     */
    private function resolveCartCategoriesFor(Agency $agency)
    {
        $cartIds = $this->cartCategoryIds();

        if ($cartIds->isEmpty()) {
            return collect();
        }

        return Category::active()
            ->whereIn('id', $cartIds->all())
            ->orderBy('name')
            ->get()
            ->reject(fn(Category $category) => $agency->hasCategoryAccess($category))
            ->values();
    }

    private function redirectIfSchemaMissing(bool $back = false)
    {
        if (!CategoryLicensing::schemaReady()) {
            return $back
                ? back()->withErrors('Kategori yetkilendirme altyapısı henüz veritabanına uygulanmamış.')
                : redirect()
                    ->route('agency.dashboard')
                    ->withErrors('Kategori yetkilendirme altyapısı henüz veritabanına uygulanmamış. Önce migration çalıştırılmalı.');
        }

        return null;
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'KYM-' . now()->format('Ymd') . '-' . strtoupper(substr((string) md5(uniqid((string) mt_rand(), true)), 0, 6));
        } while (AgencyCategoryOrder::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
