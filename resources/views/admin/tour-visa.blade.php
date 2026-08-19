@extends('layouts.app')
@section('title', 'Vize Durumu — Admin')

@section('content')
@php
    $secenekler = ['unknown' => '— belirtilmemiş —', '1' => 'Vizeli', 'kapida' => 'Kapıda vize', '0' => 'Vizesiz'];
    $mevcut = fn ($t) => $t->requires_visa === null ? 'unknown' : ($t->visa_on_arrival ? 'kapida' : ($t->requires_visa ? '1' : '0'));
@endphp
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="max-width:94%;margin:0 auto;">

                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;flex-wrap:wrap;gap:12px;">
                    <h1 style="font-size:24px;font-weight:700;">Vize Durumu</h1>
                    <div style="font-size:13px;color:var(--text-muted);">
                        <strong style="color:{{ $yurtdisiEksik ? '#b45309' : '#059669' }};">{{ $yurtdisiEksik }}</strong> yurt dışı turda eksik
                        · {{ $eksikSayisi }} / {{ $toplam }} toplam eksik
                    </div>
                </div>

                <p style="font-size:13px;color:var(--text-muted);line-height:1.7;margin-bottom:20px;max-width:820px;">
                    <strong>“Vizesiz turlar”</strong> filtresi ve kategori sayfası yalnızca burada işaretlenen
                    turları gösterir. Otomatik doldurma <strong>bilerek yok</strong>: sayfadan çıkarımda
                    “vizesiz” etiketinin kesinliği 60 sayfalık ölçümde %76,9 çıktı ve o kategoriye giren
                    turların %15,4’ü gerçekte vize istiyordu — yanlış “vizesiz” kullanıcıyı sınırda bırakır.
                    Emin olmadığın turu <em>belirtilmemiş</em> bırak; hiçbir yerde vize iddiası görünmez.
                </p>

                @if(session('success'))
                    <div style="background:var(--green-bg);color:var(--green-text);padding:12px 16px;border-radius:var(--radius);margin-bottom:16px;font-size:14px;">
                        {{ session('success') }}
                    </div>
                @endif

                <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:20px;">
                    <div style="flex:1;min-width:220px;">
                        <label style="font-size:13px;color:#475569;display:block;margin-bottom:4px;">Tur ara</label>
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Başlıkta ara…"
                               style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;">
                    </div>
                    <div style="width:230px;">
                        <label style="font-size:13px;color:#475569;display:block;margin-bottom:4px;">Durum</label>
                        <select name="durum" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
                            <option value="yurtdisi" @selected($durum === 'yurtdisi')>Yurt dışı + eksik (öncelik)</option>
                            <option value="eksik" @selected($durum === 'eksik')>Eksik olanlar</option>
                            <option value="dolu" @selected($durum === 'dolu')>İşaretlenmiş olanlar</option>
                            <option value="tumu" @selected($durum === 'tumu')>Tümü</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-outline" style="padding:10px 20px;">Filtrele</button>
                </form>

                <form method="POST" action="{{ route('admin.tour-visa.update') }}">
                    @csrf
                    @method('PUT')

                    <div class="card" style="padding:0;overflow:hidden;">
                        <table style="width:100%;border-collapse:collapse;font-size:14px;">
                            <thead>
                                <tr style="background:var(--border-light);text-align:left;">
                                    <th style="padding:12px 16px;font-weight:600;">Tur</th>
                                    <th style="padding:12px 16px;font-weight:600;width:150px;">Acenta</th>
                                    <th style="padding:12px 16px;font-weight:600;width:210px;">Vize durumu</th>
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
                                            {{-- Yurt içi/dışı işareti burada kritik: yurt içi turda vize
                                                 alanı doldurmak gereksiz iş. --}}
                                            @if($tour->is_international)
                                                <span style="color:var(--accent-dark);font-weight:600;">· yurt dışı</span>
                                            @else
                                                <span>· yurt içi</span>
                                            @endif
                                            @unless($tour->is_active)
                                                <span style="color:#b45309;">· pasif</span>
                                            @endunless
                                        </div>
                                    </td>
                                    <td style="padding:12px 16px;color:var(--text-sec);font-size:13px;">
                                        {{ $tour->agency->name ?? '—' }}
                                    </td>
                                    <td style="padding:12px 16px;">
                                        <select name="visa[{{ $tour->id }}]"
                                                style="width:100%;padding:8px 12px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;background:#fff;">
                                            @foreach($secenekler as $deger => $etiket)
                                                <option value="{{ $deger }}" @selected($mevcut($tour) === $deger)>{{ $etiket }}</option>
                                            @endforeach
                                        </select>
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
