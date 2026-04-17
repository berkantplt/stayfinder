@extends('layouts.app')
@section('title', 'Turlar — StayFinder')

@section('content')
<style>
.tour-page-layout { display:flex; flex-direction:column; gap:24px; padding-top:40px; padding-bottom:60px; }
.tour-sidebar { width:100%; }
.tour-content { flex:1; }
@media(min-width: 900px) {
    .tour-page-layout { flex-direction:row; align-items:flex-start; }
    .tour-sidebar { width:300px; position:sticky; top:24px; flex-shrink:0; }
}
.tour-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:24px; }
.filter-group { margin-bottom:20px; }
.filter-label { font-size:13px; font-weight:700; color:#475569; display:block; margin-bottom:8px; }
.filter-input, .filter-select { width:100%; padding:11px 14px; border:1px solid #cbd5e1; border-radius:10px; font-size:14px; outline:none; font-family:inherit; background:#f8fafc; transition:all 0.2s; }
.filter-input:focus, .filter-select:focus { border-color:var(--accent); background:#fff; box-shadow:0 0 0 3px rgba(13,116,144,0.1); }
.filter-radio { display:flex; align-items:center; gap:8px; font-size:14px; cursor:pointer; padding:6px 0; color:#334155; }
.filter-radio:hover { color:var(--accent); }
.filter-radio input[type="radio"] { accent-color:var(--accent); width:16px; height:16px; margin:0; cursor:pointer; }
</style>

<div class="tour-page-layout" style="max-width: 1400px; margin: 0 auto; padding-left: 20px; padding-right: 20px;">
    {{-- Left Sidebar Filter --}}
    <aside class="tour-sidebar">
        <div style="background:var(--white);border:1px solid #e2e8f0;border-radius:16px;padding:24px;box-shadow:0 10px 25px -5px rgba(0,0,0,0.05);">
            <form id="filter-form" method="GET" action="{{ route('tours.index') }}">
                <h3 style="font-size:18px;font-weight:800;margin-bottom:24px;color:#0f172a;display:flex;align-items:center;gap:8px;">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"></polygon></svg>
                    Detaylı Filtreleme
                </h3>
                
                <div class="filter-group">
                    <label class="filter-label">Arama</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Tur veya yer..." class="filter-input">
                </div>

                <div class="filter-group">
                    <label class="filter-label">Kategori</label>
                    <div style="display:flex;flex-direction:column;gap:4px;">
                        <label class="filter-radio">
                            <input type="radio" name="category" value="" {{ !request('category') ? 'checked' : '' }}> Tümü
                        </label>
                        @foreach($categories as $cat)
                        <label class="filter-radio">
                            <input type="radio" name="category" value="{{ $cat->slug }}" {{ request('category') == $cat->slug ? 'checked' : '' }}> {{ $cat->icon }} {{ $cat->name }}
                        </label>
                        @endforeach
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Destinasyon</label>
                    <select name="destination" class="filter-select">
                        <option value="">Tümü</option>
                        @foreach($destinations as $dest)
                            <option value="{{ $dest }}" {{ request('destination') == $dest ? 'selected' : '' }}>{{ $dest }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Acenta</label>
                    <select name="agency_id" class="filter-select">
                        <option value="">Tümü</option>
                        @foreach($agencies as $agency)
                            <option value="{{ $agency->id }}" {{ request('agency_id') == $agency->id ? 'selected' : '' }}>{{ $agency->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Tarih Aralığı</label>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <input type="date" name="date_start" value="{{ request('date_start') }}" class="filter-input" placeholder="Başlangıç" title="Başlangıç Tarihi">
                        <input type="date" name="date_end" value="{{ request('date_end') }}" class="filter-input" placeholder="Bitiş" title="Bitiş Tarihi">
                    </div>
                </div>

                <div class="filter-group">
                    <label class="filter-label">Tur Süresi</label>
                    <div style="display:flex;gap:8px;">
                        <input type="number" name="min_days" value="{{ request('min_days') }}" placeholder="Min Gün" min="1" class="filter-input">
                        <input type="number" name="max_days" value="{{ request('max_days') }}" placeholder="Maks Gün" min="1" class="filter-input">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:12px;font-weight:700;py:12px;">Sonuçları Göster</button>
                @if(request()->except('page'))
                    <a href="{{ route('tours.index') }}" class="btn btn-outline" style="width:100%;justify-content:center;color:#64748b;font-weight:600;">Temizle</a>
                @endif
            </form>
        </div>
    </aside>

    {{-- Right Content --}}
    <main class="tour-content" id="tours-results" style="position:relative; min-height: 400px;">
        <div id="loading-overlay" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,0.7);backdrop-filter:blur(4px);z-index:10;align-items:center;justify-content:center;border-radius:16px;">
            <div style="width:40px;height:40px;border:4px solid #f1f5f9;border-top-color:var(--accent);border-radius:50%;animation:spin 1s linear infinite;"></div>
        </div>
        <style>@keyframes spin { 100% { transform:rotate(360deg); } }</style>

        <h1 style="font-size:28px;font-weight:800;color:#0f172a;letter-spacing:-0.5px;margin-bottom:24px;">Turları Keşfedin</h1>
        
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;flex-wrap:wrap;gap:12px;background:var(--white);padding:12px 20px;border-radius:12px;border:1px solid #e2e8f0;">
            <div style="font-size:14px;color:#475569;font-weight:500;"><strong>{{ $tours->total() }}</strong> tur bulundu</div>
            <div style="display:flex;align-items:center;gap:12px;">
                <label style="font-size:13px;color:#475569;font-weight:600;">Sıralama:</label>
                <select name="sort" form="filter-form" class="filter-select" style="padding:8px 16px;width:auto;background:transparent;">
                    <option value="price_asc" {{ request('sort') == 'price_asc' ? 'selected' : '' }}>Fiyat (Artan)</option>
                    <option value="price_desc" {{ request('sort') == 'price_desc' ? 'selected' : '' }}>Fiyat (Azalan)</option>
                    <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>En Popüler</option>
                    <option value="reviews" {{ request('sort') == 'reviews' ? 'selected' : '' }}>En Çok Yorumlanan</option>
                    <option value="date" {{ request('sort') == 'date' ? 'selected' : '' }}>Tarih (Yakın)</option>
                    <option value="newest" {{ request('sort') == 'newest' ? 'selected' : '' }}>En Yeni Eklenenler</option>
                </select>
            </div>
        </div>

        <div class="tour-grid">
            @forelse($tours as $tour)
            <a href="{{ route('tours.show', $tour) }}" class="card" style="transition:all 0.3s;display:flex;flex-direction:column;">
                @if($tour->image)
                    <img src="{{ $tour->image }}" alt="{{ $tour->title }}" class="card-img" style="height:200px;object-fit:cover;">
                @else
                    <div class="card-img" style="height:200px;background:linear-gradient(135deg,#e0f2fe,#f0fdf4);display:flex;align-items:center;justify-content:center;font-size:48px;">🏖️</div>
                @endif
                <div class="card-body" style="flex:1;display:flex;flex-direction:column;">
                    @if($tour->category)
                        <div style="margin-bottom:8px;"><span class="badge badge-accent" style="font-size:11px;">{{ $tour->category->icon }} {{ $tour->category->name }}</span></div>
                    @endif
                    <div class="card-title" style="font-size:16px;margin-bottom:8px;line-height:1.4;">{{ $tour->title }}</div>
                    <div class="card-meta" style="margin-bottom:12px;">{{ $tour->agency->name }}</div>
                    
                    <div style="margin-top:auto;">
                        <div class="card-meta" style="display:flex;align-items:center;gap:4px;margin-bottom:4px;">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                            {{ $tour->destination }} · {{ $tour->duration_days }} gün
                        </div>
                        @if($tour->dates->count())
                            <div class="card-meta" style="display:flex;align-items:center;gap:4px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                {{ $tour->dates->first()->departure_date->format('d M Y') }}
                            </div>
                        @elseif($tour->departure_date)
                            <div class="card-meta" style="display:flex;align-items:center;gap:4px;">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>
                                {{ $tour->departure_date->format('d M Y') }}
                            </div>
                        @endif
                        
                        <div style="margin-top:16px;padding-top:16px;border-top:1px solid #f1f5f9;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
                            <div>
                                @php $campaign = $tour->activeCampaign; @endphp
                                @if($campaign)
                                    <div style="text-decoration:line-through;color:#94a3b8;font-size:12px;">{{ $tour->formatted_price }}</div>
                                    <div class="price-tag" style="color:#059669;font-size:20px;">{{ $campaign->formatted_discount_price }}</div>
                                @else
                                    <div class="price-tag" style="font-size:20px;">{{ $tour->formatted_price }}</div>
                                @endif
                            </div>
                            <div style="display:flex;gap:8px;">
                                <button type="button" class="btn btn-outline btn-sm compare-toggle" data-tour-id="{{ $tour->id }}" onclick="event.preventDefault(); window.toggleCompare({{ $tour->id }})" style="border-radius:8px;font-size:12px;padding:6px 10px;">+ Karşılaştır</button>
                                <span class="btn btn-primary btn-sm" style="border-radius:8px;">İncele</span>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
            @empty
            <div style="grid-column:1/-1;text-align:center;padding:80px 20px;background:var(--white);border-radius:16px;border:1px dashed #cbd5e1;">
                <div style="font-size:64px;margin-bottom:16px;opacity:0.5;">🧳</div>
                <h3 style="font-weight:800;font-size:20px;color:#0f172a;margin-bottom:8px;">Tur bulunamadı</h3>
                <p style="color:#64748b;font-size:15px;max-width:300px;margin:0 auto 20px;">Seçtiğiniz filtrelere uygun bir tur bulamadık. Lütfen farklı kriterler deneyin.</p>
                <a href="{{ route('tours.index') }}" class="btn btn-outline">Filtreleri Temizle</a>
            </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($tours->hasPages())
        <div class="pagination-wrapper" style="margin-top:32px;">
            @foreach($tours->links()->elements[0] as $page => $url)
                <a href="{{ $url }}" class="page-link {{ $tours->currentPage() == $page ? 'active' : '' }}">{{ $page }}</a>
            @endforeach
        </div>
        @endif
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('filter-form');
    let timeoutId;

    // Remove the original 'Sonuçları Göster' button since we auto-submit
    const submitBtn = form.querySelector('button[type="submit"]');
    if (submitBtn) {
        submitBtn.style.display = 'none';
    }

    form.addEventListener('input', function(e) {
        if(e.target.name === 'q' || e.target.type === 'number' || e.target.type === 'date') return;
        submitForm();
    });

    form.querySelector('input[name="q"]').addEventListener('input', function() {
        clearTimeout(timeoutId);
        timeoutId = setTimeout(submitForm, 400); // 400ms debounce
    });
    
    form.addEventListener('change', function(e) {
        if(e.target.type === 'date' || e.target.type === 'number' || e.target.type === 'radio') {
            submitForm();
        }
    });

    // Use event delegation for the sort select, because it gets replaced 
    // when the #tours-results container is updated via AJAX.
    document.addEventListener('change', function(e) {
        if (e.target && e.target.name === 'sort' && e.target.matches('.filter-select')) {
            // Because this select might be a newly inserted DOM element,
            // FormData might not pick it up correctly immediately. 
            // We pass the new sort value to submitForm.
            submitForm({ sort: e.target.value });
        }
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

    function submitForm(extraParams = {}) {
        const url = new URL(form.action);
        const formData = new FormData(form);
        const params = new URLSearchParams();
        
        for (const [key, value] of formData.entries()) {
            if (value && value !== 'all') {
                params.append(key, value);
            }
        }
        
        // Append extra params from delegation (like sort)
        for (const [key, value] of Object.entries(extraParams)) {
            if (value) params.set(key, value);
        }
        
        url.search = params.toString();
        
        // Update URL via history API
        window.history.pushState({}, '', url);
        
        fetchResults(url);
    }

    function fetchResults(url) {
        const overlay = document.getElementById('loading-overlay');
        if(overlay) overlay.style.display = 'flex';
        
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
            
            // Re-bind compare buttons state
            if(typeof window.updateCompareUI === 'function') window.updateCompareUI();
        })
        .catch(error => {
            console.error('Error fetching search results:', error);
            if(overlay) overlay.style.display = 'none';
        });
    }
});
</script>
@endsection
