@extends('layouts.app')
@section('title', $user->name . ' — Profilim')

@section('content')
<div class="container">
    <div class="section">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <style>
        @media(max-width:768px) {
            .profile-hero { padding:20px !important; }
            {{-- minmax(0,1fr): çıplak 1fr'de favori satırındaki nowrap başlık
                 sütunu min-content'ine (~412px) itip sayfayı taşırıyordu. --}}
            .profile-cols { grid-template-columns:minmax(0,1fr) !important; gap:16px !important; }
            .profile-cols > div { min-width:0; }
        }
        </style>

        {{-- Profile Header --}}
        <div class="profile-hero" style="background:linear-gradient(135deg,#0d9488,#0891b2);border-radius:var(--radius-lg);padding:32px;color:white;margin-bottom:32px;position:relative;overflow:hidden;">
            <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
                {{-- Avatar --}}
                @if($user->avatar)
                    <img src="{{ \Illuminate\Support\Str::startsWith($user->avatar, ['http://','https://']) ? $user->avatar : asset('storage/'.$user->avatar) }}" alt="{{ $user->name }}" style="width:80px;height:80px;border-radius:50%;border:3px solid rgba(255,255,255,.4);object-fit:cover;">
                @else
                    <div style="width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.2);border:3px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;font-size:32px;font-weight:800;">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</div>
                @endif
                <div style="flex:1;">
                    <h1 style="font-size:24px;font-weight:800;margin-bottom:4px;">{{ $user->name }}</h1>
                    <div style="font-size:14px;opacity:.85;margin-bottom:8px;">{{ $user->email }}</div>
                    <div style="display:flex;gap:12px;flex-wrap:wrap;font-size:13px;opacity:.8;">
                        @if($user->city) <span>📍 {{ $user->city }}</span> @endif
                        @if($user->phone) <span>📞 {{ $user->phone }}</span> @endif
                        @if($user->birth_date) <span>🎂 {{ $user->birth_date->format('d-m-Y') }}</span> @endif
                        <span>📅 Üye: {{ $user->created_at->format('M Y') }}</span>
                    </div>
                </div>
                <a href="{{ route('profile.edit') }}" class="btn" style="background:rgba(255,255,255,.15);color:white;border:1px solid rgba(255,255,255,.3);">✏️ Düzenle</a>
            </div>
            @if($user->bio)
                <p style="margin-top:16px;font-size:14px;opacity:.85;max-width:600px;">{{ $user->bio }}</p>
            @endif
        </div>

        {{-- Stats --}}
        <div class="grid-4" style="margin-bottom:32px;">
            <div class="stat-card">
                <div class="stat-value" style="color:var(--accent);">{{ $favorites->count() }}</div>
                <div class="stat-label">Favorilerim</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#f59e0b;">{{ $reviews->count() }}</div>
                <div class="stat-label">Yorumlarım</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#2563eb;">{{ $viewCount }}</div>
                <div class="stat-label">Gezilen Tur</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color:#8b5cf6;">{{ $reviews->avg('rating') ? number_format($reviews->avg('rating'), 1) : '—' }}</div>
                <div class="stat-label">Ort. Puanım</div>
            </div>
        </div>

        <div class="profile-cols" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;">
            {{-- Favorites --}}
            <div>
                <h2 style="font-size:17px;font-weight:700;margin-bottom:14px;">❤️ Favorilerim</h2>
                @forelse($favorites->take(5) as $tour)
                <a href="{{ route('tours.show', $tour) }}" style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light);">
                    @if($tour->image)
                        <img src="{{ $tour->image }}" style="width:56px;height:40px;border-radius:6px;object-fit:cover;flex-shrink:0;">
                    @else
                        <div style="width:56px;height:40px;background:var(--accent-bg);border-radius:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;">🏖️</div>
                    @endif
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $tour->title }}</div>
                        <div style="font-size:12px;color:var(--text-muted);">{{ $tour->agency->name }}</div>
                    </div>
                    <div style="font-weight:700;font-size:14px;color:var(--green);flex-shrink:0;">{{ $tour->formatted_price }}</div>
                </a>
                @empty
                    <div style="color:var(--text-muted);font-size:14px;padding:20px 0;text-align:center;">Henüz favori tur yok. <a href="{{ route('tours.index') }}" style="color:var(--accent);">Keşfet →</a></div>
                @endforelse
                @if($favorites->count() > 5)
                    <a href="{{ route('favorites.index') }}" style="font-size:13px;color:var(--accent);font-weight:600;display:block;margin-top:8px;">Tümünü gör ({{ $favorites->count() }}) →</a>
                @endif
            </div>

            {{-- Reviews --}}
            <div>
                <h2 style="font-size:17px;font-weight:700;margin-bottom:14px;">⭐ Yorumlarım</h2>
                @forelse($reviews->take(4) as $review)
                <div style="padding:10px 0;border-bottom:1px solid var(--border-light);">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px;">
                        <a href="{{ route('tours.show', $review->tour) }}" style="font-weight:600;font-size:13px;color:var(--text);">{{ $review->tour->title }}</a>
                        <span style="color:#f59e0b;font-size:12px;">{{ str_repeat('★', $review->rating) }}</span>
                    </div>
                    <p style="font-size:13px;color:var(--text-sec);line-height:1.5;margin:0;">{{ Str::limit($review->comment, 80) }}</p>
                    <div style="font-size:11px;color:var(--text-muted);margin-top:2px;">{{ $review->created_at->diffForHumans() }}</div>
                </div>
                @empty
                    <div style="color:var(--text-muted);font-size:14px;padding:20px 0;text-align:center;">Henüz yorum yapmadınız.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
