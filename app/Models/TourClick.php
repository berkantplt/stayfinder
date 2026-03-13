<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourClick extends Model
{
    public $timestamps = false;

    protected $fillable = ['tour_id', 'agency_id', 'ip_address', 'clicked_at'];

    protected $casts = ['clicked_at' => 'datetime'];

    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }
    public function agency(): BelongsTo { return $this->belongsTo(Agency::class); }
}
