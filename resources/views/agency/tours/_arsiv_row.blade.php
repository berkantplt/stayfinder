{{-- A10: Arşiv satırı. index.blade ve JS "Arşivle" cevabı (TourController::destroy / bulk) aynı parçayı basar;
     $arsiv reviews_count/favorited_by_count/dates_count ile gelir ("Kalıcı sil" onayı). --}}
<tr data-arsiv-id="{{ $arsiv->id }}">
    <td style="padding-left:0;font-weight:600;color:var(--p-metin);">{{ $arsiv->title }}</td>
    <td style="white-space:nowrap;">{{ $arsiv->deleted_at->format('d.m.Y H:i') }}</td>
    <td style="white-space:nowrap;color:var(--p-metin-3);">{{ $arsiv->deleted_at->copy()->addDays(30)->format('d.m.Y') }}</td>
    <td style="text-align:right;white-space:nowrap;">
        <form method="POST" action="{{ route('agency.tours.restore', $arsiv) }}" data-geri-al-form style="display:inline;">
            @csrf
            <button type="submit" class="p-btn p-btn-ikincil p-btn-kucuk">↩ Geri al</button>
        </form>
        {{-- 30 gün beklemeden kalıcı silme (forceDelete) — geri alınamaz --}}
        <form method="POST" action="{{ route('agency.tours.force-destroy', $arsiv) }}" data-kalici-sil-form onsubmit="return confirm({{ \Illuminate\Support\Js::from(\App\Models\Tour::permanentDeleteConfirmText($arsiv->title, (int) $arsiv->reviews_count, (int) $arsiv->favorited_by_count, (int) $arsiv->dates_count)) }})" style="display:inline;margin-left:6px;">
            @csrf @method('DELETE')
            <button type="submit" class="p-btn p-btn-tehlike p-btn-kucuk" title="Kalıcı sil — geri alınamaz">Kalıcı sil</button>
        </form>
    </td>
</tr>
