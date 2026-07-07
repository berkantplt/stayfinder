<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Şifre Sıfırlama — turXtur')
            ->greeting('Merhaba '.$notifiable->name.',')
            ->line('Hesabınız için bir şifre sıfırlama isteği aldık.')
            ->action('Şifremi Sıfırla', $url)
            ->line('Bu bağlantı '.config('auth.passwords.users.expire').' dakika boyunca geçerlidir.')
            ->line('Bu isteği siz yapmadıysanız bu e-postayı yok sayabilirsiniz; şifreniz değişmez.')
            ->salutation('turXtur Ekibi');
    }
}
