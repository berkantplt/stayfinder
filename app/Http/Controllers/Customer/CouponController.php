<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\CouponUsage;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CouponController extends Controller
{
    /**
     * Müşterinin görebileceği kuponlar: aktif + tarih-geçerli + limiti dolmamış
     * olanlar VE kullanıcının daha önce aldığı kuponlar (limit dolsa bile kodunu
     * görmeye devam etsin). Kod, kupon alınana kadar maskelidir.
     */
    public function index()
    {
        $claimedCouponIds = CouponUsage::query()
            ->where('user_id', auth()->id())
            ->pluck('coupon_id');

        $coupons = Coupon::query()
            ->where(function ($query) use ($claimedCouponIds) {
                $query->where(fn ($q) => $q->available());

                if ($claimedCouponIds->isNotEmpty()) {
                    $query->orWhereIn('id', $claimedCouponIds);
                }
            })
            ->with('agency')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('customer.coupons.index', [
            'coupons' => $coupons,
            'claimedCouponIds' => $claimedCouponIds->all(),
        ]);
    }

    /**
     * Kupon alma (claim): rezervasyon platform dışında (acenta sitesinde)
     * yapıldığı için gerçek harcama izlenemez — "kullanım" burada kodu açmak
     * demektir. used_count bu anda artar, max_uses limiti burada işler.
     */
    public function claim(Coupon $coupon)
    {
        $userId = auth()->id();

        $alreadyClaimed = CouponUsage::query()
            ->where('coupon_id', $coupon->id)
            ->where('user_id', $userId)
            ->exists();

        if ($alreadyClaimed) {
            return back()->with('success', 'Bu kuponu zaten almıştınız — kodu karttan kopyalayabilirsiniz.');
        }

        DB::transaction(function () use ($coupon, $userId) {
            // Eşzamanlı taleplere karşı satır kilidi altında limit kontrolü
            $fresh = Coupon::whereKey($coupon->id)->lockForUpdate()->firstOrFail();

            $unavailable = ! $fresh->is_active
                || ($fresh->starts_at && $fresh->starts_at->isFuture())
                || ($fresh->expires_at && $fresh->expires_at->isPast())
                || ($fresh->max_uses !== null && $fresh->used_count >= $fresh->max_uses);

            if ($unavailable) {
                throw ValidationException::withMessages([
                    'coupon' => 'Bu kupon artık kullanılabilir değil (süresi doldu, kaldırıldı veya kullanım hakkı tükendi).',
                ]);
            }

            $stillNotClaimed = ! CouponUsage::query()
                ->where('coupon_id', $fresh->id)
                ->where('user_id', $userId)
                ->exists();

            if ($stillNotClaimed) {
                CouponUsage::create([
                    'coupon_id' => $fresh->id,
                    'user_id' => $userId,
                    'used_at' => now(),
                ]);

                $fresh->increment('used_count');
            }
        });

        return back()->with('success', 'Kupon alındı! Kodu kopyalayıp acenta sitesinde kullanabilirsiniz.');
    }
}
