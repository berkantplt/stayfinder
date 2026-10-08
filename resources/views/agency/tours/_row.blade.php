{{-- Acenta tur listesi satırı. index.blade ve JS "Geri al" cevabı (TourController::restore)
     aynı parçayı basar; $tour category + reviews_count/favorited_by_count + visibility_issue ile gelir. --}}
<tr data-tour-id="{{ $tour->id }}">
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
        </div>
    </td>
</tr>
