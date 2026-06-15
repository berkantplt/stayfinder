<?php

namespace App\Notifications;

use App\Models\AgencyCategorySubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CategorySubscriptionExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public AgencyCategorySubscription $subscription) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $categoryName = $this->subscription->category?->name ?? 'Kategori';
        $expiresAt = $this->subscription->expires_at;
        $daysLeft = (int) today()->diffInDays($expiresAt, false);

        return [
            'subscription_id' => $this->subscription->id,
            'category_id' => $this->subscription->category_id,
            'title' => 'Kategori Yetkiniz Doluyor',
            'message' => "{$categoryName} kategorisi yetkiniz {$expiresAt->format('d.m.Y')} tarihinde (".max(0, $daysLeft).' gün sonra) sona eriyor. Yenilemezseniz bu kategorideki turlarınız yayından kalkacak.',
            'url' => route('agency.category-licenses.index'),
            'icon' => '⏳',
        ];
    }
}
