@extends('layouts.app')
@section('title', 'Kalkış Şehirleri — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="max-width:94%;margin:0 auto;">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:12px;">
                    <h1 style="font-size:24px;font-weight:700;">Kalkış Şehirleri</h1>
                    <div style="font-size:13px;color:var(--text-muted);">
                        <strong style="color:{{ $eksikSayisi ? '#b45309' : '#059669' }};">{{ $eksikSayisi }}</strong> eksik / {{ $toplam }} tur
                    </div>
                </div>

                <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin-bottom:20px;max-width:760px;">
                    Bu alan <strong>“{şehir} kalkışlı {destinasyon} turları”</strong> sayfalarının tek girdisi.
                    Büyük rakiplerin boş bıraktığı, doldurulduğunda doğrudan sıralama getiren alan.
                    Otomatik çıkarım yalnız başlığında açık kalkış ifadesi olan turları doldurabiliyor;
                    kalanı burada elle seçilmeli. Boş bırakılan tur, kalkış sayfalarında hiç görünmez.
                </p>

                @if(session('success'))
                    <div style="background:var(--green-bg);color:var(--green-text);padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:14px;">
                        {{ session('success') }}
                    </div>
                @endif

                {{-- Filtre --}}
                <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px;">
                    <div style="flex:1;min-width:220px;">
                        <label style="font-size:13px;color:#475569;display:block;margin-bottom:4px;">Tur ara</label>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Başlıkta ara…"
                               style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;">
                    </div>
                    <div style="width:180px;">
                        <label style="font-size:13px;color:#475569;display:block;margin-bottom:4px;">Durum</label>
                        <select name="durum" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
                            <option value="eksik" @selected($durum === 'eksik')>Eksik olanlar</option>
                            <option value="dolu" @selected($durum === 'dolu')>Dolu olanlar</option>
                            <option value="tumu" @selected($durum === 'tumu')>Tümü</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline" style="padding:10px 20px;">Filtrele</button>
                </form>

                <form method="POST" action="{{ route('admin.departure-cities.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="card" style="padding:0;overflow:hidden;">
                        <table style="width:100%;border-collapse:collapse;font-size:14px;">
                            <thead>
                                <tr style="background:var(--border-light);text-align:left;">
                                    <th style="padding:12px 16px;font-weight:600;">Tur</th>
                                    <th style="padding:12px 16px;font-weight:600;width:150px;">Acenta</th>
                                    <th style="padding:12px 16px;font-weight:600;width:230px;">Kalkış şehri</th>
                                </tr>
                            </thead>
                            <tbody>
                            @forelse($tours as $tour)
                                <tr style="border-top:1px solid var(--border-light);">
                                    <td style="padding:12px 16px;">
                                        <a href="{{ route('tours.show', $tour) }}" target="_blank" style="font-weight:600;color:var(--text);">
                                            {{ $tour->title }}
                                        </a>
                                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">
                                            📍 {{ $tour->destination ?: '—' }}
                                            @unless($tour->is_active)
                                                <span style="color:#b45309;">· pasif</span>
                                            @endunless
                                        </div>
                                    </td>
                                    <td style="padding:12px 16px;color:var(--text-sec);font-size:13px;">
                                        {{ $tour->agency->name ?? '—' }}
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <select name="cities[{{ $tour->id }}]"
                                                style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
                                            <option value="">— seçilmedi —</option>
                                            @foreach($sehirler as $sehir)
                                                <option value="{{ $sehir }}"
                                                    @selected(($tour->departure_city ?? ($oneriler[$tour->id]['city'] ?? null)) === $sehir)>
                                                    {{ $sehir }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @if(isset($oneriler[$tour->id]))
                                            {{-- Otomatik çıkarımın önerisi hazır seçili gelir; kaydetmeden
                                                 önce insan onayı şart, bu yüzden kaynağı da yazıyoruz. --}}
                                            <div style="font-size:11px;color:var(--accent);margin-top:4px;">
                                                ✨ öneri: {{ $oneriler[$tour->id]['city'] }}
                                                <span style="color:var(--text-muted);">({{ $oneriler[$tour->id]['source'] }})</span>
                                            </div>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" style="padding:40px;text-align:center;color:var(--text-muted);">
                                        Bu filtreye uyan tur yok.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($tours->count())
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:20px;gap:16px;flex-wrap:wrap;">
                            <div>{{ $tours->links() }}</div>
                            <button type="submit" class="btn btn-primary" style="padding:12px 28px;font-weight:700;">
                                Bu sayfayı kaydet
                            </button>
                        </div>
                        <p style="font-size:12px;color:var(--text-muted);margin-top:8px;">
                            Yalnız bu sayfadaki {{ $tours->count() }} tur kaydedilir — sayfa değiştirmeden önce kaydedin.
                        </p>
                    @endif
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
