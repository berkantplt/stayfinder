@extends('layouts.app')
@section('title', 'Kategori Yetkilendirme Merkezi — Acenta Paneli')

@section('content')
<div class="container">
    <div>
        @include('partials.agency-sidebar')
        <div class="section" style="padding:24px 0 0 0;">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap;max-width:94%;margin:0 auto 24px;">
                <div>
                    <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.5px;color:#0f172a;">Kategori Yetkilendirme Merkezi</h1>
                    <div style="font-size:14px;color:#64748b;margin-top:4px;">Kategori bazlı aylık yetki satın alın, sadece açık kategorilerde tur yayınlayın.</div>
                </div>
                <a href="{{ route('agency.tours.create') }}" class="btn btn-outline">Tur Oluştur</a>
            </div>

            @if(session('success'))
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 24px;">{{ session('success') }}</div>
            @endif

            @if($errors->any())
                <div class="alert alert-error" style="max-width:94%;margin:0 auto 24px;">
                    @foreach($errors->all() as $error) {{ $error }}<br> @endforeach
                </div>
            @endif

            <div id="cart-flash" role="status" aria-live="polite" style="max-width:94%;margin:0 auto 24px;display:none;"></div>

            <div style="display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px;max-width:94%;margin:0 auto 24px;">
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Aktif Yetki</div>
                    <div style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ $licensedCategories->count() }}</div>
                    <div style="font-size:13px;color:#94a3b8;margin-top:6px;">Şu anda tur açabileceğiniz kategori sayısı</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Sepet Toplamı</div>
                    <div data-cart-total-stat style="font-size:28px;font-weight:800;color:#0f172a;margin-top:6px;">{{ number_format($cartTotal, 0, ',', '.') }} TL</div>
                    <div style="font-size:13px;color:#94a3b8;margin-top:6px;">Bu ay için satın alma özeti</div>
                </div>
                <div class="stat-card" style="padding:20px;">
                    <div style="font-size:13px;color:#64748b;font-weight:600;">Faturalama Modeli</div>
                    <div style="font-size:22px;font-weight:800;color:#0f172a;margin-top:6px;">Aylık</div>
                    <div style="font-size:13px;color:#94a3b8;margin-top:6px;">Kategori bazlı yenileme döngüsü</div>
                </div>
            </div>

            @if($agency->legacy_category_access)
                <div class="alert alert-success" style="max-width:94%;margin:0 auto 24px;">
                    Bu acenta geçiş kapsamına alındı. Kayıtlı turların etkilenmemesi için tüm aktif kategoriler satın alınmış gibi tanımlandı.
                </div>
            @endif

            <div style="display:grid;grid-template-columns:minmax(0,1.7fr) minmax(320px,0.9fr);gap:24px;max-width:94%;margin:0 auto;">
                <div style="display:flex;flex-direction:column;gap:24px;">
                    <div class="stat-card" style="padding:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:20px;">
                            <h2 style="font-size:18px;font-weight:700;color:#0f172a;">Satın Alınabilir Kategoriler</h2>
                            <div style="font-size:13px;color:#64748b;">Varsayılan aylık ücret kategori bazlıdır.</div>
                        </div>

                        @if($agency->legacy_category_access)
                            <div style="padding:20px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                                Geçiş erişimi nedeniyle yeni kategori satın alımı gerekmiyor. Tüm aktif kategoriler hesabınızda açık görünüyor.
                            </div>
                        @elseif($availableCategories->isEmpty())
                            <div style="padding:20px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                                Satın alınabilir açık kategori kalmadı. Aktif yetkileriniz tüm kullanılabilir kategorileri kapsıyor.
                            </div>
                        @else
                            <div data-all-in-cart-note style="padding:20px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;{{ $availableCategories->count() === $cartCategoryIds->count() ? '' : 'display:none;' }}">
                                Satın alınabilir tüm kategoriler sepetinizde. Ödemeye geçebilirsiniz.
                            </div>
                            <div data-purchasable-grid style="display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;">
                                @foreach($availableCategories as $category)
                                    <div data-category-card="{{ $category->id }}" style="border:1px solid #e2e8f0;border-radius:18px;padding:18px;background:#fff;{{ $cartCategoryIds->contains($category->id) ? 'display:none;' : '' }}">
                                        <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
                                            <div>
                                                <div style="font-size:16px;font-weight:700;color:#0f172a;">{{ $category->icon }} {{ $category->name }}</div>
                                                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">
                                                    {{ $category->parent?->name ? 'Üst kategori: ' . $category->parent->name : 'Ana kategori' }}
                                                </div>
                                                @if($category->description)
                                                    <div style="font-size:13px;color:#475569;margin-top:10px;line-height:1.6;">{{ \Illuminate\Support\Str::limit($category->description, 110) }}</div>
                                                @endif
                                            </div>
                                            <div style="font-size:18px;font-weight:800;color:#0f172a;white-space:nowrap;">{{ number_format((float) $category->monthly_price, 0, ',', '.') }} TL</div>
                                        </div>
                                        <div style="font-size:12px;color:#94a3b8;margin-top:6px;">Aylık yetki bedeli</div>
                                        <form method="POST" action="{{ route('agency.category-licenses.cart.add') }}" data-cart-form style="margin-top:16px;">
                                            @csrf
                                            <input type="hidden" name="category_id" value="{{ $category->id }}">
                                            <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;">Sepete Ekle</button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="stat-card" style="padding:24px;">
                        <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:20px;">Aktif Kategori Yetkileri</h2>

                        @if($licensedCategories->isEmpty())
                            <div style="padding:20px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                                Henüz aktif kategori yetkiniz yok.
                            </div>
                        @else
                            <div style="overflow-x:auto;">
                                <table class="table" style="width:100%;text-align:left;">
                                    <thead>
                                        <tr>
                                            <th>Kategori</th>
                                            <th>Aylık Ücret</th>
                                            <th>Başlangıç</th>
                                            <th>Durum</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($licensedCategories as $license)
                                            <tr>
                                                <td>
                                                    <div style="font-weight:700;color:#0f172a;">{{ $license->category->icon }} {{ $license->category->name }}</div>
                                                    @if($license->category->parent)
                                                        <div style="font-size:12px;color:#94a3b8;margin-top:2px;">{{ $license->category->parent->name }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ number_format((float) $license->monthly_price, 0, ',', '.') }} TL</td>
                                                <td>{{ $license->started_at?->format('d.m.Y') ?? '—' }}</td>
                                                <td>
                                                    @if($license->source === 'legacy')
                                                        <span class="badge badge-green">Geçiş Erişimi</span>
                                                    @else
                                                        <span class="badge badge-green">Aktif · {{ $license->expires_at?->format('d.m.Y') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                </div>

                <div style="display:flex;flex-direction:column;gap:24px;">
                    <div class="stat-card" style="padding:24px;">
                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px;">
                            <h2 style="font-size:18px;font-weight:700;color:#0f172a;">Sepet</h2>
                            <span class="badge" data-cart-count>{{ $cartItems->count() }} kategori</span>
                        </div>

                        <div data-cart-empty style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;{{ $cartItems->isEmpty() ? '' : 'display:none;' }}">
                            Sepetiniz boş. Satın alınabilir kategorilerden seçim yapın.
                        </div>

                        <div data-cart-body style="{{ $cartItems->isEmpty() ? 'display:none;' : '' }}">
                            <div data-cart-items style="display:flex;flex-direction:column;gap:12px;">
                                @foreach($cartItems as $category)
                                    <div data-cart-item="{{ $category->id }}" style="border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;background:#fff;">
                                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                                            <div>
                                                <div style="font-weight:700;color:#0f172a;">{{ $category->icon }} {{ $category->name }}</div>
                                                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">{{ number_format((float) $category->monthly_price, 0, ',', '.') }} TL / ay</div>
                                            </div>
                                            <form method="POST" action="{{ route('agency.category-licenses.cart.remove', $category) }}" data-cart-form>
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-outline btn-sm">Kaldır</button>
                                            </form>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <div style="margin-top:18px;padding-top:18px;border-top:1px solid #e2e8f0;">
                                <div style="display:flex;align-items:center;justify-content:space-between;font-size:14px;color:#475569;">
                                    <span>İlk dönem toplamı</span>
                                    <strong data-cart-total style="font-size:20px;color:#0f172a;">{{ number_format($cartTotal, 0, ',', '.') }} TL</strong>
                                </div>
                                <div style="font-size:12px;color:#94a3b8;margin-top:8px;">Satın alım sonrası kategoriler 1 aylık aktif edilir.</div>
                                <a href="{{ route('agency.category-licenses.checkout-form') }}" class="btn btn-primary" style="width:100%;justify-content:center;margin-top:16px;">
                                    Ödemeye Geç
                                </a>
                            </div>
                        </div>
                    </div>

                    <div class="stat-card" style="padding:24px;">
                        <h2 style="font-size:18px;font-weight:700;color:#0f172a;margin-bottom:18px;">Son Satın Alımlar</h2>

                        @if($recentOrders->isEmpty())
                            <div style="padding:18px;border:1px dashed #cbd5e1;border-radius:16px;background:#f8fafc;color:#475569;">
                                Henüz kategori satın alımı yapılmadı.
                            </div>
                        @else
                            <div style="display:flex;flex-direction:column;gap:12px;">
                                @foreach($recentOrders as $order)
                                    <div style="border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;background:#fff;">
                                        <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                                            <div>
                                                <div style="font-weight:700;color:#0f172a;">{{ $order->order_number }}</div>
                                                <div style="font-size:12px;color:#94a3b8;margin-top:4px;">{{ $order->purchased_at?->format('d.m.Y H:i') ?? '—' }}</div>
                                            </div>
                                            <div style="font-size:16px;font-weight:800;color:#0f172a;">{{ number_format((float) $order->subtotal, 0, ',', '.') }} TL</div>
                                        </div>
                                        <div style="font-size:12px;color:#64748b;margin-top:10px;">
                                            {{ $order->items->pluck('category_name')->implode(', ') }}
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const flashBox = document.getElementById('cart-flash');
    const cartCount = document.querySelector('[data-cart-count]');
    const cartEmpty = document.querySelector('[data-cart-empty]');
    const cartBody = document.querySelector('[data-cart-body]');
    const cartItems = document.querySelector('[data-cart-items]');
    const cartTotal = document.querySelector('[data-cart-total]');
    const cartTotalStat = document.querySelector('[data-cart-total-stat]');
    const allInCartNote = document.querySelector('[data-all-in-cart-note]');

    if (!cartItems || !cartBody) return;

    let flashTimer = null;

    function esc(value) {
        return String(value ?? '').replace(/[&<>"']/g, (ch) => ({
            '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
        })[ch]);
    }

    function flash(message, type) {
        if (!flashBox || !message) return;
        flashBox.className = type === 'error' ? 'alert alert-error' : 'alert alert-success';
        flashBox.textContent = message;
        flashBox.style.display = '';
        clearTimeout(flashTimer);
        flashTimer = setTimeout(() => { flashBox.style.display = 'none'; }, 4000);
    }

    function cartItemHtml(item) {
        const label = [item.icon, item.name].filter(Boolean).join(' ');

        return `
            <div data-cart-item="${esc(item.id)}" style="border:1px solid #e2e8f0;border-radius:14px;padding:14px 16px;background:#fff;">
                <div style="display:flex;align-items:center;justify-content:space-between;gap:12px;">
                    <div>
                        <div style="font-weight:700;color:#0f172a;">${esc(label)}</div>
                        <div style="font-size:12px;color:#94a3b8;margin-top:4px;">${esc(item.price_label)} TL / ay</div>
                    </div>
                    <form method="POST" action="${esc(item.remove_url)}" data-cart-form>
                        <input type="hidden" name="_token" value="${esc(csrfToken)}">
                        <input type="hidden" name="_method" value="DELETE">
                        <button type="submit" class="btn btn-outline btn-sm">Kaldır</button>
                    </form>
                </div>
            </div>`;
    }

    function renderCart(data) {
        const items = Array.isArray(data.items) ? data.items : [];
        const inCart = new Set(items.map((item) => String(item.id)));

        cartItems.innerHTML = items.map(cartItemHtml).join('');
        if (cartCount) cartCount.textContent = `${data.count} kategori`;
        if (cartTotal) cartTotal.textContent = `${data.total_label} TL`;
        if (cartTotalStat) cartTotalStat.textContent = `${data.total_label} TL`;

        const empty = items.length === 0;
        cartBody.style.display = empty ? 'none' : '';
        if (cartEmpty) cartEmpty.style.display = empty ? '' : 'none';

        let visibleCards = 0;
        document.querySelectorAll('[data-category-card]').forEach((card) => {
            const hidden = inCart.has(card.getAttribute('data-category-card'));
            card.style.display = hidden ? 'none' : '';
            if (!hidden) visibleCards += 1;
        });

        if (allInCartNote) allInCartNote.style.display = visibleCards === 0 ? '' : 'none';
    }

    async function submitCartForm(form) {
        if (form.dataset.busy === '1') return;

        const button = form.querySelector('button[type="submit"]');
        const originalLabel = button ? button.textContent : '';
        form.dataset.busy = '1';
        if (button) {
            button.disabled = true;
            button.textContent = 'İşleniyor…';
        }

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: new FormData(form),
            });

            const data = await response.json().catch(() => null);

            if (!response.ok || !data || data.ok !== true) {
                flash(data?.message || 'İşlem tamamlanamadı. Lütfen sayfayı yenileyip tekrar deneyin.', 'error');
                return;
            }

            renderCart(data);
            flash(data.message, 'success');
        } catch (error) {
            flash('Bağlantı hatası. Lütfen tekrar deneyin.', 'error');
        } finally {
            form.dataset.busy = '0';
            if (button) {
                button.disabled = false;
                button.textContent = originalLabel;
            }
        }
    }

    // Sepetteki satırlar JS ile yeniden basıldığı için olay dinleyici document seviyesinde.
    document.addEventListener('submit', function (event) {
        const form = event.target.closest('form[data-cart-form]');
        if (!form) return;
        event.preventDefault();
        submitCartForm(form);
    });
})();
</script>
@endpush
