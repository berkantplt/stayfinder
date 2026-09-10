<?php

namespace App\Notifications;

use App\Models\SavedSearch;
use App\Models\Tour;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/** Kayıtlı aramaya uyan yeni tur(lar): site içi bildirim, sonuç sayfasına bağlantı. */
class SavedSearchMatchNotification extends Notification
{
    use Queueable;

    public function __construct(public SavedSearch $savedSearch, public int $count, public ?Tour $first = null) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $ilk = $this->first?->title;
        $message = $this->count === 1 && $ilk
            ? "{$this->savedSearch->name}: yeni tur eklendi — {$ilk}"
            : "{$this->savedSearch->name}: {$this->count} yeni tur eklendi".($ilk ? " ({$ilk} ve diğerleri)" : '');

        return [
            'saved_search_id' => $this->savedSearch->id,
            'title' => 'Aramana uyan yeni tur',
            'message' => $message,
            'url' => $this->savedSearch->url(),
            'icon' => '🔎',
        ];
    }
}
