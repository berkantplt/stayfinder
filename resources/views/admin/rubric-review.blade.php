@extends('layouts.app')

@section('content')
<div class="container" style="padding:24px 0;">
    <h1 style="font-size:22px;margin-bottom:4px;">Rubrik Puanları — Editör İncelemesi</h1>
    <p style="color:#64748b;font-size:13px;margin-bottom:20px;">
        İki geçiş arasında 1'den büyük fark çıkan puanlar otomatik yayınlanmaz (needs_review).
        Null kalan boyutlar genelde tur açıklamasının eksik olduğunu gösterir — turu zenginleştirip yeniden puanlatın.
    </p>

    @if(session('success'))
        <div style="background:#dcfce7;color:#166534;padding:10px 14px;border-radius:10px;margin-bottom:16px;font-size:13px;">{{ session('success') }}</div>
    @endif

    <h2 style="font-size:16px;margin:18px 0 10px;">Puan kayıtları</h2>
    <div style="overflow-x:auto;">
    <table style="width:100%;border-collapse:collapse;font-size:12.5px;">
        <thead>
            <tr style="text-align:left;border-bottom:2px solid #e2e8f0;">
                <th style="padding:8px 6px;">Tur</th>
                @foreach($dimensions as $d)
                    <th style="padding:8px 4px;font-size:10.5px;">{{ $d }}</th>
                @endforeach
                <th style="padding:8px 6px;">Durum</th>
                <th style="padding:8px 6px;">Puanlandı</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($scores as $score)
                <tr style="border-bottom:1px solid #f1f5f9;{{ $score->review_status === 'needs_review' ? 'background:#fef9c3;' : '' }}">
                    <td style="padding:8px 6px;max-width:220px;">{{ $score->tour?->title ?? '#'.$score->tour_id }}</td>
                    @foreach($dimensions as $d)
                        @php $v = $score->scores[$d]['value'] ?? null; @endphp
                        <td style="padding:8px 4px;{{ $v === null ? 'color:#dc2626;font-weight:700;' : '' }}">{{ $v ?? '∅' }}</td>
                    @endforeach
                    <td style="padding:8px 6px;">{{ $score->review_status }}</td>
                    <td style="padding:8px 6px;white-space:nowrap;">{{ $score->scored_at?->format('d.m H:i') }}</td>
                    <td style="padding:8px 6px;">
                        @if($score->review_status === 'needs_review')
                            <form method="POST" action="{{ route('admin.rubric.approve', $score) }}">
                                @csrf
                                <button type="submit" class="btn btn-sm" style="background:#0d9488;color:#fff;border:none;border-radius:8px;padding:5px 10px;cursor:pointer;">Onayla</button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ count($dimensions) + 4 }}" style="padding:16px;color:#94a3b8;">Henüz puanlanmış tur yok — <code>php artisan app:score-tours-rubric</code> ile başlatın.</td></tr>
            @endforelse
        </tbody>
    </table>
    </div>
    {{ $scores->links() }}

    <h2 style="font-size:16px;margin:26px 0 10px;">Eksik veri raporu (null boyutlar)</h2>
    @forelse($nullReport as $row)
        <div style="padding:8px 12px;border:1px solid #fecaca;background:#fef2f2;border-radius:10px;margin-bottom:8px;font-size:12.5px;">
            <b>{{ $row['score']->tour?->title ?? '#'.$row['score']->tour_id }}</b> — kanıt bulunamayan boyutlar: {{ implode(', ', $row['nulls']) }}
        </div>
    @empty
        <p style="color:#94a3b8;font-size:13px;">Null boyut yok — tüm turlarda tüm boyutlar kanıtlı puanlanmış. 🎉</p>
    @endforelse
</div>
@endsection
