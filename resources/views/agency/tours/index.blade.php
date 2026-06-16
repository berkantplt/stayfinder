@extends('layouts.app')
@section('title', 'Turlarım — Acenta Paneli')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:30px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Turlarım</h1>
                <a href="{{ route('agency.tours.create') }}" class="btn btn-primary" style="padding:10px 20px;font-size:15px;box-shadow:0 8px 20px -6px rgba(16,185,129,0.4);{{ !$canCreateTours ? 'opacity:.65;' : '' }}">
                    <svg width="20" height="20" viewBox="2 2 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> 
                    Yeni Tur Ekle
                </a>
            </div>

        @if(session('success'))
            <div class="alert alert-success" style="max-width:94%;margin-left:auto;margin-right:auto;">{{ session('success') }}</div>
        @endif

        @unless($canCreateTours)
            <div class="alert alert-error" style="max-width:94%;margin-left:auto;margin-right:auto;">
                Henüz aktif kategori yetkiniz yok. Yeni tur paylaşmak için
                <a href="{{ route('agency.category-licenses.index') }}" style="font-weight:700;color:inherit;text-decoration:underline;">Kategori Yetkilendirme Merkezi</a>
                üzerinden kategori ekleyin.
            </div>
        @endunless

        <div class="stat-card" style="padding:24px;max-width:94%;margin-left:auto;margin-right:auto;">
            <div style="overflow-x:auto;">
                <table class="table" style="width:100%;text-align:left;">
                    <thead>
                        <tr>
                            <th style="padding-left:0;">Tur</th>
                            <th>Kategori</th>
                            <th>Destinasyon</th>
                            <th>Fiyat</th>
                            <th>Tarih</th>
                            <th>Durum</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($tours as $tour)
                        <tr>
                            <td style="padding-left:0;"><a href="{{ route('agency.tours.show', $tour) }}" style="font-weight:600;color:#0f172a;">{{ $tour->title }}</a></td>
                            <td>{{ $tour->category?->name ?? '—' }}</td>
                            <td>{{ $tour->destination }}</td>
                            <td style="white-space:nowrap;">{{ $tour->formatted_price }}</td>
                            <td style="white-space:nowrap;">{{ $tour->departure_date?->format('d.m.Y') ?? '—' }}</td>
                            <td>
                                <span class="badge" style="background:{{ $tour->is_active ? '#d1fae5;color:#065f46' : '#fef2f2;color:#991b1b' }};border:none;padding:6px 12px;border-radius:20px;font-weight:600;">
                                    {{ $tour->is_active ? 'Aktif' : 'Pasif' }}
                                </span>
                            </td>
                            <td style="white-space:nowrap;">
                                <div style="display:inline-flex;gap:8px;align-items:center;">
                                    <a href="{{ route('agency.tours.show', $tour) }}" class="btn btn-outline btn-sm" title="Görüntüle">👁️</a>
                                    <a href="{{ route('agency.tours.edit', $tour) }}" class="btn btn-outline btn-sm">Düzenle</a>
                                    <form method="POST" action="{{ route('agency.tours.destroy', $tour) }}" onsubmit="return confirm('Bu turu silmek istediğinize emin misiniz?')" style="margin:0;">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">Sil</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:40px;">Henüz tur eklemediniz.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div style="margin-top:16px;">{{ $tours->links() }}</div>
        </div>
    </div>
</div>
@endsection
