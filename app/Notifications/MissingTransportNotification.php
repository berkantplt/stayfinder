<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Ulaşım bilgisi eksik turlar için acentaya hatırlatma.
 *
 * Tek tur başına değil, acenta başına TEK bildirim: 20 turu eksik olan acentaya
 * 20 bildirim düşürmek paneli kullanılamaz hale getirirdi.
 */
class MissingTransportNotification extends Notification
{
    use Queueable;

    /** @param  array<int, string>  $tourTitles */
    public function __construct(
        public int $tourCount,
        public array $tourTitles
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $ornekler = implode(', ', array_slice($this->tourTitles, 0, 3));
        if ($this->tourCount > 3) {
            $ornekler .= ' ve '.($this->tourCount - 3).' tur daha';
        }

        return [
            'title' => 'Turlarınızda ulaşım bilgisi eksik',
            'message' => "{$this->tourCount} turunuzda ulaşım bilgisi (uçak/otobüs) girilmemiş. "
                ."Tur kartlarında görünmesi için düzenleme sayfasından seçebilirsiniz: {$ornekler}",
            'url' => route('agency.tours.index'),
            'icon' => '🚌',
            'tour_count' => $this->tourCount,
        ];
    }
}
