<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Models\AiSearchLog;
use App\Models\Category;
use App\Models\CategoryRequest;
use App\Models\Coupon;
use App\Models\CouponUsage;
use App\Models\DiscoveryGuide;
use App\Models\Tour;
use App\Models\TourClick;
use App\Models\TourRubricScore;
use App\Models\TourView;
use App\Models\User;
use App\Notifications\CategorySubscriptionRenewalFailedNotification;
use App\Services\Matching\Rubric;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * B13 — Raporlar. Eskiden 5 sayaç + yaşam boyu "en çok görüntülenen 10" vardı;
 * platformun para kazandığı abonelik hattı (MRR, tahsilat, iptal/dolan, başarısız
 * çekim, acenta/kategori başına gelir) hiçbir yerde toplanmıyordu ve KYM Genel
 * Bakış ile Siparişler ekranı farklı ciro gösteriyordu.
 *
 * Kurallar:
 *  - Para YALNIZ status=PAID siparişlerden, ödemenin gerçekleştiği an (paid_at) ile.
 *    purchased_at ve pending/failed/cancelled asla ciroya girmez.
 *  - Dönem takvim bazlı (bu ay / geçen ay); "son 90 gün" kayan pencere.
 *  - Ham trafik tabloları 180 günde budanır (PruneAnalytics); daha eski dönem
 *    seçilince uyarı gösterilir, yaşam boyu sayaçlar ayrı satırdır.
 *  - Sonuç 5 dk önbellekte: ekran anlık ağır hesap yapmaz.
 */
class ReportController extends Controller
{
    private const DONEMLER = [
        'bu-ay' => 'Bu ay',
        'gecen-ay' => 'Geçen ay',
        'son-90' => 'Son 90 gün',
        'ozel' => 'Özel aralık',
    ];

    /** PruneAnalytics ham tour_views/tour_clicks tablolarını bu kadar gün sonra siler. */
    private const RETENTION_DAYS = 180;

    /** PruneAnalytics ai_search_logs tablosunu bu kadar gün sonra siler. */
    private const AI_RETENTION_DAYS = 90;

    public function index(Request $request)
    {
        [$donem, $start, $end] = $this->resolvePeriod($request);

        $cacheKey = 'admin:rapor:'.$donem.':'.$start->toDateString().':'.$end->toDateString();
        $rapor = cache()->remember($cacheKey, 300, fn () => $this->build($start, $end));

        return view('admin.reports.index', [
            'donem' => $donem,
            'donemler' => self::DONEMLER,
            'start' => $start,
            'end' => $end,
            'rapor' => $rapor,
        ]);
    }

    /**
     * CSV dışa aktarma (muhasebe / Excel). Noktalı virgül ayraç + UTF-8 BOM:
     * Türkçe Excel doğrudan açar. Aynı dönem çözümlemesi, aynı PAID kuralı.
     */
    public function export(Request $request): StreamedResponse
    {
        [$donem, $start, $end] = $this->resolvePeriod($request);
        $tablo = (string) $request->input('tablo', 'siparisler');
        $tablolar = ['siparisler', 'acenta-gelir', 'kategori-gelir'];
        if (! in_array($tablo, $tablolar, true)) {
            $tablo = 'siparisler';
        }

        $satirlar = match ($tablo) {
            'acenta-gelir' => $this->acentaGelirSatirlari($start, $end),
            'kategori-gelir' => $this->kategoriGelirSatirlari($start, $end),
            default => $this->siparisSatirlari($start, $end),
        };

        $dosya = sprintf('turxtur-%s-%s-%s.csv', $tablo, $start->format('Ymd'), $end->format('Ymd'));

        return response()->streamDownload(function () use ($satirlar) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            foreach ($satirlar as $satir) {
                fputcsv($out, array_map([self::class, 'csvGuvenli'], $satir), ';');
            }
            fclose($out);
        }, $dosya, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Excel/Sheets formül enjeksiyonu: = + - @ ile başlayan hücre (acenta/kategori adı
     * kullanıcı girdisidir) açılışta formül olarak çalışır. Başına tek tırnak konur.
     */
    private static function csvGuvenli(mixed $deger): mixed
    {
        if (! is_string($deger) || $deger === '') {
            return $deger;
        }

        return preg_match('/^[=+\-@\t\r]/', $deger) ? "'".$deger : $deger;
    }

    /**
     * @return array{0: string, 1: CarbonImmutable, 2: CarbonImmutable}
     */
    private function resolvePeriod(Request $request): array
    {
        $donem = (string) $request->input('donem', 'bu-ay');
        if (! isset(self::DONEMLER[$donem])) {
            $donem = 'bu-ay';
        }

        $now = CarbonImmutable::now();

        if ($donem === 'ozel') {
            $validated = $request->validate([
                'from' => 'required|date|before_or_equal:today',
                'to' => 'required|date|after_or_equal:from',
            ], [
                'from.required' => 'Özel aralık için başlangıç tarihi girin.',
                'from.before_or_equal' => 'Başlangıç tarihi bugünden ileri olamaz.',
                'to.after_or_equal' => 'Bitiş tarihi başlangıçtan önce olamaz.',
            ]);

            $start = CarbonImmutable::parse($validated['from'])->startOfDay();
            // Gelecek bitiş anlamsız; bugüne kırp (from zaten bugünü aşamaz → start <= end)
            $end = min(CarbonImmutable::parse($validated['to'])->endOfDay(), $now->endOfDay());

            return [$donem, $start, $end];
        }

        return match ($donem) {
            'gecen-ay' => [$donem, $now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'son-90' => [$donem, $now->subDays(89)->startOfDay(), $now->endOfDay()],
            default => ['bu-ay', $now->startOfMonth(), $now->endOfDay()],
        };
    }

    private function build(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return [
            'gelir' => $this->gelir($start, $end),
            'trafik' => $this->trafik($start, $end),
            'urun' => $this->urun($start, $end),
            'operasyon' => $this->operasyon($start, $end),
        ];
    }

    // ───────────────────────── Gelir ─────────────────────────

    private function gelir(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $paid = AgencyCategoryOrder::query()
            ->where('status', AgencyCategoryOrder::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end]);

        $tahsilat = (clone $paid)->selectRaw('COUNT(*) as adet, COALESCE(SUM(subtotal), 0) as tutar')->first();
        $otomatik = (clone $paid)->where('auto_renewal', true)->count();
        $manuelSiparis = (clone $paid)->where('payment_provider', AgencyCategoryOrder::PROVIDER_MANUAL)->count();

        // Admin'in bedava verdiği (manuel, 0 TL sipariş) abonelikler monthly_price'ı
        // tarife fiyatıyla taşır ama gelir değildir — MRR ve eğriden hariç, ayrı sayaç.
        $ucretli = fn ($q) => $q->whereDoesntHave('lastOrder', fn ($o) => $o->where('payment_provider', AgencyCategoryOrder::PROVIDER_MANUAL));

        // MRR: bugün aktif, ücretli aboneliklerin aylık ücret toplamı
        $mrr = (float) $ucretli(AgencyCategorySubscription::active())->sum('monthly_price');
        $manuelAbonelik = AgencyCategorySubscription::active()
            ->whereHas('lastOrder', fn ($o) => $o->where('payment_provider', AgencyCategoryOrder::PROVIDER_MANUAL))->count();

        // 12 aylık MRR eğrisi. Abonelik satırı geçmiş tutmaz (yenileme expires_at'i
        // uzatır); "o ay sonunda aktif miydi" = started_at <= gün <= expires_at.
        // Kesintili yeniden başlatmalarda started_at sıfırlandığı için kesinti
        // öncesi dönem sayılmaz; admin iptali (status=cancelled) bugünkü durumuyla
        // tüm aylardan düşer — yaklaşık değerdir, ekranda böyle etiketlenir.
        $mrrEgri = [];
        $bugun = CarbonImmutable::today();
        for ($i = 11; $i >= 0; $i--) {
            $ay = $bugun->subMonthsNoOverflow($i);
            $gun = $i === 0 ? $bugun : $ay->endOfMonth();
            $mrrEgri[] = [
                'etiket' => $ay->format('m/Y'),
                'tutar' => round((float) $ucretli(AgencyCategorySubscription::query())
                    ->where('status', '!=', AgencyCategorySubscription::STATUS_CANCELLED)
                    ->whereDate('started_at', '<=', $gun->toDateString())
                    ->whereDate('expires_at', '>=', $gun->toDateString())
                    ->sum('monthly_price'), 2),
            ];
        }

        $hareket = [
            'yeni' => AgencyCategorySubscription::whereBetween('started_at', [$start->toDateString(), $end->toDateString()])->count(),
            'iptal' => AgencyCategorySubscription::whereBetween('cancelled_at', [$start, $end])->count(),
            'dolan' => AgencyCategorySubscription::where('status', AgencyCategorySubscription::STATUS_EXPIRED)
                ->whereBetween('expires_at', [$start->toDateString(), $end->toDateString()])->count(),
        ];

        // Başarısız otomatik çekim: komut her denemede acenta kullanıcılarına bildirim
        // yazar (kalıcı tek iz). Aynı abonelik + aynı gün tek sayılır.
        $basarisizHam = DB::table('notifications')
            ->where('type', CategorySubscriptionRenewalFailedNotification::class)
            ->whereBetween('created_at', [$start, $end])
            ->orderByDesc('created_at')
            ->get(['data', 'created_at']);

        $basarisiz = [];
        foreach ($basarisizHam as $row) {
            $veri = json_decode((string) $row->data, true) ?: [];
            $subId = (int) ($veri['subscription_id'] ?? 0);
            $gunAnahtari = $subId.'|'.substr((string) $row->created_at, 0, 10);
            if ($subId === 0 || isset($basarisiz[$gunAnahtari])) {
                continue;
            }
            $basarisiz[$gunAnahtari] = ['subscription_id' => $subId, 'zaman' => $row->created_at, 'mesaj' => (string) ($veri['message'] ?? '')];
        }
        $basarisiz = array_values($basarisiz);
        $subIds = array_values(array_unique(array_column($basarisiz, 'subscription_id')));
        $subMap = $subIds === [] ? collect() : AgencyCategorySubscription::query()
            ->with(['agency' => fn ($q) => $q->withTrashed(), 'category'])
            ->whereIn('id', $subIds)->get()->keyBy('id');
        foreach ($basarisiz as &$b) {
            $sub = $subMap->get($b['subscription_id']);
            $b['acenta'] = $sub?->agency?->name ?? '—';
            $b['kategori'] = $sub?->category?->name ?? '—';
            $b['tutar'] = (float) ($sub?->monthly_price ?? 0);
            $b['bitis'] = $sub?->expires_at?->format('d.m.Y');
        }
        unset($b);

        return [
            'mrr' => round($mrr, 2),
            'mrrEgri' => $mrrEgri,
            'tahsilat' => ['adet' => (int) $tahsilat->adet, 'tutar' => round((float) $tahsilat->tutar, 2)],
            'otomatikYenileme' => $otomatik,
            'manuelSiparis' => $manuelSiparis,
            'hareket' => $hareket,
            'aktifAbonelik' => AgencyCategorySubscription::active()->count(),
            'manuelAbonelik' => $manuelAbonelik,
            'legacyAcenta' => Agency::where('legacy_category_access', true)->count(),
            'basarisizCekim' => array_slice($basarisiz, 0, 20),
            'basarisizCekimSayisi' => count($basarisiz),
            'kategoriGelir' => $this->kategoriGelir($start, $end),
            'acentaGelir' => $this->acentaGelir($start, $end, 20),
        ];
    }

    private function kategoriGelir(CarbonImmutable $start, CarbonImmutable $end)
    {
        return AgencyCategoryOrderItem::query()
            ->join('agency_category_orders as o', 'o.id', '=', 'agency_category_order_items.order_id')
            ->where('o.status', AgencyCategoryOrder::STATUS_PAID)
            ->whereBetween('o.paid_at', [$start, $end])
            ->selectRaw('agency_category_order_items.category_name as kategori, COUNT(*) as kalem, COALESCE(SUM(agency_category_order_items.unit_price), 0) as tutar')
            ->groupBy('agency_category_order_items.category_name')
            ->orderByDesc('tutar')
            ->get();
    }

    private function acentaGelir(CarbonImmutable $start, CarbonImmutable $end, ?int $limit)
    {
        $q = AgencyCategoryOrder::query()
            ->join('agencies', 'agencies.id', '=', 'agency_category_orders.agency_id')
            ->where('agency_category_orders.status', AgencyCategoryOrder::STATUS_PAID)
            ->whereBetween('agency_category_orders.paid_at', [$start, $end])
            ->selectRaw('agencies.id as agency_id, agencies.name as acenta, agencies.deleted_at as silinme, COUNT(*) as siparis, COALESCE(SUM(agency_category_orders.subtotal), 0) as tutar')
            ->groupBy('agencies.id', 'agencies.name', 'agencies.deleted_at')
            ->orderByDesc('tutar');

        return $limit ? $q->limit($limit)->get() : $q->get();
    }

    // ───────────────────────── Trafik ─────────────────────────

    private function trafik(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $views = TourView::whereBetween('viewed_at', [$start, $end])->count();
        $clicks = TourClick::whereBetween('clicked_at', [$start, $end])->count();

        $topViews = TourView::whereBetween('viewed_at', [$start, $end])
            ->selectRaw('tour_id, COUNT(*) as adet')
            ->groupBy('tour_id')->orderByDesc('adet')->limit(10)->get();
        $ids = $topViews->pluck('tour_id');
        $tours = $ids->isEmpty() ? collect() : Tour::withTrashed()->whereIn('id', $ids)->get(['id', 'title', 'slug', 'destination'])->keyBy('id');
        $clickMap = $ids->isEmpty() ? collect() : TourClick::whereIn('tour_id', $ids)->whereBetween('clicked_at', [$start, $end])
            ->selectRaw('tour_id, COUNT(*) as adet')->groupBy('tour_id')->pluck('adet', 'tour_id');

        $enCok = $topViews->map(fn ($r) => [
            'tour' => $tours->get($r->tour_id),
            'views' => (int) $r->adet,
            'clicks' => (int) ($clickMap[$r->tour_id] ?? 0),
        ])->filter(fn ($r) => $r['tour'] !== null)->values();

        $destinasyon = TourView::query()
            ->join('tours', 'tours.id', '=', 'tour_views.tour_id')
            ->whereBetween('tour_views.viewed_at', [$start, $end])
            ->selectRaw('tours.destination as ad, COUNT(*) as adet')
            ->groupBy('tours.destination')->orderByDesc('adet')->limit(8)->get();

        $kategori = TourView::query()
            ->join('tours', 'tours.id', '=', 'tour_views.tour_id')
            ->join('categories', 'categories.id', '=', 'tours.category_id')
            ->whereBetween('tour_views.viewed_at', [$start, $end])
            ->selectRaw('categories.name as ad, COUNT(*) as adet')
            ->groupBy('categories.name')->orderByDesc('adet')->limit(8)->get();

        return [
            'views' => $views,
            'clicks' => $clicks,
            'donusum' => $views > 0 ? round($clicks / $views * 100, 1) : null,
            'retentionUyari' => $start->lt(CarbonImmutable::now()->subDays(self::RETENTION_DAYS)),
            'enCok' => $enCok,
            'destinasyon' => $destinasyon,
            'kategori' => $kategori,
            'yasamBoyu' => [
                'views' => (int) Tour::withTrashed()->sum('views_count'),
                'clicks' => (int) Tour::withTrashed()->sum('clicks_count'),
            ],
        ];
    }

    // ───────────────────────── Ürün / AI ─────────────────────────

    private function urun(CarbonImmutable $start, CarbonImmutable $end): array
    {
        $aiToplam = 0;
        $aiSonucsuz = 0;
        $sonucsuzSorgular = [];
        // JSON kolon: sqlite/mysql farkı olmasın diye satır satır (cursor, belleğe yığmadan)
        foreach (AiSearchLog::whereBetween('created_at', [$start, $end])->select(['normalized_query', 'raw_query', 'result_tour_ids'])->cursor() as $log) {
            $aiToplam++;
            if (empty($log->result_tour_ids)) {
                $aiSonucsuz++;
                $k = mb_strtolower(trim((string) ($log->normalized_query ?: $log->raw_query)));
                if ($k !== '') {
                    $sonucsuzSorgular[$k] = ($sonucsuzSorgular[$k] ?? 0) + 1;
                }
            }
        }
        arsort($sonucsuzSorgular);

        $kesif = DiscoveryGuide::whereBetween('created_at', [$start, $end])
            ->selectRaw('status, COUNT(*) as adet')->groupBy('status')->pluck('adet', 'status');

        $kayitIds = User::where('role', User::ROLE_VISITOR)->whereBetween('created_at', [$start, $end])->pluck('created_at', 'id');
        $ilkFavori = $kayitIds->isEmpty() ? collect() : DB::table('favorites')
            ->whereIn('user_id', $kayitIds->keys())
            ->selectRaw('user_id, MIN(created_at) as ilk')->groupBy('user_id')->pluck('ilk', 'user_id');
        $yediGunAktif = 0;
        foreach ($kayitIds as $userId => $kayitZamani) {
            $ilk = $ilkFavori[$userId] ?? null;
            if ($ilk !== null && CarbonImmutable::parse($ilk)->lte(CarbonImmutable::parse($kayitZamani)->addDays(7))) {
                $yediGunAktif++;
            }
        }

        return [
            'aiRetentionUyari' => $start->lt(CarbonImmutable::now()->subDays(self::AI_RETENTION_DAYS)),
            'ai' => [
                'toplam' => $aiToplam,
                'sonucsuz' => $aiSonucsuz,
                'sonucsuzOran' => $aiToplam > 0 ? round($aiSonucsuz / $aiToplam * 100, 1) : null,
                'sonucsuzSorgular' => array_slice($sonucsuzSorgular, 0, 10, true),
            ],
            'kesif' => [
                'toplam' => (int) $kesif->sum(),
                'tamamlanan' => (int) ($kesif[DiscoveryGuide::STATUS_COMPLETED] ?? 0),
                'basarisiz' => (int) ($kesif[DiscoveryGuide::STATUS_FAILED] ?? 0),
            ],
            'kupon' => [
                'tanimlanan' => Coupon::whereBetween('created_at', [$start, $end])->count(),
                'alinan' => CouponUsage::whereBetween('used_at', [$start, $end])->count(),
                'aktif' => Coupon::where('is_active', true)->count(),
                'tukenen' => Coupon::whereNotNull('max_uses')->whereColumn('used_count', '>=', 'max_uses')->count(),
            ],
            'kullanici' => [
                'kayit' => $kayitIds->count(),
                'yediGunFavori' => $yediGunAktif,
                'yediGunOran' => $kayitIds->count() > 0 ? round($yediGunAktif / $kayitIds->count() * 100, 1) : null,
            ],
        ];
    }

    // ───────────────────────── Operasyon ─────────────────────────

    private function operasyon(CarbonImmutable $start, CarbonImmutable $end): array
    {
        // Admin'in "Acenta Ekle" ile doğrudan oluşturduğu kayıtlar onaylı doğar
        // (approved_at ≈ created_at); başvuru süresi hesabına girmesin (<60 sn).
        $onaylanan = Agency::where('approval_status', Agency::STATUS_APPROVED)
            ->whereBetween('approved_at', [$start, $end])
            ->get(['created_at', 'approved_at'])
            ->filter(fn ($a) => $a->created_at && $a->approved_at && $a->created_at->diffInSeconds($a->approved_at) >= 60)
            ->values();
        $ortSaat = $onaylanan->isEmpty() ? null : round($onaylanan->avg(fn ($a) => $a->created_at->diffInHours($a->approved_at)), 1);

        $puanliTurIds = TourRubricScore::where('rubric_version', Rubric::VERSION)->select('tour_id');

        $enEskiIs = DB::table('jobs')->orderBy('available_at')->value('available_at');

        return [
            'bekleyenBasvuru' => Agency::pendingApproval()->count(),
            'donemdeOnaylanan' => $onaylanan->count(),
            'ortalamaOnaySaati' => $ortSaat,
            'bekleyenTalep' => CategoryRequest::pending()->count(),
            'donemdeKarar' => CategoryRequest::whereBetween('reviewed_at', [$start, $end])->count(),
            'aktifTur' => Tour::active()->count(),
            'puansizTur' => Tour::active()->whereNotIn('tours.id', $puanliTurIds)->count(),
            'quizAcik' => (bool) config('ai.quiz_enabled'),
            'kuyruk' => [
                'bekleyen' => DB::table('jobs')->count(),
                'enEskiDakika' => $enEskiIs ? max(0, (int) round((time() - (int) $enEskiIs) / 60)) : null,
                'basarisiz' => DB::table('failed_jobs')->count(),
                'sonBasarisiz' => DB::table('failed_jobs')->max('failed_at'),
            ],
        ];
    }

    // ───────────────────────── CSV satırları ─────────────────────────

    private function siparisSatirlari(CarbonImmutable $start, CarbonImmutable $end): iterable
    {
        yield ['Sipariş No', 'Acenta', 'Ödeme Tarihi', 'Tutar', 'Para Birimi', 'Ödeme Yöntemi', 'Otomatik Yenileme', 'Kalemler'];
        $q = AgencyCategoryOrder::with(['agency' => fn ($a) => $a->withTrashed(), 'items'])
            ->where('status', AgencyCategoryOrder::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->orderBy('paid_at');
        foreach ($q->cursor() as $o) {
            yield [
                $o->order_number,
                $o->agency?->name ?? '',
                $o->paid_at?->format('d.m.Y H:i'),
                number_format((float) $o->subtotal, 2, ',', ''),
                $o->currency,
                $o->payment_provider,
                $o->auto_renewal ? 'evet' : 'hayır',
                $o->items->map(fn ($i) => $i->category_name.($i->isExtraSlot() ? ' (ekstra hak)' : ''))->implode(' | '),
            ];
        }
    }

    private function acentaGelirSatirlari(CarbonImmutable $start, CarbonImmutable $end): iterable
    {
        yield ['Acenta', 'Ödenmiş Sipariş', 'Tutar (TL)'];
        foreach ($this->acentaGelir($start, $end, null) as $r) {
            yield [$r->acenta, (int) $r->siparis, number_format((float) $r->tutar, 2, ',', '')];
        }
    }

    private function kategoriGelirSatirlari(CarbonImmutable $start, CarbonImmutable $end): iterable
    {
        yield ['Kategori', 'Kalem', 'Tutar (TL)'];
        foreach ($this->kategoriGelir($start, $end) as $r) {
            yield [$r->kategori, (int) $r->kalem, number_format((float) $r->tutar, 2, ',', '')];
        }
    }
}
