<?php

namespace App\Observers;

use App\Console\Commands\SyncKnowledgeBase;
use App\Jobs\GenerateDestinationProfileJob;
use App\Jobs\GenerateTourCharacterJob;
use App\Jobs\GenerateTourEmbeddingJob;
use App\Models\Announcement;
use App\Models\DestinationProfile;
use App\Models\Tour;
use App\Notifications\PriceDropNotification;
use App\Services\AiSearch\DestinationKnowledgeService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class TourObserver
{
    /**
     * Yeni tur oluşturulduğunda embedding, destinasyon profili ve knowledge chunk
     * üret.
     */
    public function created(Tour $tour): void
    {
        Log::info("[TourObserver] Yeni tur eklendi: #{$tour->id} ({$tour->title}). Embedding ve destinasyon profili kuyruğa alınıyor...");
        GenerateTourEmbeddingJob::dispatch($tour->id)->onQueue('default');

        $this->dispatchDestinationEnrichmentIfNeeded((string) $tour->destination);
        $this->dispatchTourCharacterEnrichment($tour);
        $this->dispatchRubricScoring($tour);
        $this->flushDestinationInventoryCaches();
        $this->syncKnowledgeChunkFor($tour);
        $this->announceNewTour($tour);
    }

    /**
     * Tur güncellendiğinde, AI aramasını etkileyen bir alan değiştiyse
     * embedding'i yeniden oluştur. Destinasyon değiştiyse yeni şehir için profil tetikle.
     */
    public function updated(Tour $tour): void
    {
        // Sadece embedding'i etkileyen alanlar değiştiyse yeniden üret.
        // itinerary/hotel_info/extras/kalkış-durak şehirleri de search_text'e
        // girer → değişince vektör bayatlamasın. is_active: pasif↔aktif geçişi de
        // arama görünürlüğünü etkilediği için yeniden üretimi tetikler.
        $embeddingFields = [
            'title', 'destination', 'description', 'price', 'currency',
            'duration_days', 'included', 'excluded', 'is_international',
            'requires_visa', 'category_id', 'itinerary', 'hotel_info',
            'extras', 'departure_city', 'stop_cities', 'is_active',
        ];

        $needsRegeneration = false;
        foreach ($embeddingFields as $field) {
            if ($tour->wasChanged($field)) {
                $needsRegeneration = true;
                break;
            }
        }

        if ($needsRegeneration) {
            Log::info("[TourObserver] Tur güncellendi: #{$tour->id} ({$tour->title}). Embedding yeniden oluşturulacak...");
            GenerateTourEmbeddingJob::dispatch($tour->id)->onQueue('default');
        }

        if ($tour->wasChanged('destination')) {
            $this->dispatchDestinationEnrichmentIfNeeded((string) $tour->destination);
        }

        // Tur karakteri: içerik alanları değiştiyse özet bayatlamasın
        $characterFields = ['title', 'destination', 'description', 'itinerary', 'included', 'extras', 'duration_days'];
        foreach ($characterFields as $field) {
            if ($tour->wasChanged($field)) {
                $this->dispatchTourCharacterEnrichment($tour);
                break;
            }
        }

        // Rubrik puanları: eşleştirme girdisi değiştiyse yeniden puanlansın
        // (job içi input_hash kontrolü gereksiz LLM tekrarını zaten eler)
        $rubricFields = ['destination', 'itinerary', 'included', 'extras', 'duration_days', 'hotel_info'];
        foreach ($rubricFields as $field) {
            if ($tour->wasChanged($field)) {
                $this->dispatchRubricScoring($tour);
                break;
            }
        }

        // Envanter cevabı ("nerelere turunuz var") görünürlük/destinasyon
        // değişiminde bayatlamasın
        if ($tour->wasChanged('destination') || $tour->wasChanged('is_active') || $tour->wasChanged('departure_date')) {
            $this->flushDestinationInventoryCaches();
        }

        // Knowledge chunk: içerik alanları VEYA is_active değiştiyse RAG bağlamı
        // güncellensin. is_active kritik: tur pasifleşince syncKnowledgeChunkFor
        // chunk'ı siler (asistan satıştan kalkan turu önermez), aktifleşince
        // yeniden oluşturur.
        $knowledgeFields = ['title', 'destination', 'description', 'price', 'currency', 'duration_days', 'is_active'];
        foreach ($knowledgeFields as $field) {
            if ($tour->wasChanged($field)) {
                $this->syncKnowledgeChunkFor($tour);
                break;
            }
        }

        $this->notifyFavoritersOnPriceDrop($tour);
    }

    /**
     * Yeni tur: kullanıcı başına notifications satırı yazmak yerine olay başına
     * tek announcement kaydı (fan-out on read). Pasif turlar duyurulmaz.
     */
    private function announceNewTour(Tour $tour): void
    {
        if (! $tour->is_active) {
            return;
        }

        Announcement::create([
            'type' => Announcement::TYPE_NEW_TOUR,
            'tour_id' => $tour->id,
            'title' => 'Yeni Tur Eklendi!',
            'message' => "Yeni bir macera seni bekliyor: {$tour->title}. Hemen incele!",
            'icon' => '✨',
        ]);
    }

    /**
     * Fiyat düşüşü bildirimi sadece o turu favorileyenlere gider. Hacim küçük
     * olduğu için senkron gönderim güvenli (queue worker bağımlılığı yok).
     */
    private function notifyFavoritersOnPriceDrop(Tour $tour): void
    {
        if (! $tour->wasChanged('price')) {
            return;
        }

        if ((float) $tour->price >= (float) $tour->getOriginal('price')) {
            return;
        }

        $favoriters = $tour->favoritedBy()->get();

        if ($favoriters->isNotEmpty()) {
            Notification::send($favoriters, new PriceDropNotification($tour));
        }
    }

    /**
     * Tur silindiğinde embedding cache'ini ve RAG bilgi bankası kaydını temizle —
     * yoksa asistan artık var olmayan turu bilgi bankasından önermeye devam ediyor.
     */
    public function deleted(Tour $tour): void
    {
        Log::info("[TourObserver] Tur silindi: #{$tour->id} ({$tour->title}). Destinasyon cache + RAG chunk temizleniyor...");
        $this->flushDestinationInventoryCaches();
        \App\Models\KnowledgeChunk::where('source_type', 'tour')->where('source_id', $tour->id)->delete();
    }

    /**
     * Chatbot'un destinasyon bilgisi cache'leri: bilinen destinasyon listesi
     * (metin eşleştirme) + şehir bazlı envanter ("nerelere turunuz var").
     */
    private function flushDestinationInventoryCaches(): void
    {
        cache()->forget('ai_search_known_destinations_v1');
        DestinationKnowledgeService::flushInventory();
    }

    /**
     * Tur karakteri üretimi: tek seferlik LLM job'ı (mini). 600 sn kilidiyle
     * ardışık kaydetmelerde tekrar dispatch önlenir; job sonucu query-builder
     * update ile yazdığından observer döngüsü oluşmaz.
     */
    private function dispatchTourCharacterEnrichment(Tour $tour): void
    {
        if (! Cache::add(GenerateTourCharacterJob::DISPATCH_LOCK_PREFIX.$tour->id, 1, 600)) {
            return;
        }

        GenerateTourCharacterJob::dispatch($tour->id)->onQueue('default');
    }

    /** Rubrik puanlama job'ı — aynı kilit deseni (600 sn dispatch fırtınası önleme). */
    private function dispatchRubricScoring(Tour $tour): void
    {
        if (! Cache::add(\App\Jobs\ScoreTourRubricJob::DISPATCH_LOCK_PREFIX.$tour->id, 1, 600)) {
            return;
        }

        \App\Jobs\ScoreTourRubricJob::dispatch($tour->id)->onQueue('default');
    }

    /**
     * Tur'un metin alanlarından KnowledgeChunk içeriği üretip syncSingle çağırır.
     * content_hash değişimi varsa embedding null'lar + GenerateKnowledgeEmbeddingJob
     * dispatch edilir (SyncKnowledgeBase::syncSingle içinde).
     */
    private function syncKnowledgeChunkFor(Tour $tour): void
    {
        if (! $tour->is_active) {
            // Pasif turlar RAG'a girmesin — VE pasifleşen turun mevcut chunk'ı da
            // silinsin (asistan satıştan kalkan turu önermeye devam etmesin)
            \App\Models\KnowledgeChunk::where('source_type', 'tour')->where('source_id', $tour->id)->delete();

            return;
        }

        $tour->loadMissing('agency', 'category');

        $content = "Tur: {$tour->title}\n".
            "Destinasyon: {$tour->destination}\n".
            "Fiyat: {$tour->price} {$tour->currency}\n".
            "Süre: {$tour->duration_days} Gün\n".
            "Acente: {$tour->agency?->name}\n".
            "Kategori: {$tour->category?->name}\n".
            'Açıklama: '.strip_tags((string) $tour->description);

        SyncKnowledgeBase::syncSingle('tour', $tour->id, $tour->title, $content);
    }

    /**
     * Destinasyon string'i için profile yoksa veya enrichment_version eskimişse
     * arka planda LLM job dispatch. Multi-city ("Paris, Roma") string'lerinde
     * her parça için ayrı kontrol — bilinmeyen yeni şehirler de zenginleşir.
     */
    private function dispatchDestinationEnrichmentIfNeeded(string $destination): void
    {
        $destination = trim($destination);
        if ($destination === '') {
            return;
        }

        // Multi-city destination: ortak bölme kuralı (envanter servisiyle aynı)
        foreach (DestinationProfile::splitCities($destination) as $part) {
            $normalized = DestinationProfile::normalize($part);
            $profile = DestinationProfile::where('normalized_city', $normalized)->first();

            if ($profile && ! $profile->needsEnrichment()) {
                continue;
            }

            Log::info('[TourObserver] Destinasyon enrichment dispatch', ['city' => $part]);

            // Placeholder yoksa oluştur (sonsuz dispatch'i önlemek için DestinationProfileService
            // pattern'ı buraya da uyarlandı)
            if (! $profile) {
                DestinationProfile::create([
                    'city' => $part,
                    'normalized_city' => $normalized,
                    'crowd_score' => 0.50,
                    'liveliness_score' => 0.50,
                    'source' => DestinationProfile::SOURCE_DEFAULT,
                    'enrichment_version' => 1,
                ]);
            }

            GenerateDestinationProfileJob::dispatch($part);
        }
    }
}
