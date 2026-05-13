<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\Agency\TourController as AgencyTourController;
use App\Http\Controllers\Agency\CategoryLicenseController as AgencyCategoryLicenseController;
use App\Http\Controllers\Agency\DashboardController as AgencyDashboardController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\CategoryLicenseController as AdminCategoryLicenseController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/turlar', [TourController::class, 'index'])->name('tours.index');
Route::get('/turlar/karsilastir', [TourController::class, 'compare'])->name('tours.compare');
Route::get('/turlar/{tour}', [TourController::class, 'show'])->name('tours.show');
Route::get('/acentalar/{agency}', [AgencyController::class, 'show'])->name('agencies.show');
Route::get('/destinasyonlar/{destination:slug}', [DestinationController::class, 'show'])->name('destinations.show');
Route::get('/blog', [PostController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [PostController::class, 'show'])->name('blog.show');
Route::get('/yapay-zeka-arama', [\App\Http\Controllers\AiSearchController::class, 'chat'])->name('ai.search');
Route::get('/yapay-zeka-arama/{uuid}', [\App\Http\Controllers\AiSearchController::class, 'chat'])->name('ai.search.show')->whereUuid('uuid');
Route::middleware('throttle:ai_search')->group(function () {
    Route::post('/yapay-zeka-arama/mesaj', [\App\Http\Controllers\AiSearchController::class, 'sendMessage'])->name('ai.search.message');
    Route::get('/yapay-zeka-arama-api', [\App\Http\Controllers\AiSearchController::class, 'searchApi'])->name('ai.search.api');
});

// Favorites (auth required)
Route::middleware('auth')->group(function () {
    Route::post('/favoriler/{tour}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/favorilerim', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/turlar/{tour}/yorum', [ReviewController::class, 'store'])->name('reviews.store');
    Route::delete('/yorum/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    // Profile
    Route::get('/profilim', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profilim/duzenle', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profilim', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profilim/sifre', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Notifications
    Route::get('/bildirimler', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/bildirimler/{id}/okundu', [\App\Http\Controllers\NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/bildirimler/hepsini-oku', [\App\Http\Controllers\NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
});

// iyzico ödeme callback — public + CSRF muaf (bootstrap/app.php içinde except listesine eklendi)
Route::post('/iyzico-callback/{order}', [\App\Http\Controllers\Agency\CategoryLicenseController::class, 'iyzicoCallback'])
    ->name('agency.category-licenses.iyzico.callback');

// Click tracking redirect
Route::get('/git/{tour}', function (\App\Models\Tour $tour) {
    abort_unless($tour->isPubliclyVisible() && $tour->agency?->is_active, 404);

    \App\Models\TourClick::create([
        'tour_id'    => $tour->id,
        'agency_id'  => $tour->agency_id,
        'ip_address' => request()->ip(),
        'clicked_at' => now(),
    ]);

    $url = $tour->tour_url ?: $tour->agency->website_url ?: route('home');
    return redirect()->away($url);
})->name('tour.redirect');

// Auth
Route::get('/giris', function () { return view('auth.login'); })->name('login');
Route::post('/giris', function (\Illuminate\Http\Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (\Illuminate\Support\Facades\Auth::attempt($credentials)) {
        $request->session()->regenerate();
        $user = auth()->user();

        if ($user->isAdmin()) return redirect()->route('admin.dashboard');
        if ($user->isAgency()) {
            if (!$user->agencyApproved()) {
                return redirect()->route('agency.application.status');
            }

            return redirect()->route('agency.dashboard');
        }
        return redirect()->route('home');
    }

    return back()->withErrors(['email' => 'Geçersiz e-posta veya şifre.']);
})->name('login.post');

Route::get('/kayit', function () { return view('auth.register'); })->name('register');
Route::get('/acenta-kayit', function () {
    return redirect()->route('register', ['type' => 'agency']);
})->name('agency.register');
Route::post('/kayit', function (\Illuminate\Http\Request $request) {
    $accountType = $request->input('account_type', 'visitor');

    if ($accountType === 'agency') {
        $validated = $request->validate([
            'account_type' => 'required|in:agency,visitor',
            'agency_name' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:50',
            'website_url' => 'nullable|url|max:255',
            'description' => 'nullable|string|max:2000',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = \Illuminate\Support\Facades\DB::transaction(function () use ($validated) {
            $agency = \App\Models\Agency::create([
                'name' => $validated['agency_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => false,
                'approval_status' => \App\Models\Agency::STATUS_PENDING,
                'legacy_category_access' => false,
            ]);

            return \App\Models\User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                'role' => 'agency',
                'agency_id' => $agency->id,
                'phone' => $validated['phone'] ?? null,
            ]);
        });

        \Illuminate\Support\Facades\Auth::login($user);

        return redirect()
            ->route('agency.application.status')
            ->with('success', 'Acenta başvurunuz alındı. Admin onayı sonrası paneliniz açılacak.');
    }

    $validated = $request->validate([
        'account_type' => 'required|in:agency,visitor',
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:6|confirmed',
    ]);

    $user = \App\Models\User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
        'role' => 'visitor',
    ]);

    \Illuminate\Support\Facades\Auth::login($user);

    return redirect()->route('home')->with('success', 'Hoş geldiniz!');
})->name('register.post');

Route::post('/cikis', function () {
    \Illuminate\Support\Facades\Auth::logout();
    return redirect()->route('home');
})->name('logout');

// Agency Panel
Route::prefix('acenta')->name('agency.')->middleware(['auth', 'role:agency'])->group(function () {
    Route::get('/basvuru-durumu', [AgencyDashboardController::class, 'applicationStatus'])->name('application.status');

    Route::middleware('agency.approved')->group(function () {
        Route::get('/', [AgencyDashboardController::class, 'index'])->name('dashboard');
        Route::get('/kategori-yetkilendirme', [AgencyCategoryLicenseController::class, 'index'])->name('category-licenses.index');
        Route::post('/kategori-yetkilendirme/sepet', [AgencyCategoryLicenseController::class, 'addToCart'])->name('category-licenses.cart.add');
        Route::delete('/kategori-yetkilendirme/sepet/{category}', [AgencyCategoryLicenseController::class, 'removeFromCart'])->name('category-licenses.cart.remove');
        Route::get('/kategori-yetkilendirme/odeme', [AgencyCategoryLicenseController::class, 'checkoutForm'])->name('category-licenses.checkout-form');
        Route::post('/kategori-yetkilendirme/odeme', [AgencyCategoryLicenseController::class, 'initiatePayment'])->name('category-licenses.initiate-payment');
        Route::get('/kategori-yetkilendirme/odeme/{order}/sonuc', [AgencyCategoryLicenseController::class, 'paymentResult'])->name('category-licenses.payment.result');
        Route::get('/turlar', [AgencyTourController::class, 'index'])->name('tours.index');
        Route::get('/turlar/ekle', [AgencyTourController::class, 'create'])->name('tours.create');
        Route::post('/turlar', [AgencyTourController::class, 'store'])->name('tours.store');
        Route::get('/turlar/{tour}', [AgencyTourController::class, 'show'])->name('tours.show');
        Route::get('/turlar/{tour}/duzenle', [AgencyTourController::class, 'edit'])->name('tours.edit');
        Route::put('/turlar/{tour}', [AgencyTourController::class, 'update'])->name('tours.update');
        Route::delete('/turlar/{tour}', [AgencyTourController::class, 'destroy'])->name('tours.destroy');

        // Tour dates
        Route::post('/turlar/{tour}/tarihler', [\App\Http\Controllers\Agency\TourDateController::class, 'store'])->name('tours.dates.store');
        Route::put('/turlar/{tour}/tarihler/{date}', [\App\Http\Controllers\Agency\TourDateController::class, 'update'])->name('tours.dates.update');
        Route::delete('/turlar/{tour}/tarihler/{date}', [\App\Http\Controllers\Agency\TourDateController::class, 'destroy'])->name('tours.dates.destroy');

        // Profile
        Route::get('/profil', [\App\Http\Controllers\Agency\ProfileController::class, 'edit'])->name('profile');
        Route::put('/profil', [\App\Http\Controllers\Agency\ProfileController::class, 'update'])->name('profile.update');

        // Stats
        Route::get('/istatistik', [\App\Http\Controllers\Agency\StatsController::class, 'index'])->name('stats');

        // Campaigns
        Route::get('/kampanyalar', [\App\Http\Controllers\Agency\CampaignController::class, 'index'])->name('campaigns.index');
        Route::post('/kampanyalar', [\App\Http\Controllers\Agency\CampaignController::class, 'store'])->name('campaigns.store');
        Route::delete('/kampanyalar/{campaign}', [\App\Http\Controllers\Agency\CampaignController::class, 'destroy'])->name('campaigns.destroy');

        // Coupons
        Route::resource('/kuponlar', \App\Http\Controllers\Agency\CouponController::class)
            ->names('coupons')
            ->parameters(['kuponlar' => 'coupon'])
            ->only(['index', 'store', 'destroy']);
        Route::post('/kuponlar/{coupon}/toggle', [\App\Http\Controllers\Agency\CouponController::class, 'toggle'])->name('coupons.toggle');
    });
});

// Admin Panel
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/kategori-yetkilendirme', [AdminCategoryLicenseController::class, 'index'])->name('category-licenses.index');
    Route::get('/kategori-yetkilendirme/kategori-tarifesi', [AdminCategoryLicenseController::class, 'pricing'])->name('category-licenses.pricing');
    Route::get('/kategori-yetkilendirme/acenta-erisimleri', [AdminCategoryLicenseController::class, 'access'])->name('category-licenses.access');
    Route::get('/kategori-yetkilendirme/siparisler', [AdminCategoryLicenseController::class, 'orders'])->name('category-licenses.orders');
    Route::put('/kategori-yetkilendirme/fiyat/{category}', [AdminCategoryLicenseController::class, 'updatePricing'])->name('category-licenses.pricing.update');
    Route::get('/acentalar', [AdminController::class, 'agencies'])->name('agencies');
    Route::get('/acenta-basvurulari', [AdminController::class, 'agencyApplications'])->name('agency-applications');
    Route::get('/acentalar/ekle', [AdminController::class, 'createAgency'])->name('agencies.create');
    Route::get('/acentalar/{agency}', [AdminController::class, 'showAgency'])->name('agencies.show');
    Route::post('/acentalar', [AdminController::class, 'storeAgency'])->name('agencies.store');
    Route::post('/acentalar/{agency}/toggle', [AdminController::class, 'toggleAgency'])->name('agencies.toggle');
    Route::post('/acenta-basvurulari/{agency}/onayla', [AdminController::class, 'approveAgencyApplication'])->name('agency-applications.approve');
    Route::post('/acenta-basvurulari/{agency}/reddet', [AdminController::class, 'rejectAgencyApplication'])->name('agency-applications.reject');
    Route::get('/turlar', [AdminController::class, 'tours'])->name('tours');
    Route::get('/destinasyonlar', [AdminController::class, 'destinations'])->name('destinations');
    Route::put('/destinasyonlar/{destination}', [AdminController::class, 'updateDestination'])->name('destinations.update');
    Route::post('/destinasyonlar/{destination}/toggle', [AdminController::class, 'toggleDestination'])->name('destinations.toggle');

    // Category Management
    Route::resource('/kategoriler', \App\Http\Controllers\Admin\CategoryController::class)
        ->names('categories')
        ->parameters(['kategoriler' => 'category'])
        ->except(['create', 'show', 'edit']);

    // Coupons
    Route::resource('/kuponlar', \App\Http\Controllers\Admin\CouponController::class)
        ->names('coupons')
        ->parameters(['kuponlar' => 'coupon'])
        ->except(['create', 'show', 'edit']);
    Route::post('/kuponlar/{coupon}/toggle', [\App\Http\Controllers\Admin\CouponController::class, 'toggle'])->name('coupons.toggle');
    
    // Reports
    Route::get('/raporlar', [\App\Http\Controllers\Admin\ReportController::class, 'index'])->name('reports.index');
    Route::post('/kategoriler/{category}/toggle', [\App\Http\Controllers\Admin\CategoryController::class, 'toggle'])->name('categories.toggle');

    // Blog management
    Route::get('/blog', [AdminBlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/ekle', [AdminBlogController::class, 'create'])->name('blog.create');
    Route::post('/blog', [AdminBlogController::class, 'store'])->name('blog.store');
    Route::get('/blog/{post}/duzenle', [AdminBlogController::class, 'edit'])->name('blog.edit');
    Route::put('/blog/{post}', [AdminBlogController::class, 'update'])->name('blog.update');
    Route::delete('/blog/{post}', [AdminBlogController::class, 'destroy'])->name('blog.destroy');

    // Banner management
    Route::get('/bannerlar', [\App\Http\Controllers\Admin\BannerController::class, 'index'])->name('banners.index');
    Route::post('/bannerlar', [\App\Http\Controllers\Admin\BannerController::class, 'store'])->name('banners.store');
    Route::put('/bannerlar/{banner}', [\App\Http\Controllers\Admin\BannerController::class, 'update'])->name('banners.update');
    Route::patch('/bannerlar/{banner}/toggle', [\App\Http\Controllers\Admin\BannerController::class, 'toggle'])->name('banners.toggle');
    Route::delete('/bannerlar/{banner}', [\App\Http\Controllers\Admin\BannerController::class, 'destroy'])->name('banners.destroy');

    // Featured Cities (Story) management
    Route::get('/one-cikan-sehirler', [\App\Http\Controllers\Admin\FeaturedCityController::class, 'index'])->name('featured_cities.index');
    Route::post('/one-cikan-sehirler', [\App\Http\Controllers\Admin\FeaturedCityController::class, 'store'])->name('featured_cities.store');
    Route::put('/one-cikan-sehirler/{city}', [\App\Http\Controllers\Admin\FeaturedCityController::class, 'update'])->name('featured_cities.update');
    Route::delete('/one-cikan-sehirler/{city}', [\App\Http\Controllers\Admin\FeaturedCityController::class, 'destroy'])->name('featured_cities.destroy');
    Route::post('/one-cikan-sehirler/{city}/gorsel', [\App\Http\Controllers\Admin\FeaturedCityController::class, 'addImage'])->name('featured_cities.add_image');
    Route::delete('/one-cikan-sehirler/gorsel/{image}', [\App\Http\Controllers\Admin\FeaturedCityController::class, 'destroyImage'])->name('featured_cities.destroy_image');
});
