<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public const TYPE_NEW_TOUR = 'new_tour';

    // A6 — Hedef kitle: duyuru hangi role gösterilir. Rol adlarıyla birebir.
    public const AUDIENCE_VISITOR = 'visitor';

    public const AUDIENCE_AGENCY = 'agency';

    public const AUDIENCE_ADMIN = 'admin';

    protected $fillable = [
        'type', 'audience', 'tour_id', 'title', 'message', 'icon',
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
        return $query
            ->forUser($user)
            ->where('created_at', '>', $user->announcements_seen_at ?? $user->created_at);
    }

    /**
     * Kullanıcının rolüne göre hedeflenmiş duyurular. Rol değeri audience ile
     * birebir (visitor/agency/admin); tanımsız rol müşteri duyurusu görür.
     */
    public function scopeForUser($query, User $user)
    {
        $audience = in_array($user->role, [self::AUDIENCE_AGENCY, self::AUDIENCE_ADMIN], true)
            ? $user->role
            : self::AUDIENCE_VISITOR;

        return $query->where('audience', $audience);
    }
}
