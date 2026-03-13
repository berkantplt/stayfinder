@extends('layouts.app')
@section('title', $tour->title . ' — Acenta Paneli')

@section('content')
<div class="container">
    <div class="section">
        {{-- Header --}}
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:12px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <a href="{{ route('agency.tours.index') }}" class="btn btn-outline btn-sm">← Geri</a>
                <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">{{ $tour->title }}</h1>
                <span class="badge" style="background:{{ $tour->is_active ? '#d1fae5;color:#065f46' : '#fef2f2;color:#991b1b' }};border:none;padding:6px 12px;border-radius:20px;font-weight:600;">{{ $tour->is_active ? 'Aktif' : 'Pasif' }}</span>
            </div>
            <div style="display:flex;gap:8px;">
                <a href="{{ route('tours.show', $tour) }}" target="_blank" class="btn btn-outline btn-sm">👁️ Herkese Açık Sayfa</a>
                <a href="{{ route('agency.tours.edit', $tour) }}" class="btn btn-primary btn-sm">✏️ Düzenle</a>
                <form method="POST" action="{{ route('agency.tours.destroy', $tour) }}" onsubmit="return confirm('Bu turu silmek istediğinize emin misiniz?')" style="display:inline;">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger btn-sm">🗑️ Sil</button>
                </form>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        {{-- Stats --}}
        <div class="grid-4" style="margin-bottom:28px;">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--accent);">{{ $clickCount }}</div>
                <div class="stat-label">Toplam Tıklama</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#2563eb;">{{ $viewCount }}</div>
                <div class="stat-label">Görüntülenme</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#f59e0b;">{{ $avgRating ?? '—' }}</div>
                <div class="stat-label">Ort. Puan</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:var(--green);">{{ $reviews->count() }}</div>
                <div class="stat-label">Yorum</div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 320px;gap:28px;">
            {{-- Left: Tour details --}}
            <div>
                @if($tour->image)
                    <img src="{{ $tour->image }}" alt="{{ $tour->title }}" style="width:100%;height:240px;object-fit:cover;border-radius:var(--radius-lg);margin-bottom:20px;">
                @endif

                {{-- Info Cards --}}
                <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px;">
                    <div class="stat-card" style="padding:16px;">
                        <div style="font-size:12px;color:#94a3b8;margin-bottom:2px;">Destinasyon</div>
                        <div style="font-weight:600;color:#0f172a;">📍 {{ $tour->destination }}</div>
                    </div>
                    <div class="stat-card" style="padding:16px;">
                        <div style="font-size:12px;color:#94a3b8;margin-bottom:2px;">Süre</div>
                        <div style="font-weight:600;color:#0f172a;">⏱ {{ $tour->duration_days }} gün</div>
                    </div>
                    <div class="stat-card" style="padding:16px;">
                        <div style="font-size:12px;color:#94a3b8;margin-bottom:2px;">Fiyat</div>
                        <div style="font-weight:700;color:#059669;">{{ $tour->formatted_price }}</div>
                    </div>
                </div>

                {{-- Tour Dates Management --}}
                <div class="stat-card" style="padding:20px;margin-bottom:20px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">📅 Tur Tarihleri</h3>

                    {{-- Add new date form --}}
                    <form method="POST" action="{{ route('agency.tours.dates.store', $tour) }}" style="background:var(--accent-bg);border-radius:var(--radius);padding:14px;margin-bottom:16px;">
                        @csrf
                        <div style="font-size:13px;font-weight:600;margin-bottom:10px;">+ Yeni Tarih Ekle</div>
                        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:10px;align-items:end;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;">Kalkış Tarihi *</label>
                                <input type="date" name="departure_date" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;">Dönüş Tarihi *</label>
                                <input type="date" name="return_date" required>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:12px;">Etiket (opsiyonel)</label>
                                <input type="text" name="label" placeholder="Ör: Bayram Özel">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary btn-sm" style="margin-top:10px;">Tarih Ekle</button>
                    </form>

                    {{-- Existing dates --}}
                    @if($tour->dates->count())
                        <div style="font-size:13px;font-weight:600;margin-bottom:8px;color:var(--text-muted);">Mevcut Tarihler ({{ $tour->dates->count() }})</div>
                        @foreach($tour->dates as $date)
                        <div style="border:1px solid var(--border-light);border-radius:var(--radius);padding:12px;margin-bottom:8px;{{ $date->departure_date->isPast() ? 'opacity:0.6;' : '' }}">
                            {{-- Display row --}}
                            <div id="date-display-{{ $date->id }}" style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                                <div style="flex:1;">
                                    <span style="font-weight:600;font-size:14px;">📅 {{ $date->departure_date->format('d M Y') }}</span>
                                    <span style="color:var(--text-muted);margin:0 4px;">→</span>
                                    <span style="font-weight:600;font-size:14px;">{{ $date->return_date->format('d M Y') }}</span>
                                    @if($date->label)
                                        <span class="badge badge-accent" style="font-size:11px;margin-left:6px;">{{ $date->label }}</span>
                                    @endif
                                    @if($date->departure_date->isPast())
                                        <span style="font-size:11px;color:#991b1b;margin-left:4px;">geçmiş</span>
                                    @elseif($date->departure_date->diffInDays(now()) <= 7)
                                        <span style="font-size:11px;color:#d97706;margin-left:4px;">yakında</span>
                                    @endif
                                </div>
                                <div style="display:flex;gap:6px;flex-shrink:0;">
                                    <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('date-display-{{ $date->id }}').style.display='none'; document.getElementById('date-edit-{{ $date->id }}').style.display='block';">✏️</button>
                                    <form method="POST" action="{{ route('agency.tours.dates.destroy', [$tour, $date]) }}" onsubmit="return confirm('Bu tarihi silmek istediğinize emin misiniz?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">🗑️</button>
                                    </form>
                                </div>
                            </div>

                            {{-- Edit row (hidden by default) --}}
                            <form id="date-edit-{{ $date->id }}" method="POST" action="{{ route('agency.tours.dates.update', [$tour, $date]) }}" style="display:none;">
                                @csrf @method('PUT')
                                <div style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:8px;align-items:end;">
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label style="font-size:11px;">Kalkış</label>
                                        <input type="date" name="departure_date" value="{{ $date->departure_date->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label style="font-size:11px;">Dönüş</label>
                                        <input type="date" name="return_date" value="{{ $date->return_date->format('Y-m-d') }}" required>
                                    </div>
                                    <div class="form-group" style="margin-bottom:0;">
                                        <label style="font-size:11px;">Etiket</label>
                                        <input type="text" name="label" value="{{ $date->label }}">
                                    </div>
                                    <div style="display:flex;gap:4px;">
                                        <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
                                        <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('date-edit-{{ $date->id }}').style.display='none'; document.getElementById('date-display-{{ $date->id }}').style.display='flex';">İptal</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        @endforeach
                    @else
                        <div style="text-align:center;color:var(--text-muted);font-size:13px;padding:16px 0;">Henüz tarih eklenmedi.</div>
                    @endif
                </div>

                @if($tour->description)
                <div class="stat-card" style="padding:20px;margin-bottom:16px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:8px;">Açıklama</h3>
                    <p style="color:var(--text-sec);line-height:1.8;font-size:14px;">{{ $tour->description }}</p>
                </div>
                @endif

                @if($tour->included)
                <div class="stat-card" style="padding:20px;margin-bottom:16px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:8px;">✅ Dahil Olanlar</h3>
                    <ul style="list-style:none;">
                        @foreach(explode("\n", $tour->included) as $item)
                            <li style="padding:3px 0;font-size:14px;color:var(--text-sec);">• {{ trim($item) }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if($tour->excluded)
                <div class="stat-card" style="padding:20px;margin-bottom:16px;">
                    <h3 style="font-size:15px;font-weight:700;margin-bottom:8px;">❌ Dahil Olmayanlar</h3>
                    <ul style="list-style:none;">
                        @foreach(explode("\n", $tour->excluded) as $item)
                            <li style="padding:3px 0;font-size:14px;color:var(--text-sec);">• {{ trim($item) }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                @if($tour->tour_url)
                <div style="font-size:13px;color:var(--text-muted);margin-bottom:16px;">
                    🔗 Tur Linki: <a href="{{ $tour->tour_url }}" target="_blank" style="color:var(--accent);">{{ $tour->tour_url }}</a>
                </div>
                @endif
            </div>

            {{-- Right: Reviews --}}
            <div>
                <div class="stat-card" style="padding:24px;">
                    <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;">
                        ⭐ Yorumlar
                        @if($avgRating) <span style="font-size:13px;color:#f59e0b;font-weight:400;">{{ $avgRating }} / 5</span> @endif
                    </h3>

                    @forelse($reviews as $review)
                    <div style="padding:10px 0;border-bottom:1px solid var(--border-light);">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                            <span style="font-weight:600;font-size:13px;">{{ $review->user->name }}</span>
                            <span style="color:#f59e0b;font-size:12px;">{{ str_repeat('★', $review->rating) }}{{ str_repeat('☆', 5 - $review->rating) }}</span>
                        </div>
                        <p style="font-size:13px;color:var(--text-sec);line-height:1.5;margin:0;">{{ $review->comment }}</p>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:3px;">{{ $review->created_at->diffForHumans() }}</div>
                    </div>
                    @empty
                        <div style="text-align:center;color:var(--text-muted);font-size:14px;padding:20px 0;">Henüz yorum yok.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
