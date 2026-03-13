@extends('layouts.app')
@section('title', 'Destinasyonlar — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h1 style="font-size:24px;font-weight:700;">Destinasyonlar</h1>
            </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div style="display:grid;grid-template-columns:repeat(2,1fr);gap:20px;max-width:94%;margin-left:auto;margin-right:auto;">
            @foreach($destinations as $dest)
            <div style="background:var(--white);border:1px solid var(--border-light);border-radius:var(--radius);overflow:hidden;">
                {{-- Preview --}}
                <div style="position:relative;height:160px;background:#f0f0f0;">
                    @if($dest->image)
                        <img src="{{ $dest->image }}" alt="{{ $dest->name }}" style="width:100%;height:100%;object-fit:cover;">
                    @else
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:48px;background:linear-gradient(135deg,var(--accent-bg),#f0fdf4);">🌍</div>
                    @endif
                    <div style="position:absolute;top:8px;right:8px;">
                        <span class="badge {{ $dest->is_active ? 'badge-green' : '' }}" style="{{ !$dest->is_active ? 'background:#fef2f2;color:#991b1b;' : '' }}">{{ $dest->is_active ? 'Aktif' : 'Pasif' }}</span>
                    </div>
                </div>

                {{-- Form --}}
                <form method="POST" action="{{ route('admin.destinations.update', $dest) }}" style="padding:16px;">
                    @csrf @method('PUT')
                    <div style="font-size:18px;font-weight:700;margin-bottom:12px;">{{ $dest->name }}</div>

                    <div class="form-group">
                        <label>Fotoğraf URL'si</label>
                        <input type="url" name="image" value="{{ $dest->image }}" placeholder="https://images.unsplash.com/...">
                    </div>

                    <div class="form-group">
                        <label>Sıralama</label>
                        <input type="number" name="sort_order" value="{{ $dest->sort_order }}" min="0">
                    </div>

                    <div style="display:flex;gap:8px;">
                        <button type="submit" class="btn btn-primary btn-sm">Kaydet</button>
                    </div>
                </form>
                <form method="POST" action="{{ route('admin.destinations.toggle', $dest) }}" style="padding:0 16px 16px;">
                    @csrf
                    <button type="submit" class="btn btn-outline btn-sm">{{ $dest->is_active ? 'Pasif Yap' : 'Aktif Yap' }}</button>
                </form>
            </div>
            @endforeach
        </div>
        </div>
    </div>
</div>
@endsection
