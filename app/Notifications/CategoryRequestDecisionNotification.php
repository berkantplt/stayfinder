<?php

namespace App\Notifications;

use App\Models\CategoryRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CategoryRequestDecisionNotification extends Notification
{
    use Queueable;

    public function __construct(public CategoryRequest $categoryRequest) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        if ($this->categoryRequest->status === CategoryRequest::STATUS_APPROVED) {
            return [
                'category_request_id' => $this->categoryRequest->id,
                'title' => 'Kategori Talebiniz Onaylandı',
                'message' => "'{$this->categoryRequest->requested_name}' kategori talebiniz onaylandı; artık turlarınızda kullanabilirsiniz.",
                'url' => route('agency.category-requests.index'),
                'icon' => '✅',
            ];
        }

        return [
            'category_request_id' => $this->categoryRequest->id,
            'title' => 'Kategori Talebiniz Reddedildi',
            'message' => "'{$this->categoryRequest->requested_name}' kategori talebiniz reddedildi."
                .($this->categoryRequest->admin_note ? ' Not: '.$this->categoryRequest->admin_note : ''),
            'url' => route('agency.category-requests.index'),
            'icon' => '🚫',
        ];
    }
}
