<?php

namespace App\Notifications;

use App\Models\AiLead;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * AI sohbetinden yeni sıcak lead düştüğünde acenta kullanıcılarına DB bildirimi.
 */
class NewAiLeadNotification extends Notification
{
    use Queueable;

    public function __construct(public AiLead $lead) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $tourTitle = $this->lead->tour?->title;

        return [
            'lead_id' => $this->lead->id,
            'tour_id' => $this->lead->tour_id,
            'title' => 'AI sohbetinden yeni lead',
            'message' => $this->lead->intentLabel().': '.$this->lead->name.' ('.$this->lead->phone.')'
                .($tourTitle ? ' — '.$tourTitle : ''),
            'url' => route('agency.dashboard'),
            'icon' => '🤖',
        ];
    }
}
