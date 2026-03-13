<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourDate extends Model
{
    protected $fillable = ['tour_id', 'departure_date', 'return_date', 'label'];

    protected $casts = [
        'departure_date' => 'date',
        'return_date'    => 'date',
    ];

    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }
}
