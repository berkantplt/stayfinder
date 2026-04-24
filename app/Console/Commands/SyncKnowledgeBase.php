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
use Illuminate\Support\Str;

class SyncKnowledgeBase extends Command
{
    protected $signature = 'app:sync-knowledge-base';
    protected $description = 'Tüm site içeriğini RAG Bilgi Bankasına senkronize eder.';

    public function handle()
    {
        $this->info('Bilgi Bankası senkronizasyonu başlatılıyor...');

        // 1. Turlar
        $this->syncTours();

        // 2. Blog Yazıları
        $this->syncPosts();

        // 3. Destinasyonlar
        $this->syncDestinations();

        // 4. Acentalar
        $this->syncAgencies();

        // 5. Sabit Bilgiler (SSS / Politikalar)
        $this->syncStaticFacts();

        $this->info('Senkronizasyon tamamlandı.');
    }

    private function syncTours()
    {
        $this->comment('Turlar senkronize ediliyor...');
        $tours = Tour::active()
            ->whereHas('agency', fn($agencyQuery) => $agencyQuery->active())
            ->with('category', 'agency')
            ->get();
        
        foreach ($tours as $tour) {
            $content = "Tur: {$tour->title}\n" .
                      "Destinasyon: {$tour->destination}\n" .
                      "Fiyat: {$tour->price} {$tour->currency}\n" .
                      "Süre: {$tour->duration_days} Gün\n" .
                      "Acente: {$tour->agency?->name}\n" .
                      "Kategori: {$tour->category?->name}\n" .
                      "Açıklama: " . strip_tags((string)$tour->description);
            
            $this->updateOrCreateChunk('tour', $tour->id, $tour->title, $content);
        }
    }

    private function syncPosts()
    {
        $this->comment('Blog yazıları senkronize ediliyor...');
        $posts = Post::where('is_published', true)->get();
        foreach ($posts as $post) {
            $content = "Blog: {$post->title}\nÖzet: {$post->excerpt}\nİçerik: " . strip_tags((string)$post->content);
            $this->updateOrCreateChunk('post', $post->id, $post->title, $content);
        }
    }

    private function syncDestinations()
    {
        $this->comment('Destinasyonlar senkronize ediliyor...');
        $destinations = Destination::where('is_active', true)->get();
        foreach ($destinations as $destination) {
            $content = "Destinasyon: {$destination->name}\n" .
                      "Ülke: {$destination->country}\n" .
                      "Öne Çıkanlar: {$destination->highlights}\n" .
                      "Bilgi: " . strip_tags((string)$destination->description);
            $this->updateOrCreateChunk('destination', $destination->id, $destination->name, $content);
        }
    }

    private function syncAgencies()
    {
        $this->comment('Acentalar senkronize ediliyor...');
        $agencies = Agency::where('is_active', true)->get();
        foreach ($agencies as $agency) {
            $content = "Acente: {$agency->name}\nBilgi: " . strip_tags((string)$agency->description) . "\nAdres: {$agency->address}\nİletişim: {$agency->phone}, {$agency->email}";
            $this->updateOrCreateChunk('agency', $agency->id, $agency->name, $content);
        }
    }

    private function syncStaticFacts()
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
            ]
        ];

        foreach ($facts as $index => $fact) {
            $this->updateOrCreateChunk('policy', $index, $fact['title'], $fact['content']);
        }
    }

    private function updateOrCreateChunk($type, $id, $title, $content)
    {
        $hash = md5($content);
        
        $chunk = KnowledgeChunk::where('source_type', $type)
                                ->where('source_id', $id)
                                ->first();

        if ($chunk) {
            if ($chunk->content_hash !== $hash) {
                $chunk->update([
                    'title' => $title,
                    'content' => $content,
                    'content_hash' => $hash,
                    'embedding' => null, // Değiştiği için sıfırla
                ]);
                GenerateKnowledgeEmbeddingJob::dispatch($chunk->id);
            }
        } else {
            $newChunk = KnowledgeChunk::create([
                'source_type' => $type,
                'source_id' => $id,
                'title' => $title,
                'content' => $content,
                'content_hash' => $hash,
            ]);
            GenerateKnowledgeEmbeddingJob::dispatch($newChunk->id);
        }
    }
}
