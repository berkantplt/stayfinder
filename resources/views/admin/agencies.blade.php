@extends('layouts.app')
@section('title', 'Acentalar — Admin')

@section('content')
<div class="container">
    <div>
        @include('partials.admin-sidebar')
        <div class="section" style="padding:0;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h1 style="font-size:24px;font-weight:700;">Acentalar</h1>
                <a href="{{ route('admin.agencies.create') }}" class="btn btn-primary">+ Acenta Ekle</a>
            </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        {{-- Search & Filter Form --}}
        <div class="card" style="margin-bottom:24px;padding:20px;max-width:94%;margin-left:auto;margin-right:auto;">
            <form id="search-form" action="{{ route('admin.agencies') }}" method="GET" style="display:flex;gap:15px;align-items:flex-end;flex-wrap:wrap;">
                <div class="form-group" style="flex:1;min-width:250px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Arama (Ad, E-posta, Telefon)</label>
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Acenta ara..." style="background:#f8fafc;border:1px solid #e2e8f0;" autocomplete="off">
                </div>
                <div class="form-group" style="width:200px;margin-bottom:0;">
                    <label style="font-size:13px;color:#475569;">Durum</label>
                    <select name="status" style="background:#f8fafc;border:1px solid #e2e8f0;padding:11px 16px;border-radius:12px;width:100%;font-size:15px;outline:none;transition:border-color 0.2s;">
                        <option value="">Tümü</option>
                        <option value="active" {{ request('status') === 'active' ? 'selected' : '' }}>Sadece Aktifler</option>
                        <option value="passive" {{ request('status') === 'passive' ? 'selected' : '' }}>Sadece Pasifler</option>
                    </select>
                </div>
                <div style="display:flex;gap:10px;">
                    <button type="submit" class="btn btn-primary" style="padding:11px 24px;">Filtrele</button>
                    <a href="{{ route('admin.agencies') }}" id="clear-filters" class="btn btn-outline" style="padding:11px 16px;display:{{ request()->hasAny(['q', 'status']) ? 'block' : 'none' }};">Temizle</a>
                </div>
            </form>
        </div>

        <div id="agencies-results" style="position:relative;min-height:200px;max-width:94%;margin-left:auto;margin-right:auto;">
            <div id="loading-overlay" style="display:none;position:absolute;inset:0;background:rgba(255,255,255,0.7);backdrop-filter:blur(4px);z-index:10;align-items:center;justify-content:center;border-radius:16px;">
                <div style="width:40px;height:40px;border:4px solid #f1f5f9;border-top-color:var(--accent);border-radius:50%;animation:spin 1s linear infinite;"></div>
            </div>
            
            <style>@keyframes spin { 100% { transform:rotate(360deg); } }</style>

            @if($agencies->isEmpty())
                <div class="card" style="text-align:center;padding:40px;color:#64748b;">
                    Arama kriterlerinize uygun acenta bulunamadı.
                </div>
            @else
                <div class="card" style="padding:0;overflow:hidden;">
                    <table class="table">
                        <thead><tr><th>Acenta</th><th>E-posta</th><th>Telefon</th><th>Aktif Tur</th><th>Kategori Yetkisi</th><th>Onay</th><th>Durum</th><th>İşlem</th></tr></thead>
                        <tbody>
                            @foreach($agencies as $a)
                            <tr>
                                <td style="font-weight:600;">
                                    <a href="{{ route('admin.agencies.show', $a) }}" style="color:#0f172a;text-decoration:none;">{{ $a->name }}</a>
                                </td>
                                <td>{{ $a->email ?? '—' }}</td>
                                <td>{{ $a->phone ?? '—' }}</td>
                                <td>
                                    <div style="font-weight:700;color:#0f172a;">{{ $a->active_tours_count }}</div>
                                    <div style="font-size:12px;color:#94a3b8;margin-top:2px;">Toplam {{ $a->tours_count }}</div>
                                </td>
                                <td>
                                    @if($a->legacy_category_access ?? false)
                                        <span class="badge" style="background:#fff7ed;color:#9a3412;border:none;">Geçiş erişimi</span>
                                    @else
                                        <span class="badge {{ ($a->active_category_subscriptions_count ?? 0) > 0 ? 'badge-green' : '' }}" style="{{ ($a->active_category_subscriptions_count ?? 0) > 0 ? '' : 'background:#eef2ff;color:#3730a3;border:none;' }}">
                                            {{ $a->active_category_subscriptions_count ?? 0 }} aktif lisans
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    @if(($a->approval_status ?? 'approved') === 'pending')
                                        <span class="badge" style="background:#fff7ed;color:#9a3412;border:none;">Onay bekliyor</span>
                                    @elseif(($a->approval_status ?? 'approved') === 'rejected')
                                        <span class="badge" style="background:#fef2f2;color:#991b1b;border:none;">Reddedildi</span>
                                    @else
                                        <span class="badge badge-green">Onaylı</span>
                                    @endif
                                </td>
                                <td><span class="badge {{ $a->is_active ? 'badge-green' : '' }}" style="{{ !$a->is_active ? 'background:#fef2f2;color:#991b1b;' : '' }}">{{ $a->is_active ? 'Aktif' : 'Pasif' }}</span></td>
                                <td>
                                    <div style="display:flex;gap:8px;flex-wrap:wrap;">
                                        <a href="{{ route('admin.agencies.show', $a) }}" class="btn btn-outline btn-sm">Görüntüle</a>
                                        @if(($a->approval_status ?? 'approved') === 'pending')
                                            <a href="{{ route('admin.agency-applications') }}" class="btn btn-outline btn-sm">Başvuruya Git</a>
                                        @endif
                                        <form method="POST" action="{{ route('admin.agencies.toggle', $a) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-outline btn-sm">{{ $a->is_active ? 'Pasif Yap' : 'Aktif Yap' }}</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                
                <div style="margin-top:24px;">
                    {{ $agencies->links() }}
                </div>
            @endif
        </div>
        
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('search-form');
    const inputQ = form.querySelector('input[name="q"]');
    const selectStatus = form.querySelector('select[name="status"]');
    const resultsContainer = document.getElementById('agencies-results');
    const clearBtn = document.getElementById('clear-filters');
    let debounceTimer;

    function fetchResults(url) {
        // Show loading
        const overlay = document.getElementById('loading-overlay');
        if(overlay) overlay.style.display = 'flex';
        
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(res => res.text())
            .then(html => {
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const newResults = doc.getElementById('agencies-results');
                if (newResults) {
                    resultsContainer.innerHTML = newResults.innerHTML;
                    // Update URL without reload
                    window.history.pushState({}, '', url);
                    
                    // Show/hide clear button based on parameters
                    const urlObj = new URL(url);
                    if (urlObj.searchParams.get('q') || urlObj.searchParams.get('status')) {
                        clearBtn.style.display = 'block';
                    } else {
                        clearBtn.style.display = 'none';
                    }
                }
            })
            .catch(err => console.error('Fetch error:', err));
    }

    function triggerSearch() {
        const url = new URL(form.action);
        if (inputQ.value.trim()) url.searchParams.set('q', inputQ.value.trim());
        if (selectStatus.value) url.searchParams.set('status', selectStatus.value);
        fetchResults(url.toString());
    }

    // Input typing with debounce
    inputQ.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(triggerSearch, 400);
    });

    // Dropdown change
    selectStatus.addEventListener('change', triggerSearch);

    // Form submit prevention and trigger via JS
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        triggerSearch();
    });
    
    // Clear button functionality
    clearBtn.addEventListener('click', (e) => {
        e.preventDefault();
        inputQ.value = '';
        selectStatus.value = '';
        triggerSearch();
    });

    // Pagination links click interception
    resultsContainer.addEventListener('click', (e) => {
        const link = e.target.closest('nav a');
        if (link && link.href) {
            e.preventDefault();
            fetchResults(link.href);
        }
    });
});
</script>
@endpush
