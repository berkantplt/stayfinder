<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = ['user_id', 'tour_id', 'rating', 'comment'];
    protected $casts    = ['rating' => 'integer'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }

    public function starsHtml(): string
    {
        $filled = str_repeat('⭐', $this->rating);
        $empty  = str_repeat('☆', 5 - $this->rating);
        return $filled . $empty;
    }
}
