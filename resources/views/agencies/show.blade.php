@extends('layouts.app')
@section('title', $agency->name . ' — turXtur')

@section('content')
<div class="container">
    {{-- Hero Banner --}}
    <div style="background:linear-gradient(135deg,#0d9488,#0891b2);border-radius:0 0 24px 24px;padding:40px 40px 32px;color:white;margin-bottom:32px;">
        <div style="font-size:13px;margin-bottom:12px;opacity:.8;">
            <a href="{{ route('home') }}" style="color:rgba(255,255,255,.7);">Ana Sayfa</a> ›
            {{ $agency->name }}
        </div>
        <div style="display:flex;align-items:center;gap:20px;">
            <div style="width:72px;height:72px;background:rgba(255,255,255,.15);backdrop-filter:blur(10px);border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:32px;">🏢</div>
            <div>
                <h1 style="font-size:28px;font-weight:800;letter-spacing:-0.5px;">{{ $agency->name }}</h1>
                <div style="opacity:.85;font-size:14px;margin-top:4px;">{{ $agency->activeTours->count() }} aktif tur</div>
            </div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:32px;">
        {{-- Left: Tours --}}
        <div>
            <h2 style="font-size:20px;font-weight:700;margin-bottom:16px;">Turlar</h2>

            @forelse($agency->activeTours->sortBy('price') as $tour)
            <a href="{{ route('tours.show', $tour) }}" style="display:flex;gap:16px;padding:16px;border:1px solid var(--border-light);border-radius:var(--radius);margin-bottom:12px;transition:all .2s;align-items:center;" onmouseover="this.style.boxShadow='0 4px 16px rgba(0,0,0,0.08)';this.style.transform='translateY(-1px)'" onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                @if($tour->image)
                    <img src="{{ $tour->image }}" alt="{{ $tour->title }}" style="width:120px;height:80px;object-fit:cover;border-radius:8px;flex-shrink:0;">
                @else
                    <div style="width:120px;height:80px;border-radius:8px;flex-shrink:0;background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:28px;">🏖️</div>
                @endif
                <div style="flex:1;min-width:0;">
                    <div style="font-size:15px;font-weight:700;">{{ $tour->title }}</div>
                    <div style="font-size:13px;color:var(--text-muted);margin-top:2px;">📍 {{ $tour->destination }} · {{ $tour->duration_days }} gün</div>
                    @if($tour->departure_date)
                        <div style="font-size:12px;color:var(--text-muted);">📅 {{ $tour->departure_date->format('d-m-Y') }}</div>
                    @endif
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <div class="price-tag" style="font-size:18px;">{{ $tour->formatted_price }}</div>
                    <div style="font-size:12px;color:var(--text-muted);">/ kişi</div>
                </div>
            </a>
            @empty
                <div style="text-align:center;padding:40px;color:var(--text-muted);">
                    <div style="font-size:40px;margin-bottom:8px;">📦</div>
                    Henüz aktif tur bulunmuyor.
                </div>
            @endforelse
        </div>

        {{-- Right: Sidebar --}}
        <div>
            {{-- Contact Card --}}
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);padding:24px;margin-bottom:16px;">
                <h3 style="font-size:16px;font-weight:700;margin-bottom:16px;">İletişim Bilgileri</h3>

                @if($agency->phone)
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <span style="width:36px;height:36px;background:var(--accent-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;">📞</span>
                    <div>
                        <div style="font-size:12px;color:var(--text-muted);">Telefon</div>
                        <a href="tel:{{ $agency->phone }}" style="font-size:14px;font-weight:600;color:var(--text);">{{ $agency->phone }}</a>
                    </div>
                </div>
                @endif

                @if($agency->email)
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <span style="width:36px;height:36px;background:var(--accent-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;">✉️</span>
                    <div>
                        <div style="font-size:12px;color:var(--text-muted);">E-posta</div>
                        <a href="mailto:{{ $agency->email }}" style="font-size:14px;font-weight:600;color:var(--text);">{{ $agency->email }}</a>
                    </div>
                </div>
                @endif

                @if($agency->address)
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:12px;">
                    <span style="width:36px;height:36px;background:var(--accent-bg);border-radius:10px;display:flex;align-items:center;justify-content:center;">📍</span>
                    <div>
                        <div style="font-size:12px;color:var(--text-muted);">Adres</div>
                        <div style="font-size:14px;color:var(--text);">{{ $agency->address }}</div>
                    </div>
                </div>
                @endif

                @if($agency->website_url)
                    <a href="{{ $agency->website_url }}" target="_blank" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:8px;">🌐 Web Sitesini Ziyaret Et</a>
                @endif
            </div>

            {{-- About --}}
            @if($agency->description)
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);padding:24px;">
                <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;">Hakkında</h3>
                <p style="font-size:14px;color:var(--text-sec);line-height:1.7;">{{ $agency->description }}</p>
            </div>
            @endif

            {{-- Stats --}}
            <div style="background:var(--accent-bg);border-radius:var(--radius);padding:20px;margin-top:16px;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div style="text-align:center;">
                        <div style="font-size:24px;font-weight:800;color:var(--accent);">{{ $agency->activeTours->count() }}</div>
                        <div style="font-size:12px;color:var(--text-muted);">Aktif Tur</div>
                    </div>
                    <div style="text-align:center;">
                        @php $destinations = $agency->activeTours->pluck('destination')->unique()->count(); @endphp
                        <div style="font-size:24px;font-weight:800;color:var(--accent);">{{ $destinations }}</div>
                        <div style="font-size:12px;color:var(--text-muted);">Destinasyon</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
