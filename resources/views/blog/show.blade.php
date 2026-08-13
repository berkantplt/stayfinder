@extends('layouts.app')
@section('title', $post->title . ' — turXtur Blog')
@section('description', $post->meta_description ?: $post->excerpt)
@if($post->image)
    @section('og_image', url($post->image))
@endif

@push('head')
@include('partials.json-ld', ['data' => [
    '@context' => 'https://schema.org',
    '@type' => 'Article',
    'headline' => $post->title,
    'image' => [$post->image ? url($post->image) : asset('images/og-default.png')],
    'datePublished' => ($post->published_at ?? $post->created_at)->toIso8601String(),
    'dateModified' => $post->updated_at->toIso8601String(),
    'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => route('blog.show', $post)],
    'author' => [[
        '@type' => 'Person',
        'name' => 'turXtur Editör',
        'url' => url('/'),
    ]],
    'publisher' => [
        '@type' => 'Organization',
        'name' => 'turXtur',
        'logo' => ['@type' => 'ImageObject', 'url' => asset('images/og-default.png')],
    ],
]])
@endpush

@section('content')
<div class="container">
    <div style="max-width:780px;margin:0 auto;padding:40px 0;">
        @include('partials.breadcrumb', ['items' => [
            ['name' => 'Blog', 'url' => route('blog.index')],
            ['name' => $post->title],
        ]])

        {{-- Category + Meta --}}
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
            <span class="badge badge-accent">{{ $post->category }}</span>
            <span style="font-size:13px;color:var(--text-muted);">{{ $post->published_at?->format('d-m-Y') }}</span>
            <span style="font-size:13px;color:var(--text-muted);">· {{ $post->reading_time }} dk okuma</span>
        </div>

        {{-- Title --}}
        <h1 style="font-size:30px;font-weight:800;letter-spacing:-0.8px;line-height:1.25;margin-bottom:16px;">{{ $post->title }}</h1>

        @if($post->excerpt)
            <p style="font-size:17px;color:var(--text-sec);line-height:1.75;margin-bottom:24px;border-left:4px solid var(--accent);padding-left:16px;">{{ $post->excerpt }}</p>
        @endif

        {{-- Cover Image --}}
        @if($post->image)
            <img src="{{ $post->image }}" alt="{{ $post->title }}" style="width:100%;max-height:420px;object-fit:cover;border-radius:var(--radius-lg);margin-bottom:32px;">
        @endif

        {{-- Content --}}
        <div style="font-size:16px;line-height:1.85;color:var(--text-sec);">
            {!! nl2br(e($post->content)) !!}
        </div>

        {{-- Share --}}
        <div style="margin-top:40px;padding:20px;background:var(--accent-bg);border-radius:var(--radius);display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <span style="font-weight:600;font-size:14px;">Bu yazıyı paylaş:</span>
            <a href="https://wa.me/?text={{ urlencode($post->title . ' - ' . request()->url()) }}" target="_blank" class="btn btn-sm btn-outline">📱 WhatsApp</a>
            <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode(request()->url()) }}" target="_blank" class="btn btn-sm btn-outline">🐦 Twitter / X</a>
        </div>
    </div>

    {{-- Related Posts --}}
    @if($related->count())
    <div style="border-top:1px solid var(--border);padding:32px 0;max-width:780px;margin:0 auto;">
        <h2 style="font-size:18px;font-weight:700;margin-bottom:16px;">İlgili Yazılar</h2>
        <div class="grid-3">
            @foreach($related as $rel)
            <a href="{{ route('blog.show', $rel) }}" class="card">
                @if($rel->image)
                    <img src="{{ $rel->image }}" alt="{{ $rel->title }}" class="card-img">
                @else
                    <div class="card-img" style="background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:28px;">✍️</div>
                @endif
                <div class="card-body">
                    <div class="card-title" style="font-size:14px;">{{ $rel->title }}</div>
                    <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">{{ $rel->published_at?->format('d-m-Y') }}</div>
                </div>
            </a>
            @endforeach
        </div>
    </div>
    @endif
</div>
@endsection
