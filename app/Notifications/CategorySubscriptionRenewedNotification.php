<?php

namespace App\Notifications;

use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategorySubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Otomatik aylık yenileme başarılı: kayıtlı karttan çekim yapıldı. */
class CategorySubscriptionRenewedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public AgencyCategorySubscription $subscription,
        public AgencyCategoryOrder $order,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $categoryName = $this->subscription->category?->name ?? 'Kategori';
        $amount = number_format((float) $this->order->subtotal, 0, ',', '.');

        return [
            'subscription_id' => $this->subscription->id,
            'category_id' => $this->subscription->category_id,
            'order_id' => $this->order->id,
            'title' => 'Aboneliğiniz Otomatik Yenilendi',
            'message' => "{$categoryName} kategorisi aboneliğiniz 1 ay uzatıldı; kayıtlı kartınızdan {$amount} TL çekildi. Yeni bitiş: ".$this->subscription->expires_at?->format('d.m.Y').'.',
            'url' => route('agency.category-licenses.index'),
            'icon' => '🔄',
        ];
    }
}
