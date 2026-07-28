<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir turun rubrik puanları (brif §3): boyut başına {value: 1-5|null,
 * confidence, evidence}. value null ⇒ o boyut eşleştirmede devre dışı.
 * 0-100 dönüşümü kodda: (value-1)*25.
 */
class TourRubricScore extends Model
{
    /** İki geçiş ayrıştı ⇒ editör onayına düşer, canlı eşleştirmede kullanılmaz. */
    public const STATUS_NEEDS_REVIEW = 'needs_review';

    public const STATUS_AUTO = 'auto';

    public const STATUS_APPROVED = 'approved';

    protected $fillable = ['tour_id', 'rubric_version', 'input_hash', 'scores', 'review_status', 'scored_at'];

    protected $casts = [
        'scores' => 'array',
        'scored_at' => 'datetime',
    ];

    public function tour(): BelongsTo
    {
        return $this->belongsTo(Tour::class);
    }

    /** Eşleştirici için 0-100 değer; kanıt yoksa null. */
    public function value100(string $dimension): ?float
    {
        $value = $this->scores[$dimension]['value'] ?? null;

        return is_numeric($value) ? ((float) $value - 1) * 25 : null;
    }

    /** value null kalan boyutlar (eksik veri raporu için). */
    public function nullDimensions(): array
    {
        return array_keys(array_filter($this->scores ?? [], fn ($s) => ! is_numeric($s['value'] ?? null)));
    }
}
