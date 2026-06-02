<?php

namespace Tests\Feature;

use App\Jobs\GenerateKnowledgeEmbeddingJob;
use App\Models\Agency;
use App\Models\Destination;
use App\Models\KnowledgeChunk;
use App\Models\Post;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class KnowledgeSyncObserverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        Notification::fake();
    }

    public function test_tour_creation_writes_knowledge_chunk_and_dispatches_embedding_job(): void
    {
        $agency = $this->makeAgency();

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Kapadokya Balonu',
            'destination' => 'Kapadokya',
            'description' => 'Sıcak hava balonu turu',
            'price' => 8000,
            'currency' => 'TRY',
            'duration_days' => 2,
            'departure_date' => today()->addDays(15),
            'return_date' => today()->addDays(17),
            'is_active' => true,
        ]);

        $chunk = KnowledgeChunk::where('source_type', 'tour')->where('source_id', $tour->id)->first();

        $this->assertNotNull($chunk);
        $this->assertStringContainsString('Kapadokya', $chunk->content);
        $this->assertStringContainsString('Balon', $chunk->content);
        Queue::assertPushed(GenerateKnowledgeEmbeddingJob::class, function ($job) use ($chunk) {
            return $job->chunkId === $chunk->id || (function () use ($job, $chunk) {
                // Job constructor: chunkId. Reflection ile fallback (private prop ihtimaline karşı).
                $ref = new \ReflectionClass($job);
                foreach ($ref->getProperties() as $p) {
                    $p->setAccessible(true);
                    $val = $p->getValue($job);
                    if ((int) $val === (int) $chunk->id) return true;
                }
                return false;
            })();
        });
    }

    public function test_tour_update_changes_chunk_hash_when_content_changes(): void
    {
        $agency = $this->makeAgency();
        $tour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Initial Title',
            'destination' => 'Antalya',
            'description' => 'Initial description',
            'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(13),
            'is_active' => true,
        ]);

        $initialHash = KnowledgeChunk::where('source_type', 'tour')->where('source_id', $tour->id)->first()->content_hash;

        Queue::fake(); // reset assertions

        $tour->update(['title' => 'Updated Title']);

        $newHash = KnowledgeChunk::where('source_type', 'tour')->where('source_id', $tour->id)->first()->content_hash;

        $this->assertNotSame($initialHash, $newHash);
        Queue::assertPushed(GenerateKnowledgeEmbeddingJob::class);
    }

    public function test_tour_update_does_not_resync_if_only_irrelevant_field_changes(): void
    {
        $agency = $this->makeAgency();
        $tour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Title',
            'destination' => 'Bodrum',
            'description' => 'Desc',
            'price' => 5000, 'currency' => 'TRY', 'duration_days' => 3,
            'departure_date' => today()->addDays(10),
            'return_date' => today()->addDays(13),
            'is_active' => true,
        ]);

        $initialHash = KnowledgeChunk::where('source_type', 'tour')->where('source_id', $tour->id)->first()->content_hash;

        Queue::fake();

        // is_international knowledge'a girmiyor → re-sync olmamalı
        $tour->update(['is_international' => false]);

        $sameHash = KnowledgeChunk::where('source_type', 'tour')->where('source_id', $tour->id)->first()->content_hash;
        $this->assertSame($initialHash, $sameHash);
        Queue::assertNotPushed(GenerateKnowledgeEmbeddingJob::class);
    }

    public function test_post_publish_writes_chunk(): void
    {
        $post = Post::create([
            'title' => 'Seyahat Rehberi',
            'slug' => 'seyahat-rehberi-' . uniqid(),
            'excerpt' => 'Kısa özet',
            'content' => 'Uzun içerik metni',
            'is_published' => true,
        ]);

        $chunk = KnowledgeChunk::where('source_type', 'post')->where('source_id', $post->id)->first();
        $this->assertNotNull($chunk);
        $this->assertStringContainsString('Seyahat Rehberi', $chunk->content);
        Queue::assertPushed(GenerateKnowledgeEmbeddingJob::class);
    }

    public function test_post_unpublish_deletes_chunk(): void
    {
        $post = Post::create([
            'title' => 'X',
            'slug' => 'x-' . uniqid(),
            'excerpt' => 'e',
            'content' => 'c',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('knowledge_chunks', ['source_type' => 'post', 'source_id' => $post->id]);

        $post->update(['is_published' => false]);

        $this->assertDatabaseMissing('knowledge_chunks', ['source_type' => 'post', 'source_id' => $post->id]);
    }

    public function test_destination_active_writes_chunk(): void
    {
        $destination = Destination::create([
            'name' => 'Pamukkale',
            'slug' => 'pamukkale-' . uniqid(),
            'is_active' => true,
            'country' => 'Türkiye',
            'highlights' => 'Beyaz travertenler',
            'description' => 'UNESCO mirası',
        ]);

        $chunk = KnowledgeChunk::where('source_type', 'destination')->where('source_id', $destination->id)->first();
        $this->assertNotNull($chunk);
        $this->assertStringContainsString('Pamukkale', $chunk->content);
        Queue::assertPushed(GenerateKnowledgeEmbeddingJob::class);
    }

    public function test_destination_deactivation_removes_chunk(): void
    {
        $destination = Destination::create([
            'name' => 'TempDest',
            'slug' => 'temp-' . uniqid(),
            'is_active' => true,
            'country' => 'Türkiye',
        ]);

        $this->assertDatabaseHas('knowledge_chunks', ['source_type' => 'destination', 'source_id' => $destination->id]);

        $destination->update(['is_active' => false]);

        $this->assertDatabaseMissing('knowledge_chunks', ['source_type' => 'destination', 'source_id' => $destination->id]);
    }

    public function test_sync_command_with_since_filter(): void
    {
        $agency = $this->makeAgency();

        $oldTour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'Old Tour',
            'destination' => 'Eski',
            'description' => 'd',
            'price' => 1000, 'currency' => 'TRY', 'duration_days' => 1,
            'departure_date' => today()->addDay(),
            'return_date' => today()->addDays(2),
            'is_active' => true,
        ]);

        // Manuel olarak updated_at'i 10 gün öncesine çek
        $oldTour->forceFill(['updated_at' => now()->subDays(10)])->save();

        $newTour = Tour::create([
            'agency_id' => $agency->id,
            'title' => 'New Tour',
            'destination' => 'Yeni',
            'description' => 'd',
            'price' => 2000, 'currency' => 'TRY', 'duration_days' => 1,
            'departure_date' => today()->addDay(),
            'return_date' => today()->addDays(2),
            'is_active' => true,
        ]);

        // Observer hem old hem new için chunk yarattı; sil ki test temiz olsun
        KnowledgeChunk::truncate();

        // --since=dün → sadece new tour işlenmeli
        $this->artisan('app:sync-knowledge-base', ['--since' => now()->subDay()->format('Y-m-d')])
            ->assertSuccessful();

        $this->assertDatabaseMissing('knowledge_chunks', ['source_type' => 'tour', 'source_id' => $oldTour->id]);
        $this->assertDatabaseHas('knowledge_chunks', ['source_type' => 'tour', 'source_id' => $newTour->id]);
    }

    private function makeAgency(): Agency
    {
        return Agency::create([
            'name' => 'Knowledge Test',
            'slug' => 'knowledge-' . uniqid(),
            'email' => uniqid() . '@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }
}
