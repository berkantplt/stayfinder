@extends('layouts.app')
@section('title', 'Blog Yönetimi — Admin')

@section('styles')
/* ── Blog yönetimi (admin) — çıplak CSS, layout <style> içine basılır ── */
.by-icerik { max-width:94%; margin:0 auto; }
.by-bas { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; margin-bottom:24px; }
.by-bas-sol { display:flex; align-items:center; gap:14px; min-width:0; }
.by-ikon { width:44px; height:44px; flex:none; border-radius:12px; background:var(--accent-bg); color:var(--accent-dark); display:flex; align-items:center; justify-content:center; font-size:20px; }
.by-sayaclar { display:grid; grid-template-columns:repeat(3, minmax(0,1fr)); gap:14px; margin-bottom:20px; }
.by-sayac { display:block; text-decoration:none; color:inherit; padding:16px 20px; transition:outline-color .15s; outline:2px solid transparent; outline-offset:-2px; }
.by-sayac:hover { outline-color:var(--p-cizgi); }
.by-sayac.aktif { outline-color:var(--p-vurgu); }
.by-sayac-etiket { font-size:11px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:var(--p-metin-3); }
.by-sayac-deger { font-size:26px; font-weight:800; letter-spacing:-.5px; margin-top:4px; color:var(--p-metin); font-variant-numeric:tabular-nums; }
.by-filtre { display:flex; gap:10px; flex-wrap:wrap; align-items:center; margin-bottom:16px; }
.by-filtre input[type="search"], .by-filtre select { height:42px; padding:0 14px; border:1.5px solid var(--border); border-radius:10px; font-size:14px; background:#fff; color:var(--text); font-family:inherit; }
.by-filtre input[type="search"] { flex:1; min-width:220px; }
.by-filtre input[type="search"]:focus, .by-filtre select:focus { outline:none; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-bg); }
.by-sifirla { font-size:13px; font-weight:600; color:var(--p-metin-3); white-space:nowrap; }
.by-sifirla:hover { color:var(--p-tehlike-metin); }
.by-tablo { table-layout:fixed; }
.by-tablo th, .by-tablo td { padding:14px 16px !important; }
.by-tablo th:first-child, .by-tablo td:first-child { padding-left:20px !important; }
.by-tablo th:last-child, .by-tablo td:last-child { padding-right:20px !important; }
.by-tablo .badge, .by-tablo .p-etiket { white-space:nowrap; }
.by-yazi { display:flex; align-items:center; gap:12px; min-width:0; }
.by-kapak { width:56px; height:42px; flex:none; border-radius:8px; object-fit:cover; background:linear-gradient(135deg,#e0f2fe,#f0fdf4); display:flex; align-items:center; justify-content:center; font-size:18px; }
.by-baslik { font-weight:700; color:var(--p-metin); text-decoration:none; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; line-height:1.3; }
.by-baslik:hover { color:var(--accent-ink); }
.by-ozet { font-size:12.5px; color:var(--p-metin-3); margin-top:3px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.by-tarih { font-size:13px; color:var(--p-metin-2); white-space:nowrap; font-variant-numeric:tabular-nums; }
.by-tarih small { display:block; font-size:11.5px; color:var(--p-metin-4); margin-top:2px; }
.by-islem { display:flex; align-items:center; justify-content:flex-end; gap:6px; }
.by-islem form { margin:0; }
.by-islem .btn-sm { padding:7px 11px; font-size:12.5px; white-space:nowrap; }
.by-bos { text-align:center; padding:56px 24px; color:var(--p-metin-3); }
.by-bos-ikon { font-size:44px; margin-bottom:10px; }
.by-bos p { margin-bottom:16px; }
@media (max-width:768px) {
    .by-icerik { max-width:none; }
    .by-bas .btn { width:100%; justify-content:center; }
    .by-sayaclar { gap:8px; }
    .by-sayac { padding:12px 14px; }
    .by-sayac-deger { font-size:20px; }
    .by-filtre input[type="search"] { min-width:0; flex-basis:100%; }
    .by-filtre select { flex:1; }
}
@endsection

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div class="by-icerik">
                <div class="by-bas">
                    <div class="by-bas-sol">
                        <div class="by-ikon" aria-hidden="true">✍️</div>
                        <div>
                            <h1 class="p-sayfa-baslik">Blog Yönetimi</h1>
                            <p class="p-alt">Rehber, ipucu ve haber yazılarını yazın, taslak tutun, hazır olunca yayınlayın.</p>
                        </div>
                    </div>
                    <a href="{{ route('admin.blog.create') }}" class="btn btn-primary">+ Yeni Yazı</a>
                </div>

                @if(session('success'))
                    <div class="alert alert-success" role="status">{{ session('success') }}</div>
                @endif
                @include('partials.form-errors')

                {{-- Sayaçlar aynı zamanda durum filtresi --}}
                <div class="by-sayaclar">
                    <a href="{{ route('admin.blog.index') }}" class="stat-card by-sayac {{ $durum === null ? 'aktif' : '' }}">
                        <div class="by-sayac-etiket">Toplam</div>
                        <div class="by-sayac-deger">{{ $sayac['toplam'] }}</div>
                    </a>
                    <a href="{{ route('admin.blog.index', ['durum' => 'yayinda']) }}" class="stat-card by-sayac {{ $durum === 'yayinda' ? 'aktif' : '' }}">
                        <div class="by-sayac-etiket">Yayında</div>
                        <div class="by-sayac-deger" style="color:var(--p-basari-metin);">{{ $sayac['yayinda'] }}</div>
                    </a>
                    <a href="{{ route('admin.blog.index', ['durum' => 'taslak']) }}" class="stat-card by-sayac {{ $durum === 'taslak' ? 'aktif' : '' }}">
                        <div class="by-sayac-etiket">Taslak</div>
                        <div class="by-sayac-deger" style="color:var(--p-uyari-metin);">{{ $sayac['taslak'] }}</div>
                    </a>
                </div>

                <form method="GET" action="{{ route('admin.blog.index') }}" class="by-filtre" role="search">
                    <input type="search" name="q" value="{{ $q }}" placeholder="Başlık veya özette ara…" aria-label="Yazı ara">
                    <select name="durum" aria-label="Durum">
                        <option value="">Tüm durumlar</option>
                        <option value="yayinda" @selected($durum === 'yayinda')>Yayında</option>
                        <option value="taslak" @selected($durum === 'taslak')>Taslak</option>
                    </select>
                    <select name="kategori" aria-label="Kategori">
                        <option value="">Tüm kategoriler</option>
                        @foreach($kategoriler as $kat)
                            <option value="{{ $kat }}" @selected($kategori === $kat)>{{ $kat }}</option>
                        @endforeach
                    </select>
                    <button type="submit" class="btn btn-outline btn-sm">Filtrele</button>
                    @if($filtreli)
                        <a href="{{ route('admin.blog.index') }}" class="by-sifirla">Temizle ✕</a>
                    @endif
                </form>

                <div class="card">
                    <div class="table-wrap"><table class="table by-tablo">
                        <colgroup>
                            <col style="width:38%"><col style="width:14%"><col style="width:10%"><col style="width:13%"><col style="width:25%">
                        </colgroup>
                        <thead>
                            <tr>
                                <th>Yazı</th>
                                <th>Kategori</th>
                                <th>Durum</th>
                                <th>Tarih</th>
                                <th style="text-align:right;">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                        @forelse($posts as $post)
                            <tr>
                                <td>
                                    <div class="by-yazi">
                                        @if($post->image)
                                            <img src="{{ $post->image }}" alt="" class="by-kapak" loading="lazy">
                                        @else
                                            <div class="by-kapak" aria-hidden="true">✍️</div>
                                        @endif
                                        <div style="min-width:0;">
                                            <a href="{{ route('admin.blog.edit', $post) }}" class="by-baslik">{{ $post->title }}</a>
                                            <div class="by-ozet">{{ $post->excerpt ? Str::limit($post->excerpt, 90) : '/blog/' . $post->slug }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge badge-accent">{{ $post->category }}</span></td>
                                <td>
                                    @if($post->is_published)
                                        <span class="p-etiket p-etiket-basari">Yayında</span>
                                    @else
                                        <span class="p-etiket p-etiket-uyari">Taslak</span>
                                    @endif
                                </td>
                                <td class="by-tarih">
                                    @if($post->is_published && $post->published_at)
                                        {{ $post->published_at->format('d-m-Y') }}
                                        <small>yayın tarihi</small>
                                    @else
                                        {{ $post->updated_at->format('d-m-Y') }}
                                        <small>son düzenleme</small>
                                    @endif
                                </td>
                                <td>
                                    <div class="by-islem">
                                        {{-- Taslak sitede 404 verir (PostController::show); bağlantı yalnız yayındakilere --}}
                                        @if($post->is_published)
                                            <a href="{{ route('blog.show', $post) }}" target="_blank" rel="noopener" class="btn btn-sm btn-outline" title="Sitede yeni sekmede aç">Görüntüle</a>
                                        @endif
                                        <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-sm btn-outline">Düzenle</a>
                                        <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" onsubmit="return confirm(@js('“' . $post->title . '” kalıcı olarak silinsin mi? Bu işlem geri alınamaz.'))">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-danger" type="submit">Sil</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">
                                    <div class="by-bos">
                                        <div class="by-bos-ikon" aria-hidden="true">✍️</div>
                                        @if($filtreli)
                                            <p>Bu filtreye uyan yazı yok.</p>
                                            <a href="{{ route('admin.blog.index') }}" class="btn btn-outline btn-sm">Filtreyi temizle</a>
                                        @else
                                            <p>Henüz blog yazısı yok. İlk yazınızı oluşturun.</p>
                                            <a href="{{ route('admin.blog.create') }}" class="btn btn-primary btn-sm">+ Yeni Yazı</a>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                        </tbody>
                    </table></div>
                </div>

                @if($posts->hasPages())
                    <div class="pagination-wrapper">{{ $posts->links() }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
