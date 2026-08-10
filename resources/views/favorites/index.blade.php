@extends('layouts.app')
@section('title', 'Favorilerim — turXtur')

@section('content')
<div class="container">
    <div class="section">
        <h1 style="font-size:24px;font-weight:800;margin-bottom:4px;">❤️ Favorilerim</h1>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:24px;">Beğendiğin turlar burada saklanır.</p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($favorites->count())
        <div class="grid-4 m-hcard-list">
            @foreach($favorites as $tour)
            <div class="card" style="position:relative;">
                {{-- Remove button --}}
                <form method="POST" action="{{ route('favorites.toggle', $tour) }}" style="position:absolute;top:8px;right:8px;z-index:10;">
                    @csrf
                    <button type="submit" style="width:34px;height:34px;border-radius:50%;background:white;border:none;cursor:pointer;font-size:16px;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.15);">❤️</button>
                </form>

                <a href="{{ route('tours.show', $tour) }}">
                    @if($tour->image)
                        <img src="{{ $tour->image }}" alt="{{ $tour->title }}" class="card-img">
                    @else
                        <div class="card-img" style="background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:36px;">🏖️</div>
                    @endif
                    <div class="card-body">
                        <div class="card-title">{{ $tour->title }}</div>
                        <div class="card-meta">{{ $tour->agency->name }} · {{ $tour->duration_label }}</div>
                    @include('partials.tour_card_meta', ['tour' => $tour])
                        <div style="margin-top:8px;display:flex;align-items:center;justify-content:space-between;">
                            <div>
                                <span class="price-tag" style="font-size:18px;">{{ $tour->formatted_price }}</span>
                                <span class="price-sm"> / kişi başı</span>
                            </div>
                            <span class="badge badge-accent">📍 {{ $tour->destination }}</span>
                        </div>
                    </div>
                </a>
                
                {{-- Compare Button for Favorites --}}
                <div style="padding:0 16px 16px 16px;">
                    <button type="button" class="btn btn-outline btn-sm compare-toggle" data-tour-id="{{ $tour->id }}" onclick="window.toggleCompare({{ $tour->id }})" style="width:100%;border-radius:8px;font-size:13px;padding:8px 10px;">+ Karşılaştır</button>
                </div>
            </div>
            @endforeach
        </div>
        @else
            <div style="text-align:center;padding:60px 20px;">
                <div style="font-size:64px;margin-bottom:16px;">💔</div>
                <h2 style="font-size:20px;font-weight:700;margin-bottom:8px;">Henüz favori turun yok</h2>
                <p style="color:var(--text-muted);margin-bottom:24px;">Turları keşfedip ❤️ butonuna tıklayarak favorilerine ekleyebilirsin.</p>
                <a href="{{ route('tours.index') }}" class="btn btn-primary">Turları Keşfet →</a>
            </div>
        @endif
    </div>
</div>
@endsection
