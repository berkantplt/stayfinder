@extends('layouts.app')
@section('title', 'Kampanyayı Düzenle — Acenta Paneli')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:30px;">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:40px;height:40px;background:var(--accent-bg);color:var(--accent-dark);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;">✏️</div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Kampanyayı Düzenle</h1>
                </div>
                <a href="{{ route('agency.campaigns.index') }}" class="btn btn-outline">← Geri</a>
            </div>

            @if($errors->any())
                <div class="alert alert-error">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
            @endif

            <div class="stat-card" style="padding:30px;margin-bottom:32px;border-left:4px solid #10b981 !important;">
                <form method="POST" action="{{ route('agency.campaigns.update', $campaign) }}">
                    @csrf
                    @method('PUT')

                    <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:24px;">
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Hedef Tur <span style="color:#ef4444">*</span></label>
                            <select name="tour_id" required style="padding:14px;background:#f8fafc;">
                                @foreach($tours as $tour)
                                    <option value="{{ $tour->id }}" {{ old('tour_id', $campaign->tour_id) == $tour->id ? 'selected' : '' }}>
                                        {{ $tour->title }} ({{ $tour->formatted_price }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">İndirimli Fiyat (₺) <span style="color:#ef4444">*</span></label>
                            <input type="number" name="discount_price" min="0" step="0.01" required
                                value="{{ old('discount_price', $campaign->discount_price) }}"
                                style="padding:14px;background:#f8fafc;font-weight:600;color:#10b981;">
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label style="font-size:13px;color:#475569;">Kampanya Etiketi (Adı) <span style="color:#ef4444">*</span></label>
                            <input type="text" name="label" required
                                value="{{ old('label', $campaign->label) }}"
                                style="padding:14px;background:#f8fafc;">
                        </div>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:13px;color:#475569;">Başlangıç <span style="color:#ef4444">*</span></label>
                                <input type="datetime-local" name="starts_at" required
                                    value="{{ old('starts_at', $campaign->starts_at?->format('Y-m-d\TH:i')) }}"
                                    style="padding:14px;background:#f8fafc;">
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label style="font-size:13px;color:#475569;">Bitiş <span style="color:#ef4444">*</span></label>
                                <input type="datetime-local" name="ends_at" required
                                    value="{{ old('ends_at', $campaign->ends_at?->format('Y-m-d\TH:i')) }}"
                                    style="padding:14px;background:#f8fafc;">
                            </div>
                        </div>
                    </div>

                    <div style="margin-top:24px;display:flex;align-items:center;gap:10px;">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" id="is_active" name="is_active" value="1" {{ old('is_active', $campaign->is_active) ? 'checked' : '' }}>
                        <label for="is_active" style="font-size:13px;color:#475569;">Kampanya aktif</label>
                    </div>

                    <div style="margin-top:32px;display:flex;justify-content:space-between;align-items:center;gap:12px;">
                        <div style="font-size:12px;color:#94a3b8;">
                            Oluşturulma: {{ $campaign->created_at?->format('d M Y H:i') }}
                            @if($campaign->updated_at && $campaign->updated_at->ne($campaign->created_at))
                                • Son güncelleme: {{ $campaign->updated_at->diffForHumans() }}
                            @endif
                        </div>
                        <div style="display:flex;gap:10px;">
                            <a href="{{ route('agency.campaigns.index') }}" class="btn btn-outline">İptal</a>
                            <button type="submit" class="btn btn-primary" style="padding:11px 24px;">💾 Değişiklikleri Kaydet</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
