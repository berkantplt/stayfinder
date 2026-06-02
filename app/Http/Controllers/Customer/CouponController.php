<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Coupon;

class CouponController extends Controller
{
    /**
     * Müşterinin görebileceği aktif + geçerli + limit dolmamış kuponlar.
     * Acentanın kaldırdığı, süresi biten, limiti dolan kuponlar listeden düşer.
     */
    public function index()
    {
        $coupons = Coupon::query()
            ->available()
            ->with('agency')
            ->orderByDesc('created_at')
            ->paginate(12);

        return view('customer.coupons.index', compact('coupons'));
    }
}
