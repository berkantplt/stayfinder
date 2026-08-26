<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CategoryLicenseController as AdminCategoryLicenseController;
use App\Http\Controllers\Admin\DestinationProfileController;
use App\Http\Controllers\Admin\FeaturedCityController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Agency\CampaignController;
use App\Http\Controllers\Agency\CategoryLicenseController;
use App\Http\Controllers\Agency\CategoryLicenseController as AgencyCategoryLicenseController;
use App\Http\Controllers\Agency\CategoryRequestController;
use App\Http\Controllers\Agency\DashboardController as AgencyDashboardController;
use App\Http\Controllers\Agency\StatsController;
use App\Http\Controllers\Agency\TourController as AgencyTourController;
use App\Http\Controllers\Agency\TourDateController;
use App\Http\Controllers\Agency\TourImportController;
use App\Http\Controllers\AgencyController;
use App\Http\Controllers\AiSearchController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Customer\CouponController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TourController;
use App\Models\Agency;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/', [HomeController::class, 'index'])->name('home');
// SEO: sitemap index + bölüm haritaları. robots.txt de rota üzerinden servis
// edilir — Sitemap satırının MUTLAK URL olması şart (spec gereği) ve mutlak URL
// ancak çalışma anında APP_URL'den kurulabilir.
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-{section}.xml', [SitemapController::class, 'section'])
    ->where('section', '[a-z]+')
    ->name('sitemap.section');
Route::get('/robots.txt', [RobotsController::class, 'index'])->name('robots');
Route::get('/turlar', [TourController::class, 'index'])->name('tours.index');
Route::get('/turlar/karsilastir', [TourController::class, 'compare'])->name('tours.compare');
Route::get('/turlar/{tour}', [TourController::class, 'show'])->name('tours.show');
Route::get('/acentalar/{agency}', [AgencyController::class, 'show'])->name('agencies.show');
Route::get('/destinasyonlar/{destination:slug}', [DestinationController::class, 'show'])->name('destinations.show');
Route::get('/blog', [PostController::class, 'index'])->name('blog.index');
Route::get('/blog/{post:slug}', [PostController::class, 'show'])->name('blog.show');

// Yasal / kurumsal sayfalar — hepsi statik view, controller gerektirmiyor.
// Künye bilgileri config/company.php'den gelir (.env COMPANY_* anahtarları).
Route::view('/nasil-calisir', 'legal.nasil-calisir')->name('legal.nasil-calisir');
Route::view('/iletisim', 'legal.iletisim')->name('legal.iletisim');
Route::view('/gizlilik', 'legal.gizlilik')->name('legal.gizlilik');
Route::view('/kvkk-aydinlatma-metni', 'legal.kvkk')->name('legal.kvkk');
Route::view('/cerez-politikasi', 'legal.cerez-politikasi')->name('legal.cerez');
Route::view('/kullanim-kosullari', 'legal.kullanim-kosullari')->name('legal.kosullar');
Route::view('/siralama-kriterleri', 'legal.siralama-kriterleri')->name('legal.siralama');
// Tur eşleştirme testi — chatbot dondurmasından ETKİLENMEZ (LLM'siz çalışır)
Route::middleware('throttle:ai_search')->group(function () {
    Route::get('/tatil-karakteri', [\App\Http\Controllers\RecreationQuizController::class, 'definition'])->name('recreation.quiz.definition');
    Route::post('/tatil-karakteri', [\App\Http\Controllers\RecreationQuizController::class, 'submit'])->name('recreation.quiz.submit');
});

// Chatbot v2 (araç çağırma) — AI_CHAT_V2_ENABLED ile ayrı açılır
Route::middleware([\App\Http\Middleware\EnsureAiChatV2Enabled::class, 'throttle:ai_search'])->group(function () {
    Route::post('/sohbet/akis', [\App\Http\Controllers\ChatV2Controller::class, 'stream'])->name('chat.v2.stream');
    Route::post('/sohbet/digerleri', [\App\Http\Controllers\ChatV2Controller::class, 'more'])->name('chat.v2.more');
    Route::post('/sohbet/sifirla', [\App\Http\Controllers\ChatV2Controller::class, 'reset'])->name('chat.v2.reset');
});

// ❄️ Sohbet asistanı uçları — AI_CHAT_ENABLED kapalıyken 404 (bkz. config/ai.php)
Route::middleware(\App\Http\Middleware\EnsureAiChatEnabled::class)->group(function () {
    Route::get('/yapay-zeka-arama', [AiSearchController::class, 'chat'])->name('ai.search');
    Route::get('/yapay-zeka-arama/{log}/turlar', [AiSearchController::class, 'showResults'])
        ->whereNumber('log')
        ->name('ai.search.results');
    Route::get('/yapay-zeka-arama/{uuid}', [AiSearchController::class, 'chat'])->name('ai.search.show')->whereUuid('uuid');
    Route::middleware('throttle:ai_search')->group(function () {
        Route::post('/yapay-zeka-arama/mesaj', [AiSearchController::class, 'sendMessage'])->name('ai.search.message');
        Route::post('/yapay-zeka-arama/mesaj/akis', [AiSearchController::class, 'streamMessage'])->name('ai.search.message.stream');
        Route::get('/yapay-zeka-arama-api', [AiSearchController::class, 'searchApi'])->name('ai.search.api');
        Route::post('/yapay-zeka-arama/{log}/reddet', [AiSearchController::class, 'rejectTour'])
            ->whereNumber('log')
            ->name('ai.search.reject');
    });
});

// Favorites (auth required)
Route::middleware('auth')->group(function () {
    Route::post('/favoriler/{tour}', [FavoriteController::class, 'toggle'])->name('favorites.toggle');
    Route::get('/favorilerim', [FavoriteController::class, 'index'])->name('favorites.index');

    // Müşterinin görebileceği aktif kuponlar (acentaların tanımladığı + admin global)
    Route::get('/kuponlarim', [CouponController::class, 'index'])->name('customer.coupons.index');
    Route::post('/kuponlarim/{coupon}/al', [CouponController::class, 'claim'])
        ->middleware('throttle:10,1')
        ->name('customer.coupons.claim');

    Route::post('/turlar/{tour}/yorum', [ReviewController::class, 'store'])->name('reviews.store');
    Route::delete('/yorum/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    // Profile
    Route::get('/profilim', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profilim/duzenle', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profilim', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profilim/sifre', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // Notifications
    Route::get('/bildirimler', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/bildirimler/{id}/okundu', [NotificationController::class, 'markAsRead'])->name('notifications.read');
    Route::post('/bildirimler/hepsini-oku', [NotificationController::class, 'markAllAsRead'])->name('notifications.readAll');
});

// iyzico ödeme callback — public + CSRF muaf (bootstrap/app.php içinde except listesine eklendi)
Route::post('/iyzico-callback/{order}', [CategoryLicenseController::class, 'iyzicoCallback'])
    ->name('agency.category-licenses.iyzico.callback');

// Click tracking redirect
Route::get('/git/{tour}', function (Tour $tour) {
    abort_unless($tour->isPubliclyVisible() && $tour->agency?->is_active, 404);

    TourClick::create([
        'tour_id' => $tour->id,
        'agency_id' => $tour->agency_id,
        'ip_address' => request()->ip(),
        'clicked_at' => now(),
    ]);
    // Yaşam-boyu sayaç: ham tour_clicks satırları retention ile silinse de toplam
    // korunur. Query builder: model event'leri ve updated_at tetiklenmesin.
    DB::table('tours')->where('id', $tour->id)->increment('clicks_count');

    $url = $tour->tour_url ?: $tour->agency->website_url ?: route('home');

    return redirect()->away($url);
})->name('tour.redirect');

// Auth
Route::get('/giris', function () {
    return view('auth.login');
})->name('login');
Route::post('/giris', function (Request $request) {
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (Auth::attempt($credentials)) {
        $request->session()->regenerate();
        $user = auth()->user();

        if ($user->isAdmin()) {
            return redirect()->route('admin.dashboard');
        }
        if ($user->isAgency()) {
            if (! $user->agencyApproved()) {
                return redirect()->route('agency.application.status');
            }

            return redirect()->route('agency.dashboard');
        }

        return redirect()->route('home');
    }

    return back()->withErrors(['email' => 'Geçersiz e-posta veya şifre.']);
})->middleware('throttle:login')->name('login.post');

// Şifre sıfırlama (guest)
Route::middleware('guest')->group(function () {
    Route::get('/sifremi-unuttum', [PasswordResetController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/sifremi-unuttum', [PasswordResetController::class, 'sendResetLink'])
        ->middleware('throttle:6,1')
        ->name('password.email');
    Route::get('/sifre-sifirla/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
    Route::post('/sifre-sifirla', [PasswordResetController::class, 'reset'])
        ->middleware('throttle:6,1')
        ->name('password.update');
});

Route::get('/kayit', function () {
    return view('auth.register');
})->name('register');
Route::get('/acenta-kayit', function () {
    return redirect()->route('register', ['type' => 'agency']);
})->name('agency.register');
Route::post('/kayit', function (Request $request) {
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
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = DB::transaction(function () use ($validated) {
            $agency = Agency::create([
                'name' => $validated['agency_name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'website_url' => $validated['website_url'] ?? null,
                'description' => $validated['description'] ?? null,
                'is_active' => false,
                'approval_status' => Agency::STATUS_PENDING,
                'legacy_category_access' => false,
            ]);

            return User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'agency',
                'agency_id' => $agency->id,
                'phone' => $validated['phone'] ?? null,
            ]);
        });

        Auth::login($user);

        return redirect()
            ->route('agency.application.status')
            ->with('success', 'Acenta başvurunuz alındı. Admin onayı sonrası paneliniz açılacak.');
    }

    $validated = $request->validate([
        'account_type' => 'required|in:agency,visitor',
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8|confirmed',
    ]);

    $user = User::create([
        'name' => $validated['name'],
        'email' => $validated['email'],
        'password' => Hash::make($validated['password']),
        'role' => 'visitor',
    ]);

    Auth::login($user);

    return redirect()->route('home')->with('success', 'Hoş geldiniz!');
})->middleware('throttle:register')->name('register.post');

Route::post('/cikis', function () {
    Auth::logout();

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
        Route::post('/kategori-yetkilendirme/sepet-ekstra-tur-hakki', [AgencyCategoryLicenseController::class, 'addSlotToCart'])->name('category-licenses.cart.add-slot');
        Route::delete('/kategori-yetkilendirme/sepet-ekstra-tur-hakki/{category}', [AgencyCategoryLicenseController::class, 'removeSlotFromCart'])->name('category-licenses.cart.remove-slot');
        Route::get('/kategori-yetkilendirme/odeme', [AgencyCategoryLicenseController::class, 'checkoutForm'])->name('category-licenses.checkout-form');
        Route::post('/kategori-yetkilendirme/odeme', [AgencyCategoryLicenseController::class, 'initiatePayment'])->name('category-licenses.initiate-payment');
        Route::get('/kategori-yetkilendirme/odeme/{order}/sonuc', [AgencyCategoryLicenseController::class, 'paymentResult'])->name('category-licenses.payment.result');
        Route::get('/turlar', [AgencyTourController::class, 'index'])->name('tours.index');
        Route::get('/turlar/ekle', [AgencyTourController::class, 'create'])->name('tours.create');
        Route::post('/turlar/ice-aktar', [TourImportController::class, 'fromUrl'])
            ->middleware('throttle:tour_import')
            ->name('tours.import');
        Route::post('/turlar/gorsel-yukle', [AgencyTourController::class, 'uploadImage'])
            ->middleware('throttle:60,1')
            ->name('tours.image.upload');
        Route::post('/turlar', [AgencyTourController::class, 'store'])->name('tours.store');
        Route::get('/turlar/{tour}', [AgencyTourController::class, 'show'])->name('tours.show');
        Route::get('/turlar/{tour}/duzenle', [AgencyTourController::class, 'edit'])->name('tours.edit');
        Route::put('/turlar/{tour}', [AgencyTourController::class, 'update'])->name('tours.update');
        Route::delete('/turlar/{tour}', [AgencyTourController::class, 'destroy'])->name('tours.destroy');

        // Tour dates
        Route::post('/turlar/{tour}/tarihler', [TourDateController::class, 'store'])->name('tours.dates.store');
        Route::put('/turlar/{tour}/tarihler/{date}', [TourDateController::class, 'update'])->name('tours.dates.update');
        Route::delete('/turlar/{tour}/tarihler/{date}', [TourDateController::class, 'destroy'])->name('tours.dates.destroy');

        // Profile
        Route::get('/profil', [App\Http\Controllers\Agency\ProfileController::class, 'edit'])->name('profile');
        Route::put('/profil', [App\Http\Controllers\Agency\ProfileController::class, 'update'])->name('profile.update');

        // Stats
        Route::get('/istatistik', [StatsController::class, 'index'])->name('stats');

        // Kategori talepleri (acenta admine yeni kategori önerir)
        Route::get('/kategori-talepleri', [CategoryRequestController::class, 'index'])->name('category-requests.index');
        Route::post('/kategori-talepleri', [CategoryRequestController::class, 'store'])->name('category-requests.store');

        // Campaigns
        Route::get('/kampanyalar', [CampaignController::class, 'index'])->name('campaigns.index');
        Route::post('/kampanyalar', [CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('/kampanyalar/{campaign}/duzenle', [CampaignController::class, 'edit'])->name('campaigns.edit');
        Route::put('/kampanyalar/{campaign}', [CampaignController::class, 'update'])->name('campaigns.update');
        Route::delete('/kampanyalar/{campaign}', [CampaignController::class, 'destroy'])->name('campaigns.destroy');

        // Coupons
        Route::resource('/kuponlar', App\Http\Controllers\Agency\CouponController::class)
            ->names('coupons')
            ->parameters(['kuponlar' => 'coupon'])
            ->only(['index', 'store', 'destroy']);
        Route::post('/kuponlar/{coupon}/toggle', [App\Http\Controllers\Agency\CouponController::class, 'toggle'])->name('coupons.toggle');
    });
});

// Admin Panel
Route::prefix('admin')->name('admin.')->middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/rubrik-inceleme', [\App\Http\Controllers\Admin\RubricReviewController::class, 'index'])->name('rubric.index');
    Route::post('/rubrik-inceleme/{score}/onayla', [\App\Http\Controllers\Admin\RubricReviewController::class, 'approve'])->name('rubric.approve');
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
    Route::post('/acentalar/{agency}/kategori-ekle', [AdminController::class, 'grantCategory'])->name('agencies.categories.grant');
    Route::post('/acentalar/{agency}/kategori-iptal/{subscription}', [AdminController::class, 'revokeCategory'])->name('agencies.categories.revoke');
    Route::post('/acenta-basvurulari/{agency}/onayla', [AdminController::class, 'approveAgencyApplication'])->name('agency-applications.approve');
    Route::post('/acenta-basvurulari/{agency}/reddet', [AdminController::class, 'rejectAgencyApplication'])->name('agency-applications.reject');
    Route::get('/turlar', [AdminController::class, 'tours'])->name('tours');
    // Trafik — hangi tur tıklanıyor/görüntüleniyor (dashboard kutularının hedefi)
    Route::get('/trafik', [App\Http\Controllers\Admin\TrafficController::class, 'index'])->name('traffic');
    Route::get('/trafik/{tour}', [App\Http\Controllers\Admin\TrafficController::class, 'show'])->name('traffic.show');
    // Kalkış şehri toplu düzenleme — "{şehir} kalkışlı" sayfa ailesinin girdisi
    Route::get('/kalkis-sehirleri', [App\Http\Controllers\Admin\DepartureCityController::class, 'index'])->name('departure-cities');
    Route::put('/kalkis-sehirleri', [App\Http\Controllers\Admin\DepartureCityController::class, 'update'])->name('departure-cities.update');
    Route::get('/vize-durumu', [App\Http\Controllers\Admin\TourVisaController::class, 'index'])->name('tour-visa');
    Route::put('/vize-durumu', [App\Http\Controllers\Admin\TourVisaController::class, 'update'])->name('tour-visa.update');
    Route::get('/destinasyonlar', [AdminController::class, 'destinations'])->name('destinations');
    Route::put('/destinasyonlar/{destination}', [AdminController::class, 'updateDestination'])->name('destinations.update');
    Route::post('/destinasyonlar/{destination}/toggle', [AdminController::class, 'toggleDestination'])->name('destinations.toggle');

    // Kategori talepleri (admin onayı)
    Route::get('/kategori-talepleri', [App\Http\Controllers\Admin\CategoryRequestController::class, 'index'])->name('category-requests.index');
    Route::post('/kategori-talepleri/{categoryRequest}/onayla', [App\Http\Controllers\Admin\CategoryRequestController::class, 'approve'])->name('category-requests.approve');
    Route::post('/kategori-talepleri/{categoryRequest}/reddet', [App\Http\Controllers\Admin\CategoryRequestController::class, 'reject'])->name('category-requests.reject');

    // Category Management
    Route::get('/kategoriler/ust-kategoriler', [CategoryController::class, 'parents'])->name('categories.parents');
    Route::post('/kategoriler/ust-kategori', [CategoryController::class, 'storeParent'])->name('categories.parents.store');
    Route::resource('/kategoriler', CategoryController::class)
        ->names('categories')
        ->parameters(['kategoriler' => 'category'])
        ->except(['create', 'show', 'edit']);

    // Coupons
    Route::resource('/kuponlar', App\Http\Controllers\Admin\CouponController::class)
        ->names('coupons')
        ->parameters(['kuponlar' => 'coupon'])
        ->except(['create', 'show', 'edit']);
    Route::post('/kuponlar/{coupon}/toggle', [App\Http\Controllers\Admin\CouponController::class, 'toggle'])->name('coupons.toggle');

    // Reports
    Route::get('/raporlar', [ReportController::class, 'index'])->name('reports.index');

    // Destination Profiles (AI-fed + manuel düzenlenebilir)
    Route::get('/destinasyon-profilleri', [DestinationProfileController::class, 'index'])->name('destination-profiles.index');
    Route::get('/destinasyon-profilleri/{profile}/duzenle', [DestinationProfileController::class, 'edit'])->name('destination-profiles.edit');
    Route::put('/destinasyon-profilleri/{profile}', [DestinationProfileController::class, 'update'])->name('destination-profiles.update');
    Route::post('/destinasyon-profilleri/{profile}/yeniden-uret', [DestinationProfileController::class, 'regenerate'])->name('destination-profiles.regenerate');
    Route::delete('/destinasyon-profilleri/{profile}', [DestinationProfileController::class, 'destroy'])->name('destination-profiles.destroy');
    Route::post('/kategoriler/{category}/toggle', [CategoryController::class, 'toggle'])->name('categories.toggle');

    // Blog management
    Route::get('/blog', [AdminBlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/ekle', [AdminBlogController::class, 'create'])->name('blog.create');
    Route::post('/blog', [AdminBlogController::class, 'store'])->name('blog.store');
    Route::get('/blog/{post}/duzenle', [AdminBlogController::class, 'edit'])->name('blog.edit');
    Route::put('/blog/{post}', [AdminBlogController::class, 'update'])->name('blog.update');
    Route::delete('/blog/{post}', [AdminBlogController::class, 'destroy'])->name('blog.destroy');

    // Banner management
    Route::get('/bannerlar', [BannerController::class, 'index'])->name('banners.index');
    Route::post('/bannerlar', [BannerController::class, 'store'])->name('banners.store');
    // Beyaz perde tek ayar: banner'a değil siteye ait, bu yüzden {banner}'sız.
    // DİKKAT: aşağıdaki {banner} rotalarından ÖNCE durmak zorunda. Sonra kalırsa
    // "beyaz-perde" bir banner anahtarı sanılır ve kaydetme 404 döner.
    Route::put('/bannerlar/beyaz-perde', [BannerController::class, 'updateVeil'])->name('banners.veil');
    // Mobil hero desen katmanı (şeffaflık + koyuluk) — {banner} rotalarından ÖNCE durmalı
    Route::put('/bannerlar/mobil-desen', [BannerController::class, 'updateDeco'])->name('banners.deco');
    Route::put('/bannerlar/{banner}', [BannerController::class, 'update'])->name('banners.update');
    Route::patch('/bannerlar/{banner}/toggle', [BannerController::class, 'toggle'])->name('banners.toggle');
    Route::delete('/bannerlar/{banner}', [BannerController::class, 'destroy'])->name('banners.destroy');

    // Featured Cities (Story) management
    Route::get('/one-cikan-sehirler', [FeaturedCityController::class, 'index'])->name('featured_cities.index');
    Route::post('/one-cikan-sehirler', [FeaturedCityController::class, 'store'])->name('featured_cities.store');
    Route::put('/one-cikan-sehirler/{city}', [FeaturedCityController::class, 'update'])->name('featured_cities.update');
    Route::delete('/one-cikan-sehirler/{city}', [FeaturedCityController::class, 'destroy'])->name('featured_cities.destroy');
    Route::post('/one-cikan-sehirler/{city}/gorsel', [FeaturedCityController::class, 'addImage'])->name('featured_cities.add_image');
    Route::delete('/one-cikan-sehirler/gorsel/{image}', [FeaturedCityController::class, 'destroyImage'])->name('featured_cities.destroy_image');
});

/*
|--------------------------------------------------------------------------
| Düz landing adresleri — DOSYANIN EN SONUNDA OLMALI
|--------------------------------------------------------------------------
|
| /kapadokya-turlari, /kultur-turlari gibi tek segmentli adresler.
|
| Rakip taramasında incelenen 9 sitenin hiçbiri kategori/destinasyon sayfasını
| query string ile sunmuyor; hepsi düz yol kullanıyor. Bu rota o kalıbı kurar.
|
| Kök dizini yutan bir catch-all DEĞİL: kısıt yalnız "-turlari" ile biten tek
| segmentli adresleri eşler, bu yüzden /turlar, /blog, /admin/... etkilenmez.
| Yine de en sona konur ki üstteki tüm açık rotalar öncelikli kalsın.
*/
Route::get('/{slug}', [\App\Http\Controllers\LandingController::class, 'show'])
    ->where('slug', \App\Support\LandingSlug::ROUTE_PATTERN)
    ->name('landing.show');
