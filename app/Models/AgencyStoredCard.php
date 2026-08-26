<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * iyzico kart saklama token'ları — kart numarası DEĞİL, iyzico tarafındaki
 * kayda işaret eden anahtarlar. Yine de at-rest şifreli tutulur (APP_KEY'e
 * bağlı; bkz. buyer_snapshot ile aynı kural). Acenta başına tek kart.
 */
class AgencyStoredCard extends Model
{
    use HasFactory;

    protected $fillable = [
        'agency_id',
        'card_user_key',
        'card_token',
        'last_four',
        'card_association',
        'card_family',
    ];

    protected $casts = [
        'card_user_key' => 'encrypted',
        'card_token' => 'encrypted',
    ];

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function displayLabel(): string
    {
        $brand = trim((string) ($this->card_association ?: $this->card_family));

        return trim(($brand !== '' ? $brand.' ' : '').'•••• '.($this->last_four ?: '????'));
    }
}
