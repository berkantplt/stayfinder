@extends('layouts.app')
@section('title', 'Başvuru Durumu — Acenta')

@section('content')
@php($isRejected = $agency?->isRejected())
@php($cardAccent = $isRejected ? '#dc2626' : '#0d9488')
@php($cardSoft = $isRejected ? '#fef2f2' : '#f0fdfa')

<div class="container" style="max-width:720px;padding-top:80px;padding-bottom:100px;">
    <div style="background:{{ $cardSoft }};border:1px solid {{ $isRejected ? '#fecaca' : '#99f6e4' }};border-radius:28px;padding:40px 36px;box-shadow:0 24px 48px rgba(15,23,42,0.08);">
        <div style="display:flex;align-items:center;gap:14px;margin-bottom:22px;">
            <div style="width:52px;height:52px;border-radius:16px;background:{{ $cardAccent }};display:flex;align-items:center;justify-content:center;font-size:24px;color:white;">
                {{ $isRejected ? '!' : '⏳' }}
            </div>
            <div>
                <div style="font-size:13px;font-weight:700;letter-spacing:0.3px;text-transform:uppercase;color:{{ $cardAccent }};">Acenta Başvurusu</div>
                <h1 style="font-size:30px;font-weight:800;color:#0f172a;letter-spacing:-0.5px;">
                    {{ $isRejected ? 'Başvurunuz Sonuçlandı' : 'Başvurunuz İnceleniyor' }}
                </h1>
            </div>
        </div>

        <div style="font-size:15px;color:#334155;line-height:1.8;">
            @if($isRejected)
                <strong>{{ $agency?->name }}</strong> için yaptığınız acenta başvurusu şu anda onaylı değil.
                Admin tarafından yeni değerlendirme yapılana kadar panel özellikleri kapalı kalır.
            @else
                <strong>{{ $agency?->name }}</strong> için yaptığınız acenta başvurusu alındı.
                Admin onayı tamamlandığında aynı hesapla giriş yaparak acenta panelinizi kullanabilirsiniz.
            @endif
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-top:24px;">
            <div style="background:white;border:1px solid rgba(148,163,184,0.18);border-radius:18px;padding:16px;">
                <div style="font-size:12px;color:#94a3b8;font-weight:700;text-transform:uppercase;">Durum</div>
                <div style="font-size:20px;font-weight:800;color:#0f172a;margin-top:6px;">
                    {{ $isRejected ? 'Reddedildi' : 'Onay Bekliyor' }}
                </div>
            </div>
            <div style="background:white;border:1px solid rgba(148,163,184,0.18);border-radius:18px;padding:16px;">
                <div style="font-size:12px;color:#94a3b8;font-weight:700;text-transform:uppercase;">Başvuru Tarihi</div>
                <div style="font-size:20px;font-weight:800;color:#0f172a;margin-top:6px;">
                    {{ $agency?->created_at?->format('d.m.Y') ?? '—' }}
                </div>
            </div>
        </div>

        @if($agency?->approval_notes)
            <div style="margin-top:22px;padding:16px 18px;border-radius:18px;background:white;border:1px solid rgba(148,163,184,0.18);">
                <div style="font-size:12px;color:#94a3b8;font-weight:700;text-transform:uppercase;margin-bottom:8px;">Admin Notu</div>
                <div style="font-size:14px;color:#475569;line-height:1.7;">{{ $agency->approval_notes }}</div>
            </div>
        @endif

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:28px;">
            <a href="{{ route('home') }}" class="btn btn-primary">Ana Sayfaya Dön</a>
            <form method="POST" action="{{ route('logout') }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-outline">Çıkış Yap</button>
            </form>
        </div>
    </div>
</div>
@endsection
