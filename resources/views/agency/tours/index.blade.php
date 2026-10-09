@extends('layouts.app')
@section('title', 'Turlarım — Acenta Paneli')

@push('head')
<style>
    /* Seç modu: onay kutusu sütunu yalnız tablo kabı data-sec-modu taşırken görünür */
    .sec-sutun { display:none; width:36px; }
    body.panel-layout-active .table .sec-sutun { padding-left:8px; padding-right:0; }
    [data-sec-modu] .sec-sutun { display:table-cell; }
    [data-sec-modu] tr[data-tour-id] { cursor:pointer; }
    body.panel-layout-active .table tr.secili td { background:#ecfdf5; }
    .sec-kutu { width:18px; height:18px; accent-color:#10b981; cursor:pointer; vertical-align:middle; }
    /* Toplu çubuk: sabit altta, seçim varken görünür */
    .toplu-cubuk { position:fixed; left:50%; bottom:20px; transform:translateX(-50%); z-index:60; display:flex; flex-wrap:wrap; gap:10px; align-items:center; padding:12px 18px; background:#0f172a; color:#fff; border-radius:14px; box-shadow:0 12px 32px -8px rgba(15,23,42,.55); max-width:calc(100% - 32px); font-size:14px; }
    .toplu-cubuk[hidden] { display:none; }
    .toplu-cubuk .p-btn[disabled] { opacity:.6; cursor:wait; }
    @media (max-width:640px) { .toplu-cubuk { left:16px; right:16px; bottom:16px; transform:none; justify-content:center; } }
</style>
@endpush

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:30px;max-width:94%;margin-left:auto;margin-right:auto;">
                <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Turlarım</h1>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;justify-content:flex-end;">
                    {{-- Seç modu: satırlara onay kutusu açar, seçim olunca alttaki toplu çubuk görünür.
                         JS yokken işe yaramaz; betik açılışta hidden'ı kaldırır. --}}
                    <button type="button" class="p-btn p-btn-ikincil" data-sec-ac aria-pressed="false" hidden{{ $tours->isEmpty() ? ' disabled' : '' }}>☑ Seç</button>
                    <a href="{{ route('agency.tours.create') }}" class="btn btn-primary" style="padding:10px 20px;font-size:15px;box-shadow:0 8px 20px -6px rgba(16,185,129,0.4);{{ !$canCreateTours ? 'opacity:.65;' : '' }}">
                        <svg width="20" height="20" viewBox="2 2 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg> 
                        Yeni Tur Ekle
                    </a>
                </div>
            </div>

        @if(session('success'))
            <div class="alert alert-success" style="max-width:94%;margin-left:auto;margin-right:auto;">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-error" style="max-width:94%;margin-left:auto;margin-right:auto;">{{ session('error') }}</div>
        @endif
        {{-- Arşivle / Geri al / Sil / toplu işlem arka planda (fetch) çalışır; sonuç mesajı bu kutuda --}}
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
                {{-- data-tur-tablo: seç modunda data-sec-modu alır, kutu sütunu CSS ile açılır --}}
                <div class="table-wrap" data-tur-tablo><table class="table" style="width:100%;text-align:left;">
                    <thead>
                        <tr>
                            <th class="sec-sutun"><input type="checkbox" class="sec-kutu" data-sec-tumu aria-label="Bu sayfadaki tüm turları seç"></th>
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
                        {{-- Boş durum satırı hep basılır; JS son tur arşivlenince/silinince açar, geri alınca gizler --}}
                        <tr data-bos-satir{{ $tours->isNotEmpty() ? ' hidden' : '' }}><td colspan="9" style="text-align:center;color:#94a3b8;padding:40px;">Henüz tur eklemediniz.</td></tr>
                    </tbody>
                </table></div>
            </div>
        </div>

        <div style="margin-top:16px;">{{ $tours->links() }}</div>

        {{-- Seç modu toplu çubuğu (JS): sabit altta, seçim varken görünür. "Tümünü seç" bu sayfadakileri seçer. --}}
        <div class="toplu-cubuk" data-toplu-cubuk data-toplu-url="{{ route('agency.tours.bulk') }}" hidden role="region" aria-label="Toplu işlem">
            <span><strong data-toplu-sayi>0</strong> tur seçildi</span>
            <button type="button" class="p-btn p-btn-kucuk" style="background:#fff;color:#0f172a;" data-toplu-islem="arsivle" title="Seçilenleri arşive taşı — 30 gün içinde geri alınabilir">Arşivle</button>
            <button type="button" class="p-btn p-btn-kucuk" style="background:#ef4444;color:#fff;" data-toplu-islem="sil" title="Seçilenleri kalıcı sil — geri alınamaz">Kalıcı Sil</button>
            <button type="button" class="p-btn p-btn-kucuk" style="background:transparent;color:#cbd5e1;border-color:#475569;" data-sec-kapat>Vazgeç</button>
        </div>

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
    // Arşivle / Geri al / Sil: form gönderimi yakalanır, fetch ile JSON istenir, satırlar
    // iki tablo arasında taşınır ya da kaldırılır. JS yoksa formlar klasik POST + yönlendirme ile çalışır.
    // Seç modu: onay kutuları + alttaki toplu çubuk (arşivle | kalıcı sil), tek istek.
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const turListesi = document.querySelector('[data-tur-listesi]');
    const bosSatir = document.querySelector('[data-bos-satir]');
    const arsivKutu = document.querySelector('[data-arsiv-kutu]');
    const arsivListesi = document.querySelector('[data-arsiv-listesi]');
    const arsivSayac = document.querySelector('[data-arsiv-sayac]');
    const flashBox = document.querySelector('[data-tur-flash]');
    const tabloKap = document.querySelector('[data-tur-tablo]');
    const secAc = document.querySelector('[data-sec-ac]');
    const secTumu = document.querySelector('[data-sec-tumu]');
    const topluCubuk = document.querySelector('[data-toplu-cubuk]');
    const topluSayi = document.querySelector('[data-toplu-sayi]');
    let flashTimer;
    let topluBusy = false;

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
        secimiGuncelle();
    }

    function geriAl(form, data) {
        form.closest('tr[data-arsiv-id]')?.remove();
        if (turListesi && data.satir_html) turListesi.insertAdjacentHTML('afterbegin', data.satir_html);
        arsivSayisiniYaz(data.arsiv_sayisi);
        bosDurumuGuncelle();
        secimiGuncelle();
    }

    // Kalıcı sil: satır listeden ya da arşivden kalkar, hiçbir yere taşınmaz
    function kaliciSil(form, data) {
        form.closest('tr[data-tour-id], tr[data-arsiv-id]')?.remove();
        arsivSayisiniYaz(data.arsiv_sayisi);
        bosDurumuGuncelle();
        secimiGuncelle();
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

            ({ arsiv: arsivle, 'geri-al': geriAl, kalici: kaliciSil })[islem](form, data);
            flash(data.message, 'success');
        } catch (error) {
            flash('Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
        } finally {
            form.dataset.busy = '0';
            if (button) button.disabled = false;
        }
    }

    // Satırlar JS ile yeniden basıldığı için dinleyici document seviyesinde.
    // Formların satır içi onsubmit onayı (confirm) önce çalışır; kullanıcı
    // vazgeçtiyse event.defaultPrevented gelir ve istek atılmaz.
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-arsiv-form], form[data-geri-al-form], form[data-kalici-sil-form]');
        if (!form || event.defaultPrevented) return;
        event.preventDefault();
        const islem = form.hasAttribute('data-arsiv-form') ? 'arsiv'
            : form.hasAttribute('data-geri-al-form') ? 'geri-al' : 'kalici';
        gonder(form, islem);
    });

    // ---- Seç modu + toplu işlem ----
    function kutular() { return turListesi ? Array.from(turListesi.querySelectorAll('[data-sec-kutu]')) : []; }
    function secModundaMi() { return !!tabloKap && tabloKap.hasAttribute('data-sec-modu'); }

    function secimiGuncelle() {
        const tum = kutular();
        const secili = tum.filter((k) => k.checked);
        tum.forEach((k) => k.closest('tr')?.classList.toggle('secili', k.checked));
        if (topluSayi) topluSayi.textContent = String(secili.length);
        if (topluCubuk) topluCubuk.hidden = !secModundaMi() || secili.length === 0;
        if (secTumu) {
            secTumu.checked = tum.length > 0 && secili.length === tum.length;
            secTumu.indeterminate = secili.length > 0 && secili.length < tum.length;
        }
        if (secAc) secAc.disabled = tum.length === 0;
        if (tum.length === 0 && secModundaMi()) secModu(false); // liste boşaldı: moddan çık
    }

    function secModu(acik) {
        if (!tabloKap) return;
        if (acik) tabloKap.setAttribute('data-sec-modu', ''); else tabloKap.removeAttribute('data-sec-modu');
        if (!acik) kutular().forEach((k) => { k.checked = false; });
        if (secAc) {
            secAc.textContent = acik ? 'Seçimi Bitir' : '☑ Seç';
            secAc.setAttribute('aria-pressed', acik ? 'true' : 'false');
        }
        secimiGuncelle();
    }

    if (secAc) {
        secAc.hidden = false; // JS var: düğme görünür
        secAc.addEventListener('click', () => secModu(!secModundaMi()));
    }
    document.querySelector('[data-sec-kapat]')?.addEventListener('click', () => secModu(false));
    secTumu?.addEventListener('change', () => {
        kutular().forEach((k) => { k.checked = secTumu.checked; });
        secimiGuncelle();
    });
    turListesi?.addEventListener('change', (event) => {
        if (event.target.matches('[data-sec-kutu]')) secimiGuncelle();
    });
    // Seç modunda satırın boş alanına tıklamak da kutuyu çevirir (bağlantı / buton / form hariç)
    turListesi?.addEventListener('click', (event) => {
        if (!secModundaMi() || event.target.closest('a, button, form, input, label')) return;
        const kutu = event.target.closest('tr[data-tour-id]')?.querySelector('[data-sec-kutu]');
        if (!kutu) return;
        kutu.checked = !kutu.checked;
        secimiGuncelle();
    });

    function topluOnayMetni(islem, satirlar) {
        const n = satirlar.length;
        if (islem === 'arsivle') {
            return n + ' tur arşive taşınacak ve sitede görünmeyecek. 30 gün içinde "Arşiv" bölümünden geri alabilirsiniz.\n\nArşive taşınsın mı?';
        }
        const toplam = (ad) => satirlar.reduce((t, tr) => t + (Number(tr.dataset[ad]) || 0), 0);
        const etki = [];
        const tarih = toplam('tarih'), yorum = toplam('yorum'), favori = toplam('favori');
        if (tarih) etki.push(tarih + ' tarih');
        if (yorum) etki.push(yorum + ' yorum');
        if (favori) etki.push(favori + ' favori');
        let metin = n + ' tur KALICI olarak silinecek. Arşive gitmez, geri alınamaz.';
        if (etki.length) metin += '\n\nSilinecekler: ' + etki.join(', ') + '.';
        return metin + '\n\nKalıcı olarak silinsin mi?';
    }

    async function topluGonder(islem) {
        if (topluBusy || !topluCubuk) return;
        const secili = kutular().filter((k) => k.checked);
        if (secili.length === 0) return;
        const satirlar = secili.map((k) => k.closest('tr[data-tour-id]')).filter(Boolean);
        if (!confirm(topluOnayMetni(islem, satirlar))) return;

        topluBusy = true;
        topluCubuk.querySelectorAll('button').forEach((b) => { b.disabled = true; });

        try {
            const response = await fetch(topluCubuk.dataset.topluUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({ islem: islem, ids: secili.map((k) => Number(k.value)) }),
            });
            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.ok !== true) {
                flash(data?.message || 'İşlem tamamlanamadı. Lütfen sayfayı yenileyip tekrar deneyin.', 'error');
                return;
            }

            (data.ids || []).forEach((id) => turListesi?.querySelector('tr[data-tour-id="' + id + '"]')?.remove());
            if (islem === 'arsivle' && arsivListesi && Array.isArray(data.arsiv_html)) {
                arsivListesi.insertAdjacentHTML('afterbegin', data.arsiv_html.join(''));
            }
            arsivSayisiniYaz(data.arsiv_sayisi);
            bosDurumuGuncelle();
            secimiGuncelle();
            flash(data.message, 'success');
        } catch (error) {
            flash('Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
        } finally {
            topluBusy = false;
            topluCubuk.querySelectorAll('button').forEach((b) => { b.disabled = false; });
        }
    }
    topluCubuk?.querySelectorAll('[data-toplu-islem]').forEach((b) => {
        b.addEventListener('click', () => topluGonder(b.dataset.topluIslem));
    });
})();
</script>
@endpush
