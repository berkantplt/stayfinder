<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Support\DepartureCityExtractor;
use App\Support\TurkishCities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kalkış şehri toplu düzenleme (Faz 0.2).
 *
 * Otomatik çıkarım (seo:backfill-departure-city) yalnız başlığında/biniş
 * noktalarında açık kalkış ifadesi olan turları doldurabiliyor — kalanı elle
 * girilmeli. Tur tur düzenleme ekranını dolaşmak 90+ turda pratik değil;
 * bu ekran hepsini tek listede, tek kaydetmede toplar.
 *
 * Alan neden önemli: "{şehir} kalkışlı {destinasyon} turları" sayfa ailesinin
 * tek girdisi. Rakip araştırmasında büyük pazaryerlerinin (tatilsepeti,
 * jollytur) 404 verdiği, orta ölçekli acentelerin 1. sırada çıktığı alan.
 */
class DepartureCityController extends Controller
{
    public function index(Request $request): View
    {
        $durum = $request->input('durum', 'eksik');

        $query = Tour::with('agency')->orderByDesc('id');

        if ($durum === 'eksik') {
            $query->where(fn ($q) => $q->whereNull('departure_city')->orWhere('departure_city', ''));
        } elseif ($durum === 'dolu') {
            $query->whereNotNull('departure_city')->where('departure_city', '!=', '');
        }

        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->q.'%');
        }

        $tours = $query->paginate(50)->withQueryString();

        // Her tur için otomatik çıkarımın ne bulduğunu göster: kullanıcı
        // onaylayıp tek tıkla kabul edebilsin.
        $oneriler = [];
        foreach ($tours as $tour) {
            if (empty($tour->departure_city) && ($sonuc = DepartureCityExtractor::extract($tour))) {
                $oneriler[$tour->id] = $sonuc;
            }
        }

        return view('admin.departure-cities', [
            'tours' => $tours,
            'oneriler' => $oneriler,
            'sehirler' => TurkishCities::all(),
            'durum' => $durum,
            'eksikSayisi' => Tour::where(fn ($q) => $q->whereNull('departure_city')->orWhere('departure_city', ''))->count(),
            'toplam' => Tour::count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'cities' => ['required', 'array'],
            'cities.*' => ['nullable', 'string'],
        ]);

        $degisen = 0;

        foreach ($validated['cities'] as $tourId => $city) {
            $city = trim((string) $city);

            // Boş bırakılan alan "temizle" demektir; dolu olan 81 il listesine
            // uymak zorunda — serbest metin filtreyi bozar ("Istanbul" ≠ "İstanbul").
            $canonical = $city === '' ? null : TurkishCities::canonical($city);

            if ($city !== '' && $canonical === null) {
                continue; // listede olmayan değer sessizce atlanır
            }

            $tour = Tour::find((int) $tourId);
            if ($tour === null || $tour->departure_city === $canonical) {
                continue;
            }

            // Tek kolon güncellemesi: fiyat geçmişi / embedding event'leri boşuna
            // tetiklenmesin.
            Tour::whereKey($tour->getKey())->update(['departure_city' => $canonical]);
            $degisen++;
        }

        if ($degisen > 0) {
            cache()->forget('sitemap_index_v2');
            \App\Services\AiSearch\DestinationKnowledgeService::flushInventory();
        }

        return back()->with('success', "{$degisen} turun kalkış şehri güncellendi.");
    }
}
