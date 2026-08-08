{{-- Yasal/kurumsal sayfaların ortak kabuğu.
     Kullanım: @extends('legal._layout') + @section('legal_title') + @section('legal_body') --}}
@extends('layouts.app')

@section('title', trim($__env->yieldContent('legal_title')).' — turXtur')
@section('description', trim($__env->yieldContent('legal_lede')) ?: 'turXtur yasal bilgilendirme sayfası.')

@section('content')
<div class="container">
    <div class="section" style="max-width:820px;margin:0 auto;">

        <nav aria-label="Konum" style="font-size:13px;color:var(--text-muted);margin-bottom:14px;">
            <a href="{{ route('home') }}" style="color:var(--text-muted);">Ana sayfa</a>
            <span aria-hidden="true"> › </span>
            <span>@yield('legal_title')</span>
        </nav>

        <h1 style="font-size:30px;font-weight:800;letter-spacing:-0.6px;line-height:1.2;margin-bottom:10px;">
            @yield('legal_title')
        </h1>

        @hasSection('legal_lede')
            <p style="font-size:16px;color:var(--text-sec);line-height:1.7;margin-bottom:26px;max-width:64ch;">
                @yield('legal_lede')
            </p>
        @endif

        <div class="legal-body">
            @yield('legal_body')
        </div>

        <p style="margin-top:36px;padding-top:16px;border-top:1px solid var(--border);font-size:13px;color:var(--text-muted);">
            Son güncelleme: {{ \Carbon\Carbon::parse(config('company.legal_updated_at'))->format('d.m.Y') }}
        </p>

    </div>
</div>

<style>
    .legal-body { font-size:15px; line-height:1.8; color:var(--text-sec); }
    .legal-body h2 {
        font-size:19px; font-weight:750; letter-spacing:-0.3px; color:var(--text);
        margin:32px 0 12px; line-height:1.3;
    }
    .legal-body h3 { font-size:16px; font-weight:700; color:var(--text); margin:22px 0 8px; }
    .legal-body p { margin-bottom:14px; max-width:70ch; }
    .legal-body ul, .legal-body ol { margin:0 0 16px 20px; max-width:70ch; }
    .legal-body li { margin-bottom:7px; }
    .legal-body a { color:var(--accent); text-decoration:underline; }
    .legal-body strong { color:var(--text); font-weight:650; }
    .legal-table { width:100%; border-collapse:collapse; margin-bottom:18px; font-size:14px; }
    .legal-table th, .legal-table td { padding:9px 12px; border:1px solid var(--border); text-align:left; vertical-align:top; }
    .legal-table th { background:#f8fafc; font-weight:700; color:var(--text); width:34%; }
    .legal-note {
        border:1px solid var(--border); border-left:3px solid var(--accent);
        background:#f8fafc; padding:14px 16px; border-radius:6px; margin:18px 0;
    }
    .legal-note p:last-child { margin-bottom:0; }
    @media (max-width:600px) {
        .legal-table, .legal-table tbody, .legal-table tr, .legal-table th, .legal-table td { display:block; width:100%; }
        .legal-table th { border-bottom:none; }
        .legal-table td { border-top:none; margin-bottom:8px; }
    }
</style>
@endsection
