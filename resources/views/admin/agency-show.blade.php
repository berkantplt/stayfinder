@extends('layouts.app')
@section('title', $agency->name . ' — Acenta Detayı')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:8px;">
                        <a href="{{ route('admin.agencies') }}" class="btn btn-outline btn-sm">Acentalara Dön</a>
                        @if(($agency->approval_status ?? 'approved') === 'pending')
                            <span class="badge" style="background:#fff7ed;color:#9a3412;border:none;">Onay bekliyor</span>
                        @elseif(($agency->approval_status ?? 'approved') === 'rejected')
                            <span class="badge" style="background:#fef2f2;color:#991b1b;border:none;">Reddedildi</span>
                        @else
                            <span class="badge badge-green">Onaylı</span>
                        @endif
                        <span class="badge {{ $agency->is_active ? 'badge-green' : '' }}" style="{{ !$agency->is_active ? 'background:#fef2f2;color:#991b1b;border:none;' : '' }}">
                            {{ $agency->is_active ? 'Aktif' : 'Pasif' }}
                        </span>
                        @if($agency->legacy_category_access)
                            <span class="badge" style="background:#fff7ed;color:#9a3412;border:none;">Geçiş erişimi</span>
                        @endif
                    </div>
                    <h1 style="font-size:28px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">{{ $agency->name }}</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:6px;">
                        {{ $agency->email ?? 'E-posta yok' }} · {{ $agency->phone ?? 'Telefon yok' }}
                    </div>
                </div>

                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="{{ route('admin.category-licenses.index') }}" class="btn btn-outline">Kategori Yetkilendirme</a>
                    @if($agency->website_url)
                        <a href="{{ $agency->website_url }}" class="btn btn-primary" target="_blank" rel="noopener">Siteyi Aç</a>
                    @endif
                </div>
            </div>

            @if(!$hasCategoryLicensing)
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    Kategori yetkilendirme tabloları bu ortamda hazır değil. Detay ekranı yalnızca temel acenta bilgilerini gösteriyor.
                </div>
            @elseif(($agency->approval_status ?? 'approved') === 'pending')
                <div style="max-width:94%;margin:0 auto 24px;padding:14px 16px;border-radius:16px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;line-height:1.6;">
                    Bu başvuru henüz admin onayı bekliyor. Başvurular ekranından onaylayabilir veya reddedebilirsiniz.
                </div>
            @elseif(($agency->approval_status ?? 'approved') === 'rejected')
                <div style="max-width:94%;margin:0 auto 24px;padding:14px 16px;border-radius:16px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;font-size:13px;line-height:1.6;">
                    Bu başvuru reddedildi.
                    @if($agency->approval_notes)
                        Not: {{ $agency->approval_notes }}
                    @endif
                </div>
            @elseif($agency->legacy_category_access)
                <div style="max-width:94%;margin:0 auto 24px;padding:14px 16px;border-radius:16px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;font-size:13px;line-height:1.6;">
                    Bu acenta geçiş erişiminde. Açık kategori listesi tüm aktif kategorilerden oluşur; aylık değer alanı ise aktif tur kullandığı kategorilerin bugünkü fiyatına göre tahmini hesaplanır.
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:94%;margin:0 auto 24px;">
                <div class="stat-card" style="padding:18px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Aktif Tur</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['active_tours'] }}</div>
                </div>
                <div class="stat-card" style="padding:18px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Açık Kategori</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['open_categories'] }}</div>
                </div>
                <div class="stat-card" style="padding:18px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Kullanılan Kategori</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['used_categories'] }}</div>
                </div>
                <div class="stat-card" style="padding:18px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Aylık Kategori Değeri</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ number_format($stats['monthly_value'], 0, ',', '.') }} TL</div>
                </div>
                <div class="stat-card" style="padding:18px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Kategori Siparişi</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $stats['total_orders'] }}</div>
                </div>
                <div class="stat-card" style="padding:18px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Toplam Sipariş Tutarı</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ number_format($stats['lifetime_order_value'], 0, ',', '.') }} TL</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:minmax(0,1.45fr) minmax(320px,1fr);gap:24px;max-width:94%;margin:0 auto;">
                <div style="display:flex;flex-direction:column;gap:24px;">
                    <div class="stat-card" style="padding:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                            <div>
                                <h2 style="font-size:18px;font-weight:700;color:#0f172a;">Satın Alınan / Açık Kategoriler</h2>
                                <div style="font-size:13px;color:#64748b;margin-top:4px;">Acentanın şu an paylaşım yapabildiği kategoriler ve kullanım yoğunluğu.</div>
                            </div>
                        </div>

                        @if($ownedCategories->isEmpty())
                            <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                                Bu acenta için açık kategori bulunmuyor.
                            </div>
                        @else
                            <div style="overflow-x:auto;">
                                <table class="table" style="width:100%;text-align:left;">
                                    <thead>
                                        <tr>
                                            <th>Kategori</th>
                                            <th>Kaynak</th>
                                            <th>Aktif Tur</th>
                                            <th>Aylık Bedel</th>
                                            <th>Süre</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($ownedCategories as $ownership)
                                            <tr>
                                                <td>
                                                    <div style="font-weight:700;color:#0f172a;">{{ $ownership->category->icon }} {{ $ownership->category->name }}</div>
                                                    @if($ownership->category->parent)
                                                        <div style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ $ownership->category->parent->name }}</div>
                                                    @endif
                                                </td>
                                                <td>
                                                    <span class="badge" style="background:{{ $ownership->source === 'legacy' ? '#fff7ed;color:#9a3412' : '#ecfeff;color:#155e75' }};border:none;">
                                                        {{ $ownership->source_label }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="font-weight:700;color:#0f172a;">{{ $ownership->active_tours_count }}</div>
                                                    <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Toplam {{ $ownership->tours_count }}</div>
                                                </td>
                                                <td>{{ number_format((float) $ownership->monthly_price, 0, ',', '.') }} TL</td>
                                                <td style="font-size:13px;color:#64748b;">
                                                    @if($ownership->source === 'legacy')
                                                        Süresiz geçiş
                                                    @else
                                                        {{ $ownership->started_at?->format('d.m.Y') }} - {{ $ownership->expires_at?->format('d.m.Y') }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <div class="stat-card" style="padding:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                            <div>
                                <h2 style="font-size:18px;font-weight:700;color:#0f172a;">Kategori Bazlı Tur Dağılımı</h2>
                                <div style="font-size:13px;color:#64748b;margin-top:4px;">Acentanın hangi kategorilerde gerçekten içerik ürettiğini gösterir.</div>
                            </div>
                        </div>

                        @if($usedCategories->isEmpty())
                            <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                                Kategorili tur kaydı bulunmuyor.
                            </div>
                        @else
                            @php($maxUsed = max(1, (int) $usedCategories->max('active_tours_count')))
                            <div style="display:flex;flex-direction:column;gap:14px;">
                                @foreach($usedCategories as $category)
                                    <div>
                                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                            <div>
                                                <div style="font-weight:700;color:#0f172a;">{{ $category->icon }} {{ $category->name }}</div>
                                                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">Toplam {{ $category->tours_count }} tur</div>
                                            </div>
                                            <div style="font-size:18px;font-weight:800;color:#0f172a;">{{ $category->active_tours_count }}</div>
                                        </div>
                                        <div style="height:8px;border-radius:999px;background:#e2e8f0;margin-top:10px;overflow:hidden;">
                                            <div style="height:100%;border-radius:999px;background:linear-gradient(90deg,#0ea5e9,#10b981);width:{{ min(100, round(($category->active_tours_count / $maxUsed) * 100)) }}%;"></div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:24px;">
                    <div class="stat-card" style="padding:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                            <div>
                                <h2 style="font-size:18px;font-weight:700;color:#0f172a;">Kategori Sipariş Geçmişi</h2>
                                <div style="font-size:13px;color:#64748b;margin-top:4px;">Gerçek satın alma kayıtları burada listelenir.</div>
                            </div>
                        </div>

                        @if($recentOrders->isEmpty())
                            <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                                Bu acenta için kategori siparişi bulunmuyor.
                            </div>
                        @else
                            <div style="display:flex;flex-direction:column;gap:12px;">
                                @foreach($recentOrders as $order)
                                    <div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;background:#fff;">
                                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                                            <div>
                                                <div style="font-weight:700;color:#0f172a;">{{ $order->order_number }}</div>
                                                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">{{ $order->purchased_at?->format('d.m.Y H:i') }}</div>
                                            </div>
                                            <div style="font-size:16px;font-weight:800;color:#0f172a;">{{ number_format((float) $order->subtotal, 0, ',', '.') }} TL</div>
                                        </div>
                                        <div style="font-size:12px;color:#64748b;margin-top:10px;">
                                            {{ $order->items->pluck('category_name')->implode(', ') }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="stat-card" style="padding:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:18px;">
                            <div>
                                <h2 style="font-size:18px;font-weight:700;color:#0f172a;">Son Turlar</h2>
                                <div style="font-size:13px;color:#64748b;margin-top:4px;">En yeni tur kayıtları ve kategori eşleşmeleri.</div>
                            </div>
                        </div>

                        @if($recentTours->isEmpty())
                            <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                                Bu acenta henüz tur paylaşmamış.
                            </div>
                        @else
                            <div style="display:flex;flex-direction:column;gap:12px;">
                                @foreach($recentTours as $tour)
                                    <div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;background:#fff;">
                                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                            <div>
                                                <div style="font-weight:700;color:#0f172a;">{{ $tour->title }}</div>
                                                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">
                                                    {{ $tour->category?->name ?? 'Kategorisiz' }} · {{ $tour->destination ?? 'Destinasyon yok' }}
                                                </div>
                                            </div>
                                            <span class="badge {{ $tour->is_active ? 'badge-green' : '' }}" style="{{ !$tour->is_active ? 'background:#fef2f2;color:#991b1b;border:none;' : '' }}">
                                                {{ $tour->is_active ? 'Aktif' : 'Pasif' }}
                                            </span>
                                        </div>
                                        <div style="font-size:12px;color:#64748b;margin-top:10px;">
                                            Çıkış: {{ $tour->departure_date?->format('d.m.Y') ?? 'Belirtilmedi' }}
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
</div>
@endsection
