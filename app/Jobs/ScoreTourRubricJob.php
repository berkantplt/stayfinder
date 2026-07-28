<?php

namespace App\Jobs;

use App\Models\Tour;
use App\Models\TourRubricScore;
use App\Services\Matching\Rubric;
use App\Support\OpenAiChatParams;
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
 * A işi (brif §3): turu rubrik boyutlarında LLM ile puanlar.
 *
 * - Girdi sözleşmesi: PAZARLAMA METNİ VERİLMEZ (description ayıklanır) —
 *   yalnız program, süre, konaklama, grup, aktiviteler, ziyaret yerleri.
 * - İki geçiş: herhangi bir boyutta fark > 1 ise review_status=needs_review,
 *   otomatik yayınlanmaz (eşleştirici yine kullanır ama editör panelinde görünür).
 * - value null ⇒ boyut devre dışı; LLM'e boşluk DOLDURTULMAZ.
 * - input_hash: program değişince hash değişir → yeniden puanlama.
 */
class ScoreTourRubricJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];

    /** Tek job içinde iki ardışık LLM geçişi var — varsayılan 60 sn yetmez. */
    public int $timeout = 300;

    public const DISPATCH_LOCK_PREFIX = 'tour_rubric_dispatch:';

    public function __construct(public readonly int $tourId, public readonly bool $force = false) {}

    public function handle(): void
    {
        $tour = Tour::find($this->tourId);
        if (! $tour) {
            Cache::forget(self::DISPATCH_LOCK_PREFIX.$this->tourId);

            return;
        }

        try {
            $input = self::sanitizedInput($tour);
            $hash = hash('sha256', Rubric::VERSION.'|'.$input);

            $existing = TourRubricScore::where('tour_id', $tour->id)
                ->where('rubric_version', Rubric::VERSION)
                ->first();
            if ($existing && $existing->input_hash === $hash && ! $this->force) {
                return; // içerik değişmemiş — yeniden puanlama gereksiz
            }

            // İki geçiş (brif §3.5)
            $pass1 = $this->scoreOnce($input);
            $pass2 = $this->scoreOnce($input);

            $needsReview = false;
            foreach (Rubric::dimensions() as $d) {
                $v1 = $pass1[$d]['value'] ?? null;
                $v2 = $pass2[$d]['value'] ?? null;
                if (is_numeric($v1) && is_numeric($v2) && abs($v1 - $v2) > 1) {
                    $needsReview = true;
                } elseif (is_numeric($v1) !== is_numeric($v2)) {
                    $needsReview = true; // biri null biri değil — kararsız kanıt
                }
                // Alıntı girdide bulunamadıysa (uydurma şüphesi) editör baksın
                if (($pass1[$d]['evidence_verified'] ?? null) === false) {
                    $needsReview = true;
                }
            }

            TourRubricScore::updateOrCreate(
                ['tour_id' => $tour->id, 'rubric_version' => Rubric::VERSION],
                [
                    'input_hash' => $hash,
                    'scores' => $pass1,
                    'review_status' => $needsReview ? TourRubricScore::STATUS_NEEDS_REVIEW : TourRubricScore::STATUS_AUTO,
                    'scored_at' => now(),
                ]
            );

            // Puanlama sürerken tur düzenlendiyse (dispatch kilidi tutulduğu için
            // observer yeniden kuyruğa alamaz) sonuç bayattır → taze girdiyle tekrar
            if (self::sanitizedInput($tour->fresh() ?? $tour) !== $input) {
                Cache::forget(self::DISPATCH_LOCK_PREFIX.$this->tourId);
                self::dispatch($this->tourId);
            }

            $nulls = array_keys(array_filter($pass1, fn ($s) => ! is_numeric($s['value'] ?? null)));
            Log::info('[TourRubric] Tur puanlandı', [
                'tour_id' => $tour->id,
                'needs_review' => $needsReview,
                'null_dimensions' => $nulls,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[TourRubric] Puanlama hatası', ['tour_id' => $tour->id, 'error' => $e->getMessage()]);
            throw $e;
        } finally {
            Cache::forget(self::DISPATCH_LOCK_PREFIX.$this->tourId);
        }
    }

    /** @return array<string, array{value: int|null, confidence: string, evidence: string|null}> */
    private function scoreOnce(string $input): array
    {
        $response = OpenAI::chat()->create(OpenAiChatParams::json(
            config('ai.rubric_model', 'gpt-5.4-mini'),
            [
                ['role' => 'system', 'content' => self::prompt()],
                ['role' => 'user', 'content' => "DEĞERLENDİRİLECEK TUR:\n".$input],
            ],
            1600,
            0.0, // brif: temperature 0 (reasoning ailesinde otomatik atlanır)
        ));

        $payload = json_decode($response->choices[0]->message->content ?? '', true);
        $scores = $payload['scores'] ?? null;
        if (! is_array($scores)) {
            throw new \RuntimeException('LLM JSON parse hatası');
        }

        // Şema disiplini: yalnız rubrik boyutları; value 1-5 tam sayı veya null
        $normalize = fn (string $t) => preg_replace('/\s+/u', ' ', mb_strtolower(strip_tags($t), 'UTF-8'));
        $inputNorm = $normalize($input);

        $temiz = [];
        foreach (Rubric::dimensions() as $d) {
            $s = $scores[$d] ?? [];
            $value = $s['value'] ?? null;
            $evidence = (isset($s['evidence']) && is_string($s['evidence']) && trim($s['evidence']) !== '')
                ? Str::limit(trim($s['evidence']), 200)
                : null;

            // Brif §3.2: kanıtsız puan KABUL EDİLMEZ. Alıntı yoksa boyut null'a
            // düşer — LLM'in boşluğu doldurmasına izin verilmez.
            $gecerli = is_numeric($value) && (int) $value >= 1 && (int) $value <= 5 && $evidence !== null;

            // Alıntı gerçekten girdide geçiyor mu (uydurma alıntı kontrolü).
            // Geçmiyorsa puan silinmez ama editör onayına düşürülür.
            $dogrulandi = $gecerli && str_contains($inputNorm, $normalize($evidence));

            $temiz[$d] = [
                'value' => $gecerli ? (int) $value : null,
                'confidence' => in_array($s['confidence'] ?? null, ['high', 'medium', 'low', 'none'], true) ? $s['confidence'] : 'none',
                'evidence' => $gecerli ? $evidence : null,
                'evidence_verified' => $gecerli ? $dogrulandi : null,
            ];
        }

        return $temiz;
    }

    /**
     * Girdi sözleşmesi (brif §3.1): pazarlama alanları AYIKLANIR.
     *
     * Kırpma sınırları bilerek geniş: tempo ve fiziksel boyutlarının kanıtı
     * (kaç durak, ne kadar yürüyüş) gün metninin ORTASINDA/SONUNDA geçiyor.
     * Dar kırpmada model kanıt bulamayıp o boyutları null bırakıyordu.
     * Girdi büyümesi maliyeti tur başına kuruş mertebesinde (ömür boyu 2 çağrı).
     */
    public static function sanitizedInput(Tour $tour): string
    {
        $itinerary = collect(is_array($tour->itinerary) ? $tour->itinerary : [])
            ->map(fn ($day, $i) => ($i + 1).'. Gün — '.($day['title'] ?? '').': '.Str::limit(strip_tags((string) ($day['content'] ?? '')), 1000))
            ->implode("\n");

        return implode("\n", array_filter([
            'Toplam süre: '.$tour->duration_days.' gün',
            'Ziyaret edilen yerler: '.$tour->destination,
            $tour->hotel_info ? 'Konaklama: '.Str::limit(strip_tags((string) $tour->hotel_info), 600) : null,
            $tour->included ? 'Dahil olan hizmet/aktiviteler: '.Str::limit(strip_tags((string) $tour->included), 800) : null,
            $tour->extras ? 'Opsiyonel aktiviteler: '.Str::limit(strip_tags((string) $tour->extras), 500) : null,
            $itinerary !== '' ? "GÜN GÜN PROGRAM:\n".Str::limit($itinerary, 10000) : null,
        ]));
    }

    /** Brif §3.3 promptu — çıpalar resources/rubric/v1.json'dan enjekte edilir. */
    public static function prompt(): string
    {
        $anchors = collect(Rubric::anchorTours())->map(function ($a) {
            return 'ÖRNEK TUR: '.$a['isim']."\nPROGRAM: ".$a['program']."\nPUANLAR: ".json_encode($a['scores'], JSON_UNESCAPED_UNICODE);
        })->implode("\n\n");

        return <<<PROMPT
Sen bir tur kataloğu için nesnel puanlama yapıyorsun. Sana bir turun programı verilecek.
Aşağıdaki 10 boyutun her biri için 1-5 arası bir tam sayı vereceksin.

MUTLAK KURALLAR:
1. Her puan için, sana verilen metinden birebir bir alıntı göstereceksin. Alıntı en fazla 25 kelime.
2. Metinde o boyutu değerlendirmeye yetecek kanıt yoksa, value alanına null yaz. Tahmin etme, ortalama verme, benzer turlardan çıkarım yapma.
3. Sadece verilen programa bak. Destinasyon hakkındaki genel bilgine dayanarak puan yükseltme veya düşürme.
4. Övgü ifadelerini yok say. "Muhteşem", "eşsiz", "unutulmaz" gibi sözcükler puan taşımaz.
5. Sadece JSON döndür. Açıklama, önsöz, markdown bloğu yazma.

ÖLÇEK TANIMLARI:

tempo — günlük program yoğunluğu
1: Serbest zaman baskın, günde en fazla 1 planlı aktivite, geç başlangıç
2: Günde 1-2 aktivite, uzun öğle arası
3: Günde 2-3 durak, akşamlar serbest
4: Günde 4+ durak, erken başlangıç, kısa molalar
5: Sabahtan akşama dolu, neredeyse her gün konum değişikliği

fiziksel — katılımcıdan beklenen fiziksel efor
1: Yürüyüş yok denecek kadar az, araçla kapı önü, günde 1 km altı
2: Günde 1-4 km, düz zemin, merdiven olabilir
3: Günde 4-8 km, hafif eğim, taşlık zemin olabilir
4: Günde 8-14 km veya 300-600 m tırmanış
5: Günde 14 km üstü veya 600 m üstü tırmanış, teknik zemin, ardışık zorlu günler

kultur — kültürel/tarihi içeriğin ağırlığı
1: Kültürel içerik yok
2: Bir iki durak, yüzeysel geçiş
3: Programın yaklaşık yarısı, rehberli anlatım var
4: Ana eksen, çok sayıda müze veya ören yeri, uzman rehber
5: Tematik uzmanlık turu (arkeoloji, sanat tarihi vb.), akademik düzey anlatım

doga_sehir — mekan ekseni
1: Tamamen kentsel
2: Ağırlıklı şehir, kısa doğa molası
3: Dengeli
4: Ağırlıklı doğa, kasaba veya köy konaklaması
5: Tamamen doğa, yerleşim dışı konaklama

adrenalin — risk ve heyecan içeren aktivite düzeyi
1: Risk içeren aktivite yok
2: Opsiyonel hafif aktivite (ATV, deniz bisikleti, kısa at binme)
3: Programda bir orta düzey aktivite (sınıf 2-3 rafting, şnorkel, zipline)
4: Birden fazla aktivite, brifing veya sertifika gerektiren (tüplü dalış, yamaç paraşütü)
5: Turun ana ekseni, ileri seviye, önceden deneyim şartı var

gastronomi — yeme içmenin program içindeki yeri
1: Sadece beslenme, standart otel yemekleri
2: Birkaç yerel restoran durağı
3: Yerel mutfak vurgusu var, 2-3 özel öğün
4: Ana eksenlerden biri, üretici veya pazar ziyareti, atölye
5: Turun tamamı gastronomi odaklı, şef veya üretici eşliğinde tadım programı

sosyallik — grup etkileşiminin yoğunluğu
1: Bireysel veya çift, özel rehber, grup yok
2: Küçük grup (8 kişi altı), az ortak aktivite
3: 8-16 kişi, düzenli ortak öğünler
4: 16-30 kişi, ortak aktivite ağırlıklı
5: Büyük grup, sürekli ortak program, sosyal etkinlik odaklı

konfor — konaklama ve hizmet seviyesi
1: Kamp veya ortak banyolu temel konaklama
2: Pansiyon, 2-3 yıldız
3: 4 yıldız veya iyi butik otel
4: 5 yıldız veya üst segment butik
5: Lüks segment, özel transfer, üst düzey kişisel hizmet

yapilandirilmislik — programın katılığı
1: Sadece konaklama ve transfer, program yok
2: Birkaç önerilen aktivite, günün çoğu serbest
3: Yarı programlı
4: Günlerin çoğu saat saat planlı
5: Tamamen planlı, serbest zaman minimum

kalabaliklik — rotanın turistik yoğunluğu
1: Bilinmeyen rota, turist yok denecek kadar az
2: Az bilinen rota veya sezon dışı
3: Orta düzey
4: Popüler rota, sezon içinde kalabalık
5: Çok popüler ana turistik akslar, sıra beklenen noktalar

KALİBRASYON ÖRNEKLERİ:
{$anchors}

Çıktı şeması:
{"rubric_version":"v1","scores":{"<boyut>":{"value":<1-5|null>,"confidence":"high|medium|low|none","evidence":"<alıntı|null>"}}}
PROMPT;
    }
}
