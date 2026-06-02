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
                'model' => config('ai.destination_enrichment_model', 'gpt-4o-mini'),
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => $this->systemPrompt(),
                    ],
                    [
                        'role' => 'user',
                        'content' => 'Şehir: ' . mb_substr($city, 0, 100, 'UTF-8'),
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 900,
            ]);

            $payload = json_decode($response->choices[0]->message->content, true);
            if (!is_array($payload)) {
                throw new \RuntimeException('LLM JSON parse hatası');
            }

            $data = $this->extractAndValidate($payload);

            DestinationProfile::updateOrCreate(
                ['normalized_city' => $normalized],
                array_merge($data, [
                    'city' => $city,
                    'source' => DestinationProfile::SOURCE_LLM,
                    'enrichment_version' => DestinationProfile::CURRENT_ENRICHMENT_VERSION,
                    'generated_at' => now(),
                ])
            );

            Log::info('[DestinationProfile] Zengin profil oluşturuldu', [
                'city' => $city,
                'country' => $data['country'] ?? null,
                'vibe_tags' => $data['vibe_tags'] ?? null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[DestinationProfile] Enrichment failed', [
                'city' => $city,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function systemPrompt(): string
    {
        return 'Sen bir turizm araştırmacısısın. Verilen şehir için aşağıdaki JSON şemasında '
            . 'derinlemesine profil çıkar. Yanıt SADECE bu JSON olmalı, başka metin olmamalı:'
            . "\n\n"
            . '{'
            . '"crowd": float (0-1, turist yoğunluğu/kalabalık), '
            . '"lively": float (0-1, canlılık/gece hayatı), '
            . '"country": string (resmi ülke adı Türkçe, ör. "Birleşik Arap Emirlikleri"), '
            . '"summary": string (2-3 cümle Türkçe şehir özeti - öne çıkan özellikler, deneyim tipi), '
            . '"climate_by_month": object {1..12: {"temp_c": int, "condition": string}} (Türkçe condition: "sıcak", "ılık", "serin", "soğuk", "yağışlı", "kuru", "karlı"), '
            . '"vibe_tags": array (şu listeden seç: "beach", "luxury", "shopping", "nightlife", "cultural", "historical", "nature", "family", "adventure", "spa", "religious", "winter_sport", "cruise"), '
            . '"best_months": array (1-12 arası tam sayılar, ideal ziyaret ayları), '
            . '"crowded_months": array (1-12 arası tam sayılar, peak/yoğun ayları), '
            . '"requires_visa_for_tr": boolean (Türk vatandaşı için vize gerekli mi), '
            . '"reasoning": string (kısa Türkçe — ana profil gerekçesi)'
            . '}'
            . "\n\n"
            . 'Bilgilerin gerçekçi ve uluslararası kaynaklarda doğrulanabilir olmalı. '
            . 'Bilinmeyen/kurgu yerler için crowd=0.50, lively=0.50, vibe_tags=[], best_months=[] dön.';
    }

    /**
     * LLM çıktısını şemaya göre güvenli şekilde çıkarır + clamp eder.
     *
     * @return array<string, mixed>
     */
    private function extractAndValidate(array $payload): array
    {
        return [
            'crowd_score' => $this->clamp01((float) ($payload['crowd'] ?? 0.5)),
            'liveliness_score' => $this->clamp01((float) ($payload['lively'] ?? 0.5)),
            'country' => $this->cleanString($payload['country'] ?? null, 100),
            'summary' => $this->cleanString($payload['summary'] ?? null, 500),
            'climate_by_month' => $this->validClimate($payload['climate_by_month'] ?? null),
            'vibe_tags' => $this->validVibeTags($payload['vibe_tags'] ?? null),
            'best_months' => $this->validMonthArray($payload['best_months'] ?? null),
            'crowded_months' => $this->validMonthArray($payload['crowded_months'] ?? null),
            'requires_visa_for_tr' => is_bool($payload['requires_visa_for_tr'] ?? null)
                ? $payload['requires_visa_for_tr']
                : null,
            'reasoning' => $this->cleanString($payload['reasoning'] ?? null, 1000),
        ];
    }

    private function clamp01(float $v): float
    {
        return max(0.0, min(1.0, $v));
    }

    private function cleanString(mixed $value, int $maxLength): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr(trim((string) $value), 0, $maxLength, 'UTF-8') ?: null;
    }

    private function validClimate(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $valid = [];
        foreach ($value as $month => $info) {
            $monthInt = (int) $month;
            if ($monthInt < 1 || $monthInt > 12) {
                continue;
            }

            if (!is_array($info)) {
                continue;
            }

            $valid[(string) $monthInt] = [
                'temp_c' => is_numeric($info['temp_c'] ?? null) ? (int) $info['temp_c'] : null,
                'condition' => $this->cleanString($info['condition'] ?? null, 40),
            ];
        }

        return empty($valid) ? null : $valid;
    }

    private function validVibeTags(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $allowed = [
            'beach', 'luxury', 'shopping', 'nightlife', 'cultural', 'historical',
            'nature', 'family', 'adventure', 'spa', 'religious', 'winter_sport', 'cruise',
        ];

        $tags = collect($value)
            ->map(fn($t) => is_string($t) ? mb_strtolower(trim($t), 'UTF-8') : null)
            ->filter()
            ->filter(fn($t) => in_array($t, $allowed, true))
            ->unique()
            ->values()
            ->all();

        return empty($tags) ? null : $tags;
    }

    private function validMonthArray(mixed $value): ?array
    {
        if (!is_array($value)) {
            return null;
        }

        $months = collect($value)
            ->map(fn($m) => is_numeric($m) ? (int) $m : null)
            ->filter(fn($m) => $m !== null && $m >= 1 && $m <= 12)
            ->unique()
            ->sort()
            ->values()
            ->all();

        return empty($months) ? null : $months;
    }
}
