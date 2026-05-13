<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DestinationProfile extends Model
{
    use HasFactory;

    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_LLM = 'llm';
    public const SOURCE_DEFAULT = 'default';

    protected $fillable = [
        'city',
        'normalized_city',
        'crowd_score',
        'liveliness_score',
        'source',
        'reasoning',
        'generated_at',
    ];

    protected $casts = [
        'crowd_score' => 'float',
        'liveliness_score' => 'float',
        'generated_at' => 'datetime',
    ];

    public static function normalize(string $city): string
    {
        // Önce büyük harfli Türkçe karakterleri ASCII'ye çevir
        // (mb_strtolower 'İ'yi combining-dot'lu 'i̇' yapıyor — onu önle)
        $normalized = strtr(trim($city), [
            'İ' => 'i', 'I' => 'i',
            'Ğ' => 'g', 'Ü' => 'u', 'Ş' => 's',
            'Ö' => 'o', 'Ç' => 'c',
            'ğ' => 'g', 'ü' => 'u', 'ş' => 's', 'ı' => 'i',
            'ö' => 'o', 'ç' => 'c',
        ]);

        $normalized = mb_strtolower($normalized, 'UTF-8');

        return preg_replace('/\s+/', ' ', $normalized);
    }
}
