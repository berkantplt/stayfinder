<?php

namespace App\Notifications;

use App\Models\AgencyCategorySubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CategorySubscriptionExpiredNotification extends Notification
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

        return [
            'subscription_id' => $this->subscription->id,
            'category_id' => $this->subscription->category_id,
            'title' => 'Kategori Yetkiniz Sona Erdi',
            'message' => "{$categoryName} kategorisi yetkiniz sona erdi — bu kategorideki turlarınız yayından kaldırıldı. Yetkilendirme sayfasından yenileyebilirsiniz.",
            'url' => route('agency.category-licenses.index'),
            'icon' => '🔒',
        ];
    }
}
