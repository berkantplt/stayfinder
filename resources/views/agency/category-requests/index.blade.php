@extends('layouts.app')
@section('title', 'Kategori Talebi — Acenta Paneli')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;gap:12px;margin-bottom:8px;">
                <div style="width:40px;height:40px;background:var(--accent-bg);color:var(--accent-dark);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:18px;">🗳️</div>
                <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Kategori Talebi</h1>
            </div>
            <p style="font-size:14px;color:#64748b;margin-bottom:24px;">
                Turunuza uygun bir kategori bulamadıysanız buradan talep oluşturun. Admin onayladığında kategori, turlarınızda seçilebilir hale gelir.
            </p>

            @if(session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
            @endif

            {{-- Yeni talep formu --}}
            <div style="background:#fff;border:1px solid var(--border-light);border-radius:var(--radius);padding:20px;margin-bottom:24px;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:16px;">+ Yeni Kategori Talebi</h3>
                <form method="POST" action="{{ route('agency.category-requests.store') }}">
                    @csrf
                    <div class="form-group">
                        <label>Kategori Adı *</label>
                        <input type="text" name="requested_name" value="{{ old('requested_name') }}" required maxlength="100" placeholder="Örn: Latin Amerika Turları">
                    </div>
                    <div class="form-group">
                        <label>Açıklama (opsiyonel)</label>
                        <textarea name="note" rows="2" maxlength="1000" placeholder="Bu kategoriye neden ihtiyacınız var?">{{ old('note') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Talebi Gönder</button>
                </form>
            </div>

            {{-- Talep geçmişi --}}
            <div style="background:#fff;border:1px solid var(--border-light);border-radius:var(--radius);overflow:hidden;">
                <table class="table" style="margin:0;border:none;">
                    <thead><tr><th>Talep</th><th>Durum</th><th>Sonuç</th><th>Tarih</th></tr></thead>
                    <tbody>
                        @forelse($requests as $req)
                        <tr style="border-bottom:1px solid var(--border-light);">
                            <td>
                                <div style="font-weight:600;">{{ $req->requested_name }}</div>
                                @if($req->note)<div style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ $req->note }}</div>@endif
                            </td>
                            <td>
                                @if($req->status === \App\Models\CategoryRequest::STATUS_PENDING)
                                    <span class="badge" style="background:#fef9c3;color:#854d0e;">⏳ Bekliyor</span>
                                @elseif($req->status === \App\Models\CategoryRequest::STATUS_APPROVED)
                                    <span class="badge badge-green">✅ Onaylandı</span>
                                @else
                                    <span class="badge" style="background:#fef2f2;color:#991b1b;">🚫 Reddedildi</span>
                                @endif
                            </td>
                            <td style="font-size:13px;color:#475569;">
                                @if($req->status === \App\Models\CategoryRequest::STATUS_APPROVED)
                                    {{ $req->createdCategory?->name }} @if($req->parent) <span style="color:#94a3b8;">({{ $req->parent->name }})</span>@endif
                                @elseif($req->status === \App\Models\CategoryRequest::STATUS_REJECTED && $req->admin_note)
                                    {{ $req->admin_note }}
                                @else
                                    —
                                @endif
                            </td>
                            <td style="font-size:12px;color:#94a3b8;">{{ $req->created_at->format('d.m.Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:24px;">Henüz talebiniz yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
