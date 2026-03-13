@extends('layouts.app')
@section('title', isset($post) ? 'Yazıyı Düzenle — Admin' : 'Yeni Blog Yazısı — Admin')

@section('content')
<div class="container">
    <div class="section">
        <h1 style="font-size:22px;font-weight:800;margin-bottom:24px;">{{ isset($post) ? 'Yazıyı Düzenle' : 'Yeni Blog Yazısı' }}</h1>

        @if($errors->any())
            <div class="alert alert-error">{{ $errors->first() }}</div>
        @endif

        <form method="POST" action="{{ isset($post) ? route('admin.blog.update', $post) : route('admin.blog.store') }}">
            @csrf
            @if(isset($post)) @method('PUT') @endif

            <div class="form-group">
                <label>Başlık *</label>
                <input type="text" name="title" value="{{ old('title', $post->title ?? '') }}" placeholder="Blog başlığı">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Kategori</label>
                    <select name="category">
                        @foreach(['Rehber','Seyahat İpuçları','Destinasyon','Tur Haberleri'] as $cat)
                            <option value="{{ $cat }}" {{ old('category', $post->category ?? 'Rehber') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label>Kapak Görseli URL</label>
                    <input type="url" name="image" value="{{ old('image', $post->image ?? '') }}" placeholder="https://...">
                </div>
            </div>

            <div class="form-group">
                <label>Kısa Özet</label>
                <textarea name="excerpt" rows="2" placeholder="Ana sayfada ve liste sayfasında görünür...">{{ old('excerpt', $post->excerpt ?? '') }}</textarea>
            </div>

            <div class="form-group">
                <label>İçerik *</label>
                <textarea name="content" rows="12" placeholder="Yazı içeriği...">{{ old('content', $post->content ?? '') }}</textarea>
            </div>

            <div class="form-group">
                <label>SEO Meta Açıklama</label>
                <input type="text" name="meta_description" value="{{ old('meta_description', $post->meta_description ?? '') }}" placeholder="Google'da görünecek açıklama (max 160 karakter)">
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
