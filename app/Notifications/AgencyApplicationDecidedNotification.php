<?php

namespace App\Notifications;

use App\Models\Agency;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * B11 — Acenta başvurusu onaylandı / reddedildi. Eskiden karar yalnız DB'ye
 * yazılıyordu; acenta durumu öğrenmek için başvuru sayfasını elle yeniliyordu.
 * Kanal: panel (database) + e-posta (mail; MAIL_MAILER=log iken yalnız loglanır).
 */
class AgencyApplicationDecidedNotification extends Notification
{
    use Queueable;

    public function __construct(public Agency $agency) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        $onaylandi = $this->agency->approval_status === Agency::STATUS_APPROVED;
        $not = trim((string) $this->agency->approval_notes);

        return [
            'agency_id' => $this->agency->id,
            'decision' => $this->agency->approval_status,
            'title' => $onaylandi ? 'Acenta Başvurunuz Onaylandı' : 'Acenta Başvurunuz Reddedildi',
            'message' => $onaylandi
                ? 'Paneliniz açıldı. Kategori yetkisi alıp tur eklemeye başlayabilirsiniz.'.($not !== '' ? " Not: {$not}" : '')
                : 'Başvurunuz onaylanmadı.'.($not !== '' ? " Gerekçe: {$not}" : '').' Sorularınız için bizimle iletişime geçebilirsiniz.',
            'url' => $onaylandi ? route('agency.dashboard') : route('agency.application.status'),
            'icon' => $onaylandi ? '✅' : '⛔',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $veri = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject($veri['title'].' — turXtur')
            ->greeting('Merhaba '.($notifiable->name ?? '').',')
            ->line($veri['message'])
            ->action($this->agency->approval_status === Agency::STATUS_APPROVED ? 'Panele Git' : 'Başvuru Durumu', $veri['url'])
            ->line('Bu e-posta, acenta hesabınızla ilişkili olduğu için gönderildi.');
    }
}
