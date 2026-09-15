{{--
    A11 — Doğrulama ve withErrors() mesajları için TEK blok.

    Eskiden 24 sayfa kendi bloğunu yazıyor, 12 sayfa hiç yazmıyordu; o 12'de
    withErrors() mesajları (ör. "onay bekleyen acenta aktifleştirilemez")
    sessizce kayboluyordu. Şimdi layout, sayfa bu partial'ı basmadıysa aynı
    bloğu kendisi basar — hiçbir sayfa unutulamaz, hiçbir sayfa iki kez basmaz.

    Kullanım: @include('partials.form-errors')                     (varsayılan)
              @include('partials.form-errors', ['style' => '...']) (ek satır içi stil)
--}}
@if($errors->any())
    @php app()->instance('view.errors_rendered', true); @endphp
    <div class="alert alert-error" role="alert"{!! isset($style) && $style !== '' ? ' style="'.e($style).'"' : '' !!}>
        @if($errors->count() === 1)
            {{ $errors->first() }}
        @else
            <ul style="margin:0;padding-left:18px;">
                @foreach($errors->all() as $hata)
                    <li>{{ $hata }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
