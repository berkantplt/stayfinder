<?php

namespace App\Jobs;

use App\Models\DestinationProfile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class GenerateDestinationProfileJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    public function __construct(public readonly string $city) {}

    public function handle(): void
    {
        $city = trim($this->city);
        if ($city === '') {
            return;
        }

        $normalized = DestinationProfile::normalize($city);

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Sen bir turizm araştırmacısısın. Verilen şehir için iki sayısal skor üret: '
                            . '"crowd" (kalabalık düzeyi, 0=tenha 1=çok kalabalık), '
                            . '"lively" (canlılık/gece hayatı, 0=durgun 1=çok canlı). '
                            . 'Şehrin uluslararası bilinen profili (turist yoğunluğu, gece hayatı, etkinlik yoğunluğu) baz alınmalı. '
                            . 'Sadece JSON dön: {"crowd": float, "lively": float, "reasoning": "kısa Türkçe açıklama"}. '
                            . 'Skorlar 0.00-1.00 arasında olmalı, 2 ondalık. Bilinmeyen/kurgu yer için ortalama (0.50) kullan.',
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Şehir: ' . mb_substr($city, 0, 100, 'UTF-8'),
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 200,
            ]);

            $payload = json_decode($response->choices[0]->message->content, true);
            if (!is_array($payload)) {
                throw new \RuntimeException('LLM JSON parse hatası');
            }

            $crowd = $this->clamp01((float) ($payload['crowd'] ?? 0.5));
            $lively = $this->clamp01((float) ($payload['lively'] ?? 0.5));
            $reasoning = isset($payload['reasoning']) ? mb_substr((string) $payload['reasoning'], 0, 1000) : null;

            DestinationProfile::updateOrCreate(
                ['normalized_city' => $normalized],
                [
                    'city' => $city,
                    'crowd_score' => $crowd,
                    'liveliness_score' => $lively,
                    'source' => DestinationProfile::SOURCE_LLM,
                    'reasoning' => $reasoning,
                    'generated_at' => now(),
                ]
            );

            Log::info('[DestinationProfile] LLM enrichment OK', [
                'city' => $city,
                'crowd' => $crowd,
                'lively' => $lively,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[DestinationProfile] LLM enrichment failed', [
                'city' => $city,
                'error' => $e->getMessage(),
            ]);

            // Default placeholder DB'de zaten var (DestinationProfileService eklemiş);
            // başarısızlıkta dokunma. Retry mekanizması job'ı yeniden çalıştırır.
            throw $e;
        }
    }

    private function clamp01(float $v): float
    {
        return max(0.0, min(1.0, $v));
    }
}
