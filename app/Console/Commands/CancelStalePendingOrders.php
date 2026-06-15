<?php

namespace App\Console\Commands;

use App\Models\AgencyCategoryOrder;
use App\Support\CategoryLicensing;
use Illuminate\Console\Command;

class CancelStalePendingOrders extends Command
{
    protected $signature = 'app:cancel-stale-pending-orders {--hours=24 : Bu süreden eski pending siparişler iptal edilir}';

    protected $description = 'Ödemesi yarım kalmış (24 saatten eski pending) kategori siparişlerini iptal eder ve alıcı kişisel verisini temizler.';

    public function handle(): int
    {
        if (! CategoryLicensing::schemaReady()) {
            $this->warn('Kategori yetkilendirme şeması hazır değil — atlanıyor.');

            return self::SUCCESS;
        }

        $threshold = now()->subHours(max(1, (int) $this->option('hours')));
        $count = 0;

        AgencyCategoryOrder::query()
            ->where('status', AgencyCategoryOrder::STATUS_PENDING)
            ->where('created_at', '<', $threshold)
            ->chunkById(100, function ($orders) use (&$count) {
                foreach ($orders as $order) {
                    $order->update([
                        'status' => AgencyCategoryOrder::STATUS_CANCELLED,
                        'failure_reason' => 'Ödeme tamamlanmadı — zaman aşımıyla otomatik iptal edildi.',
                        // KVKK veri minimizasyonu: tamamlanmamış ödemenin kimlik
                        // verisini (TC vb.) tutmanın iş gerekçesi yok.
                        'buyer_snapshot' => null,
                    ]);

                    $this->line('İptal: '.$order->order_number.' (acenta #'.$order->agency_id.')');
                    $count++;
                }
            });

        $this->info($count.' bekleyen sipariş iptal edildi.');

        return self::SUCCESS;
    }
}
