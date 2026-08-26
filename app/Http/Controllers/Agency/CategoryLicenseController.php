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
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\Status;
use Throwable;

class CategoryLicenseController extends Controller
{
    private const CART_SESSION_KEY = 'agency_category_license_cart';

    /** Ekstra tur hakkı sepeti: [category_id => adet]. */
    private const SLOT_CART_SESSION_KEY = 'agency_category_slot_cart';

    private const MAX_SLOTS_PER_CHECKOUT = 10;

    public function __construct(
        private readonly IyzicoService $iyzico,
        private readonly \App\Services\Payment\CategoryOrderFinalizer $finalizer,
    ) {}

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

        // Sepettekiler de listede kalır; JS sepete eklendikçe kartı gizler/geri gösterir.
        $availableCategories = $agency->legacy_category_access
            ? collect()
            : $categories
                ->whereNotIn('id', $licensedCategoryIds)
                ->filter(fn (Category $category) => $category->parent_id !== null) // üst kategoriler satılmaz
                ->values();

        $cartItems = $this->resolveCartCategoriesFor($agency);
        $cartCategoryIds = $cartItems->pluck('id');
        $slotCartItems = $this->resolveSlotCartFor($agency);
        $slotSchemaReady = CategoryLicensing::slotSchemaReady();
        $autoRenewEnabled = CategoryLicensing::autoRenewEnabled();
        $storedCard = $autoRenewEnabled ? $agency->storedCard : null;

        // Kategori başına kullanılan tur hakkı (aktif+pasif tüm turlar sayılır)
        $usedSlotsByCategory = $agency->tours()
            ->whereNotNull('category_id')
            ->selectRaw('category_id, COUNT(*) as used_count')
            ->groupBy('category_id')
            ->pluck('used_count', 'category_id');

        $licensedCategories = $agency->legacy_category_access
            ? $categories->map(function (Category $category) use ($agency, $usedSlotsByCategory) {
                return (object) [
                    'subscription' => null,
                    'category' => $category,
                    'monthly_price' => $category->monthly_price,
                    'started_at' => $agency->created_at,
                    'expires_at' => null,
                    'source' => 'legacy',
                    'tour_limit' => null, // legacy = limitsiz
                    'used_slots' => (int) ($usedSlotsByCategory[$category->id] ?? 0),
                    'extra_slots' => 0,
                    'cancelled' => false,
                    'next_extra_slots' => null,
                ];
            })
            : $agency->activeCategorySubscriptions()
                ->with('category.parent')
                ->orderBy('expires_at')
                ->get()
                ->map(function (AgencyCategorySubscription $subscription) use ($usedSlotsByCategory, $slotSchemaReady, $autoRenewEnabled) {
                    return (object) [
                        'subscription' => $subscription,
                        'category' => $subscription->category,
                        'monthly_price' => $subscription->monthly_price,
                        'started_at' => $subscription->started_at,
                        'expires_at' => $subscription->expires_at,
                        'source' => 'purchase',
                        'tour_limit' => $slotSchemaReady
                            ? CategoryLicensing::BASE_TOUR_ALLOWANCE + (int) $subscription->extra_tour_slots
                            : null,
                        'used_slots' => (int) ($usedSlotsByCategory[$subscription->category_id] ?? 0),
                        'extra_slots' => $slotSchemaReady ? (int) $subscription->extra_tour_slots : 0,
                        'cancelled' => $autoRenewEnabled && $subscription->isCancelled(),
                        'next_extra_slots' => $autoRenewEnabled ? $subscription->next_extra_tour_slots : null,
                    ];
                });

        // Listeler ayrı ekranlara taşındı: ana sayfada yalnız özet + yönlendirme
        $lastOrder = $agency->categoryOrders()->orderByDesc('purchased_at')->first();
        $ordersCount = $agency->categoryOrders()->count();

        $cartTotal = $cartItems->sum(fn (Category $category) => (float) $category->monthly_price)
            + $slotCartItems->sum('line_total');

        return view('agency.category-licenses.index', compact(
            'agency',
            'availableCategories',
            'cartItems',
            'cartCategoryIds',
            'slotCartItems',
            'slotSchemaReady',
            'autoRenewEnabled',
            'storedCard',
            'cartTotal',
            'licensedCategories',
            'lastOrder',
            'ordersCount'
        ));
    }

    /** Sepet ekranı: kalemler, kaldırma ve hak ekleme burada — ana sayfada özet kaldı. */
    public function showCart()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        $agency = $this->currentAgency();
        $cartItems = $this->resolveCartCategoriesFor($agency);
        $slotCartItems = $this->resolveSlotCartFor($agency);
        $cartTotal = $cartItems->sum(fn (Category $category) => (float) $category->monthly_price)
            + $slotCartItems->sum('line_total');

        return view('agency.category-licenses.cart', [
            'agency' => $agency,
            'cartItems' => $cartItems,
            'slotCartItems' => $slotCartItems,
            'cartTotal' => $cartTotal,
            'slotSchemaReady' => CategoryLicensing::slotSchemaReady(),
            'autoRenewEnabled' => CategoryLicensing::autoRenewEnabled(),
        ]);
    }

    /** Satın alım geçmişi ekranı: tüm siparişler sayfalı listede. */
    public function orders()
    {
        if ($redirect = $this->redirectIfSchemaMissing()) {
            return $redirect;
        }

        $agency = $this->currentAgency();

        $orders = $agency->categoryOrders()
            ->with('items')
            ->orderByDesc('purchased_at')
            ->paginate(15);

        return view('agency.category-licenses.orders', compact('agency', 'orders'));
    }

    public function addToCart(Request $request)
    {
        if ($response = $this->guardSchema($request)) {
            return $response;
        }

        $agency = $this->currentAgency();

        if ($agency->legacy_category_access) {
            return $this->cartError($request, 'Bu acenta için geçiş erişimi tanımlı. Tüm kategoriler zaten açık.');
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $category = Category::active()->findOrFail((int) $validated['category_id']);

        if ($category->parent_id === null) {
            return $this->cartError($request, 'Üst kategoriler satın alınamaz. Lütfen bir alt kategori seçin.');
        }

        if ($agency->hasCategoryAccess($category)) {
            return $this->cartError($request, $category->name.' kategorisi zaten aktif yetkileriniz arasında.');
        }

        $cartCategoryIds = $this->cartCategoryIds()
            ->push($category->id)
            ->unique()
            ->values()
            ->all();

        session([self::CART_SESSION_KEY => $cartCategoryIds]);

        return $this->cartSuccess($request, $agency, $category->name.' sepetinize eklendi.');
    }

    public function removeFromCart(Request $request, Category $category)
    {
        if ($response = $this->guardSchema($request)) {
            return $response;
        }

        $cartCategoryIds = $this->cartCategoryIds()
            ->reject(fn ($id) => $id === $category->id)
            ->values()
            ->all();

        session([self::CART_SESSION_KEY => $cartCategoryIds]);

        return $this->cartSuccess($request, $this->currentAgency(), $category->name.' sepetten çıkarıldı.');
    }

    /**
     * Aktif abonelikli bir kategoriye +1 ekstra tur hakkı (aynı kategoriye
     * tekrar basıldıkça adet artar). Hak tek seferlik ödenir, abonelik
     * kesintisiz sürdükçe geçerlidir.
     */
    public function addSlotToCart(Request $request)
    {
        if ($response = $this->guardSchema($request)) {
            return $response;
        }

        if (! CategoryLicensing::slotSchemaReady()) {
            return $this->cartError($request, 'Ekstra tur hakkı altyapısı henüz veritabanına uygulanmamış.');
        }

        $agency = $this->currentAgency();

        if ($agency->legacy_category_access) {
            return $this->cartError($request, 'Geçiş erişimli acentalarda tur limiti yok; ekstra hak satın almanız gerekmiyor.');
        }

        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $category = Category::active()
            ->whereNotNull('parent_id')
            ->findOrFail((int) $validated['category_id']);

        // Aktif abonelik VEYA aynı satın almada alınan kategori (sepette) —
        // ikisi de yoksa hak bağlanacak abonelik olmaz, ödeme boşa gider.
        $hasActiveSubscription = $agency->activeCategorySubscriptions()
            ->where('category_id', $category->id)
            ->exists();

        if (! $hasActiveSubscription && ! $this->cartCategoryIds()->contains($category->id)) {
            return $this->cartError($request, 'Ekstra tur hakkı için '.$category->name.' kategorisinde aktif aboneliğiniz olmalı veya kategori sepetinizde olmalı.');
        }

        $slotCart = $this->slotCartQuantities();
        $currentQuantity = (int) ($slotCart[$category->id] ?? 0);

        if ($currentQuantity >= self::MAX_SLOTS_PER_CHECKOUT) {
            return $this->cartError($request, 'Tek siparişte bir kategori için en fazla '.self::MAX_SLOTS_PER_CHECKOUT.' ekstra tur hakkı alabilirsiniz.');
        }

        $slotCart[$category->id] = $currentQuantity + 1;
        session([self::SLOT_CART_SESSION_KEY => $slotCart]);

        return $this->cartSuccess($request, $agency, $category->name.' için ekstra tur hakkı sepete eklendi.');
    }

    public function removeSlotFromCart(Request $request, Category $category)
    {
        if ($response = $this->guardSchema($request)) {
            return $response;
        }

        $slotCart = $this->slotCartQuantities();
        unset($slotCart[$category->id]);
        session([self::SLOT_CART_SESSION_KEY => $slotCart]);

        return $this->cartSuccess($request, $this->currentAgency(), $category->name.' ekstra tur hakkı sepetten çıkarıldı.');
    }

    /**
     * Abonelik iptali: dönem sonuna kadar kullanım SÜRER, dönem sonunda
     * otomatik çekim yapılmaz. Turlar dönem bitene kadar yayında kalır.
     */
    public function cancelSubscription(AgencyCategorySubscription $subscription)
    {
        if ($redirect = $this->guardAutoRenewSchema()) {
            return $redirect;
        }

        $agency = $this->currentAgency();
        abort_unless($subscription->agency_id === $agency->id, 403);

        if (! $subscription->is_active) {
            return back()->withErrors('Yalnızca aktif abonelikler iptal edilebilir.');
        }

        $subscription->update([
            'auto_renew' => false,
            'cancelled_at' => now(),
        ]);

        $categoryName = $subscription->category?->name ?? 'Kategori';

        return back()->with('success', $categoryName.' aboneliği iptal edildi. '.$subscription->expires_at?->format('d.m.Y').' tarihine kadar kullanmaya devam edebilirsiniz; kartınızdan otomatik çekim yapılmayacak.');
    }

    /** İptalden vazgeç: dönem bitmeden yenilemeyi tekrar açar. */
    public function resumeSubscription(AgencyCategorySubscription $subscription)
    {
        if ($redirect = $this->guardAutoRenewSchema()) {
            return $redirect;
        }

        $agency = $this->currentAgency();
        abort_unless($subscription->agency_id === $agency->id, 403);

        if (! $subscription->is_active) {
            return back()->withErrors('Süresi dolmuş abonelik için yenileme açılamaz; kategoriyi yeniden satın alın.');
        }

        $subscription->update([
            'auto_renew' => true,
            'cancelled_at' => null,
        ]);

        $categoryName = $subscription->category?->name ?? 'Kategori';

        // Son gün açılan yenileme sabahki çekim koşusunu kaçırmış olabilir —
        // kullanıcıyı "açıldı" mesajıyla yanıltıp aboneliği sessizce
        // düşürmemek için manuel yenilemeye yönlendir.
        if ($subscription->expires_at !== null && $subscription->expires_at->lessThanOrEqualTo(today())) {
            return back()->with('success', $categoryName.' aboneliğinde otomatik yenileme tekrar açıldı. Ancak abonelik BUGÜN sona eriyor ve günün otomatik çekim saati geçmiş olabilir — süre kaybetmemek için kategoriyi sepetten manuel yenilemenizi öneririz.');
        }

        return back()->with('success', $categoryName.' aboneliğinde otomatik yenileme tekrar açıldı.');
    }

    /**
     * Ekstra hak azaltma planı: mevcut dönemde haklar korunur, yeni dönem
     * `keep` hak ile (ve o tutarla) yenilenir. keep = mevcut → plan iptali.
     */
    public function planSlotReduction(Request $request, AgencyCategorySubscription $subscription)
    {
        if ($redirect = $this->guardAutoRenewSchema()) {
            return $redirect;
        }

        $agency = $this->currentAgency();
        abort_unless($subscription->agency_id === $agency->id, 403);

        if (! $subscription->is_active) {
            return back()->withErrors('Yalnızca aktif aboneliklerde ekstra hak planı değiştirilebilir.');
        }

        $currentSlots = (int) $subscription->extra_tour_slots;

        $validated = $request->validate([
            'keep' => 'required|integer|min:0|max:'.$currentSlots,
        ], [
            'keep.max' => 'Ekstra hak artırımı satın almayla yapılır; buradan yalnızca azaltabilirsiniz.',
        ]);

        $keep = (int) $validated['keep'];
        $subscription->update([
            'next_extra_tour_slots' => $keep === $currentSlots ? null : $keep,
        ]);

        $categoryName = $subscription->category?->name ?? 'Kategori';

        return back()->with('success', $keep === $currentSlots
            ? $categoryName.' için ekstra hak azaltma planı kaldırıldı.'
            : $categoryName.' için yeni dönemde '.$keep.' ekstra hak kalacak (mevcut dönemde '.$currentSlots.' hakkınız sürüyor).');
    }

    /** Kayıtlı kartı hem iyzico'dan hem yerelden siler (otomatik yenileme durur). */
    public function deleteStoredCard()
    {
        if ($redirect = $this->guardAutoRenewSchema()) {
            return $redirect;
        }

        $agency = $this->currentAgency();
        $storedCard = $agency->storedCard;

        if (! $storedCard) {
            return back()->withErrors('Kayıtlı kart bulunamadı.');
        }

        try {
            $this->iyzico->deleteStoredCard((string) $storedCard->card_user_key, (string) $storedCard->card_token);
        } catch (Throwable $e) {
            // Uzak silme başarısız olsa da yerel kaydı bırakmak işe yaramaz —
            // token'sız çekim yapılamaz; loglayıp yerelden siliyoruz.
            Log::warning('iyzico kart silme başarısız (yerel kayıt yine de silindi)', [
                'agency_id' => $agency->id,
                'message' => $e->getMessage(),
            ]);
        }

        $storedCard->delete();

        return back()->with('success', 'Kayıtlı kartınız silindi. Otomatik yenileme için bir sonraki ödemede kartınızı yeniden saklayabilirsiniz.');
    }

    private function guardAutoRenewSchema()
    {
        if (! CategoryLicensing::autoRenewSchemaReady()) {
            return back()->withErrors('Otomatik yenileme altyapısı henüz veritabanına uygulanmamış.');
        }

        return null;
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
        $slotCartItems = $this->resolveSlotCartFor($agency);

        if ($cartCategories->isEmpty() && $slotCartItems->isEmpty()) {
            return redirect()
                ->route('agency.category-licenses.index')
                ->withErrors('Ödeme için sepetinize en az 1 kalem eklemelisiniz.');
        }

        $cartTotal = $cartCategories->sum(fn (Category $category) => (float) $category->monthly_price)
            + $slotCartItems->sum('line_total');

        return view('agency.category-licenses.checkout', [
            'agency' => $agency,
            'cartCategories' => $cartCategories,
            'slotCartItems' => $slotCartItems,
            'cartTotal' => $cartTotal,
            'iyzicoConfigured' => $this->iyzico->isConfigured(),
            'autoRenewEnabled' => CategoryLicensing::autoRenewEnabled(),
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
        $slotCartItems = $this->resolveSlotCartFor($agency);

        if ($cartCategories->isEmpty() && $slotCartItems->isEmpty()) {
            return redirect()
                ->route('agency.category-licenses.index')
                ->withErrors('Sepetinizde satın alınabilir kalem bulunamadı.');
        }

        if (! $this->iyzico->isConfigured()) {
            return back()->withErrors('Ödeme altyapısı henüz yapılandırılmamış. Lütfen yönetici ile iletişime geçin.');
        }

        $buyer = $this->validateBuyer($request);
        $slotSchemaReady = CategoryLicensing::slotSchemaReady();

        try {
            $order = DB::transaction(function () use ($agency, $cartCategories, $slotCartItems, $buyer, $slotSchemaReady) {
                $subtotal = $cartCategories->sum(fn (Category $category) => (float) $category->monthly_price)
                    + $slotCartItems->sum('line_total');

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
                    ] + ($slotSchemaReady ? ['item_type' => AgencyCategoryOrderItem::TYPE_LICENSE] : []));
                }

                // Ekstra tur hakları: hak başına BİR kalem — finalize satır
                // sayarak aboneliğe yazar, ayrıca adet kolonu gerekmez.
                foreach ($slotCartItems as $slotItem) {
                    for ($unit = 0; $unit < $slotItem->quantity; $unit++) {
                        AgencyCategoryOrderItem::create([
                            'order_id' => $order->id,
                            'category_id' => $slotItem->category->id,
                            'category_name' => $slotItem->category->name.' — Ekstra Tur Hakkı',
                            'item_type' => AgencyCategoryOrderItem::TYPE_EXTRA_SLOT,
                            'unit_price' => $slotItem->unit_price,
                            'billing_cycle' => 'one_time',
                        ]);
                    }
                }

                return $order;
            });

            $basketItems = $cartCategories->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'category' => optional($category->parent)->name ?? 'Kategori Yetkisi',
                'price' => $category->monthly_price,
            ])->values()->all();

            foreach ($slotCartItems as $slotItem) {
                for ($unit = 1; $unit <= $slotItem->quantity; $unit++) {
                    $basketItems[] = [
                        'id' => 'slot-'.$slotItem->category->id.'-'.$unit,
                        'name' => $slotItem->category->name.' — Ekstra Tur Hakkı',
                        'category' => 'Ekstra Tur Hakkı',
                        'price' => $slotItem->unit_price,
                    ];
                }
            }

            $callbackUrl = route('agency.category-licenses.iyzico.callback', ['order' => $order->id]);

            // Otomatik yenileme açıkken kayıtlı kart anahtarı iletilir: iyzico
            // formu saklı kartları listeler, yeni kart saklamayı da formda sorar.
            $cardUserKey = CategoryLicensing::autoRenewEnabled()
                ? $agency->storedCard?->card_user_key
                : null;

            $response = $this->iyzico->initializeCheckoutForm(
                $order,
                $basketItems,
                $buyer,
                $callbackUrl,
                $request->ip() ?? '127.0.0.1',
                $cardUserKey
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
                ->withErrors('Ödeme başlatılamadı: '.$e->getMessage());
        }
    }

    public function iyzicoCallback(Request $request, AgencyCategoryOrder $order)
    {
        if (! CategoryLicensing::schemaReady()) {
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

            if ($this->finalizer->settleFromCheckout($order, $checkout) === 'paid') {
                session()->forget(self::CART_SESSION_KEY);
                session()->forget(self::SLOT_CART_SESSION_KEY);
            }
        } catch (Throwable $e) {
            Log::error('iyzico callback retrieve failed', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            $order->update([
                'status' => AgencyCategoryOrder::STATUS_FAILED,
                'failure_reason' => 'Doğrulama hatası: '.$e->getMessage(),
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

    private function validateBuyer(Request $request): array
    {
        $rules = [
            'buyer_type' => 'required|in:'.implode(',', [
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
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Sepet işlemleri XHR ile gelirse sayfa yenilemeden güncellenecek durumu döndür.
     */
    private function cartSuccess(Request $request, Agency $agency, string $message)
    {
        if (! $request->expectsJson()) {
            return back()->with('success', $message);
        }

        return response()->json([
            'ok' => true,
            'message' => $message,
        ] + $this->cartPayload($agency));
    }

    private function cartError(Request $request, string $message)
    {
        if (! $request->expectsJson()) {
            return back()->withErrors($message);
        }

        return response()->json(['ok' => false, 'message' => $message], 422);
    }

    /**
     * @return array<string, mixed>
     */
    private function cartPayload(Agency $agency): array
    {
        $cartCategories = $this->resolveCartCategoriesFor($agency);
        $slotItems = $this->resolveSlotCartFor($agency);
        $total = $cartCategories->sum(fn (Category $category) => (float) $category->monthly_price)
            + $slotItems->sum('line_total');

        $slotSchemaReady = CategoryLicensing::slotSchemaReady();
        // Otomatik yenileme açıkken ekstra hak AYLIK ücretlendirilir — etiket
        // "tek seferlik" derse yanıltıcı fiyat beyanı olur.
        $slotPriceSuffix = CategoryLicensing::autoRenewEnabled() ? ' TL / ay' : ' TL (tek seferlik)';

        $items = $cartCategories
            ->map(fn (Category $category) => [
                'key' => 'license-'.$category->id,
                'type' => 'license',
                'id' => $category->id,
                'name' => trim(($category->icon ? $category->icon.' ' : '').$category->name),
                'price_label' => number_format((float) $category->monthly_price, 0, ',', '.').' TL / ay',
                'remove_url' => route('agency.category-licenses.cart.remove', $category),
            ] + ($slotSchemaReady ? [
                'slot_add_url' => route('agency.category-licenses.cart.add-slot'),
                'slot_price_label' => number_format((float) $category->extra_tour_price, 0, ',', '.'),
            ] : []))
            ->concat($slotItems->map(fn ($slotItem) => [
                'key' => 'slot-'.$slotItem->category->id,
                'type' => 'extra_slot',
                'id' => $slotItem->category->id,
                'name' => $slotItem->category->name.' — Ekstra Tur Hakkı'.($slotItem->quantity > 1 ? ' ×'.$slotItem->quantity : ''),
                'price_label' => number_format((float) $slotItem->line_total, 0, ',', '.').$slotPriceSuffix,
                'remove_url' => route('agency.category-licenses.cart.remove-slot', $slotItem->category),
            ]))
            ->values();

        return [
            'items' => $items->all(),
            'count' => $items->count(),
            'total_label' => number_format((float) $total, 0, ',', '.'),
        ];
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
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return Collection<int, Category>
     */
    private function resolveCartCategoriesFor(Agency $agency)
    {
        $cartIds = $this->cartCategoryIds();

        if ($cartIds->isEmpty()) {
            return collect();
        }

        return Category::active()
            ->whereIn('id', $cartIds->all())
            ->whereNotNull('parent_id') // üst kategoriler satılmaz
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->reject(fn (Category $category) => $agency->hasCategoryAccess($category))
            ->values();
    }

    private function slotCartQuantities(): array
    {
        return collect(session(self::SLOT_CART_SESSION_KEY, []))
            ->mapWithKeys(fn ($quantity, $id) => [(int) $id => min(max((int) $quantity, 0), self::MAX_SLOTS_PER_CHECKOUT)])
            ->filter(fn ($quantity) => $quantity > 0)
            ->all();
    }

    /**
     * Slot sepetini doğrulanmış kalemlere çevirir: kategori aktif bir alt
     * kategori olmalı VE acentanın o kategoride aktif aboneliği olmalı.
     *
     * @return Collection<int, object{category: Category, quantity: int, unit_price: float, line_total: float}>
     */
    private function resolveSlotCartFor(Agency $agency)
    {
        if (! CategoryLicensing::slotSchemaReady() || $agency->legacy_category_access) {
            return collect();
        }

        $quantities = $this->slotCartQuantities();

        if (empty($quantities)) {
            return collect();
        }

        // Aktif abonelik VEYA aynı sepette alınan kategori için hak alınabilir
        // (yeni acenta kategori+hakları tek ödemede alabilsin; süresi dolan da
        // yenilerken haklarını yeniden ekleyebilsin).
        $allowedCategoryIds = $agency->activeCategorySubscriptions()
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id)
            ->merge($this->cartCategoryIds())
            ->unique()
            ->all();

        return Category::active()
            ->whereIn('id', array_keys($quantities))
            ->whereIn('id', $allowedCategoryIds)
            ->whereNotNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(function (Category $category) use ($quantities) {
                $quantity = $quantities[$category->id];
                $unitPrice = (float) $category->extra_tour_price;

                return (object) [
                    'category' => $category,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'line_total' => $unitPrice * $quantity,
                ];
            })
            ->values();
    }

    private function guardSchema(Request $request)
    {
        if (CategoryLicensing::schemaReady()) {
            return null;
        }

        $message = 'Kategori yetkilendirme altyapısı henüz veritabanına uygulanmamış.';

        return $request->expectsJson()
            ? response()->json(['ok' => false, 'message' => $message], 503)
            : back()->withErrors($message);
    }

    private function redirectIfSchemaMissing(bool $back = false)
    {
        if (! CategoryLicensing::schemaReady()) {
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
            $orderNumber = 'KYM-'.now()->format('Ymd').'-'.strtoupper(substr((string) md5(uniqid((string) mt_rand(), true)), 0, 6));
        } while (AgencyCategoryOrder::where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
