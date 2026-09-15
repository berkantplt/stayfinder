<?php

namespace App\Notifications;

use App\Models\AgencyCategorySubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B12 — Admin manuel kategori yetkisi verdi (0 TL sipariş). Acenta panele
 * girmeden hangi kategoride ne kadar süre yetkisi olduğunu öğrensin.
 */
class CategoryAccessGrantedNotification extends Notification
{
    use Queueable;

    public function __construct(public AgencyCategorySubscription $subscription, public int $months) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $kategori = $this->subscription->category?->name ?? 'Kategori';
        $bitis = $this->subscription->expires_at?->format('d.m.Y') ?? '—';

        return [
            'subscription_id' => $this->subscription->id,
            'category_id' => $this->subscription->category_id,
            'title' => 'Kategori Yetkisi Tanımlandı',
            'message' => "{$kategori} kategorisinde {$this->months} ay süreyle yetkiniz tanımlandı (bitiş: {$bitis}). Bu kategoride tur ekleyebilirsiniz.",
            'url' => route('agency.category-licenses.index'),
            'icon' => '🎁',
        ];
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
}
