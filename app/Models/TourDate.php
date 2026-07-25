<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TourDate extends Model
{
    protected $fillable = ['tour_id', 'departure_date', 'return_date', 'price', 'label'];

    protected $casts = [
        'departure_date' => 'date',
        'return_date'    => 'date',
        'price'          => 'decimal:2',
    ];

    protected static function booted(): void
    {
        // Chatbot envanteri satılabilirliği tarih listesinden okur — tarih
        // eklenince/silinince "nerelere turunuz var" cevabı bayat kalmasın
        static::saved(fn () => \App\Services\AiSearch\DestinationKnowledgeService::flushInventory());
        static::deleted(fn () => \App\Services\AiSearch\DestinationKnowledgeService::flushInventory());
    }

    public function tour(): BelongsTo { return $this->belongsTo(Tour::class); }
}
