<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
Schedule::command('app:sync-knowledge-base --since='.now()->subDay()->format('Y-m-d'))
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping(60)
    ->runInBackground()
    ->name('knowledge-base-sync');

/*
|--------------------------------------------------------------------------
| Embedding Güvenlik Ağı
|--------------------------------------------------------------------------
|
| AI, envanterdeki TÜM turları tanımalı: embedding'i olmayan tur aramada hiç
| görünmez. Observer job'ı kaçarsa (kuyruk aksaması, hata) saatlik tarama
| yalnızca EKSİK olanları doldurur — dolu embedding'e dokunmaz, maliyeti ~sıfır.
|
*/
Schedule::command('app:generate-tour-embeddings')
    ->hourly()
    ->onOneServer()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->name('tour-embedding-safety-net');

/*
|--------------------------------------------------------------------------
| AI Öğrenme Çarkı
|--------------------------------------------------------------------------
|
| Gece: tıklama önceliklerini (±0.03 sınırlı) ve girişli kullanıcı tercih
| profillerini tazele. Haftalık: kalite raporu (0-sonuç/gevşetme/tıklama
| oranları) — sorunlar kullanıcı sessizce terk etmeden görünür olsun.
| Ağırlık kalibrasyonu KASITLI olarak zamanlanmadı: ai:calibrate-weights
| insan gözetiminde elle çalıştırılır (--apply ile onaylanır).
|
*/
Schedule::command('ai:update-ctr-priors')
    ->dailyAt('03:40')->onOneServer()->withoutOverlapping(30)->runInBackground()
    ->name('ai-ctr-priors');

Schedule::command('ai:build-user-profiles')
    ->dailyAt('03:50')->onOneServer()->withoutOverlapping(30)->runInBackground()
    ->name('ai-user-profiles');

Schedule::command('ai:quality-report')
    ->weeklyOn(1, '08:30')->onOneServer()->withoutOverlapping(30)->runInBackground()
    ->name('ai-quality-report');

/*
|--------------------------------------------------------------------------
| Bildirim & Duyuru Temizliği
|--------------------------------------------------------------------------
|
| Okunmuş bildirimler ve eski duyurular 90 gün sonra silinir — notifications
| tablosunun sınırsız büyümesini önler. Okunmamış bildirimlere dokunulmaz.
|
*/
Schedule::call(function () {
    DB::table('notifications')
        ->whereNotNull('read_at')
        ->where('created_at', '<', now()->subDays(90))
        ->delete();

    DB::table('announcements')
        ->where('created_at', '<', now()->subDays(90))
        ->delete();
})->dailyAt('03:30')->name('notification-pruning')->onOneServer();

/*
|--------------------------------------------------------------------------
| Döviz Kuru Güncelleme
|--------------------------------------------------------------------------
|
| TCMB kurları iş günleri ~15:30'da yayınlanır. Günlük 16:00 çekimi ile
| currency_rates + tours.price_try tazelenir. TCMB erişilemezse son
| bilinen kurlar korunur (komut FAILURE döner ama veri bozulmaz).
|
*/
Schedule::command('app:update-currency-rates')
    ->dailyAt('16:00')
    ->onOneServer()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->name('currency-rates-update');

/*
|--------------------------------------------------------------------------
| Kategori Abonelik Yaşam Döngüsü
|--------------------------------------------------------------------------
|
| Her sabah 08:00: süresi dolan abonelikler expired yapılır + acentaya
| bildirim; bitişe 7 gün (ve altı) kalanlara dönem başına bir kez
| yenileme hatırlatması gönderilir (DB bildirimi — mail entegrasyonu
| geldiğinde notification'lara mail kanalı eklenebilir).
|
*/
Schedule::command('app:expire-category-subscriptions')
    ->dailyAt('08:00')
    ->onOneServer()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->name('category-subscription-lifecycle');

/*
|--------------------------------------------------------------------------
| Yarım Kalan Ödeme Siparişleri Temizliği
|--------------------------------------------------------------------------
|
| 24 saatten eski pending siparişler cancelled yapılır (silinmez — denetim
| izi korunur) ve buyer_snapshot kişisel verisi temizlenir. Geç gelen
| geçerli bir iyzico callback'i yine de siparişi finalize edebilir.
|
*/
Schedule::command('app:cancel-stale-pending-orders')
    ->dailyAt('03:15')
    ->onOneServer()
    ->withoutOverlapping(30)
    ->runInBackground()
    ->name('stale-pending-order-cleanup');

/*
|--------------------------------------------------------------------------
| Analitik Tablo Pruning
|--------------------------------------------------------------------------
|
| tour_views/tour_clicks 180 gün, ai_search_logs 90 gün (ML verisi),
| price_histories 365 gün retention. Yaşam-boyu görüntülenme/tıklama
| toplamları tours.views_count/clicks_count sayaçlarında korunur.
|
*/
Schedule::command('app:prune-analytics')
    ->dailyAt('04:00')
    ->onOneServer()
    ->withoutOverlapping(60)
    ->runInBackground()
    ->name('analytics-pruning');

/*
|--------------------------------------------------------------------------
| Queue Worker (cron-tabanlı)
|--------------------------------------------------------------------------
|
| Plesk'te supervisor/daemon olmadığı için worker scheduler üzerinden
| çalışır: her dakika başlar, kuyruğu boşaltır, kendini kapatır
| (--stop-when-empty). --max-time=50 bir sonraki dakikayla çakışmayı
| önler. Embedding, destinasyon zenginleştirme ve bilgi tabanı job'ları
| en fazla ~1 dk gecikmeyle işlenir.
|
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping()
    ->name('queue-worker');
