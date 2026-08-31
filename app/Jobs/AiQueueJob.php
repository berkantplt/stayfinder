<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * AI job'larının ortak retry iskeleti: standart queue trait'leri +
 * tries/backoff tanımları. Altı AI job'ı bundan türer; $timeout gibi
 * job'a özgü ayarlar alt sınıfta kalır (ör. GenerateDiscoveryGuideJob 180,
 * ScoreTourRubricJob 300).
 */
abstract class AiQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];
}
