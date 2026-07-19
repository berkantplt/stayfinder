<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Models\Tour;
use App\Models\TourClick;

class DashboardController extends Controller
{
    public function applicationStatus()
    {
        $agency = auth()->user()->agency;

        if ($agency?->isApproved()) {
            return redirect()->route('agency.dashboard');
        }

        return view('agency.application-status', compact('agency'));
    }

    public function index()
    {
        $agency = auth()->user()->agency;
        $agency->load('activeTours');

        // Yaşam-boyu toplamlar sayaçtan (ham tour_clicks 180 gün retention'la budanıyor);
        // pencereli istatistikler (gün/hafta/ay) ham tablodan — retention'ın çok altında.
        $totalClicks = (int) Tour::where('agency_id', $agency->id)->sum('clicks_count');
        $todayClicks = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->startOfDay())->count();
        $weekClicks = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->subDays(7))->count();
        $monthClicks = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->subDays(30))->count();

        // Per-tour yaşam-boyu tıklama (sayaçtan)
        $tourClicks = Tour::where('agency_id', $agency->id)
            ->pluck('clicks_count', 'id');

        // Daily clicks for last 30 days (chart data)
        $dailyClicks = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->subDays(30))
            ->selectRaw('DATE(clicked_at) as date, COUNT(*) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('total', 'date');

        // Fill missing dates with 0
        $chartLabels = [];
        $chartData = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $chartLabels[] = now()->subDays($i)->format('d M');
            $chartData[] = $dailyClicks[$d] ?? 0;
        }

        // Hourly distribution
        $hourlyClicks = TourClick::where('agency_id', $agency->id)
            ->where('clicked_at', '>=', now()->subDays(30))
            ->selectRaw('HOUR(clicked_at) as hour, COUNT(*) as total')
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('total', 'hour');

        $hourLabels = [];
        $hourData = [];
        for ($h = 0; $h < 24; $h++) {
            $hourLabels[] = str_pad($h, 2, '0', STR_PAD_LEFT).':00';
            $hourData[] = $hourlyClicks[$h] ?? 0;
        }

        [$unmetDemand, $missedMatches] = $this->buildDemandRadar($agency->id);

        // AI sohbetinden düşen sıcak lead'ler (geri arama / opsiyon / fiyat alarmı)
        $aiLeads = \App\Models\AiLead::with('tour')
            ->where('agency_id', $agency->id)
            ->latest()
            ->take(10)
            ->get();

        return view('agency.dashboard', compact(
            'agency', 'totalClicks', 'todayClicks', 'weekClicks', 'monthClicks',
            'tourClicks', 'chartLabels', 'chartData', 'hourLabels', 'hourData',
            'unmetDemand', 'missedMatches', 'aiLeads'
        ));
    }

    /**
     * Talep Radarı: AI aramanın gördüğü ama karşılayamadığı talep + acentanın
     * turlarının eşiğin altında kaldığı aramalar. Arz tarafı, gerçek kullanıcı
     * talebiyle beslenir ("Bu ay 9 kişi Eylül'de vizesiz Balkan aradı").
     *
     * @return array{0: array, 1: array}
     */
    private function buildDemandRadar(int $agencyId): array
    {
        $monthNames = [1 => 'Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'];
        $logs = \App\Models\AiSearchLog::where('created_at', '>=', now()->subDays(30))
            ->get(['intent', 'applied_filters', 'result_tour_ids', 'near_misses']);

        // 1) Karşılanamayan talep: 0 sonuç veya gevşetilmiş aramaların niyet kümeleri
        $unmetGroups = [];
        foreach ($logs as $log) {
            $relaxed = ! empty(($log->applied_filters ?? [])['relaxation'] ?? null);
            if (! $relaxed && ! empty($log->result_tour_ids)) {
                continue;
            }
            $intent = (array) ($log->intent ?? []);
            $key = implode(' · ', array_filter([
                $intent['preferred_destination'] ?? null,
                ! empty($intent['preferred_month']) ? ($monthNames[(int) $intent['preferred_month']] ?? null) : null,
                ($intent['is_international'] ?? null) === true ? 'yurt dışı' : (($intent['is_international'] ?? null) === false ? 'yurt içi' : null),
                ! empty($intent['max_budget']) ? '≤'.number_format((int) $intent['max_budget'], 0, ',', '.').' TL' : null,
            ]));
            if ($key === '') {
                continue;
            }
            $unmetGroups[$key] = ($unmetGroups[$key] ?? 0) + 1;
        }
        arsort($unmetGroups);
        // İstatistiksel dürüstlük: n≥3 kümeler gösterilir
        $unmetDemand = collect($unmetGroups)->filter(fn ($n) => $n >= 3)->take(5)
            ->map(fn ($n, $k) => ['criteria' => $k, 'count' => $n])->values()->all();

        // 2) "Turun neden kaçırdı": acentanın eşik altında kalan turları
        $missed = [];
        foreach ($logs as $log) {
            foreach ((array) ($log->near_misses ?? []) as $miss) {
                if ((int) ($miss['agency_id'] ?? 0) !== $agencyId) {
                    continue;
                }
                $k = $miss['tour_id'].'|'.($miss['weakest'] ?? '');
                $missed[$k] = ($missed[$k] ?? 0) + 1;
            }
        }
        arsort($missed);
        $tourTitles = Tour::whereIn('id', collect(array_keys($missed))->map(fn ($k) => (int) explode('|', $k)[0])->unique())
            ->pluck('title', 'id');
        $missedMatches = collect($missed)->take(5)->map(function ($count, $key) use ($tourTitles) {
            [$tourId, $weakest] = explode('|', $key);

            return [
                'tour_title' => $tourTitles[(int) $tourId] ?? 'Tur',
                'weakest' => $weakest,
                'count' => $count,
            ];
        })->values()->all();

        return [$unmetDemand, $missedMatches];
    }
}
