@extends('layouts.app')
@section('title', 'Kuponlarım — StayFinder')

@section('content')
<div class="container">
    <div class="section">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:4px;">
            <span style="font-size:26px;">🎟️</span>
            <h1 style="font-size:24px;font-weight:800;margin:0;">Kuponlarım</h1>
        </div>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:24px;">
            Acentaların ve StayFinder'ın tanımladığı kuponlar burada. "Kuponu Al" ile kodu açın,
            rezervasyon sırasında acenta sitesinde kullanın. Aldığınız kuponlar limit dolsa bile burada kalır.
        </p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">
                @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
            </div>
        @endif

        @if($coupons->count())
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;">
                @foreach($coupons as $coupon)
                    @php
                        $isPercent = $coupon->discount_type === 'percent';
                        $accentColor = $isPercent ? '#10b981' : '#6366f1';
                        $accentBg = $isPercent ? '#ecfdf5' : '#eef2ff';
                        $borderColor = $isPercent ? '#a7f3d0' : '#c7d2fe';
                        $remaining = $coupon->remaining_uses; // null = sınırsız
                        $isClaimed = in_array($coupon->id, $claimedCouponIds);
                    @endphp

                    <div class="coupon-card" style="background:#fff;border:2px dashed {{ $borderColor }};border-radius:16px;overflow:hidden;display:flex;flex-direction:column;transition:transform .15s,box-shadow .15s;">

                        {{-- Üst rozet: indirim --}}
                        <div style="background:{{ $accentBg }};padding:18px 18px 14px;display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <div style="font-size:11px;color:#64748b;font-weight:700;letter-spacing:.5px;text-transform:uppercase;">
                                    {{ $isPercent ? 'Yüzde İndirim' : 'Sabit İndirim' }}
                                </div>
                                <div style="font-size:28px;font-weight:800;color:{{ $accentColor }};letter-spacing:-0.5px;line-height:1;margin-top:4px;">
                                    {{ $coupon->formatted_discount }}
                                </div>
                            </div>
                            <div style="font-size:28px;">{{ $isPercent ? '🏷️' : '💸' }}</div>
                        </div>

                        {{-- Kod: alınana kadar maskeli --}}
                        <div style="padding:14px 18px;border-top:1px dashed {{ $borderColor }};border-bottom:1px dashed {{ $borderColor }};display:flex;align-items:center;justify-content:space-between;gap:10px;background:#fafafa;">
                            @if($isClaimed)
                                <div style="font-family:monospace;font-size:16px;font-weight:800;color:#0f172a;letter-spacing:1px;flex:1;overflow-x:auto;">{{ $coupon->code }}</div>
                                <button type="button" onclick="window.copyCouponCode(this, '{{ $coupon->code }}')" style="background:{{ $accentColor }};color:#fff;border:none;border-radius:8px;padding:7px 12px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                                    📋 Kopyala
                                </button>
                            @else
                                <div style="font-family:monospace;font-size:16px;font-weight:800;color:#94a3b8;letter-spacing:3px;flex:1;">●●●●●●●●</div>
                                <form method="POST" action="{{ route('customer.coupons.claim', $coupon) }}" style="margin:0;">
                                    @csrf
                                    <button type="submit" style="background:{{ $accentColor }};color:#fff;border:none;border-radius:8px;padding:7px 12px;font-size:12px;font-weight:700;cursor:pointer;white-space:nowrap;">
                                        🎟️ Kuponu Al
                                    </button>
                                </form>
                            @endif
                        </div>

                        {{-- Bilgi listesi --}}
                        <div style="padding:14px 18px;flex:1;display:flex;flex-direction:column;gap:8px;font-size:13px;color:#475569;">
                            <div style="display:flex;align-items:center;gap:6px;">
                                <span>🏢</span>
                                <span>{{ $coupon->agency?->name ?? 'StayFinder' }}</span>
                            </div>
                            @if($coupon->min_purchase_amount)
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span>🛒</span>
                                    <span>Min. {{ number_format((float) $coupon->min_purchase_amount, 0, ',', '.') }} ₺</span>
                                </div>
                            @endif
                            @if($coupon->expires_at)
                                <div style="display:flex;align-items:center;gap:6px;">
                                    <span>⏰</span>
                                    <span>Son kullanım: <strong>{{ $coupon->expires_at->format('d M Y') }}</strong></span>
                                </div>
                            @else
                                <div style="display:flex;align-items:center;gap:6px;color:#94a3b8;">
                                    <span>⏰</span>
                                    <span>Süresiz</span>
                                </div>
                            @endif
                            @if($remaining !== null)
                                <div style="display:flex;align-items:center;gap:6px;color:{{ $remaining <= 3 ? '#dc2626' : '#475569' }};">
                                    <span>🎯</span>
                                    <span>Kalan kullanım: <strong>{{ $remaining }}</strong></span>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div style="margin-top:24px;">{{ $coupons->links() }}</div>
        @else
            <div style="text-align:center;padding:48px 24px;background:#f8fafc;border:1px dashed #cbd5e1;border-radius:16px;">
                <div style="font-size:48px;margin-bottom:12px;">🎟️</div>
                <div style="font-size:16px;font-weight:700;color:#0f172a;margin-bottom:4px;">Şu an kullanılabilir kupon yok</div>
                <div style="font-size:13px;color:#64748b;">Acentalar ve StayFinder yeni kampanya tanımladığında burada görürsün.</div>
            </div>
        @endif
    </div>
</div>

<style>
    .coupon-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 24px rgba(0,0,0,.08);
    }
</style>

<script>
    window.copyCouponCode = function (btn, code) {
        const original = btn.innerHTML;
        navigator.clipboard.writeText(code).then(() => {
            btn.innerHTML = '✓ Kopyalandı';
            btn.style.background = '#0f172a';
            setTimeout(() => {
                btn.innerHTML = original;
                btn.style.background = '';
            }, 1500);
        }).catch(() => {
            btn.innerHTML = '⚠️ Kopyalanamadı';
            setTimeout(() => { btn.innerHTML = original; }, 1500);
        });
    };
</script>
@endsection
