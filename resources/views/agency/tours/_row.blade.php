{{-- Acenta tur listesi satırı. index.blade ve JS "Geri al" cevabı (TourController::restore)
     aynı parçayı basar; $tour category + reviews_count/favorited_by_count/dates_count + visibility_issue ile gelir.
     data-yorum/favori/tarih: seç modunda toplu "Kalıcı Sil" onayı etkiyi bunlardan toplar.
     Sütunlar index thead ile aynı sırada: seç | tur (başlık + kategori · destinasyon) | fiyat | tarih | durum | işlem.
     9 sütun 1440 px'te bile yatay kaydırıyordu; kategori/destinasyon başlığın altına, yayın durumu Durum hücresine alındı. --}}
@php
    $turMeta = implode(' · ', array_filter([$tour->category?->name, $tour->destination], fn ($v) => $v !== null && $v !== ''));
@endphp
<tr data-tour-id="{{ $tour->id }}" data-yorum="{{ (int) $tour->reviews_count }}" data-favori="{{ (int) $tour->favorited_by_count }}" data-tarih="{{ (int) $tour->dates_count }}">
    {{-- Seç modu kutusu: yalnız tablo kabı data-sec-modu taşırken görünür (index CSS) --}}
    <td class="sec-sutun"><input type="checkbox" class="sec-kutu" data-sec-kutu value="{{ $tour->id }}" aria-label="{{ $tour->title }} turunu seç"></td>
    <td class="tur-hucre">
        <a href="{{ route('agency.tours.show', $tour) }}" class="tur-baslik">{{ $tour->title }}</a>
        {{-- Tek satır; sığmayan kısım … ile kesilir, tamamı title'da --}}
        <div class="tur-meta" title="{{ $turMeta }}">{{ $turMeta !== '' ? $turMeta : '—' }}</div>
    </td>
    <td class="tur-sayi">{{ $tour->formatted_price }}</td>
    <td class="tur-sayi">{{ $tour->departure_date?->format('d.m.Y') ?? '—' }}</td>
    <td>
        {{-- C9: "Aktif" ayarı ile sitede görünürlük farklı şeyler; iki etiket yan yana, sebep altta --}}
        <div class="tur-durum">
            <span class="p-etiket {{ $tour->is_active ? 'p-etiket-bilgi' : 'p-etiket-notr' }}">{{ $tour->is_active ? 'Aktif' : 'Pasif' }}</span>
            @if($tour->visibility_issue === null)<span class="p-etiket p-etiket-basari">Yayında</span>@else<span class="p-etiket p-etiket-uyari">Yayında değil</span>@endif
        </div>
        @if($tour->visibility_issue !== null)<div class="tur-durum-not">{{ $tour->visibility_issue }}</div>@endif
    </td>
    <td class="tur-islem">
        <div class="tur-islem-grup">
            <a href="{{ route('agency.tours.show', $tour) }}" class="btn btn-outline btn-sm" title="Görüntüle" aria-label="Görüntüle">👁️</a>
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
                <button type="submit" class="btn btn-outline btn-sm tur-sil" title="Kalıcı sil — geri alınamaz">Sil</button>
            </form>
        </div>
    </td>
</tr>
