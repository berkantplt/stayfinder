<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            // Ana Kategoriler + Alt Kategoriler (ETS Tur, Jolly Tur, Tatilbudur, Touristica referanslı)
            [
                'name' => 'Kültür Turları', 'icon' => '🏛️', 'description' => 'Tarihi ve kültürel değerleri keşfedin',
                'children' => [
                    ['name' => 'Tarih & Arkeoloji', 'icon' => '🏺'],
                    ['name' => 'Müze & Sanat', 'icon' => '🎨'],
                    ['name' => 'Dini & Hac Turları', 'icon' => '🕌'],
                ]
            ],
            [
                'name' => 'Doğa Turları', 'icon' => '🏔️', 'description' => 'Doğanın kalbinde unutulmaz deneyimler',
                'children' => [
                    ['name' => 'Trekking & Yürüyüş', 'icon' => '🥾'],
                    ['name' => 'Kamp & Yayla', 'icon' => '⛺'],
                    ['name' => 'Safari & Doğa Gözlemi', 'icon' => '🦁'],
                ]
            ],
            [
                'name' => 'Deniz & Tekne', 'icon' => '⛵', 'description' => 'Masmavi sularda huzur ve eğlence',
                'children' => [
                    ['name' => 'Tekne Turu', 'icon' => '🚤'],
                    ['name' => 'Dalış & Su Sporları', 'icon' => '🤿'],
                    ['name' => 'Mavi Yolculuk', 'icon' => '🌊'],
                ]
            ],
            [
                'name' => 'Macera & Aktivite', 'icon' => '🪂', 'description' => 'Adrenalin dolu deneyimler sizi bekliyor',
                'children' => [
                    ['name' => 'Rafting & Kano', 'icon' => '🛶'],
                    ['name' => 'Yamaç Paraşütü', 'icon' => '🪂'],
                    ['name' => 'ATV & Off-Road', 'icon' => '🏎️'],
                ]
            ],
            [
                'name' => 'Gemi & Cruise', 'icon' => '🚢', 'description' => 'Lüks gemi seyahatleri ile dünyayı keşfedin',
                'children' => [
                    ['name' => 'Akdeniz Cruise', 'icon' => '🌅'],
                    ['name' => 'Yunan Adaları', 'icon' => '🏝️'],
                    ['name' => 'Kuzey Avrupa & Fiyort', 'icon' => '🧊'],
                ]
            ],
            [
                'name' => 'Şehir Turları', 'icon' => '🏙️', 'description' => 'Dünyanın en güzel şehirlerini keşfedin',
                'children' => [
                    ['name' => 'Avrupa Şehirleri', 'icon' => '🇪🇺'],
                    ['name' => 'Uzak Doğu', 'icon' => '🏯'],
                    ['name' => 'Amerika', 'icon' => '🗽'],
                ]
            ],
            [
                'name' => 'Kayak & Kış', 'icon' => '⛷️', 'description' => 'Kar keyfi ve kış sporları',
                'children' => [
                    ['name' => 'Kayak Turları', 'icon' => '🎿'],
                    ['name' => 'Termal & Spa', 'icon' => '♨️'],
                ]
            ],
            [
                'name' => 'Balayı & Romantik', 'icon' => '💕', 'description' => 'Hayalinizdeki romantik kaçamak',
                'children' => [
                    ['name' => 'Yurt İçi Balayı', 'icon' => '🌹'],
                    ['name' => 'Yurt Dışı Balayı', 'icon' => '✈️'],
                ]
            ],
            [
                'name' => 'Gastronomi & Lezzet', 'icon' => '🍷', 'description' => 'Yöresel lezzetler ve gurme deneyimleri',
            ],
            [
                'name' => 'Festival & Etkinlik', 'icon' => '🎉', 'description' => 'Konser, festival ve özel etkinlik turları',
            ],
            [
                'name' => 'Günübirlik Turlar', 'icon' => '📍', 'description' => 'Kısa ve öz keşif turları',
            ],
            [
                'name' => 'Hafta Sonu Kaçamağı', 'icon' => '🧳', 'description' => 'Hafta sonu için ideal kısa tatiller',
            ],
            [
                'name' => 'Aile & Çocuk', 'icon' => '👨‍👩‍👧‍👦', 'description' => 'Ailece keyifli tatil deneyimleri',
            ],
        ];

        $order = 1;
        foreach ($categories as $data) {
            $children = $data['children'] ?? [];
            unset($data['children']);

            $parent = Category::create([
                'name'        => $data['name'],
                'slug'        => Str::slug($data['name']),
                'icon'        => $data['icon'],
                'description' => $data['description'] ?? null,
                'sort_order'  => $order++,
            ]);

            $childOrder = 1;
            foreach ($children as $child) {
                Category::create([
                    'name'       => $child['name'],
                    'slug'       => Str::slug($child['name']),
                    'icon'       => $child['icon'],
                    'parent_id'  => $parent->id,
                    'sort_order' => $childOrder++,
                ]);
            }
        }
    }
}
