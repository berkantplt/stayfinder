@extends('layouts.app')
@section('title', 'Yapay Zeka Arama Sonuçları')

@section('content')
<div class="container" style="padding-top:120px; padding-bottom:60px;">
    <div style="margin-bottom:40px; text-align:center;">
        <div style="font-size:32px; margin-bottom:12px;">🤖</div>
        <h1 style="font-size:28px; font-weight:800; color:var(--text); letter-spacing:-0.5px;">Akıllı Arama Sonuçları</h1>
        <p style="color:var(--text-sec); font-size:16px;">"{{ $query }}" için sana en uygun turları buldum.</p>
    </div>

    @if($aiComment)
    <div style="background:#f0f9ff; border:1px solid #bae6fd; border-radius:16px; padding:20px; margin-bottom:40px; display:flex; gap:16px; align-items:flex-start;">
        <div style="font-size:24px;">💡</div>
        <div style="color:#0369a1; font-weight:500; line-height:1.6;">{{ $aiComment }}</div>
    </div>
    @endif

    <div class="grid-3">
        @forelse($results as $tour)
        @php
            $tourId = is_array($tour) ? ($tour['id'] ?? null) : ($tour->id ?? null);
            $tourSlug = is_array($tour) ? ($tour['slug'] ?? null) : ($tour->slug ?? null);
            $tourUrl = is_array($tour) ? ($tour['url'] ?? null) : ($tour->url ?? null);
            $detailUrl = $tourUrl
                ?? ($tourId ? route('tours.show', $tourId) : null)
                ?? ($tourSlug ? url('/turlar/' . $tourSlug) : route('tours.index'));
            $tourImage = is_array($tour) ? ($tour['image'] ?? '') : ($tour->image ?? '');
            $tourTitle = is_array($tour) ? ($tour['title'] ?? 'Tur') : ($tour->title ?? 'Tur');
            $tourDestination = is_array($tour) ? ($tour['destination'] ?? '-') : ($tour->destination ?? '-');
            $tourSimilarity = is_array($tour) ? ($tour['similarity'] ?? 0) : ($tour->similarity ?? 0);
            $tourCompatibility = is_array($tour) ? ($tour['compatibility_score'] ?? $tourSimilarity) : ($tour->compatibility_score ?? $tourSimilarity);
            $tourRank = is_array($tour) ? ($tour['rank'] ?? ($loop->index + 1)) : ($tour->rank ?? ($loop->index + 1));
            $tourPrice = is_array($tour) ? ($tour['price'] ?? 0) : ($tour->price ?? 0);
            $tourCurrency = is_array($tour) ? ($tour['currency'] ?? 'TRY') : ($tour->currency ?? 'TRY');
            $llmReason = is_array($tour) ? ($tour['llm_reason'] ?? null) : ($tour->llm_reason ?? null);
            $detailUrlWithLog = $detailUrl;
            if (!empty($logId)) {
                $detailUrlWithLog = $detailUrl . (str_contains($detailUrl, '?') ? '&' : '?') . 'ai_log_id=' . $logId . '&ai_rank=' . $tourRank;
            }
        @endphp
        <div class="card">
            <a href="{{ $detailUrlWithLog }}">
                <img src="{{ $tourImage }}" class="card-img" alt="{{ $tourTitle }}">
            </a>
            <div class="card-body">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px;">
                    <div style="font-size:12px; color:var(--accent); font-weight:700; text-transform:uppercase;">{{ $tourDestination }}</div>
                    <div style="background:var(--green-bg); color:var(--green-text); font-size:11px; font-weight:700; padding:2px 8px; border-radius:10px;">
                        %{{ round($tourCompatibility * 100) }} Uyumlu
                    </div>
                </div>
                <h3 class="card-title">{{ $tourTitle }}</h3>
                @if(!empty($llmReason))
                <div style="margin-top:10px; padding:10px 12px; background:#f8fafc; border-left:3px solid #6366f1; border-radius:6px; font-size:13px; color:#475569; line-height:1.5;">
                    <span style="font-weight:600; color:#4338ca;">Neden bu tur:</span> {{ $llmReason }}
                </div>
                @endif
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:16px;">
                    <div class="price-tag">{{ number_format($tourPrice, 0) }} {{ $tourCurrency }}</div>
                    <a href="{{ $detailUrlWithLog }}" class="btn btn-outline btn-sm">Detaylar</a>
                </div>
            </div>
        </div>
        @empty
        <div style="grid-column: span 3; text-align:center; padding:60px;">
            <p style="color:var(--text-muted); font-size:16px;">Üzgünüm, kriterlerine uygun bir tur bulamadım. İstersen farklı bir bütçe veya tatil tipi deneyebilirsin.</p>
            <a href="{{ route('home') }}" class="btn btn-primary" style="margin-top:20px;">Ana Sayfaya Dön</a>
        </div>
        @endforelse
    </div>
</div>
@endsection
