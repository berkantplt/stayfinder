<?php

namespace App\Jobs;

use App\Services\Discovery\DestinationContentService;
use App\Services\Discovery\DiscoveryCityBaseService;
use App\Services\Discovery\DiscoveryGuideAiService;
use Illuminate\Support\Facades\Log;

/**
 * Şehir tabanı üretimi — kullanıcı BEKLEMEZ. Bir şehir için ilk rehber tam
 * üretimle tamamlanır, bu job arkadan parametresiz etiketli havuzu kurar;
 * sonraki rehberler yalnız günlük plan üretir. Tanınmayan destinasyon
 * (unknown_destination) ASLA taban olarak yazılmaz.
 */
class BuildDiscoveryCityBaseJob extends AiQueueJob
{
    /** DB_QUEUE_RETRY_AFTER=600'ün altında (GenerateDiscoveryGuideJob paritesi). */
    public int $timeout = 180;

    public function __construct(public readonly string $cityInput) {}

    public function handle(
        DiscoveryGuideAiService $ai,
        DestinationContentService $content,
        DiscoveryCityBaseService $bases,
    ): void {
        try {
            // Aynı şehir için kuyrukta birikmiş ikinci job: taze taban varsa
            // AI'ya gitme (kilit TTL'i dolup yeniden dispatch edilmiş olabilir).
            if ($bases->findFresh($this->cityInput) !== null) {
                return;
            }

            if (! $ai->isConfigured()) {
                Log::warning('[DiscoveryCityBase] OPENAI_API_KEY yapılandırılmamış', ['city' => $this->cityInput]);

                return;
            }

            $lookup = $content->lookup($this->cityInput);
            $context = $content->promptContext($lookup['destination'], $lookup['profile']);

            $payload = $ai->generateCityBase($this->cityInput, $context);

            if (! empty($payload['unknown_destination'])) {
                Log::info('[DiscoveryCityBase] Tanınmayan destinasyon, taban yazılmadı', ['city' => $this->cityInput]);

                return;
            }

            $base = $bases->store($this->cityInput, $payload, (string) config('ai.discovery_model', 'gpt-5.4-mini'));

            Log::info('[DiscoveryCityBase] Şehir tabanı üretildi', [
                'city' => $base->display_name,
                'items' => $base->itemCount(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[DiscoveryCityBase] Üretim hatası', [
                'city' => $this->cityInput,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Kilit yalnız dispatch fırtınasını önler; job bitince bırakılır
            // (GenerateTourCharacterJob kalıbı) — sonraki rehber isteği gerekirse
            // yeniden kuyruğa alabilsin.
            $bases->releaseBuildLock($this->cityInput);
        }
    }
}
