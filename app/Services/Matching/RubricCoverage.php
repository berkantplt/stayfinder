<?php

namespace App\Services\Matching;

use App\Jobs\ScoreTourRubricJob;
use App\Models\Tour;
use App\Models\TourRubricScore;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Rubrik puanlama kapsamı: hangi tur puanlı, hangisi puansız / bayat / incelemede.
 *
 * Tek kaynak: admin "Tur Puanlama" sayfası, admin tur listesindeki Puan sütunu
 * ve app:score-tours-rubric komutu aynı hesabı buradan okur. Komut ile sayfa
 * ayrışmasın ("komut 13 diyor, sayfa 9 diyor" tartışması olmasın).
 *
 * Canlı şikayet (2026-10-08): sohbet "Kapadokya turlarını göster" isteğine 14
 * turdan 1'ini gösterdi — kalan 13'ün rubrik puanı yoktu ve bunu gören hiçbir
 * ekran yoktu. Puanlama tur eklenince/güncellenince gözlemciden otomatik
 * kuyruğa girer; bu sınıf "girdi mi, bitti mi, takıldı mı" sorusunu cevaplar.
 *
 * Durum anlık türetilir, kolon YOK:
 *  - puan satırı yoksa PUANSIZ,
 *  - varsa ama input_hash bugünkü program metniyle tutmuyorsa BAYAT (içerik
 *    değişmiş; eski puan sohbette hâlâ kullanılır, job yeniden puanlar),
 *  - iki geçiş uyuşmamışsa INCELEMEDE (editör onayına kadar kart olmaz),
 *  - aksi halde PUANLI.
 *
 * Kuyruk durumu jobs / failed_jobs tablolarından okunur (database sürücüsü).
 * Başka sürücüde tablolar boş kalır; sayfa kuyruk bilgisi olmadan çalışır.
 */
class RubricCoverage
{
    public const PUANSIZ = 'puansiz';

    public const BAYAT = 'bayat';

    public const INCELEMEDE = 'incelemede';

    public const PUANLI = 'puanli';

    public const DURUMLAR = [self::PUANSIZ, self::BAYAT, self::INCELEMEDE, self::PUANLI];

    /** Rozet metni + panel etiket sınıfı (admin tur listesi ve puanlama sayfası aynı görünür). */
    public const ETIKET = [
        self::PUANLI => ['Puanlı', 'p-etiket-basari'],
        self::BAYAT => ['Bayat', 'p-etiket-notr'],
        self::INCELEMEDE => ['İncelemede', 'p-etiket-uyari'],
        self::PUANSIZ => ['Puansız', 'p-etiket-tehlike'],
    ];

    /** Puanlama girdisine giren alanlar — ScoreTourRubricJob::sanitizedInput ile aynı. */
    private const GIRDI_ALANLARI = ['duration_days', 'destination', 'hotel_info', 'included', 'extras', 'itinerary'];

    /**
     * Tüm (varsayılan: aktif) turların durum raporu.
     *
     * @return array{ozet: array<string, int>, satirlar: Collection<int, array<string, mixed>>}
     */
    public function rapor(bool $pasifDahil = false): array
    {
        $today = now()->toDateString();

        $tours = Tour::query()
            ->select(array_merge(
                ['id', 'slug', 'agency_id', 'title', 'is_active', 'departure_date', 'created_at', 'updated_at'],
                self::GIRDI_ALANLARI,
            ))
            ->with('agency:id,name,is_active')
            ->withExists(['dates as gelecek_tarih_var' => fn ($q) => $q->whereDate('departure_date', '>=', $today)])
            ->when(! $pasifDahil, fn ($q) => $q->where('is_active', true))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $scores = TourRubricScore::where('rubric_version', Rubric::VERSION)
            ->whereIn('tour_id', $tours->pluck('id'))
            ->get()
            ->keyBy('tour_id');

        $kuyruk = $this->kuyrukDurumlari();

        $satirlar = $tours->map(function (Tour $tour) use ($scores, $kuyruk) {
            $score = $scores->get($tour->id);
            $durum = self::durum($tour, $score);
            // Puanı tam olan turda eski bir "başarısız" kaydı artık bilgi değil gürültü
            $kuyrukDurumu = $durum === self::PUANLI ? null : ($kuyruk[$tour->id] ?? null);

            return [
                'tour' => $tour,
                'score' => $score,
                'durum' => $durum,
                'eksikler' => self::eksikler($tour),
                'gelecek_kalkis' => (bool) $tour->gelecek_tarih_var
                    || ($tour->departure_date !== null && $tour->departure_date->gte(today())),
                'kuyruk' => $kuyrukDurumu['durum'] ?? null,
                'hata' => $kuyrukDurumu['hata'] ?? null,
            ];
        })->values();

        $sayac = fn (string $durum): int => $satirlar->where('durum', $durum)->count();

        $ozet = [
            'tur' => $satirlar->count(),
            'puanli' => $sayac(self::PUANLI),
            'bayat' => $sayac(self::BAYAT),
            'incelemede' => $sayac(self::INCELEMEDE),
            'puansiz' => $sayac(self::PUANSIZ),
            // TourMatcher ile aynı tanım: puanı var VE editör onayı beklemiyor
            // (bayat olsa da eski puan kullanılır)
            'yayinlanabilir' => $satirlar->filter(
                fn ($r) => $r['score'] !== null && $r['score']->review_status !== TourRubricScore::STATUS_NEEDS_REVIEW
            )->count(),
            'kuyrukta' => $satirlar->where('kuyruk', 'kuyrukta')->count(),
            'basarisiz' => $satirlar->where('kuyruk', 'basarisiz')->count(),
            'eksik_veri' => $satirlar->filter(
                fn ($r) => $r['score'] !== null && $r['score']->nullDimensions() !== []
            )->count(),
        ];

        return ['ozet' => $ozet, 'satirlar' => $satirlar];
    }

    /**
     * Verilen turların durumları (admin tur listesi: yalnız sayfadaki turlar için).
     *
     * @param  iterable<int, Tour>  $tours
     * @return array<int, string> tour_id → durum
     */
    public function durumlar(iterable $tours): array
    {
        $tours = Collection::make($tours);
        if ($tours->isEmpty()) {
            return [];
        }

        $scores = TourRubricScore::where('rubric_version', Rubric::VERSION)
            ->whereIn('tour_id', $tours->pluck('id'))
            ->get()
            ->keyBy('tour_id');

        return $tours->mapWithKeys(fn (Tour $t) => [$t->id => self::durum($t, $scores->get($t->id))])->all();
    }

    /**
     * Turları puanlama job'ına verir. Gözlemciyle aynı 10 dakikalık dispatch
     * kilidi: ikinci tıklama ya da komut aynı turu iki kez kuyruğa koymaz.
     *
     * @param  iterable<int, int>  $ids
     * @return int kuyruğa alınan tur sayısı
     */
    public function kuyrugaAl(iterable $ids, bool $force = false): int
    {
        $alinan = 0;
        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id <= 0 || ! Cache::add(ScoreTourRubricJob::DISPATCH_LOCK_PREFIX.$id, 1, 600)) {
                continue;
            }
            ScoreTourRubricJob::dispatch($id, $force)->onQueue('default');
            $alinan++;
        }

        return $alinan;
    }

    public static function durum(Tour $tour, ?TourRubricScore $score): string
    {
        if ($score === null) {
            return self::PUANSIZ;
        }
        // Bayatlık önce: içerik değiştiyse eski karar (incelemede/onaylı) de eski
        // içeriğe aittir, komut eskiden beri bu turları yeniden puanlatır.
        if ($score->input_hash !== self::girdiHash($tour)) {
            return self::BAYAT;
        }

        return $score->review_status === TourRubricScore::STATUS_NEEDS_REVIEW ? self::INCELEMEDE : self::PUANLI;
    }

    /** Job'ın yazdığı input_hash ile birebir aynı formül. */
    public static function girdiHash(Tour $tour): string
    {
        return hash('sha256', Rubric::VERSION.'|'.ScoreTourRubricJob::sanitizedInput($tour));
    }

    /**
     * Puanlama girdisinde boş kalan içerik parçaları. Boşsa LLM kanıt bulamaz,
     * boyut null kalır; turu puanlatmadan ÖNCE neyin eksik olduğunu gösterir.
     *
     * @return string[]
     */
    public static function eksikler(Tour $tour): array
    {
        $eksik = [];
        if (! is_array($tour->itinerary) || $tour->itinerary === []) {
            $eksik[] = 'program';
        }
        if (trim(strip_tags((string) $tour->included)) === '') {
            $eksik[] = 'dahil hizmetler';
        }
        if (trim(strip_tags((string) $tour->hotel_info)) === '') {
            $eksik[] = 'otel bilgisi';
        }

        return $eksik;
    }

    /**
     * jobs → "kuyrukta", failed_jobs → "başarısız" (+ hata ilk satırı).
     * Aynı tur hem başarısız hem yeniden kuyruktaysa kuyruk kazanır.
     *
     * @return array<int, array{durum: string, hata: ?string}>
     */
    private function kuyrukDurumlari(): array
    {
        $durum = [];
        try {
            $basarisizlar = DB::table('failed_jobs')
                ->select(['payload', 'exception'])
                ->orderByDesc('id')
                ->limit(2000)
                ->get();
            foreach ($basarisizlar as $satir) {
                $id = self::payloadTurId((string) $satir->payload);
                if ($id !== null && ! isset($durum[$id])) {
                    $ilkSatir = explode("\n", (string) $satir->exception, 2)[0];
                    $durum[$id] = ['durum' => 'basarisiz', 'hata' => Str::limit(trim($ilkSatir), 160)];
                }
            }

            $bekleyenler = DB::table('jobs')->select(['payload'])->limit(5000)->get();
            foreach ($bekleyenler as $satir) {
                $id = self::payloadTurId((string) $satir->payload);
                if ($id !== null) {
                    $durum[$id] = ['durum' => 'kuyrukta', 'hata' => null];
                }
            }
        } catch (Throwable) {
            // Tablo yok ya da başka kuyruk sürücüsü — kuyruk bilgisi olmadan devam
        }

        return $durum;
    }

    /** Laravel kuyruk payload'ından (database sürücüsü) puanlama job'ının tur ID'si. */
    private static function payloadTurId(string $payload): ?int
    {
        if (! str_contains($payload, 'ScoreTourRubricJob')) {
            return null;
        }
        $veri = json_decode($payload, true);
        if (! is_array($veri) || ($veri['displayName'] ?? '') !== ScoreTourRubricJob::class) {
            return null;
        }
        $komut = (string) ($veri['data']['command'] ?? '');

        return preg_match('/s:6:"tourId";i:(\d+);/', $komut, $m) ? (int) $m[1] : null;
    }
}
