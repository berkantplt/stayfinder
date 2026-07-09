@extends('layouts.app')
@section('title', $agency->name . ' — Acenta Detayı')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 16px;">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 16px;">{{ $errors->first() }}</div>
            @endif
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
                <div style="max-width:94%;margin:0 auto 24px;padding:18px;border-radius:16px;background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;">
                    <div style="font-size:14px;line-height:1.6;font-weight:700;margin-bottom:14px;">
                        Bu başvuru henüz admin onayı bekliyor. Buradan doğrudan onaylayabilir veya reddedebilirsiniz.
                    </div>
                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;">
                        <form method="POST" action="{{ route('admin.agency-applications.approve', $agency) }}" style="display:flex;flex-direction:column;gap:10px;">
                            @csrf
                            <textarea name="approval_notes" rows="3" placeholder="Onay notu (opsiyonel)" style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid #fed7aa;background:#fff;font-size:14px;outline:none;resize:vertical;"></textarea>
                            <button type="submit" class="btn btn-primary" style="justify-content:center;">Onayla</button>
                        </form>

                        <form method="POST" action="{{ route('admin.agency-applications.reject', $agency) }}" style="display:flex;flex-direction:column;gap:10px;">
                            @csrf
                            <textarea name="approval_notes" rows="3" placeholder="Red nedeni (opsiyonel)" style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid #fecaca;background:#fff;font-size:14px;outline:none;resize:vertical;"></textarea>
                            <button type="submit" class="btn btn-outline" style="justify-content:center;color:#991b1b;border-color:#fecaca;background:#fff5f5;">Reddet</button>
                        </form>
                    </div>
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
                            <style>
                                /* Kaydırmasız sığsın: sıkı hücreler, dar başlıklar */
                                .owned-cats-table th { font-size:11px; white-space:nowrap; padding:10px 8px; }
                                .owned-cats-table td { padding:10px 8px; font-size:13px; }
                            </style>
                            <div>
                                <table class="table owned-cats-table" style="width:100%;text-align:left;">
                                    <thead>
                                        <tr>
                                            <th>Kategori</th>
                                            <th>Kaynak</th>
                                            <th>Aktif Tur</th>
                                            <th>Aylık Bedel</th>
                                            <th>Süre</th>
                                            <th>İşlem</th>
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
                                                    @php
                                                        $sourceStyles = [
                                                            'legacy' => '#fff7ed;color:#9a3412',
                                                            'manual' => '#f5f3ff;color:#5b21b6',
                                                            'purchase' => '#ecfeff;color:#155e75',
                                                        ];
                                                    @endphp
                                                    <span class="badge" style="background:{{ $sourceStyles[$ownership->source] }};border:none;">
                                                        {{ $ownership->source_label }}
                                                    </span>
                                                </td>
                                                <td>
                                                    <div style="font-weight:700;color:#0f172a;">{{ $ownership->active_tours_count }}</div>
                                                    <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Toplam {{ $ownership->tours_count }}</div>
                                                </td>
                                                <td>{{ number_format((float) $ownership->monthly_price, 0, ',', '.') }} TL</td>
                                                <td style="font-size:12px;color:#64748b;">
                                                    @if($ownership->source === 'legacy')
                                                        Süresiz geçiş
                                                    @else
                                                        <span style="white-space:nowrap;">{{ $ownership->started_at?->format('d.m.Y') }}</span><br>
                                                        <span style="white-space:nowrap;">→ {{ $ownership->expires_at?->format('d.m.Y') }}</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($ownership->subscription)
                                                        <form method="POST" action="{{ route('admin.agencies.categories.revoke', [$agency, $ownership->subscription]) }}"
                                                              onsubmit="return confirm('{{ $ownership->category->name }} aboneliği iptal edilecek ve bu kategorideki turlar yayından kalkacak. Emin misiniz?');"
                                                              style="margin:0;">
                                                            @csrf
                                                            <button type="submit" class="btn btn-danger btn-sm" style="font-size:12px;padding:6px 10px;white-space:nowrap;">İptal</button>
                                                        </form>
                                                    @else
                                                        <span style="font-size:12px;color:#94a3b8;">—</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        {{-- Manuel kategori ekleme: yanlış satın alma telafisi / iyi niyet --}}
                        <form method="POST" action="{{ route('admin.agencies.categories.grant', $agency) }}"
                              style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-top:18px;padding-top:18px;border-top:1px solid #e2e8f0;">
                            @csrf
                            <div class="form-group" style="flex:2;min-width:230px;margin:0;">
                                <label style="font-size:13px;font-weight:700;color:#475569;">➕ Manuel Kategori Ekle</label>
                                <select name="category_id" required>
                                    <option value="">Kategori seçin...</option>
                                    @foreach($grantableCategories as $grantable)
                                        <option value="{{ $grantable->id }}">
                                            {{ $grantable->icon }} {{ $grantable->name }}{{ $grantable->parent ? ' — '.$grantable->parent->name : '' }} ({{ number_format((float) $grantable->monthly_price, 0, ',', '.') }} TL/ay)
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group" style="flex:1;min-width:110px;margin:0;">
                                <label style="font-size:13px;font-weight:700;color:#475569;">Süre</label>
                                <select name="months" required>
                                    <option value="1">1 Ay</option>
                                    <option value="3">3 Ay</option>
                                    <option value="6">6 Ay</option>
                                    <option value="12" selected>12 Ay</option>
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary" {{ $grantableCategories->isEmpty() ? 'disabled' : '' }}>Ekle</button>
                        </form>
                        @if($agency->legacy_category_access)
                            <div style="margin-top:10px;font-size:12px;color:#9a3412;background:#fff7ed;border-radius:10px;padding:8px 12px;">
                                Bu acentada geçiş erişimi açık — zaten tüm aktif kategorilere erişebiliyor. Manuel ekleme yine de kayıt altına alınır.
                            </div>
                        @elseif($grantableCategories->isEmpty())
                            <div style="margin-top:10px;font-size:12px;color:#64748b;">Eklenebilecek kategori kalmadı — tüm aktif alt kategorilerde açık aboneliği var.</div>
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
