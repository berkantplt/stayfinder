<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Favorite extends Model
{
    protected $fillable = ['user_id', 'tour_id'];

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }
}
