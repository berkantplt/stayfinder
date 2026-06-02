@extends('layouts.app')
@section('title', $profile->city . ' Profili — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')

        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">{{ $profile->city }}</h1>
                    <div style="font-size:13px;color:#64748b;margin-top:4px;">
                        Kaynak: <strong>{{ $profile->source }}</strong> •
                        Sürüm: <strong>{{ $profile->enrichment_version }}</strong> •
                        Güncellendi: <strong>{{ $profile->updated_at?->diffForHumans() }}</strong>
                    </div>
                </div>
                <div style="display:flex;gap:8px;">
                    <form method="POST" action="{{ route('admin.destination-profiles.regenerate', $profile) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline" onclick="return confirm('LLM ile yeniden üretilsin mi? Mevcut alanlar üzerine yazılır.')">🔄 LLM ile Yeniden Üret</button>
                    </form>
                    <a href="{{ route('admin.destination-profiles.index') }}" class="btn btn-outline">Listeye Dön</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 16px;">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 16px;">
                    @foreach($errors->all() as $e) {{ $e }}<br> @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('admin.destination-profiles.update', $profile) }}" style="max-width:94%;margin:0 auto 32px;">
                @csrf
                @method('PUT')

                <div class="stat-card" style="padding:24px;display:grid;grid-template-columns:1fr 1fr;gap:18px;">
                    <div>
                        <label style="font-size:13px;color:#475569;font-weight:600;">Şehir</label>
                        <input type="text" name="city" value="{{ old('city', $profile->city) }}" class="form-input" required>
                    </div>
                    <div>
                        <label style="font-size:13px;color:#475569;font-weight:600;">Ülke</label>
                        <input type="text" name="country" value="{{ old('country', $profile->country) }}" class="form-input">
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="font-size:13px;color:#475569;font-weight:600;">Özet (2-3 cümle)</label>
                        <textarea name="summary" rows="3" class="form-input">{{ old('summary', $profile->summary) }}</textarea>
                    </div>
                    <div>
                        <label style="font-size:13px;color:#475569;font-weight:600;">Kalabalık skoru (0–1)</label>
                        <input type="number" step="0.01" min="0" max="1" name="crowd_score" value="{{ old('crowd_score', $profile->crowd_score) }}" class="form-input" required>
                    </div>
                    <div>
                        <label style="font-size:13px;color:#475569;font-weight:600;">Canlılık skoru (0–1)</label>
                        <input type="number" step="0.01" min="0" max="1" name="liveliness_score" value="{{ old('liveliness_score', $profile->liveliness_score) }}" class="form-input" required>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="font-size:13px;color:#475569;font-weight:600;">
                            Vibe Tags (virgülle ayır)
                            <span style="font-size:11px;color:#94a3b8;font-weight:400;">— izin verilen: {{ implode(', ', $allowedVibeTags) }}</span>
                        </label>
                        <input type="text" name="vibe_tags" value="{{ old('vibe_tags', implode(', ', (array) ($profile->vibe_tags ?? []))) }}" class="form-input" placeholder="cultural, historical, nature">
                    </div>
                    <div>
                        <label style="font-size:13px;color:#475569;font-weight:600;">İdeal Aylar (1–12, virgül)</label>
                        <input type="text" name="best_months" value="{{ old('best_months', implode(', ', (array) ($profile->best_months ?? []))) }}" class="form-input" placeholder="4, 5, 9, 10">
                    </div>
                    <div>
                        <label style="font-size:13px;color:#475569;font-weight:600;">Yoğun Aylar (1–12, virgül)</label>
                        <input type="text" name="crowded_months" value="{{ old('crowded_months', implode(', ', (array) ($profile->crowded_months ?? []))) }}" class="form-input" placeholder="7, 8">
                    </div>
                    <div>
                        <label style="font-size:13px;color:#475569;font-weight:600;">Türk vatandaşı için vize</label>
                        @php
                            $visaValue = old('requires_visa_for_tr', $profile->requires_visa_for_tr === null ? 'unknown' : ($profile->requires_visa_for_tr ? '1' : '0'));
                        @endphp
                        <select name="requires_visa_for_tr" class="form-input">
                            <option value="unknown" {{ $visaValue === 'unknown' ? 'selected' : '' }}>Bilinmiyor</option>
                            <option value="1" {{ $visaValue === '1' ? 'selected' : '' }}>Evet, gerekli</option>
                            <option value="0" {{ $visaValue === '0' ? 'selected' : '' }}>Hayır, vizesiz</option>
                        </select>
                    </div>
                    <div style="grid-column:1/-1;">
                        <label style="font-size:13px;color:#475569;font-weight:600;">
                            Climate by Month (JSON)
                            <span style="font-size:11px;color:#94a3b8;font-weight:400;">— format: <code>{"1":{"temp_c":24,"condition":"ılık"},"7":...}</code></span>
                        </label>
                        <textarea name="climate_by_month" rows="6" class="form-input" style="font-family:monospace;font-size:12px;">{{ old('climate_by_month', $profile->climate_by_month ? json_encode($profile->climate_by_month, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : '') }}</textarea>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;gap:10px;margin-top:18px;">
                    <button type="submit" class="btn btn-primary" style="padding:11px 24px;">💾 Kaydet (manuel olarak işaretle)</button>
                    @if($profile->source === 'default')
                        <form method="POST" action="{{ route('admin.destination-profiles.destroy', $profile) }}" onsubmit="return confirm('Silinsin mi?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline" style="color:#dc2626;border-color:#fecaca;">Sil</button>
                        </form>
                    @endif
                </div>
            </form>
        </div>
    </div>
</div>

<style>
    .form-input {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        background: #fff;
        font-size: 14px;
        outline: none;
        margin-top: 6px;
        transition: border-color .15s, box-shadow .15s;
    }
    .form-input:focus {
        border-color: #6366f1;
        box-shadow: 0 0 0 3px rgba(99,102,241,0.12);
    }
</style>
@endsection
