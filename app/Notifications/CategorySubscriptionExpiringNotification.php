<?php

namespace App\Notifications;

use App\Models\AgencyCategorySubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CategorySubscriptionExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(public AgencyCategorySubscription $subscription) {}

    // A8 — Para hattı: panele girmeyen acenta bunu ancak e-postayla görür.
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $veri = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($veri['title'].' — turXtur')
            ->greeting('Merhaba '.($notifiable->name ?? '').',')
            ->line($veri['message'])
            ->action('Kategori Yetkilerine Git', $veri['url'])
            ->line('Bu e-posta, acenta hesabınızla ilişkili olduğu için gönderildi.');
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
            'url' => route('agency.category-licenses.index').'#abonelik-'.$this->subscription->id,
            'icon' => '⏳',
        ];
    }
}
