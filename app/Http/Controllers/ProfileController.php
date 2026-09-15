<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\TourView;
use App\Models\User;
use App\Notifications\EmailChangeVerificationNotification;
use App\Services\Account\AccountDeletionService;
use App\Support\TurkishCities;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function show()
    {
        $user = auth()->user();

        // D3: profil özet sayfası — tümünü çekmek yerine sayılar + son birkaç kayıt;
        // tam listeler kendi sayfalarında (favoriler sayfalı).
        $favoriteCount = $user->favoriteTours()->active()->count();
        $favorites = $user->favoriteTours()->with('agency')->active()->latest('favorites.created_at')->orderByDesc('tours.id')->take(6)->get();
        $reviewCount = Review::where('user_id', $user->id)->count();
        $reviewAvg = $reviewCount > 0 ? round((float) Review::where('user_id', $user->id)->avg('rating'), 1) : null;
        $reviews = Review::where('user_id', $user->id)->with('tour.agency')->latest()->orderByDesc('id')->take(5)->get();
        $viewCount = TourView::where('user_id', $user->id)->count();

        return view('profile.show', compact('user', 'favorites', 'favoriteCount', 'reviews', 'reviewCount', 'reviewAvg', 'viewCount'));
    }

    public function edit()
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    /** D1 — Güvenlik sekmesi: şifre (ileride e-posta onayı, veri indirme, hesap silme). */
    public function security()
    {
        return view('profile.security', ['user' => auth()->user()]);
    }

    public function update(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:20',
            // Şehir listeden: kalkış filtresiyle eşleşmesi için serbest metin değil
            'city' => ['nullable', 'string', 'max:100', Rule::in(TurkishCities::all())],
            'bio' => 'nullable|string|max:500',
            'birth_date' => 'nullable|date|before:today',
            'avatar_file' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'remove_avatar' => 'nullable|boolean',
        ]);

        if ($request->boolean('remove_avatar')) {
            if ($user->avatar && ! Str::startsWith($user->avatar, ['http://', 'https://'])) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = null;
        } elseif ($request->hasFile('avatar_file')) {
            if ($user->avatar && ! Str::startsWith($user->avatar, ['http://', 'https://'])) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar_file')->store('avatars', 'public');
        }

        unset($validated['avatar_file'], $validated['remove_avatar']);

        // D5: e-posta doğrudan değişmez — yeni adres onaylanana kadar bekler,
        // eski adres geçerli kalır (yazım hatası / erişilemeyen adres kilidi önlenir).
        $newEmail = mb_strtolower(trim((string) $validated['email']));
        unset($validated['email']);
        $emailChanged = $newEmail !== mb_strtolower((string) $user->email);

        $user->update($validated);

        if ($emailChanged) {
            $this->startEmailChange($user, $newEmail);

            return redirect()->route('profile.edit')->with('success',
                'Profiliniz güncellendi. '.$newEmail.' adresine onay bağlantısı gönderdik; onaylayana kadar '.$user->email.' geçerli kalır.');
        }

        if ($user->pending_email !== null && $newEmail === mb_strtolower((string) $user->email)) {
            // Kullanıcı eski adresini geri yazdı: bekleyen değişiklik iptal
            $user->forceFill(['pending_email' => null, 'pending_email_requested_at' => null])->save();
        }

        return redirect()->route('profile.show')->with('success', 'Profiliniz güncellendi!');
    }

    /** D4 — Kullanıcının verileri tek JSON dosyası olarak (KVKK veri taşınabilirliği). */
    public function exportData()
    {
        $user = auth()->user();

        $data = [
            'olusturma' => now()->toIso8601String(),
            'profil' => $user->only(['name', 'email', 'phone', 'city', 'bio', 'birth_date', 'created_at', 'email_verified_at']),
            'favoriler' => $user->favoriteTours()->withTrashed()->get()->map(fn ($t) => [
                'tur' => $t->title, 'adres' => route('tours.show', $t), 'eklenme' => $t->pivot->created_at?->toIso8601String(),
                'ekleme_fiyati' => $t->pivot->price_at_save, 'para_birimi' => $t->pivot->currency_at_save,
            ])->values(),
            'yorumlar' => $user->reviews()->with('tour')->get()->map(fn ($r) => [
                'tur' => $r->tour?->title, 'puan' => $r->rating, 'yorum' => $r->comment, 'tarih' => $r->created_at?->toIso8601String(),
            ])->values(),
            'kayitli_aramalar' => $user->savedSearches()->get()->map(fn ($s) => [
                'ad' => $s->name, 'filtreler' => $s->params, 'tarih' => $s->created_at?->toIso8601String(),
            ])->values(),
            'kupon_kullanimlari' => \App\Models\CouponUsage::with('coupon')->where('user_id', $user->id)->get()->map(fn ($u) => [
                'kod' => $u->coupon?->code, 'tarih' => $u->used_at?->toIso8601String(), 'indirim' => $u->discount_amount,
            ])->values(),
            'bildirimler' => $user->notifications()->latest()->take(200)->get()->map(fn ($n) => [
                'baslik' => $n->data['title'] ?? null, 'mesaj' => $n->data['message'] ?? null, 'tarih' => $n->created_at?->toIso8601String(), 'okundu' => $n->read_at?->toIso8601String(),
            ])->values(),
            'ai_aramalari' => \App\Models\AiSearchLog::where('user_id', $user->id)->latest()->take(500)->get(['raw_query', 'created_at'])->map(fn ($l) => [
                'sorgu' => $l->raw_query, 'tarih' => $l->created_at?->toIso8601String(),
            ])->values(),
            'kesif_rehberleri' => \App\Models\DiscoveryGuide::where('user_id', $user->id)->latest()->get(['destination_input', 'duration_days', 'status', 'created_at'])->map(fn ($g) => [
                'sehir' => $g->destination_input, 'gun' => $g->duration_days, 'durum' => $g->status, 'tarih' => $g->created_at?->toIso8601String(),
            ])->values(),
        ];

        return response()->json($data, 200, [
            'Content-Disposition' => 'attachment; filename="turxtur-verilerim-'.now()->format('Y-m-d').'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * D4 — Hesap silme talebi: şifre onayı → talep zamanı yazılır, oturum kapatılır.
     * 30 gün içinde giriş yapılırsa iptal (login closure), sonra anonimleştirme
     * (users:purge-deleted). Yalnız müşteri hesabı; acenta/admin bu yoldan silinmez.
     */
    public function requestDeletion(Request $request, AccountDeletionService $service)
    {
        $user = auth()->user();

        abort_unless($user->isCustomer(), 403, 'Acenta ve yönetici hesapları bu yoldan silinemez.');

        $request->validate([
            'password' => ['required', 'current_password'],
            'onay' => ['accepted'],
        ], [
            'password.current_password' => 'Şifre hatalı.',
            'onay.accepted' => 'Silme sonuçlarını okuyup onaylamanız gerekir.',
        ]);

        $service->request($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success',
            'Hesap silme talebiniz alındı. '.AccountDeletionService::WAITING_DAYS.' gün içinde tekrar giriş yaparsanız talep iptal edilir; aksi hâlde hesabınız ve kişisel verileriniz kalıcı olarak anonimleştirilir.');
    }

    /** D5 — bekleyen adres için onay bağlantısını yeniden gönderir. */
    public function resendEmailChange()
    {
        $user = auth()->user();

        if (! $user->pending_email) {
            return redirect()->route('profile.edit')->withErrors(['email' => 'Bekleyen bir e-posta değişikliği yok.']);
        }

        $this->startEmailChange($user, $user->pending_email);

        return redirect()->route('profile.edit')->with('success', 'Onay bağlantısı '.$user->pending_email.' adresine yeniden gönderildi.');
    }

    /** D5 — bekleyen e-posta değişikliğini iptal eder. */
    public function cancelEmailChange()
    {
        auth()->user()->forceFill(['pending_email' => null, 'pending_email_requested_at' => null])->save();

        return redirect()->route('profile.edit')->with('success', 'E-posta değişikliği iptal edildi.');
    }

    /**
     * D5 — İmzalı bağlantı (signed middleware: imza + 60 dk). Oturum şart değil:
     * yeni adres başka cihazda açılabilir. hash = bekleyen adresin sha1'i; bağlantı
     * üretildikten sonra adres değiştiyse eski bağlantı geçersizdir.
     */
    public function verifyEmailChange(Request $request, User $user, string $hash)
    {
        abort_unless($user->pending_email !== null && hash_equals(sha1($user->pending_email), $hash), 403, 'Onay bağlantısı geçersiz veya bekleyen değişiklik yok.');

        if (User::where('email', $user->pending_email)->whereKeyNot($user->id)->exists()) {
            $user->forceFill(['pending_email' => null, 'pending_email_requested_at' => null])->save();

            return redirect()->route(auth()->check() ? 'profile.edit' : 'login')
                ->withErrors(['email' => 'Bu e-posta adresi artık başka bir hesapta kullanılıyor; değişiklik iptal edildi.']);
        }

        $user->forceFill([
            'email' => $user->pending_email,
            'email_verified_at' => now(),
            'pending_email' => null,
            'pending_email_requested_at' => null,
        ])->save();

        return redirect()->route(auth()->id() === $user->id ? 'profile.edit' : 'login')
            ->with('success', 'E-posta adresiniz onaylandı: '.$user->email);
    }

    /** D5 — bekleyen adresi kaydeder, yeni adrese imzalı bağlantı yollar (posta yoksa ekranda gösterir). */
    private function startEmailChange(User $user, string $email): void
    {
        $user->forceFill(['pending_email' => $email, 'pending_email_requested_at' => now()])->save();

        $url = URL::temporarySignedRoute('profile.email.verify', now()->addMinutes(60), [
            'user' => $user->id,
            'hash' => sha1($email),
        ]);

        try {
            Notification::route('mail', $email)->notify(new EmailChangeVerificationNotification($url, $user->name, $user->email));
        } catch (\Throwable $e) {
            report($e); // taşıyıcı hatası profil güncellemesini bozmasın; bağlantı yeniden gönderilebilir
        }

        // Posta hesabı henüz yok (MAIL_MAILER=log): bağlantı ekranda bir kez gösterilir.
        if (in_array(config('mail.default'), ['log', 'array'], true)) {
            session()->flash('email_verify_link', $url);
        }
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if (! Hash::check($request->current_password, auth()->user()->password)) {
            return back()->withErrors(['current_password' => 'Mevcut şifre hatalı.']);
        }

        auth()->user()->update(['password' => Hash::make($request->password)]);

        // D6: ele geçirilmiş hesapta şifre değişse bile saldırganın oturumu açık
        // kalıyordu. Diğer cihazlar kapatılır (AuthenticateSession middleware'i
        // şifre hash'i değişen oturumları düşürür), bu oturumun kimliği yenilenir.
        Auth::logoutOtherDevices($request->password);
        $request->session()->regenerate();

        return redirect()->route('profile.security')->with('success', 'Şifreniz güncellendi; diğer cihazlardaki oturumlarınız kapatıldı.');
    }
}
