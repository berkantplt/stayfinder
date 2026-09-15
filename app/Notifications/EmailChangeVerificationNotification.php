<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * D5 — Yeni e-posta adresine giden onay bağlantısı (on-demand: alıcı henüz
 * kullanıcı kaydında değil). Bağlantı 60 dk imzalı; onaylanana kadar eski
 * adres geçerli kalır.
 */
class EmailChangeVerificationNotification extends Notification
{
    use Queueable;

    public function __construct(public string $verifyUrl, public string $userName, public string $currentEmail) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('E-posta adresinizi onaylayın — turXtur')
            ->greeting('Merhaba '.$this->userName.',')
            ->line('turXtur hesabınızın e-posta adresini '.$this->currentEmail.' yerine bu adrese taşımak istediniz.')
            ->action('E-postamı onayla', $this->verifyUrl)
            ->line('Bağlantı 60 dakika geçerlidir. Onaylayana kadar eski adresiniz geçerli kalır.')
            ->line('Bu isteği siz yapmadıysanız bu e-postayı yok sayabilirsiniz; hesabınızda hiçbir şey değişmez.');
    }
}
