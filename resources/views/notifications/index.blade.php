@extends('layouts.app')
@section('title', 'Bildirimlerim')

@section('content')
<div class="container" style="padding-top:120px; padding-bottom:60px;">
    <div style="max-width:800px; margin:0 auto;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;">
            <h1 style="font-size:24px; font-weight:700;">Bildirimlerim</h1>
            @if($notifications->where('read_at', null)->count() > 0)
            <form action="{{ route('notifications.readAll') }}" method="POST">
                @csrf
                <button class="btn btn-outline btn-sm">Hepsini Okundu İşaretle</button>
            </form>
            @endif
        </div>

        <div class="card" style="padding:0; overflow:hidden;">
            @forelse($notifications as $notification)
            <div style="padding:20px; border-bottom:1px solid #f1f5f9; display:flex; gap:16px; align-items:flex-start; background: {{ $notification->read_at ? '#fff' : '#f8fafc' }}; transition: background 0.2s;">
                <div style="font-size:24px; background:#fff; width:48px; height:48px; display:flex; align-items:center; justify-content:center; border-radius:12px; box-shadow:0 4px 12px rgba(0,0,0,0.05);">
                    {{ $notification->data['icon'] ?? '🔔' }}
                </div>
                <div style="flex:1;">
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <h3 style="font-size:16px; font-weight:700; margin-bottom:4px;">{{ $notification->data['title'] }}</h3>
                        <span style="font-size:12px; color:var(--text-muted);">{{ $notification->created_at->diffForHumans() }}</span>
                    </div>
                    <p style="font-size:14px; color:var(--text-sec); margin-bottom:12px;">{{ $notification->data['message'] }}</p>
                    <div style="display:flex; gap:12px; align-items:center;">
                        <a href="{{ $notification->data['url'] }}" class="btn btn-primary btn-sm" style="padding:4px 12px; font-size:12px;">İncele</a>
                        @if(!$notification->read_at)
                        <form action="{{ route('notifications.read', $notification->id) }}" method="POST">
                            @csrf
                            <button style="background:none; border:none; color:var(--accent); font-size:12px; font-weight:600; cursor:pointer;">Okundu İşaretle</button>
                        </form>
                        @endif
                    </div>
                </div>
            </div>
            @empty
            <div style="padding:60px; text-align:center; color:var(--text-muted);">
                <div style="font-size:48px; margin-bottom:16px;">📭</div>
                <p>Henüz hiç bildiriminiz bulunmuyor.</p>
            </div>
            @endforelse
        </div>

        <div style="margin-top:24px;">
            {{ $notifications->links() }}
        </div>
    </div>
</div>
@endsection
