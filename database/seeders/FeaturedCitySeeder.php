<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class FeaturedCitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $cities = [
            [
                'name' => 'Paris',
                'country' => 'Fransa',
                'images' => ['featured_cities/paris.png', 'featured_cities/paris_2.png', 'featured_cities/paris_3.png']
            ],
            [
                'name' => 'Roma',
                'country' => 'İtalya',
                'images' => ['featured_cities/rome.png', 'featured_cities/rome_2.png', 'featured_cities/rome_3.png']
            ],
            [
                'name' => 'New York',
                'country' => 'ABD',
                'images' => ['featured_cities/new_york.png', 'featured_cities/new_york_2.png', 'featured_cities/new_york_3.png']
            ],
            [
                'name' => 'Londra',
                'country' => 'İngiltere',
                'images' => ['featured_cities/london.png', 'featured_cities/london_2.png', 'featured_cities/london_3.png']
            ],
            [
                'name' => 'İstanbul',
                'country' => 'Türkiye',
                'images' => ['featured_cities/istanbul.png', 'featured_cities/istanbul_2.png', 'featured_cities/istanbul_3.png']
            ],
            [
                'name' => 'Dubai',
                'country' => 'BAE',
                'images' => ['featured_cities/dubai.png', 'featured_cities/dubai_2.png']
            ],
        ];

        foreach ($cities as $index => $cityData) {
            $city = \App\Models\FeaturedCity::create([
                'name' => $cityData['name'],
                'country' => $cityData['country'],
                'sort_order' => $index,
                'is_active' => true,
            ]);

            foreach ($cityData['images'] as $imgIndex => $imagePath) {
                $city->images()->create([
                    'image_path' => $imagePath,
                    'sort_order' => $imgIndex,
                ]);
            }
        }
    }
}
