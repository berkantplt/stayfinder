<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Knowledge Base Sync
|--------------------------------------------------------------------------
|
| Her gece 03:00'te bilgi bankası (turlar, postlar, destinasyonlar,
| acentalar) tazelenir. --since dünden bu yana güncellenmiş kayıtları
| işler (ilk gün manuel full sync gerekir). 60 dk overlap guard.
|
*/
Schedule::command('app:sync-knowledge-base --since=' . now()->subDay()->format('Y-m-d'))
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping(60)
    ->runInBackground()
    ->name('knowledge-base-sync');
