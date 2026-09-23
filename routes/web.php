<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\BannerController;
use App\Http\Controllers\Admin\BlogController as AdminBlogController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CategoryLicenseController as AdminCategoryLicenseController;
use App\Http\Controllers\Admin\DepartureCityController;
use App\Http\Controllers\Admin\DestinationProfileController;
use App\Http\Controllers\Admin\FeaturedCityController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\RubricReviewController;
use App\Http\Controllers\Admin\TourVisaController;
use App\Http\Controllers\Admin\TrafficController;
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
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\ChatV2Controller;
use App\Http\Controllers\Customer\AccountActivityController;
use App\Http\Controllers\Customer\CouponController;
use App\Http\Controllers\Customer\SavedSearchController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\DiscoveryGuideController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecreationQuizController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\RobotsController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TourController;
use App\Http\Middleware\EnsureAiChatV2Enabled;
use App\Http\Middleware\EnsureDiscoveryGuideEnabled;
use App\Models\Agency;
use App\Models\SocialAccount;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\User;
use App\Support\LandingSlug;
use App\Support\LoginFlow;
use App\Support\LoginReturn;
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
// Tur eşleştirme testi — LLM'siz, deterministik çalışır
Route::middleware('throttle:ai_search')->group(function () {
    Route::get('/tatil-karakteri', [RecreationQuizController::class, 'definition'])->name('recreation.quiz.definition');
    Route::post('/tatil-karakteri', [RecreationQuizController::class, 'submit'])->name('recreation.quiz.submit');
});

// AI Keşif Rehberi — şehir + gün girdisinden günlere bölünmüş içerik planı.
// AI_DISCOVERY_ENABLED=false ile kapatılabilir. Üretim uçları OpenAI çağrısı
// tetiklediği için ai_search limitinde; status polling ucu daha gevşek search
// limitinde (3 sn'lik poll aralığı anonim ai_search limitini aşardı).
Route::middleware(EnsureDiscoveryGuideEnabled::class)->group(function () {
    Route::get('/kesif-rehberi', [DiscoveryGuideController::class, 'index'])->name('discovery.index');
    Route::get('/kesif-rehberi/{guide}', [DiscoveryGuideController::class, 'show'])
        ->whereUuid('guide')
        ->name('discovery.show');
    Route::get('/kesif-rehberi/{guide}/durum', [DiscoveryGuideController::class, 'status'])
        ->whereUuid('guide')
        ->middleware('throttle:search')
        ->name('discovery.status');
    Route::middleware('throttle:ai_search')->group(function () {
        Route::post('/kesif-rehberi', [DiscoveryGuideController::class, 'store'])->name('discovery.store');
        Route::post('/kesif-rehberi/{guide}/kisisellestir', [DiscoveryGuideController::class, 'personalize'])
            ->whereUuid('guide')
            ->name('discovery.personalize');
    });
});

// Chatbot v2 (araç çağırma) — AI_CHAT_V2_ENABLED ile ayrı açılır
Route::middleware([EnsureAiChatV2Enabled::class, 'throttle:ai_search'])->group(function () {
    Route::post('/sohbet/akis', [ChatV2Controller::class, 'stream'])->name('chat.v2.stream');
    Route::post('/sohbet/digerleri', [ChatV2Controller::class, 'more'])->name('chat.v2.more');
    Route::post('/sohbet/sifirla', [ChatV2Controller::class, 'reset'])->name('chat.v2.reset');
});

// AI arama uçları — sohbet v1 kaldırıldı, bunlar durumsuz arama uçlarıdır.
// Bayrak/gate YOK: all_results_url (TourSearchService) ve tur kartlarındaki
// reddet butonu bunlara bağlı, 404 dönmemeli.
Route::get('/yapay-zeka-arama/{log}/turlar', [AiSearchController::class, 'showResults'])
    ->whereNumber('log')
    ->name('ai.search.results');
Route::middleware('throttle:ai_search')->group(function () {
    Route::get('/yapay-zeka-arama-api', [AiSearchController::class, 'searchApi'])->name('ai.search.api');
    Route::post('/yapay-zeka-arama/{log}/reddet', [AiSearchController::class, 'rejectTour'])
        ->whereNumber('log')
        ->name('ai.search.reject');
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

    // Kayıtlı aramalar: /turlar filtresi kaydedilir, uyan yeni turda bildirim
    Route::get('/kayitli-aramalarim', [SavedSearchController::class, 'index'])->name('customer.saved-searches.index');
    Route::post('/kayitli-aramalarim', [SavedSearchController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('customer.saved-searches.store');
    Route::delete('/kayitli-aramalarim/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('customer.saved-searches.destroy');
    // D7: yorum yazma sınırı — kullanıcı başına saatte 5 (kupon almada 10/dk vardı, burada yoktu)
    Route::post('/turlar/{tour}/yorum', [ReviewController::class, 'store'])
        ->middleware('throttle:5,60')
        ->name('reviews.store');
    Route::delete('/yorum/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    // Profile — D1: /hesabim ortak giriş kapısı, sekmeler partials/account-nav
    Route::get('/hesabim', fn () => redirect()->route('profile.show'))->name('account.index');
    // D9: Aramalarım (AI aramaları, rehberler, karşılaştırma listesi) + sunucu karşılaştırma listesi
    Route::get('/hesabim/aramalarim', [AccountActivityController::class, 'index'])->name('account.activity');
    Route::get('/karsilastirma-listem', [AccountActivityController::class, 'compareIndex'])->name('account.compare.index');
    Route::put('/karsilastirma-listem', [AccountActivityController::class, 'compareSync'])
        ->middleware('throttle:60,1')
        ->name('account.compare.sync');
    Route::delete('/karsilastirma-listem/{tour}', [AccountActivityController::class, 'compareRemove'])->name('account.compare.remove');
    Route::get('/profilim', [ProfileController::class, 'show'])->name('profile.show');
    Route::get('/profilim/duzenle', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::get('/profilim/guvenlik', [ProfileController::class, 'security'])->name('profile.security');
    Route::put('/profilim', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profilim/sifre', [ProfileController::class, 'updatePassword'])->name('profile.password');
    Route::delete('/profilim/baglanti/{provider}', [SocialAuthController::class, 'destroy'])
        ->whereIn('provider', SocialAccount::PROVIDERS)
        ->name('social.destroy');
    // D5: e-posta değişikliği onayı (yeniden gönder / iptal)
    Route::post('/profilim/eposta/yeniden-gonder', [ProfileController::class, 'resendEmailChange'])
        ->middleware('throttle:3,10')
        ->name('profile.email.resend');
    Route::delete('/profilim/eposta/bekleyen', [ProfileController::class, 'cancelEmailChange'])->name('profile.email.cancel');
    // D4: KVKK — verilerimi indir (JSON), hesabımı sil (şifre onayı + 30 gün bekleme)
    Route::get('/profilim/verilerim', [ProfileController::class, 'exportData'])
        ->middleware('throttle:6,10')
        ->name('profile.data-export');
    Route::post('/profilim/sil', [ProfileController::class, 'requestDeletion'])
        ->middleware('throttle:6,10')
        ->name('profile.delete');

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

        // Yönlendirme mantığı sosyal girişle ortak (bkz. LoginFlow)
        return LoginFlow::redirectAfterLogin(auth()->user(), $request);
    }

    return back()->withErrors(['email' => 'Geçersiz e-posta veya şifre.']);
})->middleware('throttle:login')->name('login.post');

// D5: yeni e-posta adresine giden imzalı onay bağlantısı — başka cihazda,
// oturumsuz da açılabilir; imza + süre (60 dk) + adres özeti doğrulanır.
Route::get('/profilim/eposta/onayla/{user}/{hash}', [ProfileController::class, 'verifyEmailChange'])
    ->whereNumber('user')
    ->middleware(['signed', 'throttle:6,1'])
    ->name('profile.email.verify');

/*
 | Sosyal giriş (Google / Apple). Tek route çifti hem giriş/kayıt hem de
 | profilden bağlama için çalışır; hangi mod olduğuna SocialIntent karar verir.
 | Callback POST'u da kabul eder: Apple sonucu form_post ile gönderir.
 */
Route::get('/giris/{provider}', [SocialAuthController::class, 'redirect'])
    ->whereIn('provider', SocialAccount::PROVIDERS)
    ->middleware('throttle:social')
    ->name('social.redirect');
Route::match(['get', 'post'], '/giris/{provider}/callback', [SocialAuthController::class, 'callback'])
    ->whereIn('provider', SocialAccount::PROVIDERS)
    ->middleware('throttle:social')
    ->name('social.callback');

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
            cache()->forget('admin:bekleyen-sayaclar'); // A7: admin rozeti
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
                'role' => User::ROLE_AGENCY,
                'agency_id' => $agency->id,
                'phone' => $validated['phone'] ?? null,
            ]);
        });

        $user->markPasswordSet();
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
        'role' => User::ROLE_VISITOR,
    ]);

    // Şifreyi kendisi belirledi — sosyal giriş ile açılan hesapta bu boş kalır
    // ve profilde "Şifre belirle" dalı açılır (bkz. User::hasPassword).
    $user->markPasswordSet();
    Auth::login($user);

    // Kalpten gelen ziyaretçi kayıt olunca da favorisi eklenir ve tura döner
    return LoginReturn::redirectAfter($user, $request, 'Hoş geldiniz!');
})->middleware('throttle:register')->name('register.post');

Route::post('/cikis', function (Request $request) {
    Auth::logout();
    // D6: oturum geçersiz kılınmazsa eski kullanıcının şifre izi (password_hash_web)
    // kalır; aynı tarayıcıda giriş yapan ikinci kullanıcı AuthenticateSession
    // tarafından ilk istekte düşürülürdü. Ayrıca oturum sabitleme önlemi.
    $request->session()->invalidate();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->name('logout');

// Agency Panel
Route::prefix('acenta')->name('agency.')->middleware(['auth', 'role:agency'])->group(function () {
    Route::get('/basvuru-durumu', [AgencyDashboardController::class, 'applicationStatus'])->name('application.status');

    Route::middleware('agency.approved')->group(function () {
        Route::get('/', [AgencyDashboardController::class, 'index'])->name('dashboard');
        Route::get('/kategori-yetkilendirme', [AgencyCategoryLicenseController::class, 'index'])->name('category-licenses.index');
        Route::get('/kategori-yetkilendirme/sepet', [AgencyCategoryLicenseController::class, 'showCart'])->name('category-licenses.cart.show');
        Route::get('/kategori-yetkilendirme/satin-alimlar', [AgencyCategoryLicenseController::class, 'orders'])->name('category-licenses.orders');
        Route::post('/kategori-yetkilendirme/sepet', [AgencyCategoryLicenseController::class, 'addToCart'])->name('category-licenses.cart.add');
        Route::delete('/kategori-yetkilendirme/sepet/{category}', [AgencyCategoryLicenseController::class, 'removeFromCart'])->name('category-licenses.cart.remove');
        Route::post('/kategori-yetkilendirme/sepet-ekstra-tur-hakki', [AgencyCategoryLicenseController::class, 'addSlotToCart'])->name('category-licenses.cart.add-slot');
        Route::delete('/kategori-yetkilendirme/sepet-ekstra-tur-hakki/{category}', [AgencyCategoryLicenseController::class, 'removeSlotFromCart'])->name('category-licenses.cart.remove-slot');
        Route::post('/kategori-yetkilendirme/abonelik/{subscription}/iptal', [AgencyCategoryLicenseController::class, 'cancelSubscription'])->name('category-licenses.subscription.cancel');
        Route::post('/kategori-yetkilendirme/abonelik/{subscription}/yenilemeyi-ac', [AgencyCategoryLicenseController::class, 'resumeSubscription'])->name('category-licenses.subscription.resume');
        Route::post('/kategori-yetkilendirme/abonelik/{subscription}/ekstra-hak-plani', [AgencyCategoryLicenseController::class, 'planSlotReduction'])->name('category-licenses.subscription.slot-plan');
        Route::delete('/kategori-yetkilendirme/kayitli-kart', [AgencyCategoryLicenseController::class, 'deleteStoredCard'])->name('category-licenses.stored-card.delete');
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
        // A10: arşivden geri alma — silinmiş kayıt bağlanabilsin diye withTrashed
        Route::post('/turlar/{tour}/geri-al', [AgencyTourController::class, 'restore'])->withTrashed()->name('tours.restore');

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
    Route::get('/rubrik-inceleme', [RubricReviewController::class, 'index'])->name('rubric.index');
    Route::post('/rubrik-inceleme/{score}/onayla', [RubricReviewController::class, 'approve'])->name('rubric.approve');
    Route::get('/kategori-yetkilendirme', [AdminCategoryLicenseController::class, 'index'])->name('category-licenses.index');
    Route::get('/kategori-yetkilendirme/kategori-tarifesi', [AdminCategoryLicenseController::class, 'pricing'])->name('category-licenses.pricing');
    Route::get('/kategori-yetkilendirme/acenta-erisimleri', [AdminCategoryLicenseController::class, 'access'])->name('category-licenses.access');
    Route::get('/kategori-yetkilendirme/siparisler', [AdminCategoryLicenseController::class, 'orders'])->name('category-licenses.orders');
    // B9: sipariş detayı + ödeme bekleyen siparişe müdahale
    Route::get('/kategori-yetkilendirme/siparisler/{order}', [AdminCategoryLicenseController::class, 'orderShow'])->whereNumber('order')->name('category-licenses.orders.show');
    Route::post('/kategori-yetkilendirme/siparisler/{order}/tamamla', [AdminCategoryLicenseController::class, 'orderComplete'])->whereNumber('order')->name('category-licenses.orders.complete');
    Route::post('/kategori-yetkilendirme/siparisler/{order}/iptal', [AdminCategoryLicenseController::class, 'orderCancel'])->whereNumber('order')->name('category-licenses.orders.cancel');
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
    Route::get('/trafik', [TrafficController::class, 'index'])->name('traffic');
    Route::get('/trafik/{tour}', [TrafficController::class, 'show'])->name('traffic.show');
    // Kalkış şehri toplu düzenleme — "{şehir} kalkışlı" sayfa ailesinin girdisi
    Route::get('/kalkis-sehirleri', [DepartureCityController::class, 'index'])->name('departure-cities');
    Route::put('/kalkis-sehirleri', [DepartureCityController::class, 'update'])->name('departure-cities.update');
    Route::get('/vize-durumu', [TourVisaController::class, 'index'])->name('tour-visa');
    Route::put('/vize-durumu', [TourVisaController::class, 'update'])->name('tour-visa.update');
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
    Route::get('/raporlar/disa-aktar', [ReportController::class, 'export'])->name('reports.export'); // B13: CSV

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
Route::get('/{slug}', [LandingController::class, 'show'])
    ->where('slug', LandingSlug::ROUTE_PATTERN)
    ->name('landing.show');
