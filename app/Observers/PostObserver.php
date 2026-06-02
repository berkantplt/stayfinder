<?php

namespace App\Observers;

use App\Console\Commands\SyncKnowledgeBase;
use App\Models\KnowledgeChunk;
use App\Models\Post;
use Illuminate\Support\Facades\Log;

class PostObserver
{
    /**
     * Blog yazısı kaydedildiğinde (create/update) RAG bilgi tabanı güncellensin.
     * - Published değilse: varsa chunk'ı sil
     * - Published ise: chunk update/create + embedding regenerate dispatch
     */
    public function saved(Post $post): void
    {
        if (!$post->is_published) {
            KnowledgeChunk::where('source_type', 'post')->where('source_id', $post->id)->delete();
            return;
        }

        // Sadece içerik alanlarından biri değiştiyse re-sync (yeni kayıtlarda hepsi changed)
        $relevant = ['title', 'excerpt', 'content', 'is_published'];
        if ($post->exists && !$post->wasRecentlyCreated) {
            $changed = false;
            foreach ($relevant as $field) {
                if ($post->wasChanged($field)) {
                    $changed = true;
                    break;
                }
            }
            if (!$changed) return;
        }

        $content = "Blog: {$post->title}\nÖzet: {$post->excerpt}\nİçerik: " . strip_tags((string) $post->content);

        Log::info("[PostObserver] Knowledge sync: post #{$post->id}");
        SyncKnowledgeBase::syncSingle('post', $post->id, $post->title, $content);
    }

    public function deleted(Post $post): void
    {
        KnowledgeChunk::where('source_type', 'post')->where('source_id', $post->id)->delete();
    }
}
