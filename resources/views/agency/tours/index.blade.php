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
        {{-- Arşivle / Geri al arka planda (fetch) çalışır; sonuç mesajı bu kutuda --}}
        <div class="alert" data-tur-flash hidden role="status" aria-live="polite" style="max-width:94%;margin-left:auto;margin-right:auto;"></div>

        @unless($canCreateTours)
            <div class="alert alert-error" style="max-width:94%;margin-left:auto;margin-right:auto;">
                Henüz aktif kategori yetkiniz yok. Yeni tur paylaşmak için
                <a href="{{ route('agency.category-licenses.index') }}" style="font-weight:700;color:inherit;text-decoration:underline;">Kategori Yetkileri sayfası</a>
                üzerinden kategori ekleyin.
            </div>
        @endunless

        <div class="stat-card" style="padding:24px;max-width:94%;margin-left:auto;margin-right:auto;">
            <div style="overflow-x:auto;">
                <div class="table-wrap"><table class="table" style="width:100%;text-align:left;">
                    <thead>
                        <tr>
                            <th style="padding-left:0;">Tur</th>
                            <th>Kategori</th>
                            <th>Destinasyon</th>
                            <th>Fiyat</th>
                            <th>Tarih</th>
                            <th>Durum</th>
                            <th>Yayın</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody data-tur-listesi>
                        @foreach($tours as $tour)
                            @include('agency.tours._row', ['tour' => $tour])
                        @endforeach
                        {{-- Boş durum satırı hep basılır; JS son tur arşivlenince açar, geri alınca gizler --}}
                        <tr data-bos-satir{{ $tours->isNotEmpty() ? ' hidden' : '' }}><td colspan="8" style="text-align:center;color:#94a3b8;padding:40px;">Henüz tur eklemediniz.</td></tr>
                    </tbody>
                </table></div>
            </div>
        </div>

        <div style="margin-top:16px;">{{ $tours->links() }}</div>

        {{-- A10: Arşiv — silinen turlar 30 gün geri alınabilir. Boşken gizli durur;
             JS ilk arşivlemede sayfa yenilemeden açar, sayacı ve satırları günceller. --}}
        <details class="p-kart" data-arsiv-kutu{{ $archivedTours->isEmpty() ? ' hidden' : '' }} style="padding:16px 24px;max-width:94%;margin:24px auto 0;">
            <summary style="cursor:pointer;font-weight:700;color:var(--p-metin-2);">🗄️ Arşiv — <span data-arsiv-sayac>{{ $archivedTours->count() }}</span> silinmiş tur (30 gün içinde geri alınabilir)</summary>
            <div class="table-wrap" style="margin-top:12px;"><table class="table" style="width:100%;text-align:left;">
                <thead><tr><th style="padding-left:0;">Tur</th><th>Silinme</th><th>Kalıcı silinme</th><th></th></tr></thead>
                <tbody data-arsiv-listesi>
                @foreach($archivedTours as $arsiv)
                    @include('agency.tours._arsiv_row', ['arsiv' => $arsiv])
                @endforeach
                </tbody>
            </table></div>
        </details>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    'use strict';
    // Arşivle / Geri al: form gönderimi yakalanır, fetch ile JSON istenir, satırlar
    // iki tablo arasında taşınır. JS yoksa formlar klasik POST + yönlendirme ile çalışır.
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const turListesi = document.querySelector('[data-tur-listesi]');
    const bosSatir = document.querySelector('[data-bos-satir]');
    const arsivKutu = document.querySelector('[data-arsiv-kutu]');
    const arsivListesi = document.querySelector('[data-arsiv-listesi]');
    const arsivSayac = document.querySelector('[data-arsiv-sayac]');
    const flashBox = document.querySelector('[data-tur-flash]');
    let flashTimer;

    function flash(message, type) {
        if (!flashBox || !message) return;
        flashBox.className = type === 'error' ? 'alert alert-error' : 'alert alert-success';
        flashBox.textContent = message;
        flashBox.hidden = false;
        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => { flashBox.hidden = true; }, 4000);
    }

    function arsivSayisiniYaz(sayi) {
        const n = Number(sayi) || 0;
        if (arsivSayac) arsivSayac.textContent = String(n);
        if (arsivKutu) arsivKutu.hidden = n === 0;
    }

    function bosDurumuGuncelle() {
        if (!turListesi || !bosSatir) return;
        bosSatir.hidden = turListesi.querySelector('tr[data-tour-id]') !== null;
    }

    function arsivle(form, data) {
        form.closest('tr[data-tour-id]')?.remove();
        if (arsivListesi && data.arsiv_html) arsivListesi.insertAdjacentHTML('afterbegin', data.arsiv_html);
        arsivSayisiniYaz(data.arsiv_sayisi);
        bosDurumuGuncelle();
    }

    function geriAl(form, data) {
        form.closest('tr[data-arsiv-id]')?.remove();
        if (turListesi && data.satir_html) turListesi.insertAdjacentHTML('afterbegin', data.satir_html);
        arsivSayisiniYaz(data.arsiv_sayisi);
        bosDurumuGuncelle();
    }

    async function gonder(form, islem) {
        if (form.dataset.busy === '1') return;
        const button = form.querySelector('button[type="submit"]');
        form.dataset.busy = '1';
        if (button) button.disabled = true;

        try {
            const response = await fetch(form.action, {
                method: 'POST', // _method=DELETE gizli alanı FormData içinde gider
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body: new FormData(form),
            });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.ok !== true) {
                flash(data?.message || 'İşlem tamamlanamadı. Lütfen sayfayı yenileyip tekrar deneyin.', 'error');
                return;
            }

            (islem === 'arsiv' ? arsivle : geriAl)(form, data);
            flash(data.message, 'success');
        } catch (error) {
            flash('Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
        } finally {
            form.dataset.busy = '0';
            if (button) button.disabled = false;
        }
    }

    // Satırlar JS ile yeniden basıldığı için dinleyici document seviyesinde.
    // Arşivle formunun satır içi onsubmit onayı (confirm) önce çalışır; kullanıcı
    // vazgeçtiyse event.defaultPrevented gelir ve istek atılmaz.
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-arsiv-form], form[data-geri-al-form]');
        if (!form || event.defaultPrevented) return;
        event.preventDefault();
        gonder(form, form.hasAttribute('data-arsiv-form') ? 'arsiv' : 'geri-al');
    });
})();
</script>
@endpush
