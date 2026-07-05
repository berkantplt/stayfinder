<?php

namespace App\Http\Controllers\Agency;

use App\Http\Controllers\Controller;
use App\Services\TourImport\TourUrlImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class TourImportController extends Controller
{
    public function __construct(private readonly TourUrlImporter $importer) {}

    /**
     * Acentanın yapıştırdığı URL'den tur bilgilerini çıkarıp JSON döner.
     * Form bu veriyle frontend'de otomatik doldurulur (kayıt yapılmaz).
     */
    public function fromUrl(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => 'required|url|max:2000',
            'deep' => 'sometimes|boolean',
        ]);

        try {
            $data = $this->importer->import($validated['url'], $request->boolean('deep'));

            return response()->json(['ok' => true, 'data' => $data]);
        } catch (\RuntimeException $e) {
            // Beklenen/dostane hatalar (geçersiz URL, SSRF, içerik yok vb.)
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        } catch (Throwable $e) {
            Log::warning('[TourImport] başarısız', ['url' => $validated['url'], 'message' => $e->getMessage()]);

            return response()->json([
                'ok' => false,
                'message' => 'Sayfa okunamadı veya bilgiler çıkarılamadı; lütfen alanları elle doldurun.',
            ], 422);
        }
    }
}
