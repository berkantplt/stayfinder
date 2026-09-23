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

    // A9 — Rol değerleri tek yerden. Serbest metin olduğu dönemde DB'ye tanımsız
    // bir rol (ör. "user") girip sessizce müşteri gibi davranmıştı; artık kod bu
    // sabitleri kullanır, migration kolonu enum'a çevirir (MySQL).
    public const ROLE_ADMIN = 'admin';

    public const ROLE_AGENCY = 'agency';

    public const ROLE_VISITOR = 'visitor';

    public const ROLES = [self::ROLE_ADMIN, self::ROLE_AGENCY, self::ROLE_VISITOR];

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
            'password_set_at' => 'datetime', // sosyal giriş: kendi şifresini belirledi mi
            'pending_email_requested_at' => 'datetime', // D5
            'deletion_requested_at' => 'datetime', // D4
            'anonymized_at' => 'datetime', // D4
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
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAgency(): bool
    {
        return $this->role === self::ROLE_AGENCY;
    }

    /** Müşteri (ziyaretçi) hesabı — admin ve acenta dışındaki tek rol. */
    public function isCustomer(): bool
    {
        return $this->role === self::ROLE_VISITOR;
    }

    public function agencyApproved(): bool
    {
        return $this->isAgency() && $this->agency?->isApproved();
    }

    public function savedSearches()
    {
        return $this->hasMany(SavedSearch::class);
    }

    /** Bağlı Google / Apple hesapları. */
    public function socialAccounts()
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Kullanıcı kendi şifresini belirledi mi?
     *
     * password kolonuna bakmak YETMEZ: sosyal giriş ile açılan hesapta da dolu
     * (rastgele hash — bkz. add_password_set_at_to_users_table migration'ı).
     */
    public function hasPassword(): bool
    {
        return $this->password_set_at !== null;
    }

    /** Kendi şifresini belirledi/değiştirdi (kayıt, sıfırlama, profil). */
    public function markPasswordSet(): void
    {
        $this->forceFill(['password_set_at' => now()])->save();
    }

    /**
     * Mevcut şifre sorulmadan şifre belirleyebilir mi?
     *
     * Yalnız "sosyal hesabı bağlı VE hiç şifre belirlememiş" kullanıcı. İkinci
     * koşul bilerek var: ileride bir kayıt yolu password_set_at yazmayı unutursa
     * sıradan bir hesapta mevcut-şifre kontrolü sessizce atlanmasın (çalınmış bir
     * oturum şifreyi değiştirebilirdi).
     */
    public function canSetPasswordWithoutCurrent(): bool
    {
        return ! $this->hasPassword() && $this->socialAccounts()->exists();
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    /** D9 — sunucuya yazılan karşılaştırma listesi (localStorage ile eşitlenir). */
    public function compareTours()
    {
        return $this->belongsToMany(Tour::class, 'user_compare_tours')->withTimestamps();
    }

    public function favoriteTours()
    {
        return $this->belongsToMany(Tour::class, 'favorites')
            ->withPivot(['price_at_save', 'currency_at_save'])
            ->withTimestamps();
    }

    public function hasFavorited(Tour $tour): bool
    {
        return $this->favoriteTours()->where('tour_id', $tour->id)->exists();
    }

    /**
     * A7 — Üst menü rozeti (okunmamış bildirim + görülmemiş duyuru) her istekte
     * 2 COUNT sorgusuydu; 60 sn önbellek. Okundu/görüldü işlemleri anahtarı siler.
     */
    public function badgeCacheKey(): string
    {
        return 'rozet:'.$this->id;
    }

    public function forgetBadgeCache(): void
    {
        cache()->forget($this->badgeCacheKey());
    }
}
