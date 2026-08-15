<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\Destination;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\TourView;
use App\Models\User;
use App\Support\CategoryLicensing;
use App\Support\DestinationFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'agencies' => Agency::count(),
            'tours' => Tour::count(),
            'activeTours' => Tour::active()->count(),
            // Yaşam-boyu toplamlar sayaçtan — ham tablolar 180 gün retention'la budanıyor
            'clicks' => (int) Tour::sum('clicks_count'),
            'views' => (int) Tour::sum('views_count'),
        ];

        $agencies = Agency::withCount('tours')->orderByDesc('tours_count')->get();

        // Daily clicks + views for 30 days
        $dailyClicks = TourClick::where('clicked_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(clicked_at) as date, COUNT(*) as total')
            ->groupBy('date')->orderBy('date')->pluck('total', 'date');

        $dailyViews = TourView::where('viewed_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(viewed_at) as date, COUNT(*) as total')
            ->groupBy('date')->orderBy('date')->pluck('total', 'date');

        $chartLabels = $clickData = $viewData = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('d M');
            $clickData[] = $dailyClicks[$d] ?? 0;
            $viewData[] = $dailyViews[$d] ?? 0;
        }

        // Top destinations by tour count
        $topDestinations = Tour::active()
            ->selectRaw('destination, COUNT(*) as total')
            ->groupBy('destination')
            ->orderByDesc('total')
            ->limit(8)
            ->pluck('total', 'destination');

        return view('admin.dashboard', compact(
            'stats', 'agencies', 'chartLabels', 'clickData', 'viewData', 'topDestinations'
        ));
    }

    public function agencies(Request $request)
    {
        $query = Agency::withCount([
            'tours',
            'activeTours as active_tours_count',
        ]);

        if (CategoryLicensing::schemaReady()) {
            $query->withCount([
                'activeCategorySubscriptions as active_category_subscriptions_count',
            ]);
        }

        if ($request->filled('q')) {
            $searchTerm = $request->q;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'like', '%'.$searchTerm.'%')
                    ->orWhere('email', 'like', '%'.$searchTerm.'%')
                    ->orWhere('phone', 'like', '%'.$searchTerm.'%');
            });
        }

        if ($request->filled('status')) {
            if ($request->status === 'active') {
                $query->where('is_active', true);
            } elseif ($request->status === 'passive') {
                $query->where('is_active', false);
            }
        }

        if ($request->filled('approval_status')) {
            if ($request->approval_status === Agency::STATUS_PENDING) {
                $query->pendingApproval();
            } elseif ($request->approval_status === Agency::STATUS_APPROVED) {
                $query->approved();
            } elseif ($request->approval_status === Agency::STATUS_REJECTED) {
                $query->rejected();
            }
        }

        $agencies = $query->orderBy('name')->paginate(15)->withQueryString();

        return view('admin.agencies', compact('agencies'));
    }

    public function agencyApplications()
    {
        $pendingApplications = Agency::pendingApproval()
            ->withCount([
                'tours',
                'activeTours as active_tours_count',
            ])
            ->orderBy('created_at')
            ->paginate(12, ['*'], 'pending');

        $recentApplications = Agency::query()
            ->whereIn('approval_status', [Agency::STATUS_APPROVED, Agency::STATUS_REJECTED])
            ->withCount([
                'tours',
                'activeTours as active_tours_count',
            ])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get();

        $applicationStats = [
            'pending' => Agency::pendingApproval()->count(),
            'approved' => Agency::approved()->count(),
            'rejected' => Agency::rejected()->count(),
            'today' => Agency::query()->whereDate('created_at', Carbon::today())->count(),
        ];

        return view('admin.agency-applications', compact(
            'pendingApplications',
            'recentApplications',
            'applicationStats'
        ));
    }

    public function showAgency(Agency $agency)
    {
        $agency->loadCount([
            'tours',
            'activeTours as active_tours_count',
        ]);

        $hasCategoryLicensing = CategoryLicensing::schemaReady();

        $accessibleCategories = collect();
        $ownedCategories = collect();
        $usedCategories = collect();
        $recentOrders = collect();
        $monthlyCategoryValue = 0;
        $lifetimeOrderValue = 0;

        if ($hasCategoryLicensing) {
            $agency->loadCount([
                'activeCategorySubscriptions as active_category_subscriptions_count',
                'categoryOrders as category_orders_count',
            ]);

            $activeSubscriptions = $agency->activeCategorySubscriptions()
                ->with('category.parent', 'lastOrder')
                ->orderBy('expires_at')
                ->get();

            $accessibleCategories = $agency->accessibleCategoriesQuery()
                ->with('parent')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();

            $usedCategories = Category::query()
                ->with('parent')
                ->whereHas('tours', fn ($query) => $query->where('agency_id', $agency->id))
                ->withCount([
                    'tours as tours_count' => fn ($query) => $query->where('agency_id', $agency->id),
                    'tours as active_tours_count' => fn ($query) => $query->where('agency_id', $agency->id)->where('is_active', true),
                ])
                ->orderByDesc('active_tours_count')
                ->orderByDesc('tours_count')
                ->orderBy('name')
                ->get();

            $usedCategoriesById = $usedCategories->keyBy('id');
            $subscriptionsByCategoryId = $activeSubscriptions->keyBy('category_id');

            $ownedCategories = $accessibleCategories
                ->map(function (Category $category) use ($agency, $subscriptionsByCategoryId, $usedCategoriesById) {
                    $subscription = $subscriptionsByCategoryId->get($category->id);
                    $usage = $usedCategoriesById->get($category->id);

                    $isManual = $subscription?->lastOrder?->payment_provider === AgencyCategoryOrder::PROVIDER_MANUAL;
                    $source = $agency->legacy_category_access ? 'legacy' : ($isManual ? 'manual' : 'purchase');

                    return (object) [
                        'category' => $category,
                        'subscription' => $subscription,
                        'source' => $source,
                        'source_label' => ['legacy' => 'Geçiş erişimi', 'manual' => 'Manuel eklendi', 'purchase' => 'Satın alındı'][$source],
                        'monthly_price' => (float) ($subscription?->monthly_price ?? $category->monthly_price),
                        'started_at' => $subscription?->started_at,
                        'expires_at' => $subscription?->expires_at,
                        'tours_count' => (int) ($usage?->tours_count ?? 0),
                        'active_tours_count' => (int) ($usage?->active_tours_count ?? 0),
                    ];
                })
                ->sort(function ($left, $right) {
                    return ($right->active_tours_count <=> $left->active_tours_count)
                        ?: ($right->tours_count <=> $left->tours_count)
                        ?: strcmp($left->category->name, $right->category->name);
                })
                ->values();

            $recentOrders = $agency->categoryOrders()
                ->with('items.category')
                ->orderByDesc('purchased_at')
                ->limit(12)
                ->get();

            $monthlyCategoryValue = $agency->legacy_category_access
                ? round($usedCategories->sum(fn (Category $category) => (float) $category->monthly_price), 2)
                : round($activeSubscriptions->sum(fn ($subscription) => (float) $subscription->monthly_price), 2);

            $lifetimeOrderValue = round($agency->categoryOrders()->sum('subtotal'), 2);
        } else {
            $agency->category_orders_count = 0;
            $agency->active_category_subscriptions_count = 0;
        }

        $recentTours = $agency->tours()
            ->with('category.parent')
            ->orderByDesc('created_at')
            ->limit(10)
            ->get();

        // Manuel ekleme formu: aktif aboneliği OLMAYAN aktif alt kategoriler
        $grantableCategories = collect();
        if ($hasCategoryLicensing) {
            $activeCategoryIds = $agency->activeCategorySubscriptions()->pluck('category_id');
            $grantableCategories = Category::query()
                ->active()
                ->whereNotNull('parent_id')
                ->whereNotIn('id', $activeCategoryIds)
                ->with('parent')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get();
        }

        $stats = [
            'active_tours' => $agency->active_tours_count,
            'open_categories' => $ownedCategories->count(),
            'used_categories' => $usedCategories->count(),
            'monthly_value' => $monthlyCategoryValue,
            'total_orders' => (int) ($agency->category_orders_count ?? 0),
            'lifetime_order_value' => $lifetimeOrderValue,
        ];

        return view('admin.agency-show', compact(
            'agency',
            'hasCategoryLicensing',
            'ownedCategories',
            'usedCategories',
            'recentOrders',
            'recentTours',
            'stats',
            'grantableCategories'
        ));
    }

    /**
     * Admin bir acentaya manuel kategori lisansı verir (yanlış satın alma
     * telafisi / iyi niyet jesti). Satın alma akışıyla (finalizePaidOrder)
     * birebir aynı kalıp: denetim izi için 0 TL manuel sipariş yaratılır,
     * aktif abonelik varsa uzatılır, yoksa/pasifse bugünden başlatılır.
     */
    public function grantCategory(Request $request, Agency $agency)
    {
        abort_unless(CategoryLicensing::schemaReady(), 404);

        $validated = $request->validate([
            'category_id' => [
                'required', 'integer',
                Rule::exists('categories', 'id')->whereNotNull('parent_id')->where('is_active', true),
            ],
            'months' => ['required', 'integer', 'min:1', 'max:24'],
        ], [
            'category_id.exists' => 'Yalnızca aktif alt kategoriler eklenebilir (üst kategoriler satılmaz/verilmez).',
        ]);

        $category = Category::findOrFail($validated['category_id']);
        $months = (int) $validated['months'];

        DB::transaction(function () use ($agency, $category, $months) {
            $order = AgencyCategoryOrder::create([
                'agency_id' => $agency->id,
                'order_number' => $this->generateManualOrderNumber(),
                'billing_cycle' => 'monthly',
                'subtotal' => 0,
                'currency' => 'TRY',
                'status' => AgencyCategoryOrder::STATUS_PAID,
                'payment_provider' => AgencyCategoryOrder::PROVIDER_MANUAL,
                'buyer_type' => AgencyCategoryOrder::BUYER_INDIVIDUAL,
                'purchased_at' => now(),
                'paid_at' => now(),
            ]);

            AgencyCategoryOrderItem::create([
                'order_id' => $order->id,
                'category_id' => $category->id,
                'category_name' => $category->name,
                'unit_price' => 0,
                'billing_cycle' => 'monthly',
            ]);

            // UNIQUE(agency_id, category_id): mevcut kaydı kilitle, yarışı önle
            $subscription = AgencyCategorySubscription::query()
                ->where('agency_id', $agency->id)
                ->where('category_id', $category->id)
                ->lockForUpdate()
                ->first();

            $today = now()->startOfDay();
            $stillActive = $subscription
                && $subscription->status === AgencyCategorySubscription::STATUS_ACTIVE
                && $subscription->expires_at !== null
                && $subscription->expires_at->gte($today);

            $attributes = [
                'last_order_id' => $order->id,
                // Tarife fiyatı kayda geçer (sipariş 0 TL — bedava verildiği oradan izlenir)
                'monthly_price' => (float) $category->monthly_price,
                'status' => AgencyCategorySubscription::STATUS_ACTIVE,
                'started_at' => $stillActive ? $subscription->started_at : $today,
                'expires_at' => ($stillActive ? $subscription->expires_at->copy() : $today->copy())->addMonths($months),
                'renewal_reminder_sent_at' => null,
            ];

            if ($subscription) {
                $subscription->update($attributes);
            } else {
                AgencyCategorySubscription::create(array_merge($attributes, [
                    'agency_id' => $agency->id,
                    'category_id' => $category->id,
                ]));
            }
        });

        return back()->with('success', $category->name.' kategorisi '.$months.' ay süreyle eklendi.');
    }

    /**
     * Manuel/satın alınmış kategori aboneliğini iptal eder. Kayıt silinmez
     * (denetim izi); status=cancelled olunca turlar anında yayından kalkar,
     * gerekirse yeniden eklenebilir.
     */
    public function revokeCategory(Agency $agency, AgencyCategorySubscription $subscription)
    {
        abort_unless(CategoryLicensing::schemaReady(), 404);
        abort_unless($subscription->agency_id === $agency->id, 404);

        $subscription->update(['status' => AgencyCategorySubscription::STATUS_CANCELLED]);

        $categoryName = $subscription->category?->name ?? 'Kategori';

        return back()->with('success', $categoryName.' aboneliği iptal edildi — bu kategorideki turlar yayından kalktı.');
    }

    private function generateManualOrderNumber(): string
    {
        do {
            $number = 'KYM-MAN-'.now()->format('Ymd').'-'.strtoupper(Str::random(6));
        } while (AgencyCategoryOrder::where('order_number', $number)->exists());

        return $number;
    }

    public function createAgency()
    {
        return view('admin.agency-create');
    }

    public function storeAgency(Request $request)
    {
        // E-posta ZORUNLU ve benzersiz: eskiden acenta adından türetiliyordu
        // ("admin@seturege.com") ve şifre sabit 'password' idi — acenta adını
        // gören herkes panele girebiliyordu. Giriş bilgisi artık tahmin
        // edilemez ve yalnızca bu isteğin cevabında bir kez gösterilir.
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string',
            'email' => 'required|email|max:255|unique:users,email',
            'website_url' => 'nullable|url',
            'description' => 'nullable|string',
        ]);

        $password = Str::password(16, symbols: false);

        // Acenta + kullanıcı tek işlemde: kullanıcı oluşturma patlarsa
        // sahipsiz (girilemeyen) bir acenta kaydı kalmasın.
        $agency = DB::transaction(function () use ($validated, $password) {
            $agency = Agency::create(array_merge($validated, [
                'approval_status' => Agency::STATUS_APPROVED,
                'approved_at' => now(),
                'approved_by' => auth()->id(),
            ]));

            User::create([
                'name' => $agency->name.' Yönetici',
                'email' => $validated['email'],
                'password' => Hash::make($password),
                'role' => 'agency',
                'agency_id' => $agency->id,
            ]);

            return $agency;
        });

        return redirect()->route('admin.agencies')
            ->with('success', 'Acenta oluşturuldu.')
            ->with('new_agency_credentials', [
                'agency' => $agency->name,
                'email' => $validated['email'],
                'password' => $password,
            ]);
    }

    public function approveAgencyApplication(Request $request, Agency $agency)
    {
        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:2000',
        ]);

        $agency->update([
            'approval_status' => Agency::STATUS_APPROVED,
            'approval_notes' => $validated['approval_notes'] ?? null,
            'approved_at' => now(),
            'approved_by' => auth()->id(),
            'is_active' => true,
        ]);

        return redirect()
            ->route('admin.agency-applications')
            ->with('success', $agency->name.' başvurusu onaylandı.');
    }

    public function rejectAgencyApplication(Request $request, Agency $agency)
    {
        $validated = $request->validate([
            'approval_notes' => 'nullable|string|max:2000',
        ]);

        $agency->update([
            'approval_status' => Agency::STATUS_REJECTED,
            'approval_notes' => $validated['approval_notes'] ?? null,
            'approved_at' => null,
            'approved_by' => auth()->id(),
            'is_active' => false,
            'legacy_category_access' => false,
        ]);

        return redirect()
            ->route('admin.agency-applications')
            ->with('success', $agency->name.' başvurusu reddedildi.');
    }

    public function toggleAgency(Agency $agency)
    {
        // Tutarsız state koruması: onaylanmamış (pending/rejected) acenta toggle
        // ile aktifleştirilemez — aktifleştirme yolu başvuru onayıdır.
        if (! $agency->is_active && ! $agency->isApproved()) {
            $statusLabel = $agency->isRejected() ? 'reddedilmiş' : 'onay bekleyen';

            return redirect()->route('admin.agencies')
                ->withErrors($agency->name.' '.$statusLabel.' durumda — aktifleştirmek için önce başvuruyu onaylayın.');
        }

        $agency->update(['is_active' => ! $agency->is_active]);

        return redirect()->route('admin.agencies')
            ->with('success', $agency->name.' '.($agency->is_active ? 'aktifleştirildi' : 'pasifleştirildi').'.');
    }

    public function tours(Request $request)
    {
        $query = Tour::with('agency', 'dates'); // Eager load dates as well

        // 1. Arama (q) - Başlık veya destinasyon
        if ($request->filled('q')) {
            $search = $request->q;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        // 2. Durum (status)
        if ($request->filled('status') && $request->status !== 'all') {
            $isActive = $request->status === 'active' ? 1 : 0;
            $query->where('is_active', $isActive);
        }

        // 3. Acenta (agency_id)
        if ($request->filled('agency_id')) {
            $query->where('agency_id', $request->agency_id);
        }

        // 4. Destinasyon (destination)
        if ($request->filled('destination')) {
            DestinationFilter::apply($query, $request->destination);
        }

        // 5. Tarih Aralığı (date_start, date_end)
        if ($request->filled('date_start')) {
            $query->where(function ($q) use ($request) {
                $q->where('departure_date', '>=', $request->date_start)
                    ->orWhereHas('dates', fn ($dq) => $dq->where('departure_date', '>=', $request->date_start));
            });
        }
        if ($request->filled('date_end')) {
            $query->where(function ($q) use ($request) {
                $q->where('departure_date', '<=', $request->date_end)
                    ->orWhereHas('dates', fn ($dq) => $dq->where('departure_date', '<=', $request->date_end));
            });
        }

        // 6. Tur Süresi (min_days, max_days)
        if ($request->filled('min_days')) {
            $query->where('duration_days', '>=', $request->min_days);
        }
        if ($request->filled('max_days')) {
            $query->where('duration_days', '<=', $request->max_days);
        }

        // 7. Trafik (traffic) — dashboard kutularından gelen "sadece tıklananlar" filtresi
        match ($request->input('traffic')) {
            'clicked' => $query->where('clicks_count', '>', 0),
            'viewed' => $query->where('views_count', '>', 0),
            default => null,
        };

        // 8. Sıralama (sort)
        $sort = $request->input('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'date' => $query->orderBy('departure_date'),
            'clicks_desc' => $query->orderByDesc('clicks_count'),
            'views_desc' => $query->orderByDesc('views_count'),
            default => $query->orderByDesc('created_at'),
        };

        $tours = $query->paginate(15)->withQueryString();

        // Dropdown data
        $agencies = Agency::orderBy('name')->get();
        $destinations = DestinationFilter::vocabulary(Tour::whereNotNull('destination'));

        return view('admin.tours', compact('tours', 'agencies', 'destinations'));
    }

    // --- Destination Management ---

    public function destinations()
    {
        $destinations = Destination::orderBy('sort_order')->get();

        return view('admin.destinations', compact('destinations'));
    }

    public function updateDestination(Request $request, Destination $destination)
    {
        $validated = $request->validate([
            'image' => 'nullable|url',
            'sort_order' => 'nullable|integer',
        ]);

        $destination->update($validated);

        return redirect()->route('admin.destinations')
            ->with('success', $destination->name.' güncellendi.');
    }

    public function toggleDestination(Destination $destination)
    {
        $destination->update(['is_active' => ! $destination->is_active]);

        return redirect()->route('admin.destinations')
            ->with('success', $destination->name.' '.($destination->is_active ? 'aktifleştirildi' : 'pasifleştirildi').'.');
    }
}
