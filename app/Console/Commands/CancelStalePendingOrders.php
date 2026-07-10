<?php

namespace App\Console\Commands;

use App\Models\AgencyCategoryOrder;
use App\Services\Payment\CategoryOrderFinalizer;
use App\Services\Payment\IyzicoService;
use App\Support\CategoryLicensing;
use Illuminate\Console\Command;
use Throwable;

class CancelStalePendingOrders extends Command
{
    protected $signature = 'app:cancel-stale-pending-orders {--hours=24 : Bu süreden eski pending siparişler iptal edilir}';

    protected $description = 'Yarım kalmış pending siparişleri önce iyzico ile mutabık kılar (ödendiyse lisansı verir), aksi halde iptal edip alıcı kişisel verisini temizler.';

    public function handle(IyzicoService $iyzico, CategoryOrderFinalizer $finalizer): int
    {
        if (! CategoryLicensing::schemaReady()) {
            $this->warn('Kategori yetkilendirme şeması hazır değil — atlanıyor.');

            return self::SUCCESS;
        }

        $threshold = now()->subHours(max(1, (int) $this->option('hours')));
        $cancelled = 0;
        $rescued = 0;

        AgencyCategoryOrder::query()
            ->where('status', AgencyCategoryOrder::STATUS_PENDING)
            ->where('created_at', '<', $threshold)
            ->chunkById(100, function ($orders) use (&$cancelled, &$rescued, $iyzico, $finalizer) {
                foreach ($orders as $order) {
                    // MUTABAKAT: callback hiç ulaşmamış olabilir (kullanıcı sekmeyi
                    // kapattı, ağ koptu). İptal etmeden ÖNCE parayı iyzico'ya sor —
                    // ödeme başarılıysa lisansı ver, aksi halde iptale devam et.
                    if ($order->payment_provider === AgencyCategoryOrder::PROVIDER_IYZICO
                        && (string) $order->provider_token !== '') {
                        try {
                            $checkout = $iyzico->retrieveCheckoutForm(
                                (string) $order->provider_token,
                                (string) $order->id
                            );
                            if ($finalizer->settleFromCheckout($order, $checkout) === 'paid') {
                                $this->line('Kurtarıldı (ödeme bulundu): '.$order->order_number.' (acenta #'.$order->agency_id.')');
                                $rescued++;

                                continue;
                            }
                        } catch (Throwable $e) {
                            // iyzico ulaşılamadıysa bu turda iptal etme; bir sonraki
                            // koşuda tekrar denenir (para varsa kaybolmaz).
                            $this->warn('Mutabakat başarısız, iptal ertelendi: '.$order->order_number.' — '.$e->getMessage());

                            continue;
                        }
                    }

                    $order->update([
                        'status' => AgencyCategoryOrder::STATUS_CANCELLED,
                        'failure_reason' => 'Ödeme tamamlanmadı — zaman aşımıyla otomatik iptal edildi.',
                        // KVKK veri minimizasyonu: tamamlanmamış ödemenin kimlik
                        // verisini (TC vb.) tutmanın iş gerekçesi yok.
                        'buyer_snapshot' => null,
                    ]);

                    $this->line('İptal: '.$order->order_number.' (acenta #'.$order->agency_id.')');
                    $cancelled++;
                }
            });

        // KVKK veri minimizasyonu: başarısız (FAILED) siparişlerin alıcı kimlik
        // verisi (TC dahil buyer_snapshot) süresiz saklanmasın. 7 günlük inceleme
        // payından sonra scrub'lanır — sipariş kaydı denetim için kalır, PII gider.
        $scrubThreshold = now()->subDays(7);
        $scrubbed = AgencyCategoryOrder::query()
            ->where('status', AgencyCategoryOrder::STATUS_FAILED)
            ->whereNotNull('buyer_snapshot')
            ->where('updated_at', '<', $scrubThreshold)
            ->update(['buyer_snapshot' => null]);

        $this->info($cancelled.' sipariş iptal edildi, '.$rescued.' sipariş ödeme bulunup kurtarıldı, '.$scrubbed.' başarısız siparişin kimlik verisi temizlendi.');

        return self::SUCCESS;
    }
}
