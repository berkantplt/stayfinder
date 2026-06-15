<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public const TYPE_NEW_TOUR = 'new_tour';

    protected $fillable = [
        'type', 'tour_id', 'title', 'message', 'icon',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /**
     * Kullanıcının son görme zamanından sonra eklenen duyurular.
     * Yeni kullanıcılar kayıt öncesi duyuruları "okunmamış" görmesin diye
     * baseline olarak users.created_at kullanılır.
     */
    public function scopeUnseenBy($query, User $user)
    {
        return $query->where('created_at', '>', $user->announcements_seen_at ?? $user->created_at);
    }
}
