@extends('layouts.app')
@section('title', 'Kayıtlı Aramalarım — turXtur')

@section('content')
<div class="container">
    <div class="section">
        <h1 style="font-size:24px;font-weight:800;margin-bottom:4px;">Kayıtlı Aramalarım</h1>
        <p style="color:var(--text-meta);font-size:14px;margin-bottom:24px;">Uyan yeni tur eklenince bildirimlerinde görürsün. Her arama için günde en fazla bir bildirim.</p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        @if($searches->count())
            <div style="display:flex;flex-direction:column;gap:12px;">
                @foreach($searches as $s)
                    <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius);padding:16px 18px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
                        <div style="flex:1;min-width:220px;">
                            <div style="font-weight:700;font-size:15px;">{{ $s->name }}</div>
                            <div style="font-size:12.5px;color:var(--text-meta);margin-top:3px;">
                                Kaydedildi: {{ $s->created_at->locale('tr')->isoFormat('D MMM YYYY') }}
                                @if($s->last_notified_at) · Son bildirim: {{ $s->last_notified_at->locale('tr')->isoFormat('D MMM') }} @endif
                            </div>
                        </div>
                        <a href="{{ $s->url() }}" class="btn btn-outline btn-sm">Sonuçları gör</a>
                        <form method="POST" action="{{ route('customer.saved-searches.destroy', $s) }}" onsubmit="return confirm('Kayıtlı arama silinsin mi?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" style="color:#b91c1c;">Sil</button>
                        </form>
                    </div>
                @endforeach
            </div>
        @else
            <div style="text-align:center;padding:48px 24px;background:var(--bg);border:1px dashed #cbd5e1;border-radius:16px;">
                <div style="font-size:16px;font-weight:700;margin-bottom:6px;">Henüz kayıtlı araman yok</div>
                <div style="font-size:13.5px;color:var(--text-meta);margin-bottom:18px;">Tur listesinde filtrelerini seç, sonra “Bu aramayı kaydet”e dokun.</div>
                <a href="{{ route('tours.index') }}" class="btn btn-primary">Turlara git</a>
            </div>
        @endif
    </div>
</div>
@endsection
