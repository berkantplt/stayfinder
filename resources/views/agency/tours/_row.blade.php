{{-- Acenta tur listesi satırı. index.blade ve JS "Geri al" cevabı (TourController::restore)
     aynı parçayı basar; $tour category + reviews_count/favorited_by_count/dates_count + visibility_issue ile gelir.
     data-yorum/favori/tarih: seç modunda toplu "Kalıcı Sil" onayı etkiyi bunlardan toplar. --}}
<tr data-tour-id="{{ $tour->id }}" data-yorum="{{ (int) $tour->reviews_count }}" data-favori="{{ (int) $tour->favorited_by_count }}" data-tarih="{{ (int) $tour->dates_count }}">
    {{-- Seç modu kutusu: yalnız tablo kabı data-sec-modu taşırken görünür (index CSS) --}}
    <td class="sec-sutun"><input type="checkbox" class="sec-kutu" data-sec-kutu value="{{ $tour->id }}" aria-label="{{ $tour->title }} turunu seç"></td>
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
    <td>
        {{-- C9: "Aktif" ayarı ile sitede görünürlük farklı şeyler; sebep burada --}}
        @if($tour->visibility_issue === null)
            <span class="p-etiket p-etiket-basari">Yayında</span>
        @else
            <span class="p-etiket p-etiket-uyari">Yayında değil</span>
            <div style="font-size:11px;color:var(--p-uyari-metin);margin-top:4px;white-space:nowrap;">{{ $tour->visibility_issue }}</div>
        @endif
    </td>
    <td style="white-space:nowrap;">
        <div style="display:inline-flex;gap:8px;align-items:center;">
            <a href="{{ route('agency.tours.show', $tour) }}" class="btn btn-outline btn-sm" title="Görüntüle">👁️</a>
            <a href="{{ route('agency.tours.edit', $tour) }}" class="btn btn-outline btn-sm">Düzenle</a>
            {{-- C18: silme = arşivleme (A10); onay metni etkiyi sayılarla söyler.
                 data-arsiv-form: index'teki betik gönderimi yakalar, sayfa yenilenmez. --}}
            <form method="POST" action="{{ route('agency.tours.destroy', $tour) }}" data-arsiv-form onsubmit="return confirm({{ \Illuminate\Support\Js::from(\App\Models\Tour::archiveConfirmText($tour->title, $tour->reviews_count, $tour->favorited_by_count)) }})" style="margin:0;">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-danger btn-sm" title="Arşive taşı — 30 gün içinde geri alınabilir">Arşivle</button>
            </form>
            {{-- Kalıcı silme (forceDelete): arşive gitmez, geri alınamaz; onay metni etkiyi sayılarla söyler.
                 Aynı uç arşiv satırından da çağrılır (rota withTrashed). --}}
            <form method="POST" action="{{ route('agency.tours.force-destroy', $tour) }}" data-kalici-sil-form onsubmit="return confirm({{ \Illuminate\Support\Js::from(\App\Models\Tour::permanentDeleteConfirmText($tour->title, (int) $tour->reviews_count, (int) $tour->favorited_by_count, (int) $tour->dates_count)) }})" style="margin:0;">
                @csrf @method('DELETE')
                <button type="submit" class="btn btn-outline btn-sm" style="color:#b91c1c;border-color:#fecaca;" title="Kalıcı sil — geri alınamaz">Sil</button>
            </form>
        </div>
    </td>
</tr>
