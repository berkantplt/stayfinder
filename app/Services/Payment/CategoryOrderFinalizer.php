<?php

namespace App\Services\Payment;

use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategorySubscription;
use App\Support\CategoryLicensing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\Status;

/**
 * Kategori siparişini ödeme sonrası kesinleştiren paylaşılan mantık.
 * İki yerden çağrılır:
 *   1. iyzicoCallback (kullanıcı tarayıcı yönlendirmesi) — anlık.
 *   2. ReconcilePendingOrders / CancelStalePendingOrders — callback hiç ulaşmadıysa
 *      (kullanıcı sekmeyi kapattı, ağ koptu) parayı iyzico'ya sorup lisansı verir.
 * finalize() idempotenttir: sipariş satırını kilitleyip PAID ise hiçbir şey yapmaz.
 */
class CategoryOrderFinalizer
{
    /**
     * Ödemesi doğrulanmış siparişi PAID işaretler ve abonelikleri açar/uzatır.
     * Çift callback'e karşı sipariş satırı kilitli okunur (idempotent).
     */
    public function finalize(AgencyCategoryOrder $order, ?string $paymentId): void
    {
        DB::transaction(function () use ($order, $paymentId) {
            $locked = AgencyCategoryOrder::whereKey($order->id)->lockForUpdate()->first();
            if (! $locked || $locked->isPaid()) {
                return;
            }

            $order->update([
                'status' => AgencyCategoryOrder::STATUS_PAID,
                'paid_at' => now(),
                'provider_payment_id' => $paymentId,
                'failure_reason' => null,
            ]);

            $items = $order->items()->with('category')->get();
            $slotSchemaReady = CategoryLicensing::slotSchemaReady();

            // Lisans kalemleri ÖNCE işlenir: aynı siparişte lapse yenilemesi +
            // ekstra hak varsa önce dönem sıfırdan başlar, sonra haklar eklenir.
            $licenseItems = $items->reject(fn ($item) => $item->isExtraSlot());
            $slotItems = $slotSchemaReady ? $items->filter(fn ($item) => $item->isExtraSlot()) : collect();

            foreach ($licenseItems as $item) {
                if (! $item->category_id) {
                    continue;
                }

                // Ödeme sırasında kategori pasifleştirilmiş olabilir: abonelik yine
                // verilir (para çekildi) ama admin manuel telafi/inceleme için
                // uyarılır — aksi halde acenta kullanamadığı bir kategoriye para öder.
                if ($item->category && ! $item->category->is_active) {
                    Log::warning('iyzico finalize: pasif kategoriye abonelik açıldı — inceleme gerekebilir', [
                        'order_id' => $order->id,
                        'agency_id' => $order->agency_id,
                        'category_id' => $item->category_id,
                    ]);
                }

                // Eşzamanlı çift callback'e karşı satır kilidi (transaction içindeyiz)
                $subscription = AgencyCategorySubscription::query()
                    ->where('agency_id', $order->agency_id)
                    ->where('category_id', $item->category_id)
                    ->lockForUpdate()
                    ->first();

                // Süresi geçmemiş aktif abonelik: kalan günler yanmasın — mevcut
                // bitişten +1 ay uzat, started_at korunur. Aksi halde bugünden başlat.
                $extends = $subscription
                    && $subscription->status === AgencyCategorySubscription::STATUS_ACTIVE
                    && $subscription->expires_at
                    && $subscription->expires_at->greaterThanOrEqualTo(today());

                $values = [
                    'last_order_id' => $order->id,
                    'monthly_price' => $item->unit_price,
                    'status' => AgencyCategorySubscription::STATUS_ACTIVE,
                    'started_at' => $extends ? $subscription->started_at : today(),
                    'expires_at' => $extends
                        ? $subscription->expires_at->copy()->addMonth()
                        : today()->addMonth(),
                    // Yeni dönem = yeni hatırlatma hakkı
                    'renewal_reminder_sent_at' => null,
                ];

                // Ekstra tur hakları abonelik KESİNTİSİZ sürdükçe geçerli:
                // dönem lapse sonrası sıfırdan başlıyorsa eski haklar yanar.
                if ($slotSchemaReady && ! $extends) {
                    $values['extra_tour_slots'] = 0;
                }

                if ($subscription) {
                    $subscription->update($values);
                } else {
                    AgencyCategorySubscription::create($values + [
                        'agency_id' => $order->agency_id,
                        'category_id' => $item->category_id,
                    ]);
                }
            }

            // Ekstra tur hakları: satır başına +1 hak, ilgili aboneliğe yazılır.
            foreach ($slotItems->groupBy('category_id') as $categoryId => $group) {
                if (! $categoryId) {
                    continue;
                }

                $subscription = AgencyCategorySubscription::query()
                    ->where('agency_id', $order->agency_id)
                    ->where('category_id', $categoryId)
                    ->lockForUpdate()
                    ->first();

                // Sepete eklerken aktif abonelik şartı var; yine de ödeme ile
                // finalize arasında abonelik silinmiş olabilir — para çekildi,
                // admin manuel telafi için uyarılır.
                if (! $subscription) {
                    Log::warning('iyzico finalize: aboneliği olmayan kategoriye ekstra tur hakkı ödendi — manuel inceleme gerekli', [
                        'order_id' => $order->id,
                        'agency_id' => $order->agency_id,
                        'category_id' => $categoryId,
                        'slot_count' => $group->count(),
                    ]);

                    continue;
                }

                $subscription->update([
                    'extra_tour_slots' => (int) $subscription->extra_tour_slots + $group->count(),
                ]);
            }
        });
    }

    /**
     * iyzico checkout sonucunu değerlendirip siparişi kesinleştirir/başarısız yapar.
     * Dönüş: 'paid' | 'mismatch' | 'failed' — çağıran taraf loglama/akış için kullanır.
     */
    public function settleFromCheckout(AgencyCategoryOrder $order, CheckoutForm $checkout): string
    {
        if ($checkout->getStatus() === Status::SUCCESS && $checkout->getPaymentStatus() === 'SUCCESS') {
            if ($mismatch = $this->detectAmountMismatch($order, $checkout)) {
                Log::error('iyzico amount mismatch', [
                    'order_id' => $order->id,
                    'expected' => (string) $order->subtotal,
                    'price' => (string) $checkout->getPrice(),
                    'paid_price' => (string) $checkout->getPaidPrice(),
                ]);

                $order->update([
                    'status' => AgencyCategoryOrder::STATUS_FAILED,
                    'failure_reason' => $mismatch,
                ]);

                return 'mismatch';
            }

            $this->finalize($order, $checkout->getPaymentId());

            return 'paid';
        }

        $order->update([
            'status' => AgencyCategoryOrder::STATUS_FAILED,
            'failure_reason' => $checkout->getErrorMessage()
                ?: ('Ödeme tamamlanamadı: '.($checkout->getPaymentStatus() ?: 'bilinmeyen durum')),
        ]);

        return 'failed';
    }

    public function detectAmountMismatch(AgencyCategoryOrder $order, CheckoutForm $checkout): ?string
    {
        $expected = (float) $order->subtotal;
        $basketPrice = (float) $checkout->getPrice();
        $paidPrice = (float) $checkout->getPaidPrice();

        if (abs($basketPrice - $expected) > 0.01) {
            return sprintf(
                'Tutar uyuşmazlığı: sipariş %.2f TL, iyzico sepet tutarı %.2f TL. Ödeme manuel incelenmeli.',
                $expected,
                $basketPrice
            );
        }

        if ($paidPrice + 0.01 < $expected) {
            return sprintf(
                'Eksik ödeme: sipariş %.2f TL, ödenen %.2f TL. Ödeme manuel incelenmeli.',
                $expected,
                $paidPrice
            );
        }

        return null;
    }
}
