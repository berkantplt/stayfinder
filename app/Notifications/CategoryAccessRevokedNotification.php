<?php

namespace App\Notifications;

use App\Models\AgencyCategorySubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B12 — Admin kategori aboneliğini iptal etti: o kategorideki turlar anında
 * yayından kalkar. Gerekçe zorunlu; acenta neden düştüğünü bilsin.
 */
class CategoryAccessRevokedNotification extends Notification
{
    use Queueable;

    public function __construct(public AgencyCategorySubscription $subscription, public string $reason) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $kategori = $this->subscription->category?->name ?? 'Kategori';

        return [
            'subscription_id' => $this->subscription->id,
            'category_id' => $this->subscription->category_id,
            'title' => 'Kategori Yetkiniz İptal Edildi',
            'message' => "{$kategori} kategorisi yetkiniz yönetici tarafından iptal edildi; bu kategorideki turlarınız yayından kaldırıldı. Gerekçe: {$this->reason}",
            'url' => route('agency.category-licenses.index'),
            'icon' => '🔒',
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
