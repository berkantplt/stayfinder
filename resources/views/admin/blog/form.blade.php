@extends('layouts.app')
@section('title', isset($post) ? 'Yazıyı Düzenle — Admin' : 'Yeni Blog Yazısı — Admin')

@section('content')
<div class="container">
        @include('partials.breadcrumb', ['schema' => false, 'root' => ['name' => 'Admin', 'url' => route('admin.dashboard')], 'items' => [['name' => 'Blog Yönetimi', 'url' => route('admin.blog.index')], ['name' => isset($post) ? 'Düzenle' : 'Yeni Yazı']]])
    <div class="section">
        <h1 style="font-size:22px;font-weight:800;margin-bottom:24px;">{{ isset($post) ? 'Yazıyı Düzenle' : 'Yeni Blog Yazısı' }}</h1>

        @include('partials.form-errors')

        <form method="POST" action="{{ isset($post) ? route('admin.blog.update', $post) : route('admin.blog.store') }}">
            @csrf
            @if(isset($post)) @method('PUT') @endif

            <div class="form-group">
                <label>Başlık *</label>
                <input type="text" name="title" value="{{ old('title', $post->title ?? '') }}" placeholder="Blog başlığı">
@error('title')<p class="p-hata">{{ $message }}</p>@enderror
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category">
                        @foreach(['Rehber','Seyahat İpuçları','Destinasyon','Tur Haberleri'] as $cat)
                            <option value="{{ $cat }}" {{ old('category', $post->category ?? 'Rehber') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
@error('category')<p class="p-hata">{{ $message }}</p>@enderror
                </div>
                <div class="form-group">
                    <label>Kapak Görseli URL</label>
                    <input type="url" name="image" value="{{ old('image', $post->image ?? '') }}" placeholder="https://...">
@error('image')<p class="p-hata">{{ $message }}</p>@enderror
                </div>
            </div>

            <div class="form-group">
                <label>Kısa Özet</label>
                <textarea name="excerpt" rows="2" placeholder="Ana sayfada ve liste sayfasında görünür...">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
@error('excerpt')<p class="p-hata">{{ $message }}</p>@enderror
            </div>

            <div class="form-group">
                <label>İçerik *</label>
                <textarea name="content" rows="12" placeholder="Yazı içeriği...">{{ old('content', $post->content ?? '') }}</textarea>
@error('content')<p class="p-hata">{{ $message }}</p>@enderror
            </div>

            <div class="form-group">
                <label>SEO Meta Açıklama</label>
                <input type="text" name="meta_description" value="{{ old('meta_description', $post->meta_description ?? '') }}" placeholder="Google'da görünecek açıklama (max 160 karakter)">
@error('meta_description')<p class="p-hata">{{ $message }}</p>@enderror
            </div>

            <div class="form-group" style="display:flex;align-items:center;gap:10px;">
                <input type="checkbox" name="is_published" id="is_published" value="1" {{ old('is_published', $post->is_published ?? false) ? 'checked' : '' }} style="width:auto;">
                <label for="is_published" style="margin:0;font-weight:600;">Yayınla</label>
            </div>

            <div style="display:flex;gap:12px;margin-top:8px;">
                <button type="submit" class="btn btn-primary">{{ isset($post) ? 'Güncelle' : 'Yazıyı Oluştur' }}</button>
                <a href="{{ route('admin.blog.index') }}" class="btn btn-outline">İptal</a>
            </div>
        </form>
    </div>
</div>
@endsection
