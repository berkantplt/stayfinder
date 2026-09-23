<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir kullanıcının bağlı Google / Apple hesabı.
 *
 * Eşleştirme daima provider + provider_user_id ile yapılır; e-posta yalnızca
 * gösterim ve ilk eşleştirme içindir (kullanıcı Gmail adresini değiştirebilir,
 * Apple'da adres gizli aktarma olabilir).
 */
class SocialAccount extends Model
{
    public const PROVIDER_GOOGLE = 'google';

    public const PROVIDER_APPLE = 'apple';

    public const PROVIDERS = [self::PROVIDER_GOOGLE, self::PROVIDER_APPLE];

    /** Butonlarda ve mesajlarda geçen okunur ad. */
    public const LABELS = [
        self::PROVIDER_GOOGLE => 'Google',
        self::PROVIDER_APPLE => 'Apple',
    ];

    protected $fillable = [
        'user_id', 'provider', 'provider_user_id', 'email', 'avatar', 'last_login_at',
    ];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->provider] ?? ucfirst($this->provider);
    }
}
