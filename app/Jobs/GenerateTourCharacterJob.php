<?php

namespace App\Jobs;

use App\Models\DestinationProfile;
use App\Models\Tour;
use App\Services\AiSearch\DestinationProfileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * Tur karakteri üretimi — tur başına ömür boyu tek LLM çağrısı (mini).
 * Şehir karakter verisi DB'deki destinasyon profillerinden HAZIR verilir;
 * model yalnızca program yoğunluğunu değerlendirip özet kurar, şehir bilgisi
 * uydurmaz. Sonuç: "sakin şehirler, düşük tempo — dinlenme turu" gibi
 * chatbot'un kullanacağı topraklanmış karakter cümlesi.
 */
class GenerateTourCharacterJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    /** Observer/komutun tekrar-dispatch kilidi — job bitince bırakılır. */
    public const DISPATCH_LOCK_PREFIX = 'tour_character_dispatch:';

    public function __construct(public readonly int $tourId) {}

    public function handle(): void
    {
        $tour = Tour::find($this->tourId);
        if (! $tour) {
            Cache::forget(self::DISPATCH_LOCK_PREFIX.$this->tourId);

            return;
        }

        try {
            $response = OpenAI::chat()->create([
                'model' => config('ai.tour_character_model', 'gpt-4o-mini'),
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt()],
                    ['role' => 'user', 'content' => $this->tourContext($tour)],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 300,
            ]);

            $payload = json_decode($response->choices[0]->message->content ?? '', true);
            if (! is_array($payload)) {
                throw new \RuntimeException('LLM JSON parse hatası');
            }

            $summary = trim((string) ($payload['character_summary'] ?? ''));
            $summary = $summary !== '' ? mb_substr($summary, 0, 300, 'UTF-8') : null;
            $pace = is_numeric($payload['pace'] ?? null)
                ? max(0.0, min(1.0, (float) $payload['pace']))
                : null;

            // Query builder update: model event tetiklemez — TourObserver::updated
            // üzerinden kendi kendini yeniden dispatch etme döngüsü oluşmaz.
            Tour::whereKey($tour->id)->update([
                'character_summary' => $summary,
                'pace_score' => $pace,
                'character_version' => Tour::CURRENT_CHARACTER_VERSION,
            ]);

            Log::info('[TourCharacter] Tur karakteri üretildi', [
                'tour_id' => $tour->id,
                'pace' => $pace,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TourCharacter] Üretim hatası', [
                'tour_id' => $tour->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        } finally {
            // Kilit yalnız dispatch fırtınasını önler; job bittiğinde (başarı ya da
            // hata) bırakılır — job sonrası içerik düzenlemesi 600 sn boyunca
            // sessizce düşüp turu KALICI bayat karakterle bırakmasın
            Cache::forget(self::DISPATCH_LOCK_PREFIX.$this->tourId);
        }
    }

    private function systemPrompt(): string
    {
        return 'Sen turXtur için tur karakteri analistisin. Sana bir turun program/açıklama verisi '
            .'ve turun uğradığı şehirlerin HAZIR karakter profilleri verilecek. SADECE verilen veriyi kullan; '
            .'şehirler hakkında verilenin dışında bilgi uydurma. Yanıt SADECE şu JSON olmalı:'
            ."\n\n"
            .'{'
            .'"pace": float (0-1, programın yoğunluğu: 0=bol serbest zaman/dinlenme, 1=her gün dolu tempolu gezi), '
            .'"character_summary": string (1-2 cümle Türkçe: turun karakteri — hangi tarz gezgin için, '
            .'şehirlerinin verilen karakteriyle gerekçelendir. Ör: "Sakin sahil kasabalarından geçen, '
            .'düşük tempolu bir dinlenme turu" ya da "Kalabalık ve turistik şehirleri kapsayan, gece hayatı '
            .'hareketli, eğlence odaklı tempolu bir tur")'
            .'}'
            ."\n\n"
            .'pace değerlendirmesi: gün gün programda gezi/tur/müze/yürüyüş yoğunluğu yüksekse pace yüksek; '
            .'"serbest zaman", "dinlenme", "denize girme" ağırlıklıysa pace düşük. '
            .'Şehir profili verilmemiş şehir hakkında niteleme yapma.';
    }

    private function tourContext(Tour $tour): string
    {
        $cities = DestinationProfile::splitCities((string) $tour->destination);
        $profileService = app(DestinationProfileService::class);

        $cityLines = collect($cities)->map(function ($city) use ($profileService) {
            $profile = $profileService->get($city);
            $description = DestinationProfileService::describeProfile($profile);

            return '- '.$city.': '.($description ?? 'profil henüz hazır değil — bu şehir hakkında niteleme yapma');
        })->implode("\n");

        $itinerary = collect(is_array($tour->itinerary) ? $tour->itinerary : [])
            ->map(fn ($day, $i) => ($i + 1).'. Gün — '.($day['title'] ?? '').': '.Str::limit((string) ($day['content'] ?? ''), 200))
            ->implode("\n");

        return implode("\n", array_filter([
            "TUR: {$tour->title}",
            "Süre: {$tour->duration_days} gün",
            "Destinasyon: {$tour->destination}",
            "ŞEHİR PROFİLLERİ (hazır veri):\n".($cityLines !== '' ? $cityLines : '- (şehir çıkarılamadı)'),
            $tour->description ? 'Açıklama: '.Str::limit(strip_tags((string) $tour->description), 600) : null,
            $tour->included ? 'Dahil: '.Str::limit((string) $tour->included, 300) : null,
            $tour->extras ? 'Ekstralar: '.Str::limit((string) $tour->extras, 200) : null,
            $itinerary !== '' ? "GÜN GÜN PROGRAM:\n".Str::limit($itinerary, 2500) : null,
        ]));
    }
}
