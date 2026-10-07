<?php

namespace App\Services\Discovery;

use App\Jobs\GenerateDiscoveryGuideJob;
use App\Models\DiscoveryGuide;
use App\Support\DestinationFilter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Keşif Rehberi yaşam döngüsü: oluştur → hazır içerik varsa ANINDA tamamla →
 * yoksa kuyrukta üret → (istenirse) kişiselleştir/yeniden üret. Controller
 * ince kalsın diye durum geçişleri ve sahiplik ataması burada.
 *
 * Yeniden kullanım kuralı: AI'ya gitmeden ÖNCE aynı normalize girdiyle
 * üretilmiş doğrulanmış içerik aranır (önce cache, sonra tamamlanmış
 * rehberler). Bulunursa job hiç dispatch edilmez — kullanıcı dakikalık
 * worker turunu beklemez. Cache kontrolü job içinde de kalır (ikinci
 * emniyet); asıl kazanç kuyruğu atlamak.
 */
class DiscoveryGuideService
{
    /**
     * Tamamlanmış rehberin yeniden kullanılabileceği pencere (gün). Cache
     * TTL'inden (7 gün) uzun: içerik editöryel, günlük değişmiyor; deploy
     * sonrası cache temizliği DB'deki içeriği götürmez.
     */
    public const REUSE_DAYS = 30;

    /**
     * Aday tarama tavanı. Destinasyon DB'de ham yazımıyla durduğu için
     * eşleşme PHP tarafında normalize edilerek yapılır (SQL LOWER/collation
     * tuzağı, bkz. DestinationContentService). Aynı parametre kümesindeki son
     * N tamamlanmış rehber taranır; tavan aşılırsa eski şehir bulunamaz ve
     * üretime düşülür — yalnız yavaşlama, yanlış içerik değil.
     */
    private const REUSE_SCAN_LIMIT = 200;

    public function __construct(
        private readonly DestinationContentService $content,
        private readonly DiscoveryGuideAiService $ai,
    ) {}

    /**
     * @param  array{destination: string, duration_days: int, traveler_type?: ?string, interests?: ?array, pace?: ?string, budget?: ?string}  $validated
     */
    public function create(Request $request, array $validated): DiscoveryGuide
    {
        $lookup = $this->content->lookup($validated['destination']);

        $guide = new DiscoveryGuide([
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

        $this->completeOrDispatch($guide);

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

        $this->completeOrDispatch($guide);

        return $guide;
    }

    /**
     * Hazır içerik varsa rehberi kuyruksuz tamamlar, yoksa pending kaydedip
     * üretimi kuyruğa atar. İki yolda da kayıt TEK seferde yazılır.
     *
     * Kişiselleştirmede kilit dolu (eski tercihle job çalışıyor) iken hazır
     * içerik bulunursa sorun yok: çalışan job bitişte anahtar bayatlık
     * kontrolünde kendini yeniden dispatch eder, yeni job rehberi tamamlanmış
     * görüp kilidi bırakarak çıkar — tamamlanmış içerik ezilmez.
     */
    private function completeOrDispatch(DiscoveryGuide $guide): void
    {
        $payload = $this->reusablePayload($guide);

        if ($payload !== null) {
            $guide->status = DiscoveryGuide::STATUS_COMPLETED;
            $guide->guide_payload = $payload;
            $guide->error_message = null;
            $guide->save();

            return;
        }

        $guide->save();
        $this->dispatchGeneration($guide);
    }

    /**
     * Aynı normalize girdiyle üretilmiş doğrulanmış payload: önce cache,
     * sonra REUSE_DAYS içinde tamamlanmış bir rehber (bulunursa cache
     * ısıtılır). Payload kişisel veri içermez (şehir içeriği + tercih
     * çipleri; çipler zaten anahtarın parçası olduğu için birebir aynı), bu
     * yüzden başka kullanıcının rehberinden alınması sahiplik kuralını
     * ihlal etmez.
     *
     * @return array<string, mixed>|null
     */
    private function reusablePayload(DiscoveryGuide $guide): ?array
    {
        $key = $this->ai->cacheKey($guide);

        $cached = $this->ai->cachedPayload($key);
        if ($cached !== null) {
            return $cached;
        }

        $hedefSehir = DestinationFilter::normalize($guide->destination_input);
        if ($hedefSehir === '') {
            return null;
        }

        $hedefIlgi = (array) ($guide->interests ?? []);
        sort($hedefIlgi);

        // Payload kolonu bilerek seçilmiyor: 200 adayın JSON'unu belleğe
        // çekmek yerine önce hafif kolonlarla eşleşme bulunur, payload yalnız
        // eşleşen tek kayıt için okunur.
        $adaylar = DiscoveryGuide::query()
            ->where('status', DiscoveryGuide::STATUS_COMPLETED)
            ->whereNotNull('guide_payload')
            ->where('duration_days', $guide->duration_days)
            ->where('pace', $guide->pace)
            ->where('budget', $guide->budget)
            ->when(
                $guide->traveler_type === null,
                fn ($q) => $q->whereNull('traveler_type'),
                fn ($q) => $q->where('traveler_type', $guide->traveler_type),
            )
            ->where('updated_at', '>=', now()->subDays(self::REUSE_DAYS))
            ->when($guide->exists, fn ($q) => $q->whereKeyNot($guide->getKey()))
            ->orderByDesc('id')
            ->limit(self::REUSE_SCAN_LIMIT)
            ->get(['id', 'destination_input', 'interests']);

        foreach ($adaylar as $aday) {
            if (DestinationFilter::normalize($aday->destination_input) !== $hedefSehir) {
                continue;
            }

            $adayIlgi = (array) ($aday->interests ?? []);
            sort($adayIlgi);
            if ($adayIlgi !== $hedefIlgi) {
                continue;
            }

            $payload = DiscoveryGuide::query()->find($aday->id, ['id', 'guide_payload'])?->guide_payload;
            if (is_array($payload) && $payload !== []) {
                $this->ai->rememberPayload($key, $payload);

                return $payload;
            }
        }

        return null;
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
