@extends('layouts.app')
@section('title', 'Destinasyon Profilleri — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')

        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:24px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Destinasyon Profilleri</h1>
                    <div style="font-size:13px;color:#64748b;margin-top:4px;">AI-fed şehir profilleri — yanlış olan summary/vibe/best_months alanlarını burada düzelt.</div>
                </div>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 16px;">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 16px;">
                    @foreach($errors->all() as $e) {{ $e }}<br> @endforeach
                </div>
            @endif

            {{-- Stats --}}
            <div style="display:grid;grid-template-columns:repeat(5,1fr);gap:12px;max-width:94%;margin:0 auto 20px;">
                @foreach([
                    ['Toplam', $stats['total'], '#0f172a'],
                    ['Manuel', $stats['manual'], '#0d9488'],
                    ['LLM', $stats['llm'], '#6366f1'],
                    ['Default', $stats['default'], '#f59e0b'],
                    ['Eski sürüm', $stats['stale'], '#dc2626'],
                ] as $item)
                    <div class="stat-card" style="padding:14px;">
                        <div style="font-size:11px;color:#64748b;font-weight:700;letter-spacing:.5px;text-transform:uppercase;">{{ $item[0] }}</div>
                        <div style="font-size:24px;font-weight:800;color:{{ $item[2] }};margin-top:4px;">{{ $item[1] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- Filter --}}
            <form method="GET" action="{{ route('admin.destination-profiles.index') }}" style="max-width:94%;margin:0 auto 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:end;">
                <div style="flex:1;min-width:200px;">
                    <label style="font-size:12px;color:#475569;font-weight:600;">Ara</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Şehir veya ülke" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;background:#fff;">
                </div>
                <div style="min-width:160px;">
                    <label style="font-size:12px;color:#475569;font-weight:600;">Kaynak</label>
                    <select name="source" style="width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:10px;font-size:13px;background:#fff;">
                        <option value="">Hepsi</option>
                        <option value="llm" {{ request('source') === 'llm' ? 'selected' : '' }}>LLM</option>
                        <option value="manual" {{ request('source') === 'manual' ? 'selected' : '' }}>Manuel</option>
                        <option value="default" {{ request('source') === 'default' ? 'selected' : '' }}>Default</option>
                    </select>
                </div>
                <div style="display:flex;align-items:center;gap:6px;padding-bottom:6px;">
                    <input type="checkbox" id="stale" name="stale" value="1" {{ request('stale') ? 'checked' : '' }}>
                    <label for="stale" style="font-size:13px;color:#475569;">Sadece eski sürüm</label>
                </div>
                <button type="submit" class="btn btn-primary" style="padding:9px 18px;">Filtrele</button>
                @if(request()->hasAny(['q', 'source', 'stale']))
                    <a href="{{ route('admin.destination-profiles.index') }}" class="btn btn-outline" style="padding:9px 14px;">Temizle</a>
                @endif
            </form>

            {{-- Table --}}
            <div class="stat-card" style="max-width:94%;margin:0 auto 24px;padding:0;overflow:hidden;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead>
                        <tr style="background:#f8fafc;border-bottom:1px solid #e2e8f0;">
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Şehir</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Ülke</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Vibe</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">İdeal Ay</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Kaynak</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">v</th>
                            <th style="text-align:left;padding:12px 14px;font-size:11px;color:#64748b;text-transform:uppercase;letter-spacing:.5px;">Güncelleme</th>
                            <th style="padding:12px 14px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($profiles as $p)
                            @php
                                $sourceColor = match($p->source) {
                                    'manual' => '#0d9488',
                                    'llm' => '#6366f1',
                                    default => '#f59e0b',
                                };
                                $isStale = $p->needsEnrichment();
                            @endphp
                            <tr style="border-bottom:1px solid #f1f5f9;">
                                <td style="padding:12px 14px;font-weight:700;color:#0f172a;">{{ $p->city }}</td>
                                <td style="padding:12px 14px;color:#475569;">{{ $p->country ?? '—' }}</td>
                                <td style="padding:12px 14px;">
                                    @foreach((array) ($p->vibe_tags ?? []) as $tag)
                                        <span style="display:inline-block;padding:2px 8px;border-radius:8px;background:#eef2ff;color:#4338ca;font-size:11px;font-weight:600;margin-right:4px;">{{ $tag }}</span>
                                    @endforeach
                                </td>
                                <td style="padding:12px 14px;color:#475569;font-size:12px;">
                                    {{ implode(', ', (array) ($p->best_months ?? [])) ?: '—' }}
                                </td>
                                <td style="padding:12px 14px;">
                                    <span style="padding:2px 8px;border-radius:8px;background:{{ $sourceColor }}1A;color:{{ $sourceColor }};font-size:11px;font-weight:700;text-transform:uppercase;">{{ $p->source }}</span>
                                </td>
                                <td style="padding:12px 14px;">
                                    <span style="font-size:12px;font-weight:700;color:{{ $isStale ? '#dc2626' : '#0f172a' }};">{{ $p->enrichment_version }}</span>
                                </td>
                                <td style="padding:12px 14px;font-size:12px;color:#94a3b8;">{{ $p->updated_at?->diffForHumans() }}</td>
                                <td style="padding:12px 14px;text-align:right;display:flex;gap:6px;justify-content:flex-end;">
                                    <a href="{{ route('admin.destination-profiles.edit', $p) }}" class="btn btn-outline btn-sm" style="padding:5px 10px;font-size:12px;">Düzenle</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="padding:30px;text-align:center;color:#94a3b8;">Eşleşen profil bulunamadı.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div style="max-width:94%;margin:0 auto;">{{ $profiles->links() }}</div>
        </div>
    </div>
</div>
@endsection
