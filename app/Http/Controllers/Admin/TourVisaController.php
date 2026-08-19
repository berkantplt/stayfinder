<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Vize durumu toplu düzenleme.
 *
 * NEDEN ELLE: vize kaynağı olarak iki aday vardı ve ölçüldü.
 * (1) DestinationProfile.requires_visa_for_tr — LLM üretimi, şehir→vize eşlemesi.
 *     Yerel veride Paris, Yunanistan ve Yunan Adaları "vizesiz" görünüyordu;
 *     bu kaynakla kurulan filtre 78 tur döndürüp içlerine Schengen turlarını
 *     karıştırıyordu. Ayrıca yapısal olarak yanlış: vize şehrin değil TURUN
 *     özelliği — aynı şehre giden iki tur farklı vize rejiminde olabilir.
 * (2) Sayfadan otomatik çıkarım — 120 gerçek sayfada ölçüldü: "vizeli" kesinliği
 *     %95,5 ama "vizesiz" %76,9 ve o kategoriye giren turların %15,4'ü gerçekte
 *     vize istiyordu. Yanlış "vizesiz" kullanıcıyı sınırda bırakır.
 *
 * Sonuç: tek güvenilir kaynak acentanın/adminin beyanı. Tur tur düzenleme
 * ekranını dolaşmak 90+ turda pratik değil — bu ekran hepsini tek listede toplar.
 * Kalkış şehirleri ekranıyla aynı kalıp.
 */
class TourVisaController extends Controller
{
    /** Ekranda ve kayıtta ortak sözlük: form değeri → [requires_visa, visa_on_arrival]. */
    public const SECENEKLER = [
        'unknown' => [null, null],
        '1' => [true, false],
        'kapida' => [true, true],
        '0' => [false, false],
    ];

    public function index(Request $request): View
    {
        $durum = $request->input('durum', 'eksik');

        $query = Tour::with('agency')->orderByDesc('id');

        if ($durum === 'eksik') {
            $query->whereNull('requires_visa');
        } elseif ($durum === 'dolu') {
            $query->whereNotNull('requires_visa');
        } elseif ($durum === 'yurtdisi') {
            // Asıl iş burada: yurt içi turda vize alanı zaten anlamsız.
            $query->where('is_international', true)->whereNull('requires_visa');
        }

        if ($request->filled('q')) {
            $query->where('title', 'like', '%'.$request->q.'%');
        }

        return view('admin.tour-visa', [
            'tours' => $query->paginate(50)->withQueryString(),
            'durum' => $durum,
            'eksikSayisi' => Tour::whereNull('requires_visa')->count(),
            'yurtdisiEksik' => Tour::where('is_international', true)->whereNull('requires_visa')->count(),
            'toplam' => Tour::count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'visa' => ['required', 'array'],
            'visa.*' => ['nullable', 'string', 'in:unknown,1,kapida,0'],
        ]);

        $degisen = 0;

        foreach ($validated['visa'] as $tourId => $deger) {
            [$gerekli, $kapida] = self::SECENEKLER[$deger] ?? [null, null];

            $tour = Tour::find((int) $tourId);
            if ($tour === null || ($tour->requires_visa === $gerekli && $tour->visa_on_arrival === $kapida)) {
                continue;
            }

            // Tek kolon güncellemesi: fiyat geçmişi / embedding event'leri boşuna
            // tetiklenmesin (kalkış şehirleri ekranıyla aynı gerekçe).
            Tour::whereKey($tour->getKey())->update([
                'requires_visa' => $gerekli,
                'visa_on_arrival' => $kapida,
            ]);
            $degisen++;
        }

        return back()->with('success', "{$degisen} turun vize durumu güncellendi.");
    }
}
