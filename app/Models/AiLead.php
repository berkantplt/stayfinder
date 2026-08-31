<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AI sohbetinden toplanan sıcak lead (geri arama / opsiyon / fiyat alarmı).
 * Acenta panelinde listelenir; oluşturulunca acenta kullanıcılarına DB
 * bildirimi gider.
 *
 * NOT: leadleri üreten v1 sohbet asistanı kaldırıldı — mevcut kayıtlar GEÇMİŞ
 * veridir ve acenta panelinde görünmeye devam eder. conversation_id kolonu
 * veride duruyor ama artık bir modele işaret etmiyor (ilişki kaldırıldı).
 */
class AiLead extends Model
{
    public const INTENT_CALLBACK = 'geri_arama';
    public const INTENT_OPTION = 'opsiyon';
    public const INTENT_PRICE_ALERT = 'fiyat_alarmi';

    protected $fillable = [
        'conversation_id', 'tour_id', 'agency_id',
        'name', 'phone', 'intent', 'note', 'status',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function intentLabel(): string
    {
        return match ($this->intent) {
            self::INTENT_CALLBACK => 'Geri arama',
            self::INTENT_OPTION => 'Opsiyon talebi',
            self::INTENT_PRICE_ALERT => 'Fiyat alarmı',
            default => $this->intent,
        };
    }
}
