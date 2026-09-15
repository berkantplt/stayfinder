@extends('layouts.app')
@section('title', 'Aramalarım — turXtur')

@section('content')
<div class="container">
    <div class="section">
        @include('partials.account-nav')
        <h1 style="font-size:24px;font-weight:800;margin-bottom:4px;">Aramalarım</h1>
        <p style="color:var(--text-muted);font-size:14px;margin-bottom:24px;">AI aramalarınız, keşif rehberleriniz ve karşılaştırma listeniz hesabınızda saklanır; cihaz değiştirseniz de burada.</p>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="profile-cols" style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:24px;">
            {{-- Karşılaştırma listem --}}
            <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;">
                <h2 style="font-size:16px;font-weight:700;margin-bottom:12px;">⚖️ Karşılaştırma Listem <span style="font-size:12px;color:var(--text-muted);font-weight:600;">({{ $compareTours->count() }}/{{ \App\Http\Controllers\Customer\AccountActivityController::COMPARE_LIMIT }})</span></h2>
                @forelse($compareTours as $tour)
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light);">
                        <a href="{{ route('tours.show', $tour) }}" style="flex:1;min-width:0;font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">{{ $tour->title }}</a>
                        <span style="font-size:13px;font-weight:700;color:var(--green);white-space:nowrap;">{{ $tour->formatted_price }}</span>
                        <form method="POST" action="{{ route('account.compare.remove', $tour) }}" style="margin:0;">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-outline btn-sm" title="Listeden çıkar">×</button>
                        </form>
                    </div>
                @empty
                    <div style="color:var(--text-muted);font-size:13px;padding:14px 0;">Listeniz boş. Tur kartlarındaki "+ Karşılaştır" ile ekleyin.</div>
                @endforelse
                @if($compareTours->count() >= 2)
                    <a href="{{ route('tours.compare', ['ids' => $compareTours->pluck('id')->all()]) }}" class="btn btn-primary btn-sm" style="margin-top:12px;">Karşılaştır →</a>
                @endif
            </div>

            {{-- AI aramalarım --}}
            <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;">
                <h2 style="font-size:16px;font-weight:700;margin-bottom:12px;">🤖 AI Aramalarım</h2>
                @forelse($aiSearches as $log)
                    @php $sonuc = count($log->result_tour_ids ?? []); @endphp
                    <div style="padding:10px 0;border-bottom:1px solid var(--border-light);">
                        <div style="font-weight:600;font-size:13px;">“{{ \Illuminate\Support\Str::limit($log->raw_query, 90) }}”</div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;display:flex;gap:10px;flex-wrap:wrap;">
                            <span>{{ $log->created_at->locale('tr')->isoFormat('D MMM YYYY HH:mm') }}</span>
                            <span>{{ $sonuc }} tur</span>
                            @if($sonuc > 0)
                                <a href="{{ route('ai.search.results', $log) }}" style="color:var(--accent);font-weight:600;">Sonuçları aç →</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div style="color:var(--text-muted);font-size:13px;padding:14px 0;">Henüz AI araması yapmadınız. <a href="{{ route('home') }}" style="color:var(--accent);">Ana sayfadan deneyin →</a></div>
                @endforelse
            </div>

            {{-- Rehberlerim --}}
            @if(config('ai.discovery_enabled'))
            <div style="background:var(--white);border:1px solid var(--border);border-radius:var(--radius-lg);padding:20px;">
                <h2 style="font-size:16px;font-weight:700;margin-bottom:12px;">🧭 Keşif Rehberlerim</h2>
                @forelse($guides as $guide)
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border-light);">
                        <div style="flex:1;min-width:0;">
                            <div style="font-weight:600;font-size:13px;">{{ $guide->destination_input }} · {{ $guide->duration_days }} gün</div>
                            <div style="font-size:12px;color:var(--text-muted);">{{ $guide->created_at->locale('tr')->isoFormat('D MMM YYYY') }} · {{ ['pending' => 'Hazırlanıyor', 'processing' => 'Hazırlanıyor', 'completed' => 'Hazır', 'failed' => 'Başarısız'][$guide->status] ?? $guide->status }}</div>
                        </div>
                        @if($guide->isCompleted())
                            <a href="{{ route('discovery.show', $guide) }}" class="btn btn-outline btn-sm">Aç</a>
                        @endif
                    </div>
                @empty
                    <div style="color:var(--text-muted);font-size:13px;padding:14px 0;">Henüz rehber oluşturmadınız. <a href="{{ route('discovery.index') }}" style="color:var(--accent);">Keşif rehberi →</a></div>
                @endforelse
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
