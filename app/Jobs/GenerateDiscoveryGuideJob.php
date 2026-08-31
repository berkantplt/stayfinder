<?php

namespace App\Jobs;

use App\Models\DiscoveryGuide;
use App\Services\Discovery\DestinationContentService;
use App\Services\Discovery\DiscoveryGuideAiService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Keşif Rehberi üretimi: AI çağrısı uzun sürebildiği için kuyruğa alınır,
 * frontend status'u poll eder. Durum makinesi: pending → processing →
 * completed | failed. Hatalı/eksik AI içeriği kullanıcıya asla gösterilmez —
 * doğrulama servis katmanında, buraya yalnız geçerli payload düşer.
 */
class GenerateDiscoveryGuideJob extends AiQueueJob
{
    /**
     * Aynı rehber için üst üste dispatch'i emen kilit (Cache::add kalıbı,
     * GenerateTourCharacterJob paritesi). Başarıda ve kalıcı hatada bırakılır;
     * retry'lar arasında tutulur — TTL job timeout'una eşit, best-effort.
     */
    public const DISPATCH_LOCK_PREFIX = 'discovery_guide_dispatch:';

    public const DISPATCH_LOCK_SECONDS = 180;

    /** DB_QUEUE_RETRY_AFTER=600'ün altında kalmalı (çifte faturalama notu, .env). */
    public int $timeout = 180;

    public function __construct(public readonly int $guideId) {}

    public function handle(DiscoveryGuideAiService $ai, DestinationContentService $content): void
    {
        $guide = DiscoveryGuide::find($this->guideId);
        if (! $guide || $guide->isCompleted()) {
            $this->releaseDispatchLock();

            return;
        }

        // Anahtar yoksa uygulama çökmez, retry da edilmez: anlaşılır
        // yapılandırma hatasıyla failed'a düşer.
        if (! $ai->isConfigured()) {
            $guide->update([
                'status' => DiscoveryGuide::STATUS_FAILED,
                'error_message' => 'AI servisi henüz yapılandırılmamış. Lütfen daha sonra tekrar deneyin.',
            ]);
            Log::warning('[DiscoveryGuide] OPENAI_API_KEY yapılandırılmamış', ['guide_id' => $guide->id]);
            $this->releaseDispatchLock();

            return;
        }

        $guide->update(['status' => DiscoveryGuide::STATUS_PROCESSING, 'error_message' => null]);

        try {
            $lookup = $content->lookup($guide->destination_input);
            $context = $content->promptContext($lookup['destination'], $lookup['profile']);

            $usedKey = $ai->cacheKey($guide);
            $payload = $ai->generateCached($guide, $context);

            // Üretim sürerken kullanıcı tercihleri değiştiyse (personalize,
            // dispatch kilidine takıldığı için yeni job atmamıştır) bayat
            // içeriği yazma: güncel tercihlerle kendini yeniden kuyruğa al
            // (ScoreTourRubricJob'daki bayat-sonuç kalıbı).
            $fresh = $guide->fresh();
            if ($fresh && $ai->cacheKey($fresh) !== $usedKey) {
                self::dispatch($guide->id);

                return;
            }

            $guide->update([
                'status' => DiscoveryGuide::STATUS_COMPLETED,
                'guide_payload' => $payload,
                'error_message' => null,
            ]);

            $this->releaseDispatchLock();
        } catch (\Throwable $e) {
            Log::warning('[DiscoveryGuide] Üretim hatası', [
                'guide_id' => $guide->id,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // tries/backoff mekanizmasına bırak; son denemede failed() çalışır
        }
    }

    /** Tüm denemeler tükendi: kullanıcıya jenerik Türkçe mesaj, detay logda. */
    public function failed(?\Throwable $exception): void
    {
        DiscoveryGuide::whereKey($this->guideId)->update([
            'status' => DiscoveryGuide::STATUS_FAILED,
            'error_message' => 'Rehber şu anda oluşturulamadı. Lütfen tekrar deneyin.',
        ]);

        $this->releaseDispatchLock();
    }

    private function releaseDispatchLock(): void
    {
        Cache::forget(self::DISPATCH_LOCK_PREFIX.$this->guideId);
    }
}
