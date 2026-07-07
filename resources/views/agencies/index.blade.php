@extends('layouts.app')
@section('title', 'Acentalar — turXtur')

@section('content')
<div class="container">
    <div class="section">
        <h1 style="font-size:24px;font-weight:700;margin-bottom:8px;">Tur Acentaları</h1>
        <p style="color:var(--text-muted);font-size:15px;margin-bottom:24px;">Güvenilir acentalardan turları karşılaştırın</p>

        <div class="grid-3">
            @foreach($agencies as $agency)
            <a href="{{ route('agencies.show', $agency) }}" class="card">
                <div style="padding:24px;">
                    <div style="display:flex;align-items:center;gap:14px;margin-bottom:16px;">
                        <div style="width:48px;height:48px;background:var(--accent-bg);border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:22px;">🏢</div>
                        <div>
                            <div style="font-size:16px;font-weight:700;">{{ $agency->name }}</div>
                            <div style="font-size:13px;color:var(--text-muted);">{{ $agency->active_tours_count }} tur</div>
                        </div>
                    </div>
                    @if($agency->description)
                        <p style="font-size:13px;color:var(--text-sec);line-height:1.6;margin-bottom:12px;">{{ Str::limit($agency->description, 100) }}</p>
                    @endif
                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                        @if($agency->phone)
                            <span style="font-size:12px;color:var(--text-muted);background:var(--bg);padding:4px 8px;border-radius:6px;">📞 {{ $agency->phone }}</span>
                        @endif
                        @if($agency->website_url)
                            <span style="font-size:12px;color:var(--text-muted);background:var(--bg);padding:4px 8px;border-radius:6px;">🌐 Web Sitesi</span>
                        @endif
                    </div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
</div>
@endsection
