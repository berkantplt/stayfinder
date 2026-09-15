<?php

namespace App\Notifications;

use App\Models\Agency;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B11 — Admin acentayı aktif/pasif yaptı. Pasifleştirme turların tamamını
 * yayından kaldırır; acenta bunu tesadüfen fark ediyordu.
 */
class AgencyStatusChangedNotification extends Notification
{
    use Queueable;

    public function __construct(public Agency $agency) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $aktif = (bool) $this->agency->is_active;

        return [
            'agency_id' => $this->agency->id,
            'is_active' => $aktif,
            'title' => $aktif ? 'Acenta Hesabınız Yeniden Aktif' : 'Acenta Hesabınız Pasife Alındı',
            'message' => $aktif
                ? 'Hesabınız yeniden aktifleştirildi; yetkili kategorilerdeki turlarınız tekrar yayında.'
                : 'Hesabınız yönetici tarafından pasife alındı; turlarınız yayından kaldırıldı. Sebebini öğrenmek için bizimle iletişime geçin.',
            'url' => route('agency.dashboard'),
            'icon' => $aktif ? '🟢' : '⏸️',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $veri = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($veri['title'].' — turXtur')
            ->greeting('Merhaba '.($notifiable->name ?? '').',')
            ->line($veri['message'])
            ->action('Panele Git', $veri['url'])
            ->line('Bu e-posta, acenta hesabınızla ilişkili olduğu için gönderildi.');
    }
}
