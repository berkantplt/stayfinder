<?php

namespace App\Console\Commands;

use App\Models\AgencyCategorySubscription;
use App\Notifications\CategorySubscriptionExpiredNotification;
use App\Notifications\CategorySubscriptionExpiringNotification;
use App\Support\CategoryLicensing;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class ExpireCategorySubscriptions extends Command
{
    protected $signature = 'app:expire-category-subscriptions';

    protected $description = 'Süresi dolan kategori aboneliklerini expired yapar; dolmak üzere olanlara yenileme hatırlatması gönderir.';

    /** Hatırlatma, bitişe bu kadar gün kala (ve sonrasında) gönderilir. */
    private const REMINDER_DAYS_BEFORE = 7;

    public function handle(): int
    {
        if (! CategoryLicensing::schemaReady()) {
            $this->warn('Kategori yetkilendirme şeması hazır değil — atlanıyor.');

            return self::SUCCESS;
        }

        $expired = $this->expireLapsedSubscriptions();
        $reminded = $this->sendRenewalReminders();

        $this->info("Süresi dolan: {$expired} abonelik expired yapıldı. Hatırlatma: {$reminded} abonelik.");

        return self::SUCCESS;
    }

    /**
     * expires_at geçmiş ama hâlâ 'active' görünen abonelikleri kapat ve acenta
     * kullanıcılarına haber ver. Görünürlük zaten expires_at ile kesiliyor —
     * buradaki amaç durumu netleştirmek + acentayı bilgilendirmek.
     */
    private function expireLapsedSubscriptions(): int
    {
        $count = 0;

        AgencyCategorySubscription::query()
            ->where('status', AgencyCategorySubscription::STATUS_ACTIVE)
            ->whereDate('expires_at', '<', today())
            ->with(['agency.users', 'category'])
            ->chunkById(100, function ($subscriptions) use (&$count) {
                foreach ($subscriptions as $subscription) {
                    // Ekstra tur hakları abonelik kesintisiz sürdükçe geçerli —
                    // süre dolunca haklar da yanar (satın alımda bu böyle sunuluyor).
                    // Bekleyen azaltma planı da anlamını yitirir, temizlenir.
                    $subscription->update([
                        'status' => AgencyCategorySubscription::STATUS_EXPIRED,
                    ] + (CategoryLicensing::slotSchemaReady() ? ['extra_tour_slots' => 0] : [])
                        + (CategoryLicensing::autoRenewSchemaReady() ? ['next_extra_tour_slots' => null] : []));

                    $users = $subscription->agency?->users ?? collect();
                    if ($users->isNotEmpty()) {
                        Notification::send($users, new CategorySubscriptionExpiredNotification($subscription));
                    }

                    $this->line('Expired: #'.$subscription->id.' ('.($subscription->category?->name ?? '?').')');
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Bitişe REMINDER_DAYS_BEFORE gün (veya daha az) kalan aktif aboneliklere
     * dönem başına BİR kez hatırlatma. İdempotency: renewal_reminder_sent_at —
     * yenilemede (finalizePaidOrder) sıfırlanır, böylece yeni dönemde tekrar gönderilir.
     */
    private function sendRenewalReminders(): int
    {
        $count = 0;

        $autoRenewEnabled = CategoryLicensing::autoRenewEnabled();

        AgencyCategorySubscription::query()
            ->where('status', AgencyCategorySubscription::STATUS_ACTIVE)
            ->whereDate('expires_at', '>=', today())
            ->whereDate('expires_at', '<=', today()->addDays(self::REMINDER_DAYS_BEFORE))
            ->whereNull('renewal_reminder_sent_at')
            // İptal edilen aboneliğe "yenile" diye dürtme — bilinçli bitiriyor
            ->when(CategoryLicensing::autoRenewSchemaReady(), fn ($query) => $query->whereNull('cancelled_at'))
            // storedCard tablosu şema hazır değilse sorgulanamaz (guard şart)
            ->with(array_merge(
                ['agency.users', 'category'],
                CategoryLicensing::autoRenewSchemaReady() ? ['agency.storedCard'] : []
            ))
            ->chunkById(100, function ($subscriptions) use (&$count, $autoRenewEnabled) {
                foreach ($subscriptions as $subscription) {
                    // Otomatik yenileme bu aboneliği zaten çekecekse hatırlatma
                    // gereksiz (kart var + yenileme açık + bayrak açık).
                    if ($autoRenewEnabled
                        && (bool) ($subscription->auto_renew ?? true)
                        && $subscription->agency?->storedCard !== null) {
                        continue;
                    }

                    $users = $subscription->agency?->users ?? collect();
                    if ($users->isNotEmpty()) {
                        Notification::send($users, new CategorySubscriptionExpiringNotification($subscription));
                    }

                    $subscription->update(['renewal_reminder_sent_at' => now()]);

                    $this->line('Hatırlatma: #'.$subscription->id.' ('.($subscription->category?->name ?? '?').') → '.$subscription->expires_at->format('d.m.Y'));
                    $count++;
                }
            });

        return $count;
    }
}
