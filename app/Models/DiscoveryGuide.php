<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * AI Keşif Rehberi: kullanıcının "şehir + gün sayısı" girdisinden üretilen,
 * günlere bölünmüş içerik planı. Harita/rota/navigasyon DEĞİLDİR — çıktı
 * tamamen editöryel bir gezi rehberidir (guide_payload JSON'ı).
 *
 * Sahiplik iki uçlu: giriş yapmış kullanıcıda user_id, misafirde session_id.
 * Rehber URL'si uuid ile çözülür.
 */
class DiscoveryGuide extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    /**
     * Tembel zaman aşımı eşiği: tek job denemesi en çok ~3 dk (timeout 180) ve
     * her deneme başında updated_at tazelenir; 10 dk'dır kıpırdamayan kayıt
     * job'ı kuyrukta kaybolmuş demektir (worker ölümü, deploy kesintisi).
     */
    public const STUCK_MINUTES = 10;

    /** Kabul edilen değer → arayüz etiketi. Validation da bu anahtarları kullanır. */
    public const TRAVELER_TYPES = [
        'solo' => 'Yalnız',
        'couple' => 'Sevgilimle',
        'friends' => 'Arkadaşlarımla',
        'family' => 'Ailemle',
        'with_kids' => 'Çocuklarla',
    ];

    public const INTERESTS = [
        'history' => 'Tarih',
        'museum' => 'Müze',
        'gastronomy' => 'Gastronomi',
        'nature' => 'Doğa',
        'entertainment' => 'Eğlence',
        'shopping' => 'Alışveriş',
        'art' => 'Sanat',
    ];

    public const PACES = [
        'relaxed' => 'Rahat',
        'normal' => 'Normal',
        'intense' => 'Yoğun',
    ];

    public const BUDGETS = [
        'economy' => 'Ekonomik',
        'standard' => 'Standart',
        'premium' => 'Premium',
    ];

    protected $fillable = [
        'uuid', 'user_id', 'session_id', 'destination_input', 'destination_id',
        'duration_days', 'traveler_type', 'interests', 'pace', 'budget',
        'status', 'guide_payload', 'error_message',
    ];

    protected $casts = [
        'duration_days' => 'integer',
        'interests' => 'array',
        'guide_payload' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (DiscoveryGuide $guide) {
            if (empty($guide->uuid)) {
                $guide->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Destination::class);
    }

    /**
     * Sahiplik kontrolü: kullanıcıya bağlı rehberi yalnız o kullanıcı, misafir
     * rehberini yalnız aynı oturum açar.
     */
    public function canBeAccessedBy(Request $request): bool
    {
        if ($this->user_id) {
            return $this->user_id === $request->user()?->id;
        }

        return $this->session_id === Str::limit($request->session()->getId(), 64, '');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isGenerating(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_PROCESSING], true);
    }

    /**
     * Kuyrukta kaybolan job'a karşı tembel zaman aşımı: cron gerektirmez,
     * rehbere bakan ilk istek (show/status) takılı kaydı failed'a çevirir ve
     * kullanıcı "Tekrar dene" ekranını görür. Job daha sonra yine de koşarsa
     * sorun yok: handle() rehberi taze okur ve geç kalmış başarı, hatayı ezer.
     */
    public function failIfStuck(): void
    {
        if ($this->isGenerating()
            && $this->updated_at
            && $this->updated_at->lt(now()->subMinutes(self::STUCK_MINUTES))) {
            $this->update([
                'status' => self::STATUS_FAILED,
                'error_message' => 'Üretim beklenenden uzun sürdü. Lütfen tekrar deneyin.',
            ]);
        }
    }

    /** Sonuç ekranındaki "Genel gezi • Normal tempo • ..." varsayım şeridi. */
    public function assumptionChips(): array
    {
        $chips = [];

        $chips[] = $this->traveler_type
            ? (self::TRAVELER_TYPES[$this->traveler_type] ?? $this->traveler_type)
            : 'Genel gezi';

        $chips[] = (self::PACES[$this->pace] ?? 'Normal').' tempo';
        $chips[] = (self::BUDGETS[$this->budget] ?? 'Standart').' bütçe';

        if (! $this->traveler_type) {
            $chips[] = 'İlk ziyaret';
        }

        return $chips;
    }
}
