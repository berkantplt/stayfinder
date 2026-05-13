<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class NewTourNotification extends Notification
{
    use Queueable;

    public $tour;

    public function __construct($tour)
    {
        $this->tour = $tour;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tour_id' => $this->tour->id,
            'title' => 'Yeni Tur Eklendi!',
            'message' => "Yeni bir macera seni bekliyor: {$this->tour->title}. Hemen incele!",
            'url' => route('tours.show', $this->tour),
            'icon' => '✨'
        ];
    }
}
