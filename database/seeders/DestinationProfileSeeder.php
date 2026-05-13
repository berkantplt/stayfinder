<?php

namespace Database\Seeders;

use App\Models\DestinationProfile;
use Illuminate\Database\Seeder;

class DestinationProfileSeeder extends Seeder
{
    public function run(): void
    {
        // AiSearchController'daki orijinal hardcoded liste (manuel kalibre)
        $profiles = [
            'istanbul' => ['crowd' => 0.98, 'lively' => 0.90],
            'ankara' => ['crowd' => 0.90, 'lively' => 0.74],
            'izmir' => ['crowd' => 0.78, 'lively' => 0.82],
            'antalya' => ['crowd' => 0.74, 'lively' => 0.86],
            'bodrum' => ['crowd' => 0.68, 'lively' => 0.86],
            'marmaris' => ['crowd' => 0.66, 'lively' => 0.80],
            'fethiye' => ['crowd' => 0.58, 'lively' => 0.68],
            'kapadokya' => ['crowd' => 0.54, 'lively' => 0.62],
            'trabzon' => ['crowd' => 0.48, 'lively' => 0.34],
            'rize' => ['crowd' => 0.42, 'lively' => 0.35],
            'mardin' => ['crowd' => 0.50, 'lively' => 0.52],
            'eskisehir' => ['crowd' => 0.62, 'lively' => 0.76],
            'canakkale' => ['crowd' => 0.56, 'lively' => 0.64],
            'dubai' => ['crowd' => 0.86, 'lively' => 0.92],
            'londra' => ['crowd' => 0.88, 'lively' => 0.90],
            'paris' => ['crowd' => 0.86, 'lively' => 0.88],
            'roma' => ['crowd' => 0.80, 'lively' => 0.84],
            'new york' => ['crowd' => 0.92, 'lively' => 0.94],
        ];

        foreach ($profiles as $city => $scores) {
            DestinationProfile::updateOrCreate(
                ['normalized_city' => DestinationProfile::normalize($city)],
                [
                    'city' => $city,
                    'crowd_score' => $scores['crowd'],
                    'liveliness_score' => $scores['lively'],
                    'source' => DestinationProfile::SOURCE_MANUAL,
                    'reasoning' => 'Manuel kalibre',
                    'generated_at' => now(),
                ]
            );
        }
    }
}
