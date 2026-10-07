@extends('layouts.app')
@section('title', 'Keşif Tabanları — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')

        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Keşif Tabanları</h1>
                    <div style="font-size:13px;color:#64748b;margin-top:4px;">
                        Keşif Rehberi'nin şehir başına parametresiz içerik havuzu. Taban varken rehber yalnız günlük planı AI'dan ister.
                        Yanlış mekân gördüğün tabanı sil ya da yeniden üret; {{ \App\Models\DiscoveryCityBase::MAX_AGE_DAYS }} günden eski taban kendiliğinden yenilenir.
                    </div>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 16px;">{{ session('success') }}</div>
            @endif
            @include('partials.form-errors', ['style' => 'max-width:94%;margin:0 auto 16px;'])

            {{-- Stats --}}
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;max-width:94%;margin:0 auto 20px;">
                @foreach([
                    ['Toplam taban', $stats['total'], '#0f172a'],
                    ['Tabanla üretilen rehber', $stats['hits'], '#0d9488'],
                    ['Bayat (yenilenecek)', $stats['stale'], '#dc2626'],
                ] as $item)
                    <div class="stat-card" style="padding:14px;">
                        <div style="font-size:11px;color:#64748b;font-weight:700;letter-spacing:.5px;text-transform:uppercase;">{{ $item[0] }}</div>
                        <div style="font-size:24px;font-weight:800;color:{{ $item[2] }};margin-top:4px;">{{ $item[1] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('admin.discovery-city-bases.index') }}" style="max-width:94%;margin:0 auto 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <div style="flex:1;min-width:200px;">
                    <label style="font-size:12px;color:#475569;font-weight:600;">Ara</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Şehir veya ülke" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;background:#fff;">
                </div>
                <div style="display:flex;align-items:center;gap:6px;padding-bottom:6px;">
                    <input type="checkbox" id="stale" name="stale" value="1" {{ request('stale') ? 'checked' : '' }}>
                    <label for="stale" style="font-size:13px;color:#475569;">Sadece bayat</label>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:9px 18px;">Filtrele</button>
                @if(request()->hasAny(['q', 'stale']))
                    <a href="{{ route('admin.discovery-city-bases.index') }}" class="btn btn-outline" style="padding:9px 14px;">Temizle</a>
                @endif
            </form>

            {{-- Table --}}
            <div class="stat-card" style="max-width:94%;margin:0 auto 24px;padding:0;overflow:hidden;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Şehir</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Ülke</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Havuz</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Kullanım</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Model</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Üretim</th>
                            <th style="padding:12px 14px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($bases as $b)
                            @php($bayat = $b->isStale())
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px 14px;">
                                    <div style="font-weight:700;color:#0f172a;">{{ $b->display_name }}</div>
                                    <div style="font-size:11px;color:#94a3b8;">{{ $b->normalized_city }}</div>
                                </td>
                                <td style="padding:12px 14px;color:#475569;">{{ $b->country ?? '—' }}</td>
                                <td style="padding:12px 14px;color:#475569;" title="Öne çıkan + yapılacak + tarihi + müze + yemek">
                                    {{ $b->itemCount() }} öğe
                                </td>
                                <td style="padding:12px 14px;font-weight:700;color:#0d9488;">{{ $b->hit_count }}</td>
                                <td style="padding:12px 14px;font-size:12px;color:#64748b;">{{ $b->model ?? '—' }}</td>
                                <td style="padding:12px 14px;font-size:12px;color:#94a3b8;">
                                    {{ $b->generated_at?->diffForHumans() ?? '—' }}
                                    @if($bayat)
                                        <span style="display:inline-block;margin-left:6px;padding:2px 8px;border-radius:8px;background:#fee2e2;color:#dc2626;font-size:11px;font-weight:700;">BAYAT</span>
                                    @endif
                                </td>
                                <td style="padding:12px 14px;text-align:right;white-space:nowrap;">
                                    <form method="POST" action="{{ route('admin.discovery-city-bases.regenerate', $b) }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" class="btn btn-outline btn-sm" style="padding:5px 10px;font-size:12px;">Yenile</button>
                                    </form>
                                    <form method="POST" action="{{ route('admin.discovery-city-bases.destroy', $b) }}" style="display:inline;margin-left:4px;"
                                          onsubmit="return confirm('{{ $b->display_name }} tabanı silinsin mi? Bir sonraki rehber tam üretimle gelir ve taban yeniden kurulur.');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-outline btn-sm" style="padding:5px 10px;font-size:12px;color:#dc2626;border-color:#fecaca;">Sil</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" style="padding:30px;text-align:center;color:#94a3b8;">Henüz şehir tabanı yok — ilk rehber tamamlandığında arka planda kurulur.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="max-width:94%;margin:0 auto;">{{ $bases->links() }}</div>
        </div>
    </div>
</div>
@endsection
