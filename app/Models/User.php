<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'agency_id',
        'phone', 'avatar', 'city', 'bio', 'birth_date',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birth_date' => 'date',
            'announcements_seen_at' => 'datetime',
            'ai_preference' => 'array', // AI tercih profili (gece komutu üretir)
        ];
    }

    /**
     * Laravel'in İngilizce default maili yerine Türkçe sıfırlama maili.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isAgency(): bool
    {
        return $this->role === 'agency';
    }

    public function agencyApproved(): bool
    {
        return $this->isAgency() && $this->agency?->isApproved();
    }

    public function favoriteTours()
    {
        return $this->belongsToMany(Tour::class, 'favorites')->withTimestamps();
    }

    public function hasFavorited(Tour $tour): bool
    {
        return $this->favoriteTours()->where('tour_id', $tour->id)->exists();
    }
}
