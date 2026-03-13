@extends('layouts.app')
@section('title', 'Blog Yönetimi — Admin')

@section('content')
<div class="container">
    <div class="section">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;">
            <div style="display:flex;align-items:center;gap:12px;">
                <a href="{{ route('admin.dashboard') }}" class="btn btn-outline btn-sm">← Geri</a>
                <div>
                    <h1 style="font-size:22px;font-weight:800;margin-bottom:2px;">Blog Yönetimi</h1>
                    <p style="color:var(--text-muted);font-size:14px;">{{ $posts->total() }} yazı</p>
                </div>
            </div>
            <a href="{{ route('admin.blog.create') }}" class="btn btn-primary">+ Yeni Yazı</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card" style="overflow:visible;">
            <table class="table">
                <thead>
                    <tr>
                        <th>Başlık</th>
                        <th>Kategori</th>
                        <th>Durum</th>
                        <th>Tarih</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($posts as $post)
                    <tr>
                        <td>
                            <a href="{{ route('blog.show', $post) }}" target="_blank" style="font-weight:600;">{{ $post->title }}</a>
                        </td>
                        <td><span class="badge badge-accent">{{ $post->category }}</span></td>
                        <td>
                            @if($post->is_published)
                                <span class="badge badge-green">Yayında</span>
                            @else
                                <span class="badge" style="background:#fef3c7;color:#92400e;">Taslak</span>
                            @endif
                        </td>
                        <td style="font-size:13px;color:var(--text-muted);">{{ $post->published_at?->format('d M Y') ?? '—' }}</td>
                        <td style="display:flex;gap:6px;">
                            <a href="{{ route('admin.blog.edit', $post) }}" class="btn btn-sm btn-outline">Düzenle</a>
                            <form method="POST" action="{{ route('admin.blog.destroy', $post) }}" onsubmit="return confirm('Yazı silinsin mi?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" type="submit">Sil</button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:var(--text-muted);padding:32px;">Henüz blog yazısı yok.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrapper">{{ $posts->links() }}</div>
    </div>
</div>
@endsection
