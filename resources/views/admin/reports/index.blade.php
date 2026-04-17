@extends('layouts.app')
@section('title', 'Raporlar ve İstatistikler')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div class="admin-header" style="margin-bottom:24px; max-width:94%; margin-left:auto; margin-right:auto;">
                <h1 style="font-size:24px; font-weight:700; color:var(--text);">Raporlar ve İstatistikler</h1>
                <p style="color:var(--text-sec); font-size:14px; margin-top:4px;">Platformun genel metriklerini analiz edin.</p>
            </div>

            <div style="max-width:94%; margin-left:auto; margin-right:auto;">
                {{-- Top Cards --}}
                <div class="grid-4" style="margin-bottom:32px;">
    <div class="card" style="padding:20px;">
        <div style="font-size:13px; color:var(--text-muted); font-weight:600; text-transform:uppercase;">Toplam Tur</div>
        <div style="font-size:28px; font-weight:800; color:var(--accent); margin-top:8px;">{{ number_format($stats['total_tours']) }}</div>
        <div style="font-size:12px; color:var(--green); margin-top:4px;">{{ number_format($stats['active_tours']) }} Aktif</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:13px; color:var(--text-muted); font-weight:600; text-transform:uppercase;">Kullanıcılar</div>
        <div style="font-size:28px; font-weight:800; color:var(--text); margin-top:8px;">{{ number_format($stats['total_users']) }}</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:13px; color:var(--text-muted); font-weight:600; text-transform:uppercase;">Acentalar</div>
        <div style="font-size:28px; font-weight:800; color:var(--text); margin-top:8px;">{{ number_format($stats['total_agencies']) }}</div>
    </div>
    <div class="card" style="padding:20px;">
        <div style="font-size:13px; color:var(--text-muted); font-weight:600; text-transform:uppercase;">Kategoriler</div>
        <div style="font-size:28px; font-weight:800; color:var(--text); margin-top:8px;">{{ number_format($stats['total_categories']) }}</div>
    </div>
</div>

<div class="grid-2">
    {{-- Top Viewed Tours --}}
    <div class="card">
        <div style="padding:20px; border-bottom:1px solid #f1f5f9;">
            <h2 style="font-size:16px; font-weight:700;">En Çok Görüntülenen Turlar</h2>
        </div>
        <div style="padding:20px;">
            <table class="table">
                <thead>
                    <tr>
                        <th>TUR ADI</th>
                        <th style="text-align:right;">GÖRÜNTÜLENME</th>
                        <th style="text-align:right;">TIKLANMA</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topViewedTours as $tour)
                    <tr>
                        <td style="font-weight:600;">{{ $tour->title }} <br><span style="font-size:12px; color:var(--text-muted); font-weight:400;">{{ $tour->destination }}</span></td>
                        <td style="text-align:right; color:var(--accent); font-weight:700;">{{ number_format($tour->views_count) }}</td>
                        <td style="text-align:right;">{{ number_format($tour->clicks_count) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" style="text-align:center;">Veri bulunamadı.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Popular Destinations Column --}}
    <div class="card">
        <div style="padding:20px; border-bottom:1px solid #f1f5f9;">
            <h2 style="font-size:16px; font-weight:700;">En Çok Tura Sahip Destinasyonlar</h2>
        </div>
        <div style="padding:20px;">
            <div style="display:flex; flex-direction:column; gap:16px;">
                @php $maxDest = $destinations->first() ? $destinations->first()->count : 1; @endphp
                @forelse($destinations as $dest)
                <div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:6px; font-size:14px; font-weight:600;">
                        <span>{{ $dest->destination }}</span>
                        <span>{{ $dest->count }} tur</span>
                    </div>
                    <div style="width:100%; background:#f1f5f9; border-radius:10px; height:8px; overflow:hidden;">
                        <div style="background:var(--accent); height:100%; width:{{ ($dest->count / $maxDest) * 100 }}%"></div>
                    </div>
                </div>
                @empty
                <div>Veri bulunamadı.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
    </div>
</div>
@endsection
