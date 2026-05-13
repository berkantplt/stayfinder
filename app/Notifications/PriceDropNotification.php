<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class PriceDropNotification extends Notification
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
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'tour_id' => $this->tour->id,
            'title' => 'Fiyat Düştü!',
            'message' => "{$this->tour->title} turunun fiyatı düştü! Yeni fiyat: " . $this->tour->formatted_price,
            'url' => route('tours.show', $this->tour),
            'icon' => '📉'
        ];
    }
}
