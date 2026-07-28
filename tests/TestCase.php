<?php

namespace Tests;

use App\Jobs\GenerateDestinationProfileJob;
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
        ]);
    }
}
