<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Support\DepartureCityExtractor;
use App\Support\TurkishCities;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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

        // B10 — Eskiden satır başına Tour::find + update (50 satır = 100 sorgu) ve
        // listede olmayan değer SESSİZCE atlanıyordu: admin "50 tur güncellendi"
        // görüp değişmeyeni fark etmiyordu. Şimdi: tek SELECT, şehir başına tek
        // UPDATE, atlananlar tur numarasıyla bildirilir.
        $hedef = [];    // tour_id => canonical (null = temizle)
        $atlanan = [];  // tour_id => girilen metin

        foreach ($validated['cities'] as $tourId => $city) {
            $city = trim((string) $city);

            // Boş bırakılan alan "temizle" demektir; dolu olan 81 il listesine
            // uymak zorunda — serbest metin filtreyi bozar ("Istanbul" ≠ "İstanbul").
            $canonical = $city === '' ? null : TurkishCities::canonical($city);

            if ($city !== '' && $canonical === null) {
                $atlanan[(int) $tourId] = $city;
                continue;
            }

            $hedef[(int) $tourId] = $canonical;
        }

        $mevcut = $hedef === []
            ? collect()
            : Tour::whereIn('id', array_keys($hedef))->get(['id', 'departure_city'])->keyBy('id');

        // Aynı şehre giden turlar tek UPDATE'te; sadece gerçekten değişenler.
        $sehreGore = [];
        foreach ($hedef as $tourId => $canonical) {
            $tour = $mevcut->get($tourId);
            if ($tour === null || $tour->departure_city === $canonical) {
                continue;
            }
            $sehreGore[$canonical ?? ''][] = $tourId;
        }

        $degisen = 0;
        foreach ($sehreGore as $sehir => $ids) {
            // Query builder: fiyat geçmişi / embedding event'leri boşuna tetiklenmesin.
            $degisen += Tour::whereIn('id', $ids)->update(['departure_city' => $sehir === '' ? null : $sehir]);
        }

        if ($degisen > 0) {
            cache()->forget('sitemap_index_v2');
            \App\Services\AiSearch\DestinationKnowledgeService::flushInventory();
        }

        $yanit = back()->with('success', "{$degisen} turun kalkış şehri güncellendi.");

        if ($atlanan !== []) {
            // Tur numarası ekranda görünmez; adminin satırı bulabilmesi için başlıkla
            // bildir, yazdığı metni alanda bırak (withInput + old) ve satırı işaretle.
            $basliklar = Tour::whereIn('id', array_keys($atlanan))->pluck('title', 'id');
            $ornekler = collect($atlanan)
                ->take(10)
                ->map(fn ($metin, $id) => '"'.$metin.'" → '.Str::limit((string) ($basliklar[$id] ?? "tur #{$id}"), 40))
                ->implode(', ');

            $yanit
                ->withInput()
                ->with('kalkis_reddedilen', array_keys($atlanan))
                ->withErrors(sprintf(
                    '%d satır 81 il listesinde olmayan değer nedeniyle KAYDEDİLMEDİ (kırmızı satırlar): %s%s. Listeden bir il seçin.',
                    count($atlanan),
                    $ornekler,
                    count($atlanan) > 10 ? ' ve '.(count($atlanan) - 10).' satır daha' : ''
                ));
        }

        return $yanit;
    }
}
