@extends('layouts.app')
@section('title', 'Kategori Talepleri — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;max-width:94%;margin:0 auto 24px;">
                <h1 style="font-size:24px;font-weight:700;">🗳️ Kategori Talepleri</h1>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 24px;">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">@foreach($errors->all() as $e) {{ $e }}<br> @endforeach</div>
            @endif

            @if($parentCategories->isEmpty())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    Talepleri onaylayabilmek için önce en az bir <a href="{{ route('admin.categories.parents') }}" style="font-weight:700;">üst kategori</a> oluşturmalısınız.
                </div>
            @endif

            {{-- Bekleyen talepler --}}
            <h2 style="font-size:16px;font-weight:700;margin-bottom:12px;max-width:94%;margin-left:auto;margin-right:auto;">Bekleyen Talepler ({{ $pendingRequests->count() }})</h2>
            <div style="max-width:94%;margin:0 auto 32px;display:flex;flex-direction:column;gap:16px;">
                @forelse($pendingRequests as $req)
                <div style="background:#fff;border:1px solid var(--border-light);border-radius:var(--radius);padding:20px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
                        <div>
                            <div style="font-size:17px;font-weight:700;color:#0f172a;">{{ $req->requested_name }}</div>
                            <div style="font-size:13px;color:#64748b;margin-top:2px;">{{ $req->agency?->name }} · {{ $req->created_at->diffForHumans() }}</div>
                            @if($req->note)<div style="font-size:13px;color:#475569;margin-top:8px;background:#f8fafc;border-radius:8px;padding:8px 12px;">{{ $req->note }}</div>@endif
                        </div>
                    </div>

                    {{-- Onay formu --}}
                    <form method="POST" action="{{ route('admin.category-requests.approve', $req) }}" style="border-top:1px solid var(--border-light);padding-top:16px;margin-bottom:12px;">
                        @csrf
                        <div style="display:grid;grid-template-columns:repeat({{ $categoryLicensingReady ? 4 : 3 }}, 1fr) auto;gap:12px;align-items:end;">
                            <div class="form-group" style="margin:0;">
                                <label>Kategori Adı *</label>
                                <input type="text" name="name" value="{{ $req->requested_name }}" required maxlength="100">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>İkon</label>
                                <input type="text" name="icon" placeholder="🌎" maxlength="20">
                            </div>
                            <div class="form-group" style="margin:0;">
                                <label>Üst Kategori *</label>
                                <select name="parent_id" required>
                                    <option value="">Seçiniz…</option>
                                    @foreach($parentCategories as $parent)
                                        <option value="{{ $parent->id }}">{{ $parent->icon }} {{ $parent->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            @if($categoryLicensingReady)
                                <div class="form-group" style="margin:0;">
                                    <label>Aylık Ücret (TL) *</label>
                                    <input type="number" name="monthly_price" value="2000" min="0" step="0.01" required>
                                </div>
                            @endif
                            <button type="submit" class="btn btn-primary" style="height:44px;" {{ $parentCategories->isEmpty() ? 'disabled' : '' }}>✅ Onayla</button>
                        </div>
                    </form>

                    {{-- Red formu --}}
                    <form method="POST" action="{{ route('admin.category-requests.reject', $req) }}" style="display:flex;gap:8px;align-items:center;" onsubmit="return confirm('Talep reddedilsin mi?');">
                        @csrf
                        <input type="text" name="admin_note" placeholder="Red gerekçesi (opsiyonel)" maxlength="500" style="flex:1;margin:0;">
                        <button type="submit" class="btn btn-danger btn-sm" style="white-space:nowrap;">🚫 Reddet</button>
                    </form>
                </div>
                @empty
                <div style="background:#fff;border:1px dashed var(--border-light);border-radius:var(--radius);padding:32px;text-align:center;color:#94a3b8;">Bekleyen talep yok.</div>
                @endforelse
            </div>

            {{-- Son sonuçlananlar --}}
            <h2 style="font-size:16px;font-weight:700;margin-bottom:12px;max-width:94%;margin-left:auto;margin-right:auto;">Son Sonuçlananlar</h2>
            <div style="background:#fff;border:1px solid var(--border-light);border-radius:var(--radius);overflow:hidden;max-width:94%;margin:0 auto;">
                <table class="table" style="margin:0;border:none;">
                    <thead><tr><th>Talep</th><th>Acenta</th><th>Durum</th><th>Sonuç</th><th>Karar</th></tr></thead>
                    <tbody>
                        @forelse($recentRequests as $req)
                        <tr style="border-bottom:1px solid var(--border-light);">
                            <td style="font-weight:600;">{{ $req->requested_name }}</td>
                            <td style="font-size:13px;color:#475569;">{{ $req->agency?->name }}</td>
                            <td>
                                @if($req->status === \App\Models\CategoryRequest::STATUS_APPROVED)
                                    <span class="badge badge-green">✅ Onaylandı</span>
                                @else
                                    <span class="badge" style="background:#fef2f2;color:#991b1b;">🚫 Reddedildi</span>
                                @endif
                            </td>
                            <td style="font-size:13px;color:#475569;">
                                @if($req->status === \App\Models\CategoryRequest::STATUS_APPROVED)
                                    {{ $req->createdCategory?->name }} @if($req->parent)<span style="color:#94a3b8;">({{ $req->parent->name }})</span>@endif
                                @else
                                    {{ $req->admin_note ?: '—' }}
                                @endif
                            </td>
                            <td style="font-size:12px;color:#94a3b8;">{{ $req->reviewed_at?->format('d.m.Y') }}</td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:24px;">Henüz sonuçlanmış talep yok.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
