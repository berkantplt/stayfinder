@extends('layouts.app')
@section('title', $destination->name . ' Turları — turXtur')
@section('description', $destination->description ?: $destination->name . ' destinasyonundaki ' . $tourCount . ' tur seçeneğini karşılaştırın. ' . ($minPrice ? number_format($minPrice, 0, ',', '.') . ' ₺\'den başlayan fiyatlar.' : ''))
@if($destination->image)
    @section('og_image', url($destination->image))
@endif

@section('content')
<div class="container">
    {{-- Hero --}}
    <div style="position:relative;height:280px;border-radius:var(--radius-lg);overflow:hidden;margin-bottom:32px;">
        @if($destination->image)
            <img src="{{ $destination->image }}" alt="{{ $destination->name }}" style="width:100%;height:100%;object-fit:cover;">
        @else
            <div style="width:100%;height:100%;background:linear-gradient(135deg,#0d9488,#0891b2);"></div>
        @endif
        <div style="position:absolute;inset:0;background:linear-gradient(transparent 30%, rgba(0,0,0,.65));display:flex;flex-direction:column;justify-content:flex-end;padding:28px;">
            <div style="font-size:13px;color:rgba(255,255,255,.75);margin-bottom:8px;">
                <a href="{{ route('home') }}" style="color:rgba(255,255,255,.75);">Ana Sayfa</a> ›
                <a href="{{ route('tours.index') }}" style="color:rgba(255,255,255,.75);">Turlar</a> › {{ $destination->name }}
            </div>
            <h1 style="font-size:28px;font-weight:800;color:white;letter-spacing:-0.5px;">{{ $destination->name }}</h1>
            <div style="font-size:14px;color:rgba(255,255,255,.85);margin-top:4px;">{{ $destination->country }}</div>
        </div>
    </div>

    {{-- Stats Row --}}
    <div class="grid-4" style="margin-bottom:32px;">
        <div class="stat-card">
            <div class="stat-value" style="color:var(--accent);">{{ $tourCount }}</div>
            <div class="stat-label">Aktif Tur</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:var(--green);">{{ $minPrice ? number_format($minPrice, 0, ',', '.') . ' ₺' : '—' }}</div>
            <div class="stat-label">En Ucuz</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:#2563eb;">{{ $avgPrice ? number_format($avgPrice, 0, ',', '.') . ' ₺' : '—' }}</div>
            <div class="stat-label">Ortalama Fiyat</div>
        </div>
        <div class="stat-card">
            <div class="stat-value" style="color:#d97706;">{{ $agencies->count() }}</div>
            <div class="stat-label">Acenta</div>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:1fr 300px;gap:32px;">
        {{-- Left: Tours --}}
        <div>
            @if($destination->description)
            <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:24px;">
                <p style="color:var(--text-sec);line-height:1.8;font-size:15px;">{{ $destination->description }}</p>
            </div>
            @endif

            @if($destination->highlights)
            <div style="background:var(--accent-bg);border-radius:var(--radius);padding:16px 20px;margin-bottom:24px;">
                <div style="font-weight:700;margin-bottom:10px;">🌟 Öne Çıkanlar</div>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    @foreach(explode(',', $destination->highlights) as $h)
                        <span class="badge badge-accent">{{ trim($h) }}</span>
                    @endforeach
                </div>
            </div>
            @endif

            <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">{{ $destination->name }} Turları</h2>

            @forelse($tours as $tour)
            <a href="{{ route('tours.show', $tour) }}" style="display:flex;gap:14px;background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:16px;margin-bottom:10px;transition:all .2s;align-items:center;" onmouseover="this.style.boxShadow='var(--shadow-md)';this.style.transform='translateY(-1px)'" onmouseout="this.style.boxShadow='none';this.style.transform='none'">
                @if($tour->image)
                    <img src="{{ $tour->image }}" alt="{{ $tour->title }}" style="width:110px;height:74px;object-fit:cover;border-radius:8px;flex-shrink:0;">
                @else
                    <div style="width:110px;height:74px;border-radius:8px;flex-shrink:0;background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:28px;">🏖️</div>
                @endif
                <div style="flex:1;min-width:0;">
                    <div style="font-weight:700;font-size:15px;margin-bottom:2px;">{{ $tour->title }}</div>
                    <div style="font-size:13px;color:var(--text-muted);">{{ $tour->agency->name }} · {{ $tour->duration_days }} gün</div>
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
                    <div style="font-size:40px;margin-bottom:8px;">📭</div>
                    Henüz bu destinasyona tur bulunmuyor.
                </div>
            @endforelse
        </div>

        {{-- Right: Sidebar --}}
        <div style="align-self:start;">
            {{-- Agencies --}}
            <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:20px;margin-bottom:16px;">
                <h3 style="font-size:15px;font-weight:700;margin-bottom:14px;">Bu Destinasyona Tur Satan Acentalar</h3>
                @foreach($agencies as $agency)
                <div style="display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid var(--border-light);">
                    <div style="width:36px;height:36px;background:var(--accent-bg);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:16px;">🏢</div>
                    <div>
                        <a href="{{ route('agencies.show', $agency) }}" style="font-weight:600;font-size:14px;color:var(--text);">{{ $agency->name }}</a>
                        <div style="font-size:12px;color:var(--text-muted);">{{ $tours->where('agency_id', $agency->id)->count() }} tur</div>
                    </div>
                </div>
                @endforeach
            </div>

            {{-- CTA --}}
            <div style="background:linear-gradient(135deg,#0d9488,#0891b2);border-radius:var(--radius);padding:20px;color:white;">
                <div style="font-weight:700;margin-bottom:6px;">Fiyat Alarmı</div>
                <div style="font-size:13px;opacity:.85;margin-bottom:12px;">{{ $destination->name }} turları için fiyat düşünce bildirim al.</div>
                <a href="{{ route('register') }}" class="btn" style="background:rgba(255,255,255,.15);color:white;border:1px solid rgba(255,255,255,.3);width:100%;">Kayıt Ol →</a>
            </div>
        </div>
    </div>
</div>
@endsection
