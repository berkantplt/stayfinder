@extends('layouts.app')
@section('title', 'Acenta Başvuruları — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:28px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Acenta Başvuruları</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:4px;">Yeni acenta başvurularını inceleyin, onaylayın veya reddedin.</div>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <a href="{{ route('admin.agencies') }}" class="btn btn-outline">Acentalar</a>
                    <a href="{{ route('admin.agencies.create') }}" class="btn btn-primary">Manuel Acenta Ekle</a>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 24px;">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </div>
            @endif

            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;max-width:94%;margin:0 auto 24px;">
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Bekleyen Başvuru</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $applicationStats['pending'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Onaylı Acenta</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $applicationStats['approved'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Reddedilen Başvuru</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $applicationStats['rejected'] }}</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Bugünkü Başvuru</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $applicationStats['today'] }}</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:minmax(0,1.5fr) minmax(320px,1fr);gap:24px;max-width:94%;margin:0 auto;">
                <div class="stat-card" style="padding:24px;">
                    <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:18px;">Bekleyen Başvurular</h2>

                    @if($pendingApplications->isEmpty())
                        <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                            Bekleyen başvuru bulunmuyor.
                        </div>
                    @else
                        <div style="display:flex;flex-direction:column;gap:16px;">
                            @foreach($pendingApplications as $agency)
                                <div style="border:1px solid #e2e8f0;border-radius:18px;padding:18px;background:#fff;">
                                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
                                        <div>
                                            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">
                                                <div style="font-size:20px;font-weight:800;color:#0f172a;">{{ $agency->name }}</div>
                                                <span class="badge" style="background:#fff7ed;color:#9a3412;border:none;">Onay bekliyor</span>
                                            </div>
                                            <div style="font-size:13px;color:#64748b;margin-top:6px;">
                                                {{ $agency->email ?? 'E-posta yok' }} · {{ $agency->phone ?? 'Telefon yok' }}
                                            </div>
                                            <div style="font-size:12px;color:#94a3b8;margin-top:6px;">
                                                Başvuru: {{ $agency->created_at?->format('d.m.Y H:i') }} · Toplam {{ $agency->tours_count }} tur
                                            </div>
                                        </div>
                                        <a href="{{ route('admin.agencies.show', $agency) }}" class="btn btn-outline btn-sm">Detay</a>
                                    </div>

                                    @if($agency->website_url || $agency->description)
                                        <div style="margin-top:14px;padding-top:14px;border-top:1px solid #f1f5f9;">
                                            @if($agency->website_url)
                                                <div style="font-size:13px;color:#0f172a;">
                                                    Web: <a href="{{ $agency->website_url }}" target="_blank" rel="noopener" style="color:var(--accent);font-weight:600;">{{ $agency->website_url }}</a>
                                                </div>
                                            @endif
                                            @if($agency->description)
                                                <div style="font-size:13px;color:#475569;line-height:1.6;margin-top:8px;">{{ $agency->description }}</div>
                                            @endif
                                        </div>
                                    @endif

                                    <div style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;margin-top:16px;">
                                        <form method="POST" action="{{ route('admin.agency-applications.approve', $agency) }}" style="display:flex;flex-direction:column;gap:10px;">
                                            @csrf
                                            <textarea name="approval_notes" rows="3" placeholder="Onay notu (opsiyonel)" style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid #cbd5e1;background:#f8fafc;font-size:14px;outline:none;resize:vertical;"></textarea>
                                            <button type="submit" class="btn btn-primary" style="justify-content:center;">Onayla</button>
                                        </form>

                                        <form method="POST" action="{{ route('admin.agency-applications.reject', $agency) }}" style="display:flex;flex-direction:column;gap:10px;">
                                            @csrf
                                            <textarea name="approval_notes" rows="3" placeholder="Red nedeni (opsiyonel)" style="width:100%;padding:12px 14px;border-radius:12px;border:1px solid #fecaca;background:#fef2f2;font-size:14px;outline:none;resize:vertical;"></textarea>
                                            <button type="submit" class="btn btn-outline" style="justify-content:center;color:#991b1b;border-color:#fecaca;background:#fff5f5;">Reddet</button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div style="margin-top:24px;">
                            {{ $pendingApplications->links() }}
                        </div>
                    @endif
                </div>

                <div class="stat-card" style="padding:24px;">
                    <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:18px;">Sonuçlanan Başvurular</h2>

                    @if($recentApplications->isEmpty())
                        <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                            Henüz sonuçlanmış başvuru yok.
                        </div>
                    @else
                        <div style="display:flex;flex-direction:column;gap:12px;">
                            @foreach($recentApplications as $agency)
                                <div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;background:#fff;">
                                    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                        <div>
                                            <div style="font-weight:700;color:#0f172a;">{{ $agency->name }}</div>
                                            <div style="font-size:12px;color:#94a3b8;margin-top:4px;">{{ $agency->email ?? 'E-posta yok' }}</div>
                                        </div>
                                        @if($agency->approval_status === 'approved')
                                            <span class="badge badge-green">Onaylandı</span>
                                        @else
                                            <span class="badge" style="background:#fef2f2;color:#991b1b;border:none;">Reddedildi</span>
                                        @endif
                                    </div>
                                    <div style="font-size:12px;color:#64748b;margin-top:10px;">
                                        {{ $agency->approved_at?->format('d.m.Y H:i') ?? $agency->updated_at?->format('d.m.Y H:i') }}
                                    </div>
                                    @if($agency->approval_notes)
                                        <div style="font-size:12px;color:#475569;line-height:1.6;margin-top:8px;">{{ $agency->approval_notes }}</div>
                                    @endif
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
