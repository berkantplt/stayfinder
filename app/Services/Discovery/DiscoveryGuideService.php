<?php

namespace App\Services\Discovery;

use App\Jobs\GenerateDiscoveryGuideJob;
use App\Models\DiscoveryGuide;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Keşif Rehberi yaşam döngüsü: oluştur → kuyrukta üret → (istenirse)
 * kişiselleştir/yeniden üret. Controller ince kalsın diye durum geçişleri
 * ve sahiplik ataması burada.
 */
class DiscoveryGuideService
{
    public function __construct(
        private readonly DestinationContentService $content,
    ) {}

    /**
     * @param  array{destination: string, duration_days: int, traveler_type?: ?string, interests?: ?array, pace?: ?string, budget?: ?string}  $validated
     */
    public function create(Request $request, array $validated): DiscoveryGuide
    {
        $lookup = $this->content->lookup($validated['destination']);

        $guide = DiscoveryGuide::create([
            'user_id' => $request->user()?->id,
            'session_id' => $request->user()
                ? null
                : Str::limit($request->session()->getId(), 64, ''),
            'destination_input' => trim($validated['destination']),
            'destination_id' => $lookup['destination']?->id,
            'duration_days' => (int) $validated['duration_days'],
            'traveler_type' => $validated['traveler_type'] ?? null,
            'interests' => $this->cleanInterests($validated['interests'] ?? null),
            'pace' => $validated['pace'] ?? 'normal',
            'budget' => $validated['budget'] ?? 'standard',
            'status' => DiscoveryGuide::STATUS_PENDING,
        ]);

        $this->dispatchGeneration($guide);

        return $guide;
    }

    /**
     * Tercih güncelle + yeniden üret. Boş dizi ile çağrılırsa tercih değişmez —
     * bu "Tekrar dene / Rehberi yeniden oluştur" akışıdır. Eski payload yeni
     * üretim tamamlanana kadar yerinde kalır; arayüz status'a bakar.
     *
     * @param  array{traveler_type?: ?string, interests?: ?array, pace?: ?string, budget?: ?string}  $validated
     */
    public function personalize(DiscoveryGuide $guide, array $validated): DiscoveryGuide
    {
        $degisiklik = [];

        if (array_key_exists('traveler_type', $validated)) {
            $degisiklik['traveler_type'] = $validated['traveler_type'];
        }
        if (array_key_exists('interests', $validated)) {
            $degisiklik['interests'] = $this->cleanInterests($validated['interests']);
        }
        if (array_key_exists('pace', $validated) && $validated['pace'] !== null) {
            $degisiklik['pace'] = $validated['pace'];
        }
        if (array_key_exists('budget', $validated) && $validated['budget'] !== null) {
            $degisiklik['budget'] = $validated['budget'];
        }

        $guide->fill($degisiklik);
        $guide->status = DiscoveryGuide::STATUS_PENDING;
        $guide->error_message = null;
        $guide->save();

        $this->dispatchGeneration($guide);

        return $guide;
    }

    /**
     * Kilitli dispatch: aynı rehber için job zaten kuyruktaysa/çalışıyorsa
     * yenisi atılmaz (art arda "yeniden oluştur" tıklamaları tek AI çağrısına
     * iner). Kilit doluyken tercih değişirse sorun yok: çalışan job bitişte
     * bayatlık kontrolü yapar ve kendini güncel tercihlerle yeniden dispatch
     * eder. Kilidi job başarıda/kalıcı hatada bırakır; TTL emniyet supabıdır.
     */
    private function dispatchGeneration(DiscoveryGuide $guide): void
    {
        $acquired = Cache::add(
            GenerateDiscoveryGuideJob::DISPATCH_LOCK_PREFIX.$guide->id,
            1,
            GenerateDiscoveryGuideJob::DISPATCH_LOCK_SECONDS,
        );

        if ($acquired) {
            GenerateDiscoveryGuideJob::dispatch($guide->id);
        }
    }

    /** @return array<int, string>|null */
    private function cleanInterests(?array $interests): ?array
    {
        if ($interests === null) {
            return null;
        }

        $temiz = array_values(array_intersect(
            array_map(fn ($i) => (string) $i, $interests),
            array_keys(DiscoveryGuide::INTERESTS)
        ));

        return $temiz === [] ? null : $temiz;
    }
}
