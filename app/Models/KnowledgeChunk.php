<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeChunk extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'title',
        'content',
        'content_hash',
        'embedding'
    ];

    protected $casts = [
        'embedding' => 'array',
    ];

    public function source()
    {
        return $this->morphTo();
    }
}
