@extends('layouts.app')
@section('title', 'Tüm Turlar — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h1 style="font-size:24px;font-weight:700;">Tüm Turlar</h1>
            </div>

        <div class="card" style="margin-bottom:24px;padding:20px;max-width:94%;margin-left:auto;margin-right:auto;">
            <form id="search-form" action="{{ route('admin.tours') }}" method="GET" style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">
                
                <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Arama (Tur, Destinasyon)</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Tur ara..." style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;">
                </div>

                <div class="form-group" style="width:140px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Durum</label>
                    <select name="status" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;background:#fff;">
                        <option value="all">Tümü</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Aktif</option>
                        <option value="inactive" {{ request('status') === 'inactive' ? 'selected' : '' }}>Pasif</option>
                    </select>
                </div>
                
                <div class="form-group" style="width:160px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Acenta</label>
                    <select name="agency_id" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;background:#fff;">
                        <option value="">Tümü</option>
                        @foreach($agencies as $agency)
                            <option value="{{ $agency->id }}" {{ request('agency_id') == $agency->id ? 'selected' : '' }}>{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="width:160px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Destinasyon</label>
                    <select name="destination" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;background:#fff;">
                        <option value="">Tümü</option>
                        @foreach($destinations as $dest)
                            <option value="{{ $dest['city'] }}" {{ request('destination') === $dest['city'] ? 'selected' : '' }}>{{ $dest['city'] }} ({{ $dest['count'] }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="form-group" style="width:140px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Başlangıç</label>
                    <input type="date" name="date_start" value="{{ request('date_start') }}" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;">
                </div>

                <div class="form-group" style="width:140px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Bitiş</label>
                    <input type="date" name="date_end" value="{{ request('date_end') }}" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;">
                </div>

                <div class="form-group" style="width:90px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Min Gün</label>
                    <input type="number" name="min_days" value="{{ request('min_days') }}" min="1" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;">
                </div>

                <div class="form-group" style="width:150px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Sıralama</label>
                    <select name="sort" style="width:100%;padding:10px 14px;border:1px solid #cbd5e1;border-radius:8px;font-size:14px;outline:none;background:#fff;">
                        <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>En Yeni</option>
                        <option value="date" {{ request('sort') === 'date' ? 'selected' : '' }}>Tarih (Yakın)</option>
                        <option value="price_asc" {{ request('sort') === 'price_asc' ? 'selected' : '' }}>Fiyat (Artan)</option>
                        <option value="price_desc" {{ request('sort') === 'price_desc' ? 'selected' : '' }}>Fiyat (Azalan)</option>
                    </select>
                </div>

                <div style="display:flex;gap:8px;">
                    <button type="submit" class="btn btn-primary" style="height:41px;padding:0 20px;">Ara</button>
                    @if(request()->except('page'))
                        <a href="{{ route('admin.tours') }}" class="btn btn-outline" style="height:41px;padding:0 20px;display:flex;align-items:center;">Temizle</a>
                    @endif
                </div>
            </form>
        </div>

        <div id="tours-results" style="position:relative;min-height:200px;max-width:94%;margin-left:auto;margin-right:auto;">
            <div id="loading-overlay" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,0.7);backdrop-filter:blur(4px);z-index:10;align-items:center;justify-content:center;border-radius:16px;">
                <div style="width:40px;height:40px;border:4px solid #f1f5f9;border-top-color:var(--accent);border-radius:50%;animation:spin 1s linear infinite;"></div>
            </div>
            <style>@keyframes spin { 100% { transform:rotate(360deg); } }</style>

            @if($tours->isEmpty())
                <div class="card" style="text-align:center;padding:40px;color:#64748b;">
                    Arama kriterlerinize uygun tur bulunamadı.
                </div>
            @else
                <div class="card" style="padding:0;overflow:hidden;margin:0;">
                    <table class="table" style="margin:0;border:none;">
                        <thead><tr><th>Tur</th><th>Acenta</th><th>Destinasyon</th><th>Süre</th><th>Fiyat</th><th>Tarih</th><th>Durum</th></tr></thead>
                        <tbody>
                            @foreach($tours as $tour)
                            <tr style="border-bottom:1px solid var(--border-light);">
                                <td style="font-weight:600;">{{ $tour->title }}</td>
                                <td>{{ optional($tour->agency)->name }}</td>
                                <td>{{ $tour->destination }}</td>
                                <td>{{ $tour->duration_label }}</td>
                                <td>{{ $tour->formatted_price }}</td>
                                <td>{{ $tour->departure_date?->format('d.m.Y') ?? '—' }}</td>
                                <td><span class="badge {{ $tour->is_active ? 'badge-green' : '' }}" style="{{ !$tour->is_active ? 'background:#fef2f2;color:#991b1b;' : '' }}">{{ $tour->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div style="margin-top:16px;">{{ $tours->links() }}</div>
            @endif
        </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('search-form');
    let timeoutId;

    form.addEventListener('input', function(e) {
        if(e.target.name === 'q' || e.target.type === 'number' || e.target.type === 'date') return;
        submitForm();
    });

    form.querySelector('input[name="q"]').addEventListener('input', function() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(submitForm, 400); // 400ms debounce
    });
    
    form.addEventListener('change', function(e) {
        if(e.target.type === 'date' || e.target.type === 'number') submitForm();
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        submitForm();
    });

    document.addEventListener('click', function(e) {
        const link = e.target.closest('#tours-results .pagination a');
        if (link) {
            e.preventDefault();
            fetchResults(link.href);
        }
    });

    function submitForm() {
        const url = new URL(form.action);
        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        for (const [key, value] of formData.entries()) {
            if (value && value !== 'all') {
                params.append(key, value);
            }
        }
        
        url.search = params.toString();
        
        // Update URL via history API
        window.history.pushState({}, '', url);
        
        fetchResults(url);
    }

    function fetchResults(url) {
        document.getElementById('loading-overlay').style.display = 'flex';
        
        fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(response => response.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newResults = doc.getElementById('tours-results');
            document.getElementById('tours-results').innerHTML = newResults.innerHTML;
        })
        .catch(error => {
            console.error('Error fetching search results:', error);
            document.getElementById('loading-overlay').style.display = 'none';
        });
    }
});
</script>
@endsection
