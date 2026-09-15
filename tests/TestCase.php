<?php

namespace Tests;

use App\Jobs\GenerateDestinationProfileJob;
use App\Jobs\GenerateDiscoveryGuideJob;
use App\Jobs\GenerateKnowledgeEmbeddingJob;
use App\Jobs\GenerateTourCharacterJob;
use App\Jobs\GenerateTourEmbeddingJob;
use App\Jobs\ScoreTourRubricJob;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Queue;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // A14 — Yaşanmış veri kaybına karşı korkuluk (2026-08-26: config cache varken
        // test koşumu yerel MySQL'i migrate:fresh ile sildi). Hangi yoldan koşulursa
        // koşulsun (composer test, php artisan test, vendor/bin/phpunit) bağlantı
        // sqlite :memory: değilse suite ilk testte durur; migrate:fresh hiç çalışmaz.
        if (config('database.default') !== 'sqlite'
            || config('database.connections.sqlite.database') !== ':memory:') {
            static::fail(
                'Testler gerçek veritabanına bağlı görünüyor ('.config('database.default').'). '
                .'Önce `php artisan config:clear` çalıştırın; phpunit.xml sqlite :memory: tanımlar.'
            );
        }

        // Testler HERMETİK olmalı: observer'ların tetiklediği embedding/enrichment
        // job'ları sync kuyrukta anında koşup GERÇEK OpenAI'a gidiyordu — ağ yokken
        // suite düşüyor (cURL error 6), ağ varken her koşu gerçek API parası yakıyordu.
        // Kısmi fake: yalnızca bu üç job kuyrukta tutulur; diğer her şey normal çalışır.
        // Bu job'ları bilerek test edenler etkilenmez: handle()'ı doğrudan çağırırlar
        // veya kendi Queue::fake()'leri üzerinden assertPushed yaparlar.
        Queue::fake([
            GenerateTourEmbeddingJob::class,
            GenerateKnowledgeEmbeddingJob::class,
            GenerateDestinationProfileJob::class,
            GenerateTourCharacterJob::class,
            ScoreTourRubricJob::class,
            GenerateDiscoveryGuideJob::class,
        ]);
    }
}
