<?php

namespace Database\Seeders;

use App\Models\Agency;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@stayfinder.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        // Agencies
        $agencyData = [
            ['name' => 'Jolly Tur', 'phone' => '0850 222 5655', 'email' => 'info@jollytur.com', 'website_url' => 'https://www.jollytur.com', 'description' => 'Türkiye\'nin önde gelen tur operatörü.'],
            ['name' => 'ETS Tur', 'phone' => '0850 811 8811', 'email' => 'info@etstur.com', 'website_url' => 'https://www.etstur.com', 'description' => 'Yurt içi ve yurt dışı tatil fırsatları.'],
            ['name' => 'Tatil Sepeti', 'phone' => '0850 724 7474', 'email' => 'info@tatilsepeti.com', 'website_url' => 'https://www.tatilsepeti.com', 'description' => 'Online tatil rezervasyon platformu.'],
            ['name' => 'Setur', 'phone' => '0850 222 0900', 'email' => 'info@setur.com.tr', 'website_url' => 'https://www.setur.com.tr', 'description' => 'Koç Topluluğu seyahat markası.'],
            ['name' => 'Touristica', 'phone' => '0212 410 0000', 'email' => 'info@touristica.com.tr', 'website_url' => 'https://www.touristica.com.tr', 'description' => 'Kültür ve doğa turları uzmanı.'],
            ['name' => 'Anı Tur', 'phone' => '0212 241 5781', 'email' => 'info@anitur.com.tr', 'website_url' => 'https://www.anitur.com.tr', 'description' => 'Avrupa ve uzak doğu turları.'],
            ['name' => 'Coral Travel', 'phone' => '0850 255 8900', 'email' => 'info@coraltravel.com.tr', 'website_url' => 'https://www.coraltravel.com.tr', 'description' => 'Her şey dahil tatil paketleri.'],
            ['name' => 'Odeon Tours', 'phone' => '0212 240 7000', 'email' => 'info@odeontours.com.tr', 'website_url' => 'https://www.odeontours.com.tr', 'description' => 'Kültür ve tarih odaklı turlar.'],
            ['name' => 'Biblo Tur', 'phone' => '0312 418 2484', 'email' => 'info@biblotur.com', 'website_url' => 'https://www.biblotur.com', 'description' => 'Ankara merkezli tur operatörü.'],
            ['name' => 'Tropik Tur', 'phone' => '0242 323 5050', 'email' => 'info@tropiktur.com', 'website_url' => 'https://www.tropiktur.com', 'description' => 'Antalya ve çevresi uzmanı.'],
        ];

        $agencies = [];
        foreach ($agencyData as $data) {
            $agency   = Agency::create($data);
            $agencies[] = $agency;

            // Create agency user
            User::create([
                'name'      => $data['name'] . ' Yönetici',
                'email'     => 'admin@' . strtolower(str_replace(' ', '', $data['name'])) . '.com',
                'password'  => Hash::make('password'),
                'role'      => 'agency',
                'agency_id' => $agency->id,
            ]);
        }

        // Tours
        $tours = [
            // Antalya
            ['title' => 'Antalya Her Şey Dahil Tatil', 'destination' => 'Antalya', 'duration_days' => 7, 'departure_date' => '2026-06-15', 'return_date' => '2026-06-22', 'included' => "Uçak bileti\nOtel konaklaması\nHer şey dahil\nTransfer", 'image' => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=600'],
            ['title' => 'Antalya Kemer Tatili', 'destination' => 'Antalya', 'duration_days' => 5, 'departure_date' => '2026-07-01', 'return_date' => '2026-07-06', 'included' => "Uçak bileti\nOtel\nYarım pansiyon\nTransfer", 'image' => 'https://images.unsplash.com/photo-1506929562872-bb421503ef21?w=600'],
            // İstanbul
            ['title' => 'İstanbul Kültür Turu', 'destination' => 'İstanbul', 'duration_days' => 3, 'departure_date' => '2026-04-10', 'return_date' => '2026-04-13', 'included' => "Otel\nKahvaltı\nRehberli gezi\nMüze biletleri", 'image' => 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?w=600'],
            ['title' => 'İstanbul Boğaz Turu', 'destination' => 'İstanbul', 'duration_days' => 2, 'departure_date' => '2026-05-20', 'return_date' => '2026-05-22', 'included' => "Otel\nBoğaz gezisi\nAkşam yemeği", 'image' => 'https://images.unsplash.com/photo-1541432901042-2d8bd64b4a9b?w=600'],
            // Kapadokya
            ['title' => 'Kapadokya Balon Turu', 'destination' => 'Kapadokya', 'duration_days' => 3, 'departure_date' => '2026-05-01', 'return_date' => '2026-05-04', 'included' => "Uçak\nOtel\nBalon turu\nRehberli gezi", 'image' => 'https://images.unsplash.com/photo-1641128324972-af3212f0f6bd?w=600'],
            // Ege
            ['title' => 'Bodrum Mavi Yolculuk', 'destination' => 'Bodrum', 'duration_days' => 4, 'departure_date' => '2026-08-10', 'return_date' => '2026-08-14', 'included' => "Tekne\nTam pansiyon\n12 koy gezisi", 'image' => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=600'],
            ['title' => 'Fethiye Ölüdeniz Tatili', 'destination' => 'Fethiye', 'duration_days' => 5, 'departure_date' => '2026-07-15', 'return_date' => '2026-07-20', 'included' => "Uçak\nOtel\nYamaç paraşütü\nTekne turu", 'image' => 'https://images.unsplash.com/photo-1527631746610-bca00a040d60?w=600'],
            // Yurt dışı
            ['title' => 'Yunanistan Adalar Turu', 'destination' => 'Yunanistan', 'duration_days' => 7, 'departure_date' => '2026-06-01', 'return_date' => '2026-06-08', 'included' => "Uçak\nFeribot\nOtel\nKahvaltı", 'image' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?w=600'],
            ['title' => 'Dubai Şehir Turu', 'destination' => 'Dubai', 'duration_days' => 5, 'departure_date' => '2026-03-15', 'return_date' => '2026-03-20', 'included' => "Uçak\nOtel\nÇöl safarisi\nAquaventure", 'image' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=600'],
            ['title' => 'Roma & Venedik Turu', 'destination' => 'İtalya', 'duration_days' => 6, 'departure_date' => '2026-09-10', 'return_date' => '2026-09-16', 'included' => "Uçak\nOtel\nRehber\nMüze biletleri", 'image' => 'https://images.unsplash.com/photo-1552832230-c0197dd311b5?w=600'],
            ['title' => 'Trabzon Uzungöl Turu', 'destination' => 'Trabzon', 'duration_days' => 4, 'departure_date' => '2026-08-01', 'return_date' => '2026-08-05', 'included' => "Uçak\nOtel\nGünübirlik turlar\nKahvaltı", 'image' => 'https://images.unsplash.com/photo-1596402184320-417e7178b2cd?w=600'],
            ['title' => 'Marmaris Tatili', 'destination' => 'Marmaris', 'duration_days' => 7, 'departure_date' => '2026-06-20', 'return_date' => '2026-06-27', 'included' => "Uçak\nHer şey dahil\nTransfer", 'image' => 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?w=600'],
        ];

        // URL patterns per agency
        $urlPatterns = [
            'Jolly Tur'    => 'https://www.jollytur.com/tur/',
            'ETS Tur'      => 'https://www.etstur.com/tur/',
            'Tatil Sepeti' => 'https://www.tatilsepeti.com/tur/',
            'Setur'        => 'https://www.setur.com.tr/tur/',
            'Touristica'   => 'https://www.touristica.com.tr/',
            'Anı Tur'      => 'https://www.anitur.com.tr/tur/',
            'Coral Travel' => 'https://www.coraltravel.com.tr/tur/',
            'Odeon Tours'  => 'https://www.odeontours.com.tr/tur/',
            'Biblo Tur'    => 'https://www.biblotur.com/tur/',
            'Tropik Tur'   => 'https://www.tropiktur.com/tur/',
        ];

        // Each tour offered by 2-4 random agencies at different prices
        foreach ($tours as $tour) {
            $numAgencies = rand(2, 4);
            $selected    = collect($agencies)->shuffle()->take($numAgencies);
            $basePrice   = rand(3000, 25000);
            $tourSlug    = \Illuminate\Support\Str::slug($tour['title']);

            foreach ($selected as $agency) {
                $priceVariation = $basePrice + rand(-1500, 3000);
                $priceVariation = max(1500, $priceVariation);
                $baseUrl = $urlPatterns[$agency->name] ?? $agency->website_url . '/tur/';

                Tour::create([
                    'agency_id'      => $agency->id,
                    'title'          => $tour['title'],
                    'destination'    => $tour['destination'],
                    'description'    => 'Bu muhteşem tur ile ' . $tour['destination'] . ' keşfedin. ' . $tour['duration_days'] . ' gün boyunca unutulmaz anılar biriktirin.',
                    'price'          => $priceVariation,
                    'currency'       => 'TRY',
                    'duration_days'  => $tour['duration_days'],
                    'departure_date' => $tour['departure_date'],
                    'return_date'    => $tour['return_date'],
                    'included'       => $tour['included'],
                    'image'          => $tour['image'],
                    'tour_url'       => $baseUrl . $tourSlug,
                    'is_active'      => true,
                ]);
            }
        }
    }
}
