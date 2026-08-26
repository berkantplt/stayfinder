@extends('layouts.app')
@section('title', 'Kategori Yetkilendirme — Acenta Erişimleri')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <div style="font-size:13px;font-weight:700;color:#0ea5e9;letter-spacing:0.3px;text-transform:uppercase;">Kategori Yetkilendirme</div>
                    <h1 style="font-size:28px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;margin-top:6px;">Acenta Erişimleri</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:4px;">Gerçek kategori aboneliklerini ve geçiş erişimli acentaları ayrı ayrı izleyin.</div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="{{ route('admin.agencies') }}" class="btn btn-outline">Acentalar</a>
                    <a href="{{ route('admin.category-licenses.orders') }}" class="btn btn-primary">Siparişler</a>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:94%;margin:0 auto 24px;">
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Gerçek Aktif Abonelik</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['active_subscriptions'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Geçiş Erişimli Acenta</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['legacy_agencies'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Legacy Aktif Tur</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['legacy_active_tours'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Toplam Yetki Talebi</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['combined_demand'] }}</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:minmax(0,1.45fr) minmax(320px,1fr);gap:24px;max-width:94%;margin:0 auto;">
                <div class="stat-card" style="padding:24px;">
                    <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:18px;">Aktif Kategori Abonelikleri</h2>

                    @if($activeSubscriptions->isEmpty())
                        <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                            Henüz aktif kategori aboneliği bulunmuyor.
                        </div>
                    @else
                        <div style="overflow-x:auto;">
                            <table class="table" style="width:100%;text-align:left;">
                                <thead>
                                    <tr>
                                        <th>Acenta</th>
                                        <th>Kategori</th>
                                        <th>Ücret</th>
                                        <th>Bitiş</th>
                                        {{-- Bayrak kapalıyken "Otomatik" rozeti yanıltır: çekim yapılmayacak --}}
                                        @if(\App\Support\CategoryLicensing::autoRenewEnabled())
                                            <th>Yenileme</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($activeSubscriptions as $subscription)
                                        <tr>
                                            <td>
                                                <a href="{{ route('admin.agencies.show', $subscription->agency) }}" style="color:#0f172a;text-decoration:none;font-weight:600;">
                                                    {{ $subscription->agency->name }}
                                                </a>
                                            </td>
                                            <td>
                                                <div style="font-weight:700;color:#0f172a;">{{ $subscription->category->icon }} {{ $subscription->category->name }}</div>
                                                @if($subscription->category->parent)
                                                    <div style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ $subscription->category->parent->name }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                {{ number_format((float) $subscription->monthly_price, 0, ',', '.') }} TL
                                                @if(\App\Support\CategoryLicensing::slotSchemaReady() && (int) $subscription->extra_tour_slots > 0)
                                                    <div style="font-size:12px;color:#94a3b8;margin-top:2px;">+{{ $subscription->extra_tour_slots }} ekstra tur hakkı</div>
                                                @endif
                                            </td>
                                            <td>{{ $subscription->expires_at?->format('d.m.Y') }}</td>
                                            @if(\App\Support\CategoryLicensing::autoRenewEnabled())
                                                <td>
                                                    @if($subscription->isCancelled())
                                                        <span class="badge" style="background:#fef2f2;color:#991b1b;">İptal edildi</span>
                                                        <div style="font-size:11px;color:#94a3b8;margin-top:2px;">{{ $subscription->cancelled_at?->format('d.m.Y') }}</div>
                                                    @else
                                                        <span class="badge badge-green">Otomatik</span>
                                                    @endif
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="stat-card" style="padding:24px;">
                    <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:18px;">Geçiş Erişimli Acentalar</h2>

                    @if($legacyAgencies->isEmpty())
                        <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                            Geçiş erişimi tanımlı acenta yok.
                        </div>
                    @else
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            @foreach($legacyAgencies as $agency)
                                <div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;background:#fff;">
                                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                        <div>
                                            <div style="font-weight:700;color:#0f172a;">{{ $agency->name }}</div>
                                            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">
                                                {{ $agency->used_categories_count }} kullanılan kategori · {{ $agency->active_tours_count }} aktif tur
                                            </div>
                                        </div>
                                        <a href="{{ route('admin.agencies.show', $agency) }}" class="btn btn-outline btn-sm">İncele</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
