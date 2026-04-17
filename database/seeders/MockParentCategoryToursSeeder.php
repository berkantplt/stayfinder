<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MockParentCategoryToursSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->toursData() as $data) {
            $dates = $data['dates'] ?? [];
            unset($data['dates']);
            $data['slug'] = Str::slug($data['title']) . '-' . Str::random(4);
            $tour = Tour::create($data);
            foreach ($dates as $date) {
                TourDate::create(array_merge($date, ['tour_id' => $tour->id]));
            }
        }
    }

    private function toursData(): array
    {
        return [

            // =====================================================
            // 1 - Kültür Turları
            // =====================================================
            [
                'agency_id' => 2, 'category_id' => 1,
                'title' => 'Mardin ve Midyat Kültür Turu – Taş Evler ve Süryani Mirası',
                'destination' => 'Mardin',
                'description' => '<p>Güneydoğu Anadolu\'nun mistik şehri Mardin\'de, sarı taşlardan örülü tarihi sokaklar ve çok kültürlü mirasın izlerini takip ediyoruz. Süryani Ortodoks kiliseleri, Ulu Camii, antika çarşısı ve ovayı seyreden Mardin Kalesi\'nden sonra Midyat\'taki Mor Gabriel Manastırı\'nı ziyaret ediyoruz.</p><p>Deyrulzafaran Manastırı\'nda MÖ 4. yüzyıldan bu yana Süryani Hristiyanların yurdu olan bu kadim mekânda dua odaları ve yeraltı bölümlerini geziyoruz. Mardin Müzesi\'nde bölgenin 10.000 yıllık tarihine ışık tutan eserleri inceliyoruz. Konaklama tarihi bir konak otelde, yemekler ise yerel Kürt ve Arap mutfağından seçilmiş lezzetlerle sağlanmaktadır.</p>',
                'price' => 7200.00, 'currency' => 'TRY', 'duration_days' => 3,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1549880338-65ddcdfd017b?w=800',
                'included' => "Uçak bileti (İstanbul – Mardin – İstanbul)\n2 gece tarihi konak otel (kahvaltı dahil)\nTüm rehberli turlar\nMor Gabriel ve Deyrulzafaran manastır girişleri\nMardin Müzesi girişi\nŞehir içi transferler",
                'excluded' => "Öğle ve akşam yemekleri\nKişisel harcamalar",
                'tour_url' => 'https://etstur.com',
                'dates' => [
                    ['departure_date' => '2026-05-08', 'return_date' => '2026-05-10', 'price' => 7200.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-09-25', 'return_date' => '2026-09-27', 'price' => 7500.00, 'label' => 'Eylül'],
                ],
            ],
            [
                'agency_id' => 3, 'category_id' => 1,
                'title' => 'Efes Antik Kenti ve Şirince Köyü Kültür Turu',
                'destination' => 'Selçuk, İzmir',
                'description' => '<p>UNESCO Dünya Mirası Listesi\'nde yer alan Efes Antik Kenti, antik dünyanın en iyi korunmuş şehirlerinden biridir. Celcus Kütüphanesi, tiyatro, mermer caddeler ve Artemis Tapınağı kalıntılarını uzman arkeolog rehberimiz eşliğinde keşfediyoruz.</p><p>Öğleden sonra bağ bozumunun kalbi Şirince köyüne geçiyoruz. 19. yüzyıldan kalma Rum evleri, taş sokaklar, meyve şarapları ve el yapımı sabunlarıyla ünlü bu köyde öğle yemeği ve serbest alışveriş vakti tanınmaktadır. Antik çağdan modern köy yaşamına, tek günde iki farklı dünya.</p>',
                'price' => 2200.00, 'currency' => 'TRY', 'duration_days' => 1,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1558618047-f4e4c4e47dfb?w=800',
                'included' => "İzmir – Efes – Şirince – İzmir transferleri\nEfes Antik Kenti girişi\nUzman arkeolog rehber\nÖğle yemeği (Şirince'de)\nSigorta",
                'excluded' => "Efes Müzesi girişi (isteğe bağlı)\nKişisel harcamalar ve alışveriş",
                'tour_url' => 'https://tatilsepeti.com',
                'dates' => [
                    ['departure_date' => '2026-04-25', 'return_date' => '2026-04-25', 'price' => 2200.00, 'label' => 'Nisan'],
                    ['departure_date' => '2026-05-16', 'return_date' => '2026-05-16', 'price' => 2200.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-13', 'return_date' => '2026-06-13', 'price' => 2400.00, 'label' => 'Haziran'],
                ],
            ],

            // =====================================================
            // 5 - Doğa Turları
            // =====================================================
            [
                'agency_id' => 4, 'category_id' => 5,
                'title' => 'Kaçkar Dağları Doğa Turu – Yaylalar ve Buz Gölleri',
                'destination' => 'Kaçkar Dağları, Rize',
                'description' => '<p>Doğu Karadeniz\'in saklı cenneti Kaçkar Dağları\'nda, 3000 metre yüksekliğindeki yaylalar ve buz göllerini keşfediyoruz. Ayder Yaylası\'nın termal sularından başlayan yolculuğumuzda Pokut, Hazindağ ve Sal Yaylası\'na kadar uzanan parkurlarda yürüyüş yapıyoruz.</p><p>Her yayla, kendine özgü mimarisi ve yöresel mutfağıyla ayrı bir deneyim sunuyor. Akşamları katır kiremit çatılı yayla evlerinde ya da eko-bungalovlarda konaklıyoruz. Sabah sisinin dağıldığı anlarda Kaçkar\'ın görkemli silüetini izlemek bu turun en unutulmaz anlarından biridir.</p>',
                'price' => 8500.00, 'currency' => 'TRY', 'duration_days' => 5,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=800',
                'included' => "Trabzon – Ayder transferleri\n4 gece yayla/eko-bungalov konaklaması (yarım pansiyon)\nDeneyimli dağcı rehber\nKatır eşliğinde bagaj taşıma\nYürüyüş parkuru izinleri",
                'excluded' => "Trabzon'a geliş-dönüş ulaşım\nKişisel yürüyüş ekipmanı\nKişisel harcamalar",
                'tour_url' => 'https://setur.com.tr',
                'dates' => [
                    ['departure_date' => '2026-07-05', 'return_date' => '2026-07-09', 'price' => 8500.00, 'label' => 'Temmuz'],
                    ['departure_date' => '2026-08-09', 'return_date' => '2026-08-13', 'price' => 9000.00, 'label' => 'Ağustos'],
                ],
            ],
            [
                'agency_id' => 5, 'category_id' => 5,
                'title' => 'Pamukkale ve Hierapolis Doğa & Antik Kent Turu',
                'destination' => 'Pamukkale, Denizli',
                'description' => '<p>Türkiye\'nin en özgün doğa harikalarından biri olan Pamukkale\'nin pamuk gibi beyaz travertenlerinde yürüyor, kalsiyum bikarbonat dolu sıcak sularında ayaklarınızı dinlendiriyorsunuz. Travertenlerin hemen üzerinde yükselen Hierapolis Antik Kenti\'nde Roma hamamları, tiyatro ve nekropolü keşfediyoruz.</p><p>Antik Havuz\'da (Kleopatra Havuzu) yüzme deneyimi ve günbatımında traverten platformlarının al renge büründüğü manzara bu turun en büyülü anlarıdır. Konaklama Pamukkale\'de termal havuzlu otelde sağlanmaktadır.</p>',
                'price' => 4800.00, 'currency' => 'TRY', 'duration_days' => 2,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1524231757912-21f4fe3a7200?w=800',
                'included' => "İstanbul – Denizli – İstanbul uçak bileti\n1 gece termal havuzlu otel (kahvaltı dahil)\nPamukkale travertenler giriş ücreti\nHierapolis Antik Kenti girişi\nKleopatra Antik Havuzu girişi\nRehberli tur",
                'excluded' => "Öğle yemeği\nKişisel harcamalar",
                'tour_url' => 'https://touristica.com.tr',
                'dates' => [
                    ['departure_date' => '2026-05-02', 'return_date' => '2026-05-03', 'price' => 4800.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-06', 'return_date' => '2026-06-07', 'price' => 5100.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-09-19', 'return_date' => '2026-09-20', 'price' => 4600.00, 'label' => 'Eylül'],
                ],
            ],

            // =====================================================
            // 9 - Deniz & Tekne
            // =====================================================
            [
                'agency_id' => 6, 'category_id' => 9,
                'title' => 'Antalya Körfezi Günlük Tekne Turu – Sualtı Mağaraları ve Koylar',
                'destination' => 'Antalya',
                'description' => '<p>Antalya\'nın kristal berraklığındaki körfezinde günlük tekne turumuza katılarak en gizli koy ve mağaraları keşfediyoruz. Düden Şelalesi\'nin denize döküldüğü noktaya yanaşmak, Karanlık Koyu\'nun sualtı mağarasında şnorkel yapmak ve Phaselis antik limanında durmak bu turun öne çıkan uğrak noktaları arasındadır.</p><p>Teknemizde öğle yemeği ikram edilmekte, denizin ortasında ızgara et ve taze balık sunulmaktadır. Gün boyunca 5 farklı koy ve 2 mağarada demir atıyoruz. Çocuk dostu atmosferi ve güvenli rotasıyla aileler için de idealdir.</p>',
                'price' => 1400.00, 'currency' => 'TRY', 'duration_days' => 1,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1533105079780-92b9be482077?w=800',
                'included' => "Antalya limanından kalkış\nTüm gün tekne turu (09:00-18:00)\nÖğle yemeği (ızgara et + salata)\nAlkolsüz içecekler\nŞnorkel ekipmanı\nCan yeleği",
                'excluded' => "Otel transferleri\nAlkollü içecekler\nKişisel harcamalar",
                'tour_url' => 'https://anitur.com.tr',
                'dates' => [
                    ['departure_date' => '2026-05-04', 'return_date' => '2026-05-04', 'price' => 1400.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-01', 'return_date' => '2026-06-01', 'price' => 1400.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-07-06', 'return_date' => '2026-07-06', 'price' => 1600.00, 'label' => 'Temmuz'],
                    ['departure_date' => '2026-08-03', 'return_date' => '2026-08-03', 'price' => 1600.00, 'label' => 'Ağustos'],
                ],
            ],
            [
                'agency_id' => 7, 'category_id' => 9,
                'title' => 'Çeşme\'den Yunan Adaları Tekne Turu – Sakız ve Midilli',
                'destination' => 'Çeşme, İzmir',
                'description' => '<p>Çeşme\'den kalkan feribotlarla Yunan adası Sakız\'a (Chios) geçiyor, mahlep ağaçlarının gölgesinde ortaçağ köyleri ve Ceneviz Kalesi\'ni keşfediyoruz. İkinci gün Midilli\'de (Lesbos) zeytinlikler arasında yürüyüş yapıyor, taş yapılı köy evleri ve balıkçı tavernaları arasında yerel deneyim yaşıyoruz.</p><p>Her iki ada da Türkiye\'ye vizesiz girilen Yunan adalarıdır; pasaport yeterlidir. Deniz tutmayanlar için feribotun güverte barında kahve ve atıştırmalık imkânı bulunmaktadır. Tura en az 2 kişilik katılım şartı aranmaktadır.</p>',
                'price' => 5600.00, 'currency' => 'TRY', 'duration_days' => 2,
                'is_active' => true, 'is_international' => true, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?w=800',
                'included' => "Çeşme – Sakız – Midilli – Çeşme feribot biletleri\n1 gece Sakız butik otel (kahvaltı dahil)\nSakız ve Midilli rehberli şehir turları\nCeneviz Kalesi girişi\nTüm liman vergileri",
                'excluded' => "Çeşme'ye geliş-dönüş ulaşım\nÖğle ve akşam yemekleri\nKişisel harcamalar",
                'tour_url' => 'https://coraltravel.com.tr',
                'dates' => [
                    ['departure_date' => '2026-05-23', 'return_date' => '2026-05-24', 'price' => 5600.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-20', 'return_date' => '2026-06-21', 'price' => 5900.00, 'label' => 'Haziran'],
                ],
            ],

            // =====================================================
            // 13 - Macera & Aktivite
            // =====================================================
            [
                'agency_id' => 8, 'category_id' => 13,
                'title' => 'Antalya Macera Paketi – Rafting, Zipline ve Kanyon Yürüyüşü',
                'destination' => 'Antalya',
                'description' => '<p>Türkiye\'nin macera başkenti Antalya\'da üç farklı aktiviteyi tek pakette sunuyoruz. Sabah Köprülü Kanyon\'da rafting, öğleden sonra Tazı Kanyonu\'nda zipline ve güneş batmadan önce Güver Uçurumu\'nda kanyon yürüyüşü yapıyoruz.</p><p>Her aktivite için güvenlik brifingi verilmekte ve tüm ekipman temin edilmektedir. Tecrübeli eğitmenlerimiz gruba eşlik ederek güvenli ve eğlenceli bir deneyim sunmaktadır. Aktiviteler arası transferler ve öğle yemeği de pakete dahildir.</p>',
                'price' => 3200.00, 'currency' => 'TRY', 'duration_days' => 1,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=800',
                'included' => "Antalya şehir merkezi transferleri\nRafting ekipmanı + rehber\nZipline (600 metre)\nKanyon yürüyüşü rehberi\nÖğle yemeği (nehir kenarı mangal)\nSigorta",
                'excluded' => "Kişisel harcamalar\nFotoğraf/video paketi (isteğe bağlı)",
                'tour_url' => 'https://odeontours.com',
                'dates' => [
                    ['departure_date' => '2026-05-09', 'return_date' => '2026-05-09', 'price' => 3200.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-06', 'return_date' => '2026-06-06', 'price' => 3400.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-07-11', 'return_date' => '2026-07-11', 'price' => 3400.00, 'label' => 'Temmuz'],
                ],
            ],
            [
                'agency_id' => 9, 'category_id' => 13,
                'title' => 'Bolu Abant – Yedigöller Doğa Yürüyüşü ve Kamp',
                'destination' => 'Bolu',
                'description' => '<p>Bolu\'nun eşsiz doğasında, Abant Gölü çevresi ve Yedigöller Milli Parkı\'nda iki günlük bir yürüyüş ve kamp macerası yaşıyoruz. Sonbahar renkleri veya bahar yeşilliğinin hüküm sürdüğü ormanlık parkurlarda yürürken berrak gölleri, küçük şelaleleri ve yaban hayatını izliyoruz.</p><p>Abant\'ta gün batımında kano deneyimi ve Yedigöller\'de sabah sisi ortasında çadır sabahı unutulmaz anların başında geliyor. Doğa fotoğrafçıları için özellikle güz mevsiminde muhteşem ışık koşulları sunan bu parkurlar aynı zamanda orta güçlükte yürüyüş tercih edenler için idealdir.</p>',
                'price' => 3800.00, 'currency' => 'TRY', 'duration_days' => 2,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1501854140801-50d01698950b?w=800',
                'included' => "İstanbul – Bolu – İstanbul özel araç\n1 gece kamp çadırı (uyku tulumu dahil)\nSabah kahvaltısı + akşam kamp yemeği\nKano turu (Abant Gölü)\nDeneyimli doğa rehberi\nSigorta",
                'excluded' => "Öğle yemekleri\nKişisel yürüyüş ekipmanı\nKişisel harcamalar",
                'tour_url' => 'https://biblotur.com.tr',
                'dates' => [
                    ['departure_date' => '2026-05-16', 'return_date' => '2026-05-17', 'price' => 3800.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-10-10', 'return_date' => '2026-10-11', 'price' => 3800.00, 'label' => 'Ekim Sonbahar'],
                ],
            ],

            // =====================================================
            // 17 - Gemi & Cruise
            // =====================================================
            [
                'agency_id' => 10, 'category_id' => 17,
                'title' => 'Karadeniz Cruise – Trabzon, Batum ve Soçi 7 Gece',
                'destination' => 'Karadeniz',
                'description' => '<p>Karadeniz\'in az keşfedilen güzelliklerini cruise gemisiyle keşfedin. İstanbul\'dan hareket eden gemimiz Trabzon\'un yeşil ovalarına, Gürcistan\'ın inci şehri Batum\'un çılgın mimarisine ve Rus Rivierası Soçi\'nin palmiye kıyılarına yanaşmaktadır.</p><p>Trabzon\'da Sümela Manastırı ziyareti, Batum\'da modern mimari ve Gürcü mutfağı deneyimi, Soçi\'de ise Olimpiyat Köyü ve Rosa Khutor kayak merkezi görünüme öne çıkan duraklar arasındadır. Gemide çeşitli restoranlar, havuz ve gece eğlence programı bulunmaktadır.</p>',
                'price' => 48000.00, 'currency' => 'TRY', 'duration_days' => 8,
                'is_active' => true, 'is_international' => true, 'requires_visa' => true,
                'image' => 'https://images.unsplash.com/photo-1548574505-5e239809ee19?w=800',
                'included' => "İstanbul kalkışlı cruise (7 gece iç kabin)\nTam pansiyon (gemide)\nTrabzon, Batum ve Soçi liman transferleri\nSümela Manastırı tur girişi\nVize danışmanlığı (Rusya için)",
                'excluded' => "Rusya vizesi ücreti (~50 USD)\nEkstra şehir turları\nAlkollü içecekler\nKişisel harcamalar",
                'tour_url' => 'https://tropiktur.com',
                'dates' => [
                    ['departure_date' => '2026-06-07', 'return_date' => '2026-06-14', 'price' => 48000.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-08-01', 'return_date' => '2026-08-08', 'price' => 52000.00, 'label' => 'Ağustos'],
                ],
            ],
            [
                'agency_id' => 1, 'category_id' => 17,
                'title' => 'Dubai ve Abu Dabi Cruise – Körfez\'de 5 Gece Lüks Yolculuk',
                'destination' => 'Dubai, BAE',
                'description' => '<p>Körfez\'in ihtişamlı şehirleri Dubai ve Abu Dabi arasında gerçekleştireceğiniz bu cruise yolculuğunda modern mimarinin sınırlarını zorlayan yapılar, lüks alışveriş merkezleri ve geleneksel Arap kültürünün bir arada yaşandığı eşsiz bir deneyim yaşayacaksınız.</p><p>Dubai\'de Burj Khalifa, Dubai Mall ve eski Dubai (Deira) çarşısını; Abu Dabi\'de ise dünyanın en büyük camilerinden Şeyh Zayed Camii\'ni ve Louvre Abu Dabi\'yi ziyaret ediyoruz. Gemide her gece farklı bir tema ile gala yemeği düzenlenmektedir.</p>',
                'price' => 72000.00, 'currency' => 'TRY', 'duration_days' => 6,
                'is_active' => true, 'is_international' => true, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1512453979798-5ea266f8880c?w=800',
                'included' => "İstanbul – Dubai – İstanbul uçak bileti\n5 gece cruise (dış kabin)\nTam pansiyon\nBurj Khalifa girişi (124. kat)\nŞeyh Zayed Camii ziyareti\nLouvre Abu Dabi girişi\nDubai çöl safarisi",
                'excluded' => "Alkollü içecekler\nDubai Mall ekstra aktiviteleri\nKişisel harcamalar",
                'tour_url' => 'https://jollytur.com',
                'dates' => [
                    ['departure_date' => '2026-11-07', 'return_date' => '2026-11-12', 'price' => 72000.00, 'label' => 'Kasım'],
                    ['departure_date' => '2026-12-26', 'return_date' => '2026-12-31', 'price' => 85000.00, 'label' => 'Yılbaşı'],
                ],
            ],

            // =====================================================
            // 21 - Şehir Turları
            // =====================================================
            [
                'agency_id' => 2, 'category_id' => 21,
                'title' => 'Tokyo ve Osaka Şehir Turu – Japonya\'da 10 Gün',
                'destination' => 'Tokyo, Osaka, Japonya',
                'description' => '<p>Modern ile gelenekselin mükemmel uyum içinde bir arada yaşadığı Japonya\'nın iki büyük şehrini keşfediyoruz. Tokyo\'da Shibuya\'nın kaotik kavşaklarından, Senso-ji Tapınağı\'nın sakin avlusuna, Akihabara\'nın elektronik cennетinden Harajuku\'nun renkli sokak modasına kadar şehrin tüm yüzleriyle tanışıyoruz.</p><p>Shinkansen (mermi tren) ile 2,5 saatte Osaka\'ya geçiyor; Namba\'nın gece hayatı, Dotonbori\'nin dev neon tabelaları ve sokak mutfağı lezzetleriyle şehri keşfediyoruz. Osaka Kalesi ve Kyoto günübirlik ziyareti de programa dahildir.</p>',
                'price' => 115000.00, 'currency' => 'TRY', 'duration_days' => 10,
                'is_active' => true, 'is_international' => true, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1540959733332-eab4deabeeaf?w=800',
                'included' => "İstanbul – Tokyo – Osaka – İstanbul uçak bileti\n5 gece Tokyo 4 yıldızlı otel (kahvaltı)\n4 gece Osaka 4 yıldızlı otel (kahvaltı)\nTokyo – Osaka Shinkansen bileti\nSenso-ji ve Osaka Kalesi turları\nKyoto günübirlik tur",
                'excluded' => "Öğle ve akşam yemekleri\nJaponya ICCard ulaşım kartı\nKişisel harcamalar",
                'tour_url' => 'https://etstur.com',
                'dates' => [
                    ['departure_date' => '2026-04-01', 'return_date' => '2026-04-10', 'price' => 120000.00, 'label' => 'Kiraz Çiçeği Dönemi'],
                    ['departure_date' => '2026-09-12', 'return_date' => '2026-09-21', 'price' => 112000.00, 'label' => 'Eylül'],
                    ['departure_date' => '2026-11-14', 'return_date' => '2026-11-23', 'price' => 110000.00, 'label' => 'Sonbahar Yaprakları'],
                ],
            ],
            [
                'agency_id' => 3, 'category_id' => 21,
                'title' => 'New York Şehir Turu – Manhattan ve Brooklyn\'de 6 Gün',
                'destination' => 'New York, ABD',
                'description' => '<p>Dünyanın en heyecan verici şehri New York\'ta 6 gün boyunca Manhattan\'ın çılgın temposunu yaşıyoruz. Times Square\'in ışıltısı, Central Park\'ın huzuru, Brooklyn Köprüsü\'nün muhteşem manzarası, MOMA\'nın sanat hazineleri ve Özgürlük Heykeli\'nin sembolik duruşu bu turda sizi bekliyor.</p><p>Sabahları rehberli tematik city walk\'larla (Milyarderler Sırası, Sanat Müzeleri, Tarihi Downtown), öğleden sonra serbest keşif vaktiyle New York\'u hem derinlemesine hem de kendi ritminizde yaşıyorsunuz. Brooklyn\'de yerel çarşı ve food market ziyareti de programa dahil.</p>',
                'price' => 78000.00, 'currency' => 'TRY', 'duration_days' => 6,
                'is_active' => true, 'is_international' => true, 'requires_visa' => true,
                'image' => 'https://images.unsplash.com/photo-1534430480872-3498386e7856?w=800',
                'included' => "İstanbul – New York – İstanbul uçak bileti\n5 gece Manhattan 4 yıldızlı otel (kahvaltı dahil)\nSabahları rehberli city walk\'lar\nÖzgürlük Heykeli feribot bileti\nTOP of the ROCK girişi\nMOMA müze girişi\nMetro kartı (5 günlük)",
                'excluded' => "ABD vizesi (ESTA ~21 USD veya vize)\nÖğle ve akşam yemekleri\nKişisel harcamalar",
                'tour_url' => 'https://tatilsepeti.com',
                'dates' => [
                    ['departure_date' => '2026-06-06', 'return_date' => '2026-06-11', 'price' => 78000.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-09-05', 'return_date' => '2026-09-10', 'price' => 75000.00, 'label' => 'Eylül'],
                ],
            ],

            // =====================================================
            // 25 - Kayak & Kış
            // =====================================================
            [
                'agency_id' => 4, 'category_id' => 25,
                'title' => 'Erzurum Palandöken Kayak Turu – 3 Gece Tam Ekipman',
                'destination' => 'Palandöken, Erzurum',
                'description' => '<p>Türkiye\'nin en yüksek ve karı en bol kayak merkezlerinden Palandöken\'de üç gecelik yoğun bir kayak tatiline çıkıyoruz. 3100 metreye kadar çıkan pistler, Türk kayak sezonunun en uzun sürdüğü bu merkezi hem yeni başlayanlar hem de ileri düzey kayakçılar için cazip kılıyor.</p><p>Otelden pistlere sıfır mesafe. Her sabah ekipmanlarınızı hazırlayıp doğrudan piste geçiyorsunuz. Öğle yenirken pist üstü restoranda dağ manzarası eşliğinde kuzu tandır keyfi yapıyorsunuz. Akşamları otel saunasında kaslarınızı dinlendiriyorsunuz.</p>',
                'price' => 12500.00, 'currency' => 'TRY', 'duration_days' => 4,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1548075534-7e8fc6efbcb2?w=800',
                'included' => "İstanbul – Erzurum – İstanbul uçak bileti\n3 gece piste sıfır otel (tam pansiyon)\n3 günlük kayak pist pass\nKayak ve bot kiralama\nBaşlangıç düzeyinde kayak dersi (2 saat)",
                'excluded' => "Kişisel kıyafet ve ekipman\nİleri düzey dersler\nKişisel harcamalar",
                'tour_url' => 'https://setur.com.tr',
                'dates' => [
                    ['departure_date' => '2026-12-26', 'return_date' => '2026-12-29', 'price' => 14000.00, 'label' => 'Yılbaşı'],
                    ['departure_date' => '2027-01-17', 'return_date' => '2027-01-20', 'price' => 12500.00, 'label' => 'Ocak 2027'],
                    ['departure_date' => '2027-02-07', 'return_date' => '2027-02-10', 'price' => 12500.00, 'label' => 'Şubat 2027'],
                ],
            ],
            [
                'agency_id' => 5, 'category_id' => 25,
                'title' => 'Finlandiya Kış Masalı – Lapland\'da Aurora ve Ren Geyiği Safarisi',
                'destination' => 'Lapland, Finlandiya',
                'description' => '<p>Noel Baba\'nın memleketi Rovaniemi\'de ve Lapland\'ın sonsuz kar ormanlarında kuzey ışıklarını izleyeceğiniz, ren geyiği kızağıyla süreceğiniz ve camdan tavanı olan iglu kulübede sabahın erken saatlerine kadar Aurora Borealis seyrederek uyuyacağınız bu sihirli kış turuna davetlisiniz.</p><p>Karla kaplı Arktik ormanlarda safariler, husky köpek kızağı deneyimi, Noel Baba Köyü ziyareti ve geleneksel Fin saunası bu turun öne çıkan anları. Işığın yarım saat bile çıkmadığı Polar Gece döneminde kuzey yarımkürenin en büyülü manzaralarından birini yaşıyorsunuz.</p>',
                'price' => 105000.00, 'currency' => 'TRY', 'duration_days' => 6,
                'is_active' => true, 'is_international' => true, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?w=800',
                'included' => "İstanbul – Helsinki – Rovaniemi – İstanbul uçak bileti\n2 gece iglu / cam tavanlı kulübe\n3 gece Rovaniemi otel\nTam pansiyon\nRen geyiği kızağı safarisi\nHusky köpek kızağı\nNoel Baba Köyü ziyareti\nKuzey ışıkları safarisi (jeep)\nFin sauna deneyimi",
                'excluded' => "Kişisel harcamalar\nEkstra aktiviteler (snowmobile vb.)",
                'tour_url' => 'https://touristica.com.tr',
                'dates' => [
                    ['departure_date' => '2026-12-20', 'return_date' => '2026-12-25', 'price' => 112000.00, 'label' => 'Yılbaşı Öncesi'],
                    ['departure_date' => '2027-01-10', 'return_date' => '2027-01-15', 'price' => 105000.00, 'label' => 'Ocak 2027'],
                ],
            ],

            // =====================================================
            // 28 - Balayı & Romantik
            // =====================================================
            [
                'agency_id' => 6, 'category_id' => 28,
                'title' => 'Kapadokya Romantik Kaçamak – Mağara Süit ve Özel Balon',
                'destination' => 'Kapadokya, Nevşehir',
                'description' => '<p>Kapadokya\'nın büyülü peri bacaları arasında sevgilinizle unutulmaz bir romantik kaçamak yaşıyorsunuz. Tarihi mağaraya oyulmuş süit odanızda Jakuzi\'ye girerken peri bacası manzarası izleyebiliyor, sabah şafağında sadece sizin için organize edilmiş özel balonlu uçuşta gökyüzüne yükseliyorsunuz.</p><p>Özel şarap tadımı, Anadolu\'nun geleneksel Çömlekçilik\'te ortak seramik deneyimi ve ışık şenliğine dönüşen Göreme\'de gece fotoğraf turu sevgilinizle paylaşacağınız eşsiz anlardır. Akşam yemeği mağara restoranında şömine başında özel menü ile servis edilmektedir.</p>',
                'price' => 18500.00, 'currency' => 'TRY', 'duration_days' => 3,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1504150558240-0b4fd8946624?w=800',
                'included' => "2 gece mağara süit otel (çift kişilik)\nOda süslemesi (gül yaprağı, mum, şampanya)\nÖzel balonlu uçuş (2 kişilik)\nUçuş sertifikası\nŞarap tadımı (3 çeşit yerel şarap)\nÇömlekçilik deneyimi\nMağara restoranında özel romantik akşam yemeği\nGöreme gece turu",
                'excluded' => "Kapadokya'ya geliş-dönüş ulaşım\nKahvaltı dışı yemekler\nKişisel harcamalar",
                'tour_url' => 'https://anitur.com.tr',
                'dates' => [
                    ['departure_date' => '2026-05-15', 'return_date' => '2026-05-17', 'price' => 18500.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-07-10', 'return_date' => '2026-07-12', 'price' => 21000.00, 'label' => 'Temmuz'],
                    ['departure_date' => '2026-09-18', 'return_date' => '2026-09-20', 'price' => 18000.00, 'label' => 'Eylül'],
                ],
            ],
            [
                'agency_id' => 7, 'category_id' => 28,
                'title' => 'Santorini Çift Kaçamağı – Kaldera Manzaralı Villa ve Tekne',
                'destination' => 'Santorini, Yunanistan',
                'description' => '<p>Dünyanın en romantik adası Santorini\'de, kaldera kenarına inşa edilmiş özel villada İa\'nın meşhur günbatımını izleyecek, Ege\'nin derin mavisiyle bütünleşmiş beyaz kubbeli evlerin arasında el ele dolaşacaksınız.</p><p>Özel katamaran tekne turuyla Santorini\'nin volkanik koylarını gezerek Akrotiri volkanının yanından geçiyor, volkanik siyah kumsalda şnorkel yapıyor ve denizin ortasında şampanya tostlaşıyorsunuz. Oia\'da şaraphane yemeği, Fira\'da alışveriş ve geleneksel ürün tadımı da programa dahildir.</p>',
                'price' => 64000.00, 'currency' => 'TRY', 'duration_days' => 5,
                'is_active' => true, 'is_international' => true, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1570077188670-e3a8d69ac5ff?w=800',
                'included' => "İstanbul – Atina – Santorini – İstanbul biletleri\n4 gece kaldera manzaralı villa (çift kişilik)\nOda süslemesi\nÖzel katamaran teknesi (tam gün)\nŞampanya ve aperatifler (tekne)\nOia şaraphane akşam yemeği\nFira rehberli şehir turu",
                'excluded' => "Öğle yemekleri\nKişisel harcamalar ve alışveriş\nEkstra aktiviteler",
                'tour_url' => 'https://coraltravel.com.tr',
                'dates' => [
                    ['departure_date' => '2026-05-23', 'return_date' => '2026-05-27', 'price' => 62000.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-20', 'return_date' => '2026-06-24', 'price' => 68000.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-09-05', 'return_date' => '2026-09-09', 'price' => 60000.00, 'label' => 'Eylül'],
                ],
            ],

            // =====================================================
            // 31 - Gastronomi & Lezzet
            // =====================================================
            [
                'agency_id' => 8, 'category_id' => 31,
                'title' => 'İtalya Gastronomi Turu – Bologna, Parma ve Modena\'da Lezzet Keşfi',
                'destination' => 'Bologna, Parma, Modena, İtalya',
                'description' => '<p>İtalyan mutfağının kalbi Emilia-Romagna bölgesinde, dünyaca ünlü ürünlerin yapımını yerinde göreceğiniz, tadacağınız ve ustalardan öğreneceğiniz bu gastronomi turuyla damak zevkinizi İtalyan ustaların eline bırakıyorsunuz.</p><p>Parma\'da prosciutto ve Parmigiano Reggiano üretim çiftliklerine özel ziyaret, Modena\'da geleneksel balsamik sirke mahzenlerinde tadım, Bologna\'da İtalyan pasta ve ragù ustalık dersi ve yerel pazar turu bu turun öne çıkan anları. Her akşam farklı bir bölge restoranında tatlıya kadar 5 çeşit degustasyon menüsü ikram edilmektedir.</p>',
                'price' => 62000.00, 'currency' => 'TRY', 'duration_days' => 6,
                'is_active' => true, 'is_international' => true, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1414235077428-338989a2e8c0?w=800',
                'included' => "İstanbul – Bologna – İstanbul uçak bileti\n5 gece 4 yıldızlı otel (kahvaltı dahil)\nParma çiftliği ziyareti (Prosciutto + Parmigiano)\nModena balsamik sirke tur ve tadımı\nBologna pasta ustalık dersi\nHer gece degustasyon akşam yemeği\nŞehirler arası özel araç transferleri\nGastronomi rehberi",
                'excluded' => "Öğle yemekleri\nKişisel harcamalar ve alışveriş\nEkstra şarap satın alma",
                'tour_url' => 'https://odeontours.com',
                'dates' => [
                    ['departure_date' => '2026-05-09', 'return_date' => '2026-05-14', 'price' => 62000.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-09-19', 'return_date' => '2026-09-24', 'price' => 60000.00, 'label' => 'Eylül Hasat Dönemi'],
                ],
            ],
            [
                'agency_id' => 9, 'category_id' => 31,
                'title' => 'Güneydoğu Anadolu Lezzet Turu – Gaziantep, Şanlıurfa ve Mardin',
                'destination' => 'Gaziantep, Şanlıurfa, Mardin',
                'description' => '<p>UNESCO Gastronomi Şehri unvanına sahip Gaziantep ve komşu illerin eşsiz mutfak mirasını keşfediyoruz. Gaziantep\'te baklavanın sırrını ustasından öğreniyor, fıstık ezme atölyesine katılıyor ve tarihi çarşıda baharat turu yapıyoruz. Şanlıurfa\'da ciğer ustalarının tezgahında güneş doğarken kahvaltı yapıyor, Kapalı Çarşı\'da çiğ köfte muhabbeti kuruyoruz. Mardin\'de Kürt ve Arap mutfağından oluşan sofralar kurulurken antik sokakların tadını çıkarıyoruz.</p>',
                'price' => 9500.00, 'currency' => 'TRY', 'duration_days' => 4,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1504674900247-0877df9cc836?w=800',
                'included' => "İstanbul – Gaziantep – İstanbul uçak bileti\n3 gece otel (kahvaltı dahil)\nFıstık ezme ve baklava atölyesi\nBaharat çarşısı rehberli turu\nHer şehirde yerel lezzet turu (rehber eşliğinde)\n2 özel akşam yemeği (yöresel sofrası)",
                'excluded' => "Öğle yemekleri (serbest)\nKişisel harcamalar ve alışveriş",
                'tour_url' => 'https://biblotur.com.tr',
                'dates' => [
                    ['departure_date' => '2026-05-14', 'return_date' => '2026-05-17', 'price' => 9500.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-10-08', 'return_date' => '2026-10-11', 'price' => 9800.00, 'label' => 'Ekim'],
                ],
            ],

            // =====================================================
            // 32 - Festival & Etkinlik
            // =====================================================
            [
                'agency_id' => 10, 'category_id' => 32,
                'title' => 'Münih Oktoberfest Turu – Bira Festivali\'nde 4 Gün',
                'destination' => 'Münih, Almanya',
                'description' => '<p>Dünyanın en büyük halk festivali Oktoberfest için Münih\'te dört gün geçiriyoruz. Theresienwiese meydanındaki devasa birahane çadırlarında geleneksel Bavyera kıyafetleriyle (Dirndl/Lederhosen) yerel bira marka çadırlarını ziyaret ediyoruz.</p><p>Festival alanının dışında da Marienplatz\'ın tarihi Kent Binası, Englischer Garten\'ın sörf kanalı ve Nymphenburg Sarayı\'nı görüyoruz. Tur grubuyla tahsis edilmiş özel Bierzeltte masalarımız rezerve edilmekte; kalabalıkta yer bulmak için uzun saatler beklemek gerekmiyor.</p>',
                'price' => 42000.00, 'currency' => 'TRY', 'duration_days' => 4,
                'is_active' => true, 'is_international' => true, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1467269204165-a51a5a84b0a0?w=800',
                'included' => "İstanbul – Münih – İstanbul uçak bileti\n3 gece 4 yıldızlı otel (kahvaltı dahil)\nOktoberfest festival alanı girişi\nÖzel bierhalle masa rezervasyonu (2 kalkış içerir)\nMünih şehir rehberli turu\nNymphenburg Sarayı girişi\nU-Bahn/metro ulaşım bileti (4 gün)",
                'excluded' => "Bira ve yiyecek harcamaları (bierhallelerde)\nÖğle ve akşam yemekleri\nKişisel harcamalar",
                'tour_url' => 'https://tropiktur.com',
                'dates' => [
                    ['departure_date' => '2026-09-19', 'return_date' => '2026-09-22', 'price' => 42000.00, 'label' => 'Eylül Açılış'],
                    ['departure_date' => '2026-10-03', 'return_date' => '2026-10-06', 'price' => 44000.00, 'label' => 'Ekim Finali'],
                ],
            ],
            [
                'agency_id' => 1, 'category_id' => 32,
                'title' => 'Rio Karnavalı Turu – Samba ve Renk Şöleninde 5 Gün',
                'destination' => 'Rio de Janeiro, Brezilya',
                'description' => '<p>Dünyanın en büyük karnavalını yerinde yaşamak için Rio de Janeiro\'ya gidiyoruz. Sambodromo\'da samba okullarının birbirleriyle yarıştığı gece geçitlerini VIP tribün biletleriyle izliyor, Copacabana ve Ipanema plajlarında kostümlü sokak bloklarına (blocos) katılıyoruz.</p><p>Gündüzleri Sugar Loaf Dağı teleferik turu, Corcovado\'da Kurtarıcı İsa heykeli ziyareti ve Santa Teresa\'nın renkli mahallelerini keşfediyoruz. Karnaval süresince güvenlik için deneyimli yerel rehber gruba sürekli eşlik etmektedir.</p>',
                'price' => 98000.00, 'currency' => 'TRY', 'duration_days' => 6,
                'is_active' => true, 'is_international' => true, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1483729558449-99ef09a8c325?w=800',
                'included' => "İstanbul – Rio de Janeiro – İstanbul uçak bileti\n5 gece Ipanema 4 yıldızlı otel (kahvaltı)\nSambodromo VIP tribün bileti (1 gece)\nSugar Loaf teleferik bileti\nCorcovado tren bileti\nRehberli mahalle turu",
                'excluded' => "Brezilya vizesi (gerekli değil Türk pasaportu ile)\nÖğle ve akşam yemekleri\nBloco kıyafetleri\nKişisel harcamalar",
                'tour_url' => 'https://jollytur.com',
                'dates' => [
                    ['departure_date' => '2027-02-12', 'return_date' => '2027-02-17', 'price' => 105000.00, 'label' => 'Karnaval 2027'],
                ],
            ],

            // =====================================================
            // 33 - Günübirlik Turlar
            // =====================================================
            [
                'agency_id' => 2, 'category_id' => 33,
                'title' => 'Sapanca Gölü ve Maşukiye Günübirlik Doğa Turu',
                'destination' => 'Sapanca, Kocaeli',
                'description' => '<p>İstanbul\'dan yalnızca 100 km uzaklıktaki Sapanca\'ya sabah erkenden hareket ederek şehrin stresini tek günde geride bırakıyoruz. Sapanca Gölü kıyısında bisiklet turu, Maşukiye\'nin serin ormanlarında yürüyüş ve şelalenin yanı başında piknik yapıyoruz.</p><p>Öğle yemeği Maşukiye\'nin meşhur alabalık tesislerinden birinde ikram edilmektedir. Taze yaprak çayıyla hazırlanan çay molası ve yerel bal-kaymak kahvaltısı da gün programına dahildir. Akşam İstanbul\'a dönüş sağlanmaktadır.</p>',
                'price' => 950.00, 'currency' => 'TRY', 'duration_days' => 1,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1501854140801-50d01698950b?w=800',
                'included' => "İstanbul – Sapanca – İstanbul özel araç\nBisiklet kiralama (Sapanca kıyısı)\nÖğle yemeği (Maşukiye alabalık tesisi)\nÇay ve ikramlar\nDoğa rehberi",
                'excluded' => "Kişisel harcamalar ve alışveriş",
                'tour_url' => 'https://etstur.com',
                'dates' => [
                    ['departure_date' => '2026-04-25', 'return_date' => '2026-04-25', 'price' => 950.00, 'label' => 'Cumartesi'],
                    ['departure_date' => '2026-05-02', 'return_date' => '2026-05-02', 'price' => 950.00, 'label' => 'Cumartesi'],
                    ['departure_date' => '2026-05-09', 'return_date' => '2026-05-09', 'price' => 950.00, 'label' => 'Cumartesi'],
                    ['departure_date' => '2026-05-16', 'return_date' => '2026-05-16', 'price' => 950.00, 'label' => 'Cumartesi'],
                ],
            ],
            [
                'agency_id' => 3, 'category_id' => 33,
                'title' => 'Bursa Uludağ, Yeşil Türbe ve Çarşılar Günübirlik Turu',
                'destination' => 'Bursa',
                'description' => '<p>Osmanlı\'nın ilk başkenti Bursa\'yı bir günde enlemesine geçiyoruz. Ulu Camii ve Kapalıçarşı\'da Osmanlı mirasını keşfediyoruz, Yeşil Cami ve Yeşil Türbe\'de İznik çinilerinin renk cümbüşüne hayran kalıyoruz.</p><p>Öğle yemeği olarak Bursa\'nın simge lezzeti İskender Kebap'ı ilk icra edildiği ustanın torunlarından yiyoruz. Sonrasında Uludağ Teleferik ile dağın zirvesine çıkıp manzarayı seyrediyoruz. Tarihi çarşıda ipekli kumaş ve peştamal alışverişi için serbest vakit tanınıyor.</p>',
                'price' => 1100.00, 'currency' => 'TRY', 'duration_days' => 1,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1564507592333-c60657eea523?w=800',
                'included' => "İstanbul – Bursa – İstanbul feribot + araç transferleri\nUludağ teleferik bileti\nYeşil Cami ve Yeşil Türbe rehberli turu\nÖğle yemeği (İskender Kebap)\nRehber",
                'excluded' => "Çarşı alışverişleri\nKişisel harcamalar",
                'tour_url' => 'https://tatilsepeti.com',
                'dates' => [
                    ['departure_date' => '2026-04-26', 'return_date' => '2026-04-26', 'price' => 1100.00, 'label' => 'Pazar'],
                    ['departure_date' => '2026-05-03', 'return_date' => '2026-05-03', 'price' => 1100.00, 'label' => 'Pazar'],
                    ['departure_date' => '2026-05-10', 'return_date' => '2026-05-10', 'price' => 1100.00, 'label' => 'Pazar'],
                ],
            ],

            // =====================================================
            // 35 - Aile & Çocuk
            // =====================================================
            [
                'agency_id' => 4, 'category_id' => 35,
                'title' => 'Disneyland Paris Aile Turu – Sihrin Krallığında 4 Gün',
                'destination' => 'Paris, Fransa',
                'description' => '<p>Çocukların hayallerini süsleyen Disney karakterleri, büyüleyici oyun parkları ve hikayelerin gerçeğe dönüştüğü Disneyland Paris\'te aile olarak unutulmaz anlar yaşıyorsunuz. Walt Disney Studios ve Disneyland Park olmak üzere iki dev park, her yaştan ziyaretçiye hitap eden onlarca çekici sunuyor.</p><p>Çocuklar favori karakterleriyle fotoğraf çekilirken ebeveynler için de heyecan verici turnikeler, restoranlar ve gösteri programları hazırda. Sabah erken girişle kalabalığı beklemeden en popüler atraksiyonlara önce siz giriyorsunuz. Konaklama Disney Hotel\'de yapılmakta; kahvaltıda Disney karakterleri masaları geziyor.</p>',
                'price' => 52000.00, 'currency' => 'TRY', 'duration_days' => 4,
                'is_active' => true, 'is_international' => true, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1541480601022-2308c0f02487?w=800',
                'included' => "İstanbul – Paris – İstanbul uçak biletleri\n3 gece Disney Hotel (kahvaltı + karakter kahvaltısı dahil)\n2 günlük Disneyland + Disney Studios park biletleri\nSabah erken giriş ayrıcalığı (Early Entry)\nPark içi ulaşım\nDinozor figürlü çocuk hediye paketi",
                'excluded' => "Öğle ve akşam yemekleri\nPark içi ekstra aktiviteler (BuyFast pass)\nKişisel harcamalar ve hatıra eşyaları",
                'tour_url' => 'https://setur.com.tr',
                'dates' => [
                    ['departure_date' => '2026-05-22', 'return_date' => '2026-05-25', 'price' => 52000.00, 'label' => 'Mayıs Okul Tatili'],
                    ['departure_date' => '2026-07-04', 'return_date' => '2026-07-07', 'price' => 58000.00, 'label' => 'Temmuz Yaz Tatili'],
                    ['departure_date' => '2026-10-24', 'return_date' => '2026-10-27', 'price' => 50000.00, 'label' => 'Ekim Tatili'],
                ],
            ],
            [
                'agency_id' => 5, 'category_id' => 35,
                'title' => 'Antalya Aquapark ve Hayvanat Bahçesi Çocuk Turu',
                'destination' => 'Antalya',
                'description' => '<p>Çocuklar için mükemmel bir yaz tatili planıyorsanız bu paket tam size göre. Antalya\'nın en büyük aquaparkında kaydıraklarla dolu coşkulu bir gün, ardından Antalya Hayvanat Bahçesi\'nde gerçek zürafa besleme deneyimi ve akşam Lara plajında ailece denize giriş ve güneş batımı keyfi.</p><p>Çocukların güvenliği için tüm aktivitelerde uzman animasyon ekibimiz ve lifeguardlar hazır. Aquaparkta ebeveyn eşliğinde katılabileceğiniz aile kaydırakları olduğu gibi küçüklere özel çocuk havuzları da bulunmaktadır. Otelden akşam dönüşü özel araçla sağlanmaktadır.</p>',
                'price' => 2800.00, 'currency' => 'TRY', 'duration_days' => 1,
                'is_active' => true, 'is_international' => false, 'requires_visa' => false,
                'image' => 'https://images.unsplash.com/photo-1555041469-a586c61ea9bc?w=800',
                'included' => "Antalya otel – aquapark – hayvanat bahçesi – otel transferleri\nAquapark tam gün girişi\nHayvanat Bahçesi girişi + zürafa besleme\nÖğle yemeği (aquapark restoranı)\nAnimasyon rehberi",
                'excluded' => "Kişisel harcamalar\nAquapark içi atıştırmalık ve içecekler\nHatıra fotoğrafları",
                'tour_url' => 'https://touristica.com.tr',
                'dates' => [
                    ['departure_date' => '2026-06-20', 'return_date' => '2026-06-20', 'price' => 2800.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-07-11', 'return_date' => '2026-07-11', 'price' => 3000.00, 'label' => 'Temmuz'],
                    ['departure_date' => '2026-08-08', 'return_date' => '2026-08-08', 'price' => 3000.00, 'label' => 'Ağustos'],
                ],
            ],

        ];
    }
}
