<?php

namespace App\Console\Commands;

use App\Jobs\GenerateKnowledgeEmbeddingJob;
use App\Models\Agency;
use App\Models\Category;
use App\Models\Destination;
use App\Models\KnowledgeChunk;
use App\Models\Post;
use App\Models\Tour;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class SyncKnowledgeBase extends Command
{
    protected $signature = 'app:sync-knowledge-base
        {--since= : Sadece bu tarihten sonra updated_at olan kayıtları işle (YYYY-MM-DD)}';

    protected $description = 'Tüm site içeriğini RAG Bilgi Bankasına senkronize eder.';

    public function handle()
    {
        $since = $this->resolveSince();
        if ($since) {
            $this->info('Bilgi Bankası inkremental sync başlatılıyor: --since=' . $since->toDateString());
        } else {
            $this->info('Bilgi Bankası full sync başlatılıyor...');
        }

        $this->syncTours($since);
        $this->syncPosts($since);
        $this->syncDestinations($since);
        $this->syncAgencies($since);

        // Statik politikalar her sync'te yenilenir (sayı çok az)
        $this->syncStaticFacts();

        // Güvenlik ağı: observer'ı atlayarak silinen/pasifleşen turların chunk'ları
        // RAG'de kalmasın — asistan var olmayan turu önermesin.
        $this->pruneStaleTourChunks();

        $this->info('Senkronizasyon tamamlandı.');
    }

    private function pruneStaleTourChunks(): void
    {
        $pruned = \App\Models\KnowledgeChunk::where('source_type', 'tour')
            ->whereNotIn('source_id', Tour::where('is_active', true)->pluck('id'))
            ->delete();

        if ($pruned > 0) {
            $this->comment("Bayat tur chunk'ları temizlendi: {$pruned}");
        }
    }

    private function resolveSince(): ?Carbon
    {
        $raw = $this->option('since');
        if (!$raw) {
            return null;
        }

        try {
            return Carbon::parse($raw)->startOfDay();
        } catch (\Throwable $e) {
            $this->warn('Geçersiz --since tarihi: ' . $raw . ' (full sync\'e fallback)');
            return null;
        }
    }

    private function syncTours(?Carbon $since): void
    {
        $this->comment('Turlar senkronize ediliyor...');
        $query = Tour::active()
            ->whereHas('agency', fn($q) => $q->active())
            ->with('category', 'agency');

        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        $count = 0;
        foreach ($query->cursor() as $tour) {
            $content = "Tur: {$tour->title}\n" .
                      "Destinasyon: {$tour->destination}\n" .
                      "Fiyat: {$tour->price} {$tour->currency}\n" .
                      "Süre: {$tour->duration_days} Gün\n" .
                      "Acente: {$tour->agency?->name}\n" .
                      "Kategori: {$tour->category?->name}\n" .
                      "Açıklama: " . strip_tags((string) $tour->description);

            $this->updateOrCreateChunk('tour', $tour->id, $tour->title, $content);
            $count++;
        }
        $this->info(" -> {$count} tur işlendi");
    }

    private function syncPosts(?Carbon $since): void
    {
        $this->comment('Blog yazıları senkronize ediliyor...');
        $query = Post::where('is_published', true);

        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        $count = 0;
        foreach ($query->cursor() as $post) {
            $content = "Blog: {$post->title}\nÖzet: {$post->excerpt}\nİçerik: " . strip_tags((string) $post->content);
            $this->updateOrCreateChunk('post', $post->id, $post->title, $content);
            $count++;
        }
        $this->info(" -> {$count} blog yazısı işlendi");
    }

    private function syncDestinations(?Carbon $since): void
    {
        $this->comment('Destinasyonlar senkronize ediliyor...');
        $query = Destination::where('is_active', true);

        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        $count = 0;
        foreach ($query->cursor() as $destination) {
            $content = "Destinasyon: {$destination->name}\n" .
                      "Ülke: {$destination->country}\n" .
                      "Öne Çıkanlar: {$destination->highlights}\n" .
                      "Bilgi: " . strip_tags((string) $destination->description);
            $this->updateOrCreateChunk('destination', $destination->id, $destination->name, $content);
            $count++;
        }
        $this->info(" -> {$count} destinasyon işlendi");
    }

    private function syncAgencies(?Carbon $since): void
    {
        $this->comment('Acentalar senkronize ediliyor...');
        $query = Agency::where('is_active', true);

        if ($since) {
            $query->where('updated_at', '>=', $since);
        }

        $count = 0;
        foreach ($query->cursor() as $agency) {
            $content = "Acente: {$agency->name}\nBilgi: " . strip_tags((string) $agency->description)
                . "\nAdres: {$agency->address}\nİletişim: {$agency->phone}, {$agency->email}";
            $this->updateOrCreateChunk('agency', $agency->id, $agency->name, $content);
            $count++;
        }
        $this->info(" -> {$count} acenta işlendi");
    }

    private function syncStaticFacts(): void
    {
        $this->comment('Sabit bilgiler senkronize ediliyor...');
        $facts = [
            [
                'title' => 'İptal ve İade Politikası',
                'content' => 'StayFinder üzerinden alınan turlarda, turun başlamasına 30 gün kala yapılan iptallerde %100 iade yapılır. 15-30 gün kala yapılan iptallerde %50 iade, 15 günden az süre kalan iptallerde iade yapılmaz. Bazı kampanyalı turlarda iade hakkı bulunmayabilir.'
            ],
            [
                'title' => 'Ödeme Seçenekleri',
                'content' => 'Tüm kredi kartlarına 12 aya varan taksit imkanı sunuyoruz. Havale/EFT ile ödemelerde %3 ekstra indirim uygulanmaktadır. Ödemeleriniz 256-bit SSL güvenlik sertifikası ile korunmaktadır.'
            ],
            [
                'title' => 'Vize İşlemleri',
                'content' => 'Vize gereken turlarda, acentalarımız vize danışmanlık hizmeti vermektedir. Vize ücreti genellikle tur fiyatına dahil değildir. Vize reddi durumunda, sigortanız varsa tur ücretinin bir kısmı iade edilebilir.'
            ],
            [
                'title' => 'İletişim ve Destek',
                'content' => 'StayFinder destek hattına +90 (212) 555 01 02 numaralı telefondan veya destek@stayfinder.com adresinden 7/24 ulaşabilirsiniz. WhatsApp hattımız üzerinden de anlık destek alabilirsiniz.'
            ],
        ];

        foreach ($facts as $index => $fact) {
            $this->updateOrCreateChunk('policy', $index, $fact['title'], $fact['content']);
        }
    }

    /**
     * KnowledgeChunk için kullanılabilir kamu API.
     * Tek bir kaynağın (tour/post/destination) chunk'ını günceller veya yaratır.
     * content_hash değişikliği varsa embedding'i null'lar + job dispatch eder.
     *
     * Observer'lardan da çağrılır (tek-kayıt incremental sync).
     */
    public static function syncSingle(string $sourceType, int|string $sourceId, string $title, string $content): void
    {
        $hash = md5($content);
        $chunk = KnowledgeChunk::where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->first();

        if ($chunk) {
            if ($chunk->content_hash !== $hash) {
                $chunk->update([
                    'title' => $title,
                    'content' => $content,
                    'content_hash' => $hash,
                    'embedding' => null,
                ]);
                GenerateKnowledgeEmbeddingJob::dispatch($chunk->id);
            }
            return;
        }

        $newChunk = KnowledgeChunk::create([
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'title' => $title,
            'content' => $content,
            'content_hash' => $hash,
        ]);
        GenerateKnowledgeEmbeddingJob::dispatch($newChunk->id);
    }

    private function updateOrCreateChunk(string $type, int|string $id, string $title, string $content): void
    {
        self::syncSingle($type, $id, $title, $content);
    }
}
