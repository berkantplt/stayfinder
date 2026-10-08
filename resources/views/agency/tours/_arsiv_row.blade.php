{{-- A10: Arşiv satırı. index.blade ve JS "Arşivle" cevabı (TourController::destroy) aynı parçayı basar. --}}
<tr data-arsiv-id="{{ $arsiv->id }}">
    <td style="padding-left:0;font-weight:600;color:var(--p-metin);">{{ $arsiv->title }}</td>
    <td style="white-space:nowrap;">{{ $arsiv->deleted_at->format('d.m.Y H:i') }}</td>
    <td style="white-space:nowrap;color:var(--p-metin-3);">{{ $arsiv->deleted_at->copy()->addDays(30)->format('d.m.Y') }}</td>
    <td style="text-align:right;">
        <form method="POST" action="{{ route('agency.tours.restore', $arsiv) }}" data-geri-al-form style="display:inline;">
            @csrf
            <button type="submit" class="p-btn p-btn-ikincil p-btn-kucuk">↩ Geri al</button>
        </form>
    </td>
</tr>
