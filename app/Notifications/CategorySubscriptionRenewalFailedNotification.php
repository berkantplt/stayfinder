<?php

namespace App\Notifications;

use App\Models\AgencyCategorySubscription;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
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
