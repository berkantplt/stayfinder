@extends('layouts.app')
@section('title', 'Blog & Rehber — turXtur')
@section('description', 'Seyahat rehberleri, destinasyon önerileri ve tur ipuçları.')

@section('content')
<div class="container">
    <div class="section">
        @include('partials.breadcrumb', ['items' => [
            ['name' => 'Blog'],
        ]])
        @include('partials.pagination-seo', ['paginator' => $posts])
        <div style="margin-bottom:32px;">
            <h1 style="font-size:28px;font-weight:800;letter-spacing:-0.5px;margin-bottom:6px;">Blog & Seyahat Rehberi</h1>
            <p style="color:var(--text-muted);font-size:15px;">Destinasyon önerileri, seyahat ipuçları ve tur rehberleri</p>
        </div>

        @if($posts->count())
        {{-- Featured first post --}}
        @php $first = $posts->first(); @endphp
        <a href="{{ route('blog.show', $first) }}" style="display:block;background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);overflow:hidden;margin-bottom:32px;transition:all .2s;" onmouseover="this.style.boxShadow='var(--shadow-lg)'" onmouseout="this.style.boxShadow='none'">
            <div style="display:grid;grid-template-columns:1fr 1fr;min-height:240px;">
                @if($first->image)
                    <img src="{{ $first->image }}" alt="{{ $first->title }}" style="width:100%;height:100%;object-fit:cover;display:block;">
                @else
                    <div style="background:linear-gradient(135deg,#0d9488,#0891b2);display:flex;align-items:center;justify-content:center;font-size:64px;">✈️</div>
                @endif
                <div style="padding:28px 32px;display:flex;flex-direction:column;justify-content:center;">
                    <div style="display:flex;gap:8px;margin-bottom:12px;">
                        <span class="badge badge-accent">{{ $first->category }}</span>
                        <span style="font-size:12px;color:var(--text-muted);">{{ $first->reading_time }} dk okuma</span>
                    </div>
                    <h2 style="font-size:22px;font-weight:800;margin-bottom:10px;line-height:1.3;">{{ $first->title }}</h2>
                    @if($first->excerpt)
                        <p style="color:var(--text-sec);font-size:14px;line-height:1.7;">{{ $first->excerpt }}</p>
                    @endif
                    <div style="margin-top:16px;font-size:13px;color:var(--text-muted);">{{ $first->published_at?->format('d-m-Y') }}</div>
                </div>
            </div>
        </a>

        {{-- Rest of posts --}}
        <div class="grid-3">
            @foreach($posts->slice(1) as $post)
            <a href="{{ route('blog.show', $post) }}" class="card">
                @if($post->image)
                    <img src="{{ $post->image }}" alt="{{ $post->title }}" class="card-img">
                @else
                    <div class="card-img" style="background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:36px;">✍️</div>
                @endif
                <div class="card-body">
                    <div style="margin-bottom:6px;"><span class="badge badge-accent" style="font-size:11px;">{{ $post->category }}</span></div>
                    <div class="card-title">{{ $post->title }}</div>
                    @if($post->excerpt)
                        <div class="card-meta" style="margin-top:4px;line-height:1.5;">{{ Str::limit($post->excerpt, 80) }}</div>
                    @endif
                    <div style="margin-top:10px;font-size:12px;color:var(--text-muted);">{{ $post->published_at?->format('d-m-Y') }} · {{ $post->reading_time }} dk</div>
                </div>
            </a>
            @endforeach
        </div>

        <div class="pagination-wrapper">{{ $posts->links() }}</div>

        @else
        <div style="text-align:center;padding:60px;color:var(--text-muted);">
            <div style="font-size:48px;margin-bottom:12px;">✍️</div>
            <p>Henüz blog yazısı yok.</p>
        </div>
        @endif
    </div>
</div>
@endsection
