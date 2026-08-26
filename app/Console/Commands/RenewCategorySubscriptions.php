<?php

namespace App\Console\Commands;

use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Notifications\CategorySubscriptionRenewalFailedNotification;
use App\Notifications\CategorySubscriptionRenewedNotification;
use App\Services\Payment\CategoryOrderFinalizer;
use App\Services\Payment\IyzicoService;
use App\Support\CategoryLicensing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Iyzipay\Model\Status;
use Throwable;

/**
 * Otomatik aylık yenileme: dolmak üzere olan (bugün/yarın biten) abonelikleri
 * acentanın KAYITLI KARTINDAN güncel tarife üzerinden tahsil edip 1 ay uzatır.
 * Tutar = kategori aylık ücreti + hak sayısı × ekstra tur fiyatı (yenilemede
 * azaltma planı — next_extra_tour_slots — uygulanır).
 *
 * İptal edilen (auto_renew=false) abonelik ÇEKİLMEZ; dönem sonuna kadar
 * kullanım sürer, sonra expire komutu kapatır. Bayrak (IYZICO_AUTO_RENEW)
 * kapalıyken komut hiçbir şey yapmaz — sistem hatırlatma+manuel yenilemede.
 */
class RenewCategorySubscriptions extends Command
{
    protected $signature = 'app:renew-category-subscriptions';

    protected $description = 'Kayıtlı kartı olan abonelikleri dönem sonunda otomatik yeniler (IYZICO_AUTO_RENEW açıkken).';

    public function __construct(
        private readonly IyzicoService $iyzico,
        private readonly CategoryOrderFinalizer $finalizer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (! CategoryLicensing::autoRenewEnabled()) {
            $this->warn('Otomatik yenileme kapalı (IYZICO_AUTO_RENEW bayrağı veya şema hazır değil) — atlanıyor.');

            return self::SUCCESS;
        }

        if (! $this->iyzico->isConfigured()) {
            $this->warn('iyzico anahtarları tanımlı değil — otomatik yenileme atlanıyor.');

            return self::SUCCESS;
        }

        $renewed = 0;
        $failed = 0;
        $skipped = 0;

        // Pencere: son gün + bir gün öncesi (geçici iyzico arızasında ikinci
        // şans). Uzatma semantiği expires_at üzerinden olduğu için erken çekim
        // acentanın gününü YEMEZ. renewal_attempted_at günde tek deneme sağlar.
        AgencyCategorySubscription::query()
            ->where('status', AgencyCategorySubscription::STATUS_ACTIVE)
            ->where('auto_renew', true)
            ->whereNull('cancelled_at')
            ->whereDate('expires_at', '>=', today())
            ->whereDate('expires_at', '<=', today()->addDay())
            ->where(function ($query) {
                $query->whereNull('renewal_attempted_at')
                    ->orWhereDate('renewal_attempted_at', '<', today());
            })
            ->whereHas('agency', fn ($query) => $query->where('is_active', true))
            ->with(['agency.storedCard', 'agency.users', 'category'])
            ->chunkById(50, function ($subscriptions) use (&$renewed, &$failed, &$skipped) {
                foreach ($subscriptions as $subscription) {
                    try {
                        $result = $this->renewSubscription($subscription);
                    } catch (Throwable $e) {
                        $result = 'failed';
                        $this->reportFailure($subscription, $e->getMessage());
                    }

                    match ($result) {
                        'renewed' => $renewed++,
                        'failed' => $failed++,
                        default => $skipped++,
                    };
                }
            });

        $this->info("Otomatik yenileme: {$renewed} yenilendi, {$failed} başarısız, {$skipped} atlandı.");

        return self::SUCCESS;
    }

    /** @return 'renewed'|'failed'|'skipped' */
    private function renewSubscription(AgencyCategorySubscription $subscription): string
    {
        // Günde tek deneme — ATOMİK claim: koşullu UPDATE 0 satır dönerse bir
        // başka süreç bugün zaten denemiş demektir (çift çekim önlenir;
        // scheduler withoutOverlapping'e ek savunma).
        $claimed = AgencyCategorySubscription::whereKey($subscription->id)
            ->where(function ($query) {
                $query->whereNull('renewal_attempted_at')
                    ->orWhereDate('renewal_attempted_at', '<', today());
            })
            ->update(['renewal_attempted_at' => today()]);

        if ($claimed === 0) {
            return 'skipped';
        }

        $agency = $subscription->agency;
        $category = $subscription->category;
        $storedCard = $agency?->storedCard;

        // MUTABAKAT: bu kategori için yakın tarihli, sonuçsuz kalmış bir
        // yenileme siparişi varsa (çekim yapılmış ama kayıt düşmemiş olabilir:
        // zaman aşımı, finalize sırasında crash, stale-cleanup iptali) parayı
        // önce iyzico'ya sor — KÖRLEMESİNE ikinci çekim yapma.
        $unresolvedOrder = AgencyCategoryOrder::query()
            ->where('agency_id', $agency->id)
            ->where('auto_renewal', true)
            ->where('created_at', '>=', now()->subDays(3))
            ->whereIn('status', [
                AgencyCategoryOrder::STATUS_PENDING,
                AgencyCategoryOrder::STATUS_FAILED,
                AgencyCategoryOrder::STATUS_CANCELLED,
            ])
            ->whereHas('items', fn ($query) => $query->where('category_id', $category->id))
            ->orderByDesc('id')
            ->first();

        if ($unresolvedOrder) {
            $reconciled = $this->reconcileUnresolvedOrder($unresolvedOrder, $subscription);

            if ($reconciled === 'paid') {
                return 'renewed'; // önceki çekim iyzico'da başarılıymış — hak verildi, yeni çekim YOK
            }

            if ($reconciled === 'unknown') {
                // iyzico'ya sorulamadı: bugün çekim YAPMA (çift çekim riski);
                // pencere sürerken yarın tekrar denenir.
                $this->line("Atlandı (mutabakat belirsiz): #{$subscription->id} {$category->name}");

                return 'skipped';
            }
            // 'not-paid': önceki deneme gerçekten ödenmemiş — normal akışla devam.
        }

        // Kart yoksa sessiz geç: dolum hatırlatması (expire komutu) manuel
        // yenilemeye yönlendiriyor zaten.
        if (! $storedCard) {
            $this->line("Atlandı (kart yok): #{$subscription->id} ".($category?->name ?? '?'));

            return 'skipped';
        }

        if (! $category || ! $category->is_active) {
            $this->line("Atlandı (kategori pasif/yok): #{$subscription->id}");

            return 'skipped';
        }

        $buyer = $this->resolveBuyer($agency->id);

        if ($buyer === null) {
            $this->reportFailure($subscription, 'fatura bilgisi bulunamadı');

            return 'failed';
        }

        $slots = $subscription->slotsAfterRenewal();
        $monthlyPrice = (float) $category->monthly_price;
        $extraTourPrice = (float) $category->extra_tour_price;
        $subtotal = $monthlyPrice + $slots * $extraTourPrice;

        $order = AgencyCategoryOrder::create([
            'agency_id' => $agency->id,
            'order_number' => $this->generateRenewalOrderNumber(),
            'billing_cycle' => 'monthly',
            'subtotal' => $subtotal,
            'currency' => 'TRY',
            'status' => AgencyCategoryOrder::STATUS_PENDING,
            'payment_provider' => AgencyCategoryOrder::PROVIDER_IYZICO,
            'auto_renewal' => true,
            'buyer_type' => $buyer['type'] ?? AgencyCategoryOrder::BUYER_INDIVIDUAL,
            'buyer_snapshot' => $buyer,
            'purchased_at' => now(),
        ]);

        AgencyCategoryOrderItem::create([
            'order_id' => $order->id,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'item_type' => AgencyCategoryOrderItem::TYPE_LICENSE,
            'unit_price' => $monthlyPrice,
            'billing_cycle' => 'monthly',
        ]);

        $basketItems = [[
            'id' => $category->id,
            'name' => $category->name,
            'category' => $category->parent?->name ?? 'Kategori Yetkisi',
            'price' => $monthlyPrice,
        ]];

        for ($unit = 1; $unit <= $slots; $unit++) {
            AgencyCategoryOrderItem::create([
                'order_id' => $order->id,
                'category_id' => $category->id,
                'category_name' => $category->name.' — Ekstra Tur Hakkı',
                'item_type' => AgencyCategoryOrderItem::TYPE_EXTRA_SLOT,
                'unit_price' => $extraTourPrice,
                'billing_cycle' => 'monthly',
            ]);

            $basketItems[] = [
                'id' => 'slot-'.$category->id.'-'.$unit,
                'name' => $category->name.' — Ekstra Tur Hakkı',
                'category' => 'Ekstra Tur Hakkı',
                'price' => $extraTourPrice,
            ];
        }

        try {
            $payment = $this->iyzico->chargeStoredCard(
                $order,
                $basketItems,
                $buyer,
                (string) $storedCard->card_user_key,
                (string) $storedCard->card_token,
            );
        } catch (Throwable $e) {
            $order->update([
                'status' => AgencyCategoryOrder::STATUS_FAILED,
                'failure_reason' => $e->getMessage(),
            ]);
            $this->reportFailure($subscription, $e->getMessage());

            return 'failed';
        }

        if (! $this->paymentSucceeded($payment)) {
            $reason = $payment->getErrorMessage() ?: 'kart çekimi reddedildi';

            $order->update([
                'status' => AgencyCategoryOrder::STATUS_FAILED,
                'failure_reason' => $reason,
            ]);
            $this->reportFailure($subscription, $reason);

            return 'failed';
        }

        // paymentId'yi finalize'DAN ÖNCE yaz: finalize patlarsa bile paranın
        // izi siparişte kalır ve yarınki mutabakat çekimi tamamlar.
        $order->update(['provider_payment_id' => $payment->getPaymentId()]);

        try {
            // finalize: PAID + uzatma (expires_at += 1 ay) + slot kalemleri yeni
            // dönemin hak sayısını OLDUĞU GİBİ yazar (auto_renewal SET semantiği).
            $this->finalizer->finalize($order, $payment->getPaymentId());
        } catch (Throwable $e) {
            // Para ÇEKİLDİ ama kayıt düşmedi: sipariş pending + paymentId'li
            // kalır, yarınki mutabakat finalize eder. Acentaya "başarısız"
            // bildirimi GÖNDERME (yanlış olur, para çekildi).
            Log::error('Yenileme çekimi başarılı ama finalize hata verdi — yarınki mutabakat tamamlayacak', [
                'order_id' => $order->id,
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->getPaymentId(),
                'message' => $e->getMessage(),
            ]);
            $this->error("Finalize hatası (çekim BAŞARILI, mutabakata kaldı): #{$subscription->id}");

            return 'skipped';
        }

        $subscription->refresh();

        $users = $agency->users ?? collect();
        if ($users->isNotEmpty()) {
            Notification::send($users, new CategorySubscriptionRenewedNotification($subscription, $order->fresh()));
        }

        $this->line("Yenilendi: #{$subscription->id} {$category->name} → ".$subscription->expires_at?->format('d.m.Y')." ({$subtotal} TL)");

        return 'renewed';
    }

    /**
     * Non-3DS /payment/auth cevabında iyzico paymentStatus alanını DÖNDÜRMEZ
     * (resmi doküman: "The paymentStatus parameter is null for this service") —
     * başarı yalnızca status alanından okunur; paymentStatus geldiyse de
     * SUCCESS olmalı (savunmacı kontrol).
     */
    private function paymentSucceeded(\Iyzipay\Model\Payment $payment): bool
    {
        return $payment->getStatus() === Status::SUCCESS
            && in_array($payment->getPaymentStatus(), [null, 'SUCCESS'], true);
    }

    /**
     * Sonuçsuz yenileme siparişini iyzico'yla mutabık kılar.
     *
     * @return 'paid'|'not-paid'|'unknown'
     */
    private function reconcileUnresolvedOrder(
        AgencyCategoryOrder $order,
        AgencyCategorySubscription $subscription
    ): string {
        try {
            $payment = $this->iyzico->retrievePayment((string) $order->id);
        } catch (Throwable $e) {
            Log::warning('Yenileme mutabakat sorgusu başarısız — bugün çekim yapılmayacak', [
                'order_id' => $order->id,
                'message' => $e->getMessage(),
            ]);

            return 'unknown';
        }

        if (! $this->paymentSucceeded($payment)) {
            // Çekim iyzico'ya hiç ulaşmamış veya reddedilmiş — yeni deneme serbest.
            if ($order->isPending()) {
                $order->update([
                    'status' => AgencyCategoryOrder::STATUS_FAILED,
                    'failure_reason' => 'Mutabakat: iyzico tarafında başarılı ödeme bulunamadı.',
                ]);
            }

            return 'not-paid';
        }

        // Para GERÇEKTEN çekilmiş: eski siparişi finalize et, hak ver.
        $this->finalizer->finalize($order, $payment->getPaymentId() ?: $order->provider_payment_id);

        $subscription->refresh();

        $users = $subscription->agency?->users ?? collect();
        if ($users->isNotEmpty()) {
            Notification::send($users, new CategorySubscriptionRenewedNotification($subscription, $order->fresh()));
        }

        $this->line("Mutabakatla tamamlandı: #{$subscription->id} sipariş {$order->order_number}");

        return 'paid';
    }

    /**
     * Fatura bilgisi: acentanın buyer_snapshot'ı dolu son ödenmiş siparişi.
     * (Şifreli kolon SQL'de sorgulanamaz — PHP tarafında ayıklanır.)
     *
     * @return array<string, mixed>|null
     */
    private function resolveBuyer(int $agencyId): ?array
    {
        $orders = AgencyCategoryOrder::query()
            ->where('agency_id', $agencyId)
            ->where('status', AgencyCategoryOrder::STATUS_PAID)
            ->orderByDesc('paid_at')
            ->limit(10)
            ->get();

        foreach ($orders as $order) {
            $snapshot = $order->buyer_snapshot;

            if (is_array($snapshot) && ! empty($snapshot['name']) && ! empty($snapshot['identity_number'])) {
                return $snapshot;
            }
        }

        return null;
    }

    private function reportFailure(AgencyCategorySubscription $subscription, string $reason): void
    {
        $this->error("Başarısız: #{$subscription->id} ".($subscription->category?->name ?? '?')." — {$reason}");

        $users = $subscription->agency?->users ?? collect();
        if ($users->isNotEmpty()) {
            Notification::send($users, new CategorySubscriptionRenewalFailedNotification($subscription, $reason));
        }
    }

    private function generateRenewalOrderNumber(): string
    {
        do {
            $number = 'KYM-YEN-'.now()->format('Ymd').'-'.strtoupper(substr((string) md5(uniqid((string) mt_rand(), true)), 0, 6));
        } while (AgencyCategoryOrder::where('order_number', $number)->exists());

        return $number;
    }
}
