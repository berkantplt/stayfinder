<?php

namespace App\Http\Controllers;

use App\Models\DiscoveryGuide;
use App\Services\Discovery\DiscoveryGuideService;
use App\Services\Discovery\RelatedTourService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * AI Keşif Rehberi: /kesif-rehberi. Kullanıcı şehir + gün girer, kuyruktaki
 * job rehberi üretir, sonuç sayfası tamamlanana kadar status ucunu poll eder.
 * Misafir de oluşturabilir (session sahipliği); rehber yalnız sahibine açılır.
 */
class DiscoveryGuideController extends Controller
{
    public function __construct(
        private readonly DiscoveryGuideService $guides,
        private readonly RelatedTourService $relatedTours,
    ) {}

    public function index()
    {
        return view('discovery.index');
    }

    /**
     * store + personalize ortak tercih kuralları (TEK KAYNAK). Bilerek
     * FormRequest değil: bu uçlarda sahiplik kontrolü (403) validasyondan
     * ÖNCE koşmalı — FormRequest enjeksiyonu sırayı tersine çevirirdi.
     *
     * @return array<string, mixed>
     */
    private static function preferenceRules(): array
    {
        return [
            'traveler_type' => ['nullable', 'string', Rule::in(array_keys(DiscoveryGuide::TRAVELER_TYPES))],
            'interests' => 'nullable|array|max:7',
            'interests.*' => ['string', Rule::in(array_keys(DiscoveryGuide::INTERESTS))],
            'pace' => ['nullable', Rule::in(array_keys(DiscoveryGuide::PACES))],
            'budget' => ['nullable', Rule::in(array_keys(DiscoveryGuide::BUDGETS))],
        ];
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'destination' => 'required|string|min:2|max:100',
            'duration_days' => 'required|integer|min:1|max:7',
            ...self::preferenceRules(),
        ]);

        $guide = $this->guides->create($request, $validated);

        return response()->json([
            'uuid' => $guide->uuid,
            'status' => $guide->status,
            'redirect_url' => route('discovery.show', $guide),
        ], 201);
    }

    public function show(Request $request, DiscoveryGuide $guide)
    {
        abort_unless($guide->canBeAccessedBy($request), 403);

        $guide->failIfStuck();

        // İlgili turlar SADECE turXtur veritabanından — AI tur önermez.
        $relatedTours = $guide->isCompleted()
            ? $this->relatedTours->forGuide($guide)
            : collect();

        return view('discovery.show', [
            'guide' => $guide,
            'relatedTours' => $relatedTours,
        ]);
    }

    /** Polling ucu: yalnız durum döner; içerik sayfa yenilenince sunucudan basılır. */
    public function status(Request $request, DiscoveryGuide $guide): JsonResponse
    {
        abort_unless($guide->canBeAccessedBy($request), 403);

        $guide->failIfStuck();

        return response()->json([
            'status' => $guide->status,
            'error_message' => $guide->isFailed() ? $guide->error_message : null,
        ]);
    }

    public function personalize(Request $request, DiscoveryGuide $guide): JsonResponse
    {
        abort_unless($guide->canBeAccessedBy($request), 403);

        $validated = $request->validate(self::preferenceRules());

        $this->guides->personalize($guide, $validated);

        return response()->json([
            'uuid' => $guide->uuid,
            'status' => DiscoveryGuide::STATUS_PENDING,
        ]);
    }
}
