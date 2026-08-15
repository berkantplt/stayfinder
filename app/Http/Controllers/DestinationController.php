<?php

namespace App\Http\Controllers;

use App\Models\Destination;
use App\Support\LandingSlug;
use Illuminate\Http\RedirectResponse;

class DestinationController extends Controller
{
    /**
     * Eski adres: /destinasyonlar/kapadokya
     *
     * Kanonik hâli artık düz landing URL'i (/kapadokya-turlari). Rakip
     * taramasında incelenen sitelerin tamamı bu kalıbı kullanıyor ve
     * "kapadokya turları" sorgusuyla birebir eşleşen tek biçim bu.
     *
     * Sayfayı LandingController render ediyor; burası yalnız index edilmiş
     * eski adresin değerini yeni adrese taşıyan 301.
     */
    public function show(Destination $destination): RedirectResponse
    {
        return redirect(LandingSlug::urlForDestination($destination), 301);
    }
}
