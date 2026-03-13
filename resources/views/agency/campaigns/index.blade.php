@extends('layouts.app')
@section('title', 'Kampanyalar — Acenta Paneli')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:30px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;background:var(--accent-bg);color:var(--accent-dark);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;">🏷️</div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Kampanyalar</h1>
                </div>
            </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-error">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
        @endif

        {{-- Create Campaign --}}
        <div class="stat-card" style="padding:30px;margin-bottom:32px;border-left:4px solid #10b981 !important;">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
                <h3 style="font-size:18px;font-weight:800;letter-spacing:-0.3px;color:#0f172a;">Yeni Kampanya Oluştur</h3>
            </div>
            <form method="POST" action="{{ route('agency.campaigns.store') }}">
                @csrf
                <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;">
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:13px;color:#475569;">Hedef Tur <span style="color:#ef4444">*</span></label>
                        <select name="tour_id" required style="padding:14px;background:#f8fafc;">
                            <option value="">Tur Seçin</option>
                            @foreach($tours as $tour)
                                <option value="{{ $tour->id }}">{{ $tour->title }} ({{ $tour->formatted_price }})</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:13px;color:#475569;">İndirimli Fiyat (₺) <span style="color:#ef4444">*</span></label>
                        <input type="number" name="discount_price" min="0" step="0.01" required placeholder="Ör: 2499" style="padding:14px;background:#f8fafc;font-weight:600;color:#10b981;">
                    </div>
                    <div class="form-group" style="margin-bottom:0;">
                        <label style="font-size:13px;color:#475569;">Kampanya Etiketi (Adı) <span style="color:#ef4444">*</span></label>
                        <input type="text" name="label" required placeholder="Ör: Erken Rezervasyon İndirimi" value="{{ old('label') }}" style="padding:14px;background:#f8fafc;">
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Başlangıç <span style="color:#ef4444">*</span></label>
                            <input type="datetime-local" name="starts_at" required style="padding:14px;background:#f8fafc;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Bitiş <span style="color:#ef4444">*</span></label>
                            <input type="datetime-local" name="ends_at" required style="padding:14px;background:#f8fafc;">
                        </div>
                    </div>
                </div>
                <div style="margin-top:32px;display:flex;justify-content:flex-end;">
                    <button type="submit" class="btn btn-primary" style="padding:14px 32px;font-size:15px;box-shadow:0 8px 20px -6px rgba(16,185,129,0.4);">
                        Kampanya Oluştur
                    </button>
                </div>
            </form>
        </div>

        {{-- Existing Campaigns --}}
        <div class="stat-card" style="padding:32px;">
            <h3 style="font-size:18px;font-weight:800;margin-bottom:24px;color:#0f172a;letter-spacing:-0.3px;">Mevcut Kampanyalar</h3>

            @forelse($campaigns as $campaign)
            <div style="border:1px solid #e2e8f0;border-radius:16px;padding:24px;margin-bottom:16px;background:{{ $campaign->isRunning() ? '#fafafa' : '#fff' }};{{ $campaign->isRunning() ? 'border-left:4px solid #10b981;box-shadow:0 4px 12px rgba(0,0,0,0.02);' : ($campaign->ends_at->isPast() ? 'opacity:0.6;' : '') }} transition:all 0.2s;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                    <div style="flex:1;min-width:240px;">
                        <div style="font-weight:800;font-size:15px;color:#0f172a;letter-spacing:-0.2px;">{{ $campaign->label }}</div>
                        <div style="font-size:13px;color:#64748b;margin-top:4px;font-weight:500;">
                            Hedef Tur: <span style="color:#334155;">{{ $campaign->tour->title }}</span>
                        </div>
                    </div>
                    <div style="text-align:center;padding:0 24px;border-left:1px dashed #e2e8f0;border-right:1px dashed #e2e8f0;">
                        <div style="text-decoration:line-through;color:#94a3b8;font-size:13px;font-weight:500;">{{ $campaign->tour->formatted_price }}</div>
                        <div style="font-weight:800;color:#10b981;font-size:22px;letter-spacing:-0.5px;">{{ $campaign->formatted_discount_price }}</div>
                    </div>
                    <div style="text-align:right;min-width:160px;display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
                        <div style="font-size:12px;color:#64748b;font-weight:500;background:#f8fafc;padding:6px 10px;border-radius:8px;">
                            <span style="color:#10b981;">●</span> {{ $campaign->starts_at->format('d M H:i') }} <span style="margin:0 4px;color:#cbd5e1;">—</span> {{ $campaign->ends_at->format('d M H:i') }}
                        </div>
                        <div>
                        @if($campaign->isRunning())
                            <span class="badge" style="background:#d1fae5;color:#065f46;font-size:12px;border:none;padding:6px 14px;border-radius:20px;font-weight:700;">Aktif</span>
                        @elseif($campaign->ends_at->isPast())
                            <span class="badge" style="background:#fef2f2;color:#991b1b;font-size:12px;border:none;padding:6px 14px;border-radius:20px;font-weight:700;">Sona Erdi</span>
                        @else
                            <span class="badge" style="background:#fef3c7;color:#92400e;font-size:12px;border:none;padding:6px 14px;border-radius:20px;font-weight:700;">Başlamadı</span>
                        @endif
                        </div>
                    </div>
                    <div style="margin-left:8px;">
                        <form method="POST" action="{{ route('agency.campaigns.destroy', $campaign) }}" onsubmit="return confirm('Kampanya silinsin mi?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm" style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;padding:0;border-radius:12px;" title="Kampanyayı Sil">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"></path><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"></path></svg>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            @empty
            <div style="text-align:center;color:#64748b;font-size:14px;padding:48px;background:#f8fafc;border-radius:16px;border:1px dashed #cbd5e1;">
                <div style="font-size:32px;margin-bottom:12px;">📭</div>
                <div style="font-weight:600;color:#0f172a;margin-bottom:4px;">Henüz kampanya yok</div>
                <div>Yeni bir kampanya oluşturarak satışlarınızı artırabilirsiniz.</div>
            </div>
            @endforelse
        </div>
        </div>
    </div>
</div>
@endsection
