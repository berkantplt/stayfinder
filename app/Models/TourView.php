<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourView extends Model
{
    public $timestamps = false;

    protected $fillable = ['tour_id', 'session_id', 'user_id', 'viewed_at'];

    protected $casts = ['viewed_at' => 'datetime'];

    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }
}
