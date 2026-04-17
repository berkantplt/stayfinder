<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index()
    {
        $agencyId = $this->currentAgencyId();

        $coupons = Coupon::query()
            ->withCount('usages')
            ->where('agency_id', $agencyId)
            ->latest()
            ->get();

        return view('agency.coupons.index', compact('coupons'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50|unique:coupons,code',
            'discount_type' => 'required|in:percent,fixed',
            'discount_value' => 'required|numeric|min:0',
            'min_purchase_amount' => 'nullable|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['agency_id'] = $this->currentAgencyId();
        $validated['is_active'] = $request->boolean('is_active');

        Coupon::create($validated);

        return back()->with('success', 'Kupon başarıyla eklendi.');
    }

    public function destroy(Coupon $coupon)
    {
        $this->authorizeCoupon($coupon);

        $coupon->delete();

        return back()->with('success', 'Kupon silindi.');
    }

    public function toggle(Coupon $coupon)
    {
        $this->authorizeCoupon($coupon);

        $coupon->update(['is_active' => !$coupon->is_active]);

        return back()->with('success', 'Kupon durumu güncellendi.');
    }

    private function authorizeCoupon(Coupon $coupon): void
    {
        if ((int) $coupon->agency_id !== $this->currentAgencyId()) {
            abort(403, 'Bu kupon üzerinde işlem yetkiniz yok.');
        }
    }

    private function currentAgencyId(): int
    {
        $agencyId = (int) auth()->user()->agency_id;

        if ($agencyId <= 0) {
            abort(403, 'Acenta kaydı bulunamadı.');
        }

        return $agencyId;
    }
}
