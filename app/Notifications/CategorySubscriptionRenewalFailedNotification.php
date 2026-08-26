<?php

namespace App\Notifications;

use App\Models\AgencyCategorySubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Otomatik aylık yenileme başarısız: kart reddi/limit vb. Acenta dönem sonuna
 * kadar manuel yenileyebilir; yenilemezse turlar yayından kalkar.
 */
class CategorySubscriptionRenewalFailedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AgencyCategorySubscription $subscription,
        public string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $categoryName = $this->subscription->category?->name ?? 'Kategori';

        return [
            'subscription_id' => $this->subscription->id,
            'category_id' => $this->subscription->category_id,
            'title' => 'Otomatik Yenileme Başarısız',
            'message' => "{$categoryName} kategorisi aboneliğinizin otomatik yenilemesi başarısız oldu ({$this->reason}). ".$this->subscription->expires_at?->format('d.m.Y').' tarihine kadar manuel yenilemezseniz bu kategorideki turlarınız yayından kalkacak.',
            'url' => route('agency.category-licenses.index'),
            'icon' => '⚠️',
        ];
    }
}
