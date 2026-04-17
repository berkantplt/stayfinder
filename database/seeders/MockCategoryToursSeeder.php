<?php

namespace Database\Seeders;

use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class MockCategoryToursSeeder extends Seeder
{
    public function run(): void
    {
        $tours = $this->toursData();

        foreach ($tours as $data) {
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

            // -------------------------------------------------------
            // 3 - Müze & Sanat
            // -------------------------------------------------------
            [
                'agency_id'      => 1,
                'category_id'    => 3,
                'title'          => 'İstanbul Müzeler ve Sanat Turu – Topkapı, Arkeoloji & Pera',
                'destination'    => 'İstanbul',
                'description'    => '<p>İstanbul\'un tarihi yarımadasında yer alan en prestijli müzeleri ve sanat galerilerini keşfedin. Osmanlı İmparatorluğu\'nun hazinelerini barındıran Topkapı Sarayı\'ndan antik uygarlıkların eserlerini sergileyen İstanbul Arkeoloji Müzesi\'ne, çağdaş Türk sanatının nabzını tutan Pera Müzesi\'nden büyüleyici İslam Eserleri Müzesi\'ne kadar İstanbul\'un kültürel zenginliklerini deneyimli rehberler eşliğinde derinlemesine keşfediyoruz.</p><p>Sabah erken saatlerde otelden alınarak başlayan turunuzda her müzede uzman rehber eşliğinde kapsamlı tur yapılmaktadır. Öğle yemeği Sultanahmet\'te tarihi bir Osmanlı restoranında ikram edilmektedir. Tur akşam saatlerinde katılımcıları kendi otellerine bırakarak son bulmaktadır.</p>',
                'price'          => 2800.00,
                'currency'       => 'TRY',
                'duration_days'  => 1,
                'is_active'      => true,
                'is_international' => false,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1554907984-15263bfd63bd?w=800',
                'included'       => "Tüm müze giriş ücretleri\nUzman sanat tarihi rehberi (Türkçe/İngilizce)\nÖğle yemeği (Sultanahmet'te Osmanlı mutfağı)\nOtel transferleri (İstanbul içi)\nSigorta",
                'excluded'       => "Kişisel harcamalar\nMüzelerdeki fotoğraf izni (bazı bölümler ücretli)\nAkşam yemeği",
                'tour_url'       => 'https://jollytur.com',
                'dates'          => [
                    ['departure_date' => '2026-05-10', 'return_date' => '2026-05-10', 'price' => 2800.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-05-24', 'return_date' => '2026-05-24', 'price' => 2800.00, 'label' => 'Mayıs Sonu'],
                    ['departure_date' => '2026-06-07', 'return_date' => '2026-06-07', 'price' => 3100.00, 'label' => 'Haziran'],
                ],
            ],

            // -------------------------------------------------------
            // 4 - Dini & Hac Turları
            // -------------------------------------------------------
            [
                'agency_id'      => 2,
                'category_id'    => 4,
                'title'          => 'Umre Turu – Mekke ve Medine\'de 10 Gün Manevi Yolculuk',
                'destination'    => 'Suudi Arabistan',
                'description'    => '<p>Her Müslümanın hayatının en anlamlı deneyimlerinden biri olan Umre ibadetini kolaylaştırmak için titizlikle hazırlanmış bu programımızla Mekke ve Medine\'yi ziyaret ediyoruz. Kâbe-i Muazzama\'nın başında dua etmek, Arafat\'ta vakfe yapmak ve Hz. Peygamber\'in (s.a.v.) Mescid-i Nebevi\'sinde namaz kılmak bu kutsal yolculuğun ayrılmaz parçalarıdır.</p><p>Mekke\'de Mescid-i Haram\'a yürüme mesafesindeki 5 yıldızlı otelimizde konaklıyoruz. Tecrübeli dini rehberimiz tüm ibadetlerde rehberlik etmekte, grup namazlarını organize etmekte ve sorularınızı yanıtlamaktadır. Medine\'de ise Mescid-i Nebevi\'ye çok yakın konumda lüks konaklamadan yararlanıyoruz.</p>',
                'price'          => 45000.00,
                'currency'       => 'TRY',
                'duration_days'  => 10,
                'is_active'      => true,
                'is_international' => true,
                'requires_visa'  => true,
                'image'          => 'https://images.unsplash.com/photo-1576437734703-65e7e2a7c45f?w=800',
                'included'       => "Türkiye - Cidde - İstanbul uçak bileti (ekonomi sınıf)\nMekke'de 5 gece 5 yıldızlı otel (Mescid-i Haram'a 200m)\nMedine'de 4 gece 5 yıldızlı otel (Mescid-i Nebevi'ye 150m)\nTam pansiyon (sabah-öğle-akşam)\nVize işlemleri ve harçları\nDini rehber (tüm tur boyunca)\nMekke – Medine özel transfer\nZiyaret turları (Arafat, Mina, Müzdelife, Kuba Mescidi)",
                'excluded'       => "Kişisel harcamalar ve alışveriş\nZam dönemlerinde fiyat farkı\nİhram kıyafeti (istek üzerine temin edilebilir)\nHava yolu ekstra bagaj ücreti",
                'tour_url'       => 'https://etstur.com',
                'dates'          => [
                    ['departure_date' => '2026-05-15', 'return_date' => '2026-05-25', 'price' => 45000.00, 'label' => 'Mayıs Kalkışı'],
                    ['departure_date' => '2026-06-20', 'return_date' => '2026-06-30', 'price' => 47500.00, 'label' => 'Haziran Kalkışı'],
                    ['departure_date' => '2026-09-05', 'return_date' => '2026-09-15', 'price' => 44000.00, 'label' => 'Eylül Kalkışı'],
                ],
            ],

            // -------------------------------------------------------
            // 8 - Safari & Doğa Gözlemi
            // -------------------------------------------------------
            [
                'agency_id'      => 3,
                'category_id'    => 8,
                'title'          => 'Kenya Safari Turu – Masai Mara\'da Büyük Göç 7 Gün',
                'destination'    => 'Kenya',
                'description'    => '<p>Dünyanın en büyük yaban hayatı göçüne tanıklık etmek için Kenya\'nın efsanevi Masai Mara Ulusal Rezervi\'ne gidiyoruz. Temmuz-Ekim aylarında gerçekleşen "Büyük Göç" sırasında milyonlarca GNU ve zebranın Serengeti\'den Masai Mara\'ya geçişini bizzat izliyoruz.</p><p>Açık tavanlı özel safari araçlarımızla sabah ve akşam günde iki game drive yapıyoruz. Aslan, leopar, fil, gergedan ve bufalo – "Büyük Beşli"yi doğal yaşam alanlarında görmek için en uygun zamanlarda rezerve çıkıyoruz. Maasai kabilesini ziyaret ederek yerel kültürü tanıma fırsatı da buluyoruz. Konaklama, rezervin içinde ya da bitişiğindeki "tented camp" veya lüks safari lodgelarda sağlanmaktadır.</p>',
                'price'          => 95000.00,
                'currency'       => 'TRY',
                'duration_days'  => 7,
                'is_active'      => true,
                'is_international' => true,
                'requires_visa'  => true,
                'image'          => 'https://images.unsplash.com/photo-1516426122078-c23e76319801?w=800',
                'included'       => "İstanbul – Nairobi – İstanbul uçak bileti\nNairobi'de 1 gece şehir oteli (4 yıldız)\nMasai Mara'da 5 gece safari lodge (tam pansiyon)\nGünde 2 game drive (açık tavanlı 4x4 araç)\nUzman İngilizce-Türkçe safari rehberi\nTüm park giriş ücretleri\nMaasai köyü ziyareti\nVize desteği",
                'excluded'       => "Kenya vizesi ücreti (yaklaşık 50 USD)\nKişisel harcamalar ve bahşiş\nİsteğe bağlı balonlu uçuş (yaklaşık 450 USD)\nAlışveriş ve hediyelik eşya",
                'tour_url'       => 'https://tatilsepeti.com',
                'dates'          => [
                    ['departure_date' => '2026-07-18', 'return_date' => '2026-07-25', 'price' => 95000.00, 'label' => 'Temmuz – Göç Dönemi'],
                    ['departure_date' => '2026-08-15', 'return_date' => '2026-08-22', 'price' => 99000.00, 'label' => 'Ağustos – Zirve Sezon'],
                    ['departure_date' => '2026-09-12', 'return_date' => '2026-09-19', 'price' => 92000.00, 'label' => 'Eylül – Sezon Sonu'],
                ],
            ],

            // -------------------------------------------------------
            // 10 - Tekne Turu
            // -------------------------------------------------------
            [
                'agency_id'      => 4,
                'category_id'    => 10,
                'title'          => 'İstanbul Boğazı Gün Batımı Tekne Turu – Akşam Yemeği Dahil',
                'destination'    => 'İstanbul',
                'description'    => '<p>İstanbul\'un eşsiz siluetini denizden izleyeceğiniz bu özel tekne turunda Boğaz\'ın büyülü atmosferini yaşayacaksınız. İki kıtayı birbirine bağlayan Boğaz\'da seyrederken Dolmabahçe Sarayı, Rumeli Hisarı, Yıldız Sarayı ve Boğaz köprülerinin muhteşem manzaralarını yakından göreceksiniz.</p><p>Gün batımı saatinde başlayan turimizde özel olarak hazırlanmış Türk mutfağından lezzetler ile canlı fasıl müziği eşliğinde akşam yemeği ikram edilmektedir. Üst güvertede kokteyl saatiyle başlayan deneyim, şehir ışıklarının suya yansıdığı romantic bir akşama dönüşmektedir.</p>',
                'price'          => 1950.00,
                'currency'       => 'TRY',
                'duration_days'  => 1,
                'is_active'      => true,
                'is_international' => false,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1544551763-46a013bb70d5?w=800',
                'included'       => "3 saatlik özel tekne turu (Boğaz)\nAkşam yemeği (3 çeşit Türk mutfağı)\nAlkollü/alkolsüz içecekler\nCanlı Türk müziği\nHostes hizmeti\nİskelede karşılama",
                'excluded'       => "Otel transferleri\nKişisel harcamalar\nFotoğraf çekimi (ekstra ücret)",
                'tour_url'       => 'https://setur.com.tr',
                'dates'          => [
                    ['departure_date' => '2026-05-08', 'return_date' => '2026-05-08', 'price' => 1950.00, 'label' => 'Cuma'],
                    ['departure_date' => '2026-05-09', 'return_date' => '2026-05-09', 'price' => 1950.00, 'label' => 'Cumartesi'],
                    ['departure_date' => '2026-05-16', 'return_date' => '2026-05-16', 'price' => 1950.00, 'label' => 'Cumartesi'],
                    ['departure_date' => '2026-05-23', 'return_date' => '2026-05-23', 'price' => 2200.00, 'label' => 'Özel Gece Kalkışı'],
                ],
            ],

            // -------------------------------------------------------
            // 12 - Mavi Yolculuk
            // -------------------------------------------------------
            [
                'agency_id'      => 5,
                'category_id'    => 12,
                'title'          => 'Bodrum – Göcek Mavi Yolculuk – 7 Gece Gulet ile Ege Koyları',
                'destination'    => 'Bodrum, Ege',
                'description'    => '<p>Türkiye\'nin masmavi Ege kıyılarında, geleneksel ahşap guletimizle Bodrum\'dan Göcek\'e uzanan bu efsanevi mavi yolculukta zamanın nasıl geçtiğini unutacaksınız. Her sabah farklı bir koyda demir atarak kristal berraklığındaki sularda şnorkel yapıyor, kayıkla sahile çıkıyor ya da güverte şezlongunda kitabınızı okuyorsunuz.</p><p>Rota boyunca Hisarönü Körfezi\'nin saklı koyları, Marmaris\'in yemyeşil ormanları, Ekincik Koyu\'nun bakir güzelliği ve Göcek\'in seçkin marinaları sizi beklemektedir. Teknede kişisel baş aşçımız her öğün taze Ege lezzetleri hazırlamakta; deniz ürünleri, mevsim sebzeleri ve ev yapımı lezzetlerle oluşturulan zengin sofra kurulmaktadır.</p>',
                'price'          => 28500.00,
                'currency'       => 'TRY',
                'duration_days'  => 8,
                'is_active'      => true,
                'is_international' => false,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?w=800',
                'included'       => "7 gece gulet konaklaması (çift kişilik kabin)\nTam pansiyon (kahvaltı + öğle + akşam yemeği)\nKaptanlı ve mürettebatlı gulet\nŞnorkel ekipmanı\nSörf tahtası ve kano kullanımı\nLiman ve koruma vergileri",
                'excluded'       => "Bodrum'a/Göcek'ten geliş-dönüş ulaşım\nAlkolü içecekler\nKişisel harcamalar\nİsteğe bağlı aktiviteler (dalış kursu, jet ski vb.)\nBahşiş",
                'tour_url'       => 'https://touristica.com.tr',
                'dates'          => [
                    ['departure_date' => '2026-06-06', 'return_date' => '2026-06-13', 'price' => 26000.00, 'label' => 'Haziran Erken Sezon'],
                    ['departure_date' => '2026-07-04', 'return_date' => '2026-07-11', 'price' => 28500.00, 'label' => 'Temmuz'],
                    ['departure_date' => '2026-08-01', 'return_date' => '2026-08-08', 'price' => 32000.00, 'label' => 'Ağustos Yüksek Sezon'],
                    ['departure_date' => '2026-09-05', 'return_date' => '2026-09-12', 'price' => 25000.00, 'label' => 'Eylül'],
                ],
            ],

            // -------------------------------------------------------
            // 13 - Macera & Aktivite
            // -------------------------------------------------------
            [
                'agency_id'      => 6,
                'category_id'    => 13,
                'title'          => 'Kapadokya Macera Paketi – Balon, ATV ve Yürüyüş',
                'destination'    => 'Kapadokya, Nevşehir',
                'description'    => '<p>Kapadokya\'nın peri bacaları arasında nefes kesen aktivitelerle dolu bu macera paketinde adrenalin ve doğal güzelliği bir arada yaşayacaksınız. Şafak sökerken sıcak hava balonuyla gökyüzüne yükseliyor, peri bacaları vadilerini ATV ile keşfediyor ve Göreme Vadisi\'nin yürüyüş rotalarında tarihi kaya kiliselerini ziyaret ediyorsunuz.</p><p>Yeraltı şehirleri, üzüm bağları ve çömlekçilik atölyeleriyle zenginleştirilen program, Kapadokya\'yı sadece görmekle kalmayıp hissetmenizi sağlıyor. Tarihi taş otelde geceleme ve Kapadokya mutfağının özel lezzetleri de deneyimin ayrılmaz parçası.</p>',
                'price'          => 9800.00,
                'currency'       => 'TRY',
                'duration_days'  => 3,
                'is_active'      => true,
                'is_international' => false,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1527786356703-4b100091cd2c?w=800',
                'included'       => "2 gece butik kaya oteli (kahvaltı dahil)\nSabah şafağı balonlu uçuş (CAA lisanslı)\nUçuş sertifikası ve şampanya\n2 saatlik ATV safari turu\nGöreme Vadisi rehberli yürüyüş\nDerinkuyu Yeraltı Şehri ziyareti\nÇömlekçilik atölyesi deneyimi\nÜzüm bağı ve şaraphane ziyareti\nKapadokya – Nevşehir uçak transferi",
                'excluded'       => "İstanbul/Ankara – Kapadokya ulaşım\nÖğle ve akşam yemekleri\nKişisel harcamalar",
                'tour_url'       => 'https://anitur.com.tr',
                'dates'          => [
                    ['departure_date' => '2026-05-01', 'return_date' => '2026-05-03', 'price' => 9800.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-12', 'return_date' => '2026-06-14', 'price' => 10500.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-09-18', 'return_date' => '2026-09-20', 'price' => 9500.00, 'label' => 'Eylül'],
                ],
            ],

            // -------------------------------------------------------
            // 14 - Rafting & Kano
            // -------------------------------------------------------
            [
                'agency_id'      => 7,
                'category_id'    => 14,
                'title'          => 'Köprülü Kanyon Rafting Turu – Antalya\'dan Günübirlik',
                'destination'    => 'Köprülü Kanyon, Antalya',
                'description'    => '<p>Türkiye\'nin en iyi rafting noktalarından Köprülü Kanyon\'da, 14 km\'lik heyecan dolu güzergahta Köprüçay nehrinin coşkun sularıyla mücadele ediyorsunuz. Sertifikalı rehberlerimiz eşliğinde 3-4 derece zorluğundaki bu güzergah hem ilk kez rafting yapacaklar hem de tecrübeli macera severler için idealdir.</p><p>Sabah Antalya\'dan hareketle Köprülü Kanyon Milli Parkı\'na ulaşıyoruz. Güvenlik brifinginin ardından ekipmanlarınız giyilip nehre giriliyor. Yaklaşık 2 saat süren rafting deneyiminin ardından kanyonun içindeki doğal havuzda serinleme fırsatı buluyorsunuz. Öğle yemeği nehir kıyısında ateşte pişirilen mangal ile ikram ediliyor.</p>',
                'price'          => 1650.00,
                'currency'       => 'TRY',
                'duration_days'  => 1,
                'is_active'      => true,
                'is_international' => false,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1530866495561-507c9faab2ed?w=800',
                'included'       => "Antalya – Köprülü Kanyon – Antalya transferleri\nProfesyonel raft ekipmanı (kask, yelek, kürek, mayo)\nSertifikalı rafting rehberi\n14 km rafting deneyimi (~2 saat)\nÖğle yemeği (nehir kenarı mangal)\nSigorta",
                'excluded'       => "Kişisel harcamalar\nFotoğraf/video paketi (isteğe bağlı 200 TRY)\n12 yaş altı katılım mümkün değildir",
                'tour_url'       => 'https://coraltravel.com.tr',
                'dates'          => [
                    ['departure_date' => '2026-04-25', 'return_date' => '2026-04-25', 'price' => 1650.00, 'label' => 'Nisan'],
                    ['departure_date' => '2026-05-03', 'return_date' => '2026-05-03', 'price' => 1650.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-05-10', 'return_date' => '2026-05-10', 'price' => 1650.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-07', 'return_date' => '2026-06-07', 'price' => 1800.00, 'label' => 'Haziran'],
                ],
            ],

            // -------------------------------------------------------
            // 15 - Yamaç Paraşütü
            // -------------------------------------------------------
            [
                'agency_id'      => 8,
                'category_id'    => 15,
                'title'          => 'Ölüdeniz Yamaç Paraşütü – Babadağ\'dan Denize İniş',
                'destination'    => 'Ölüdeniz, Fethiye',
                'description'    => '<p>Dünyanın en güzel 10 yamaç paraşütü lokasyonu arasında gösterilen Ölüdeniz\'de, Babadağ\'ın 1960 metre zirvesinden Mavi Lagün\'e iniş yaparak yaşamınızın deneyimini yaşayacaksınız. Sertifikalı tandem pilotumuzla beraber gerçekleştirilen bu uçuşta herhangi bir tecrübeye ihtiyaç duyulmamaktadır.</p><p>Minibüsle tepe istasyonuna ulaşıldıktan sonra kısa bir brifing ile uçuş öncesi hazırlık yapılmaktadır. Yaklaşık 25-40 dakika süren uçuş sırasında profesyonel kameraman eşliğinde fotoğraf ve video çekimi yapılmaktadır. İniş noktasında özel araçla karşılanmaktadır.</p>',
                'price'          => 3200.00,
                'currency'       => 'TRY',
                'duration_days'  => 1,
                'is_active'      => true,
                'is_international' => false,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1503220317375-aaad61436b1b?w=800',
                'included'       => "Otel – Babadağ tepe istasyonu – otel transferleri\nTandem yamaç paraşütü uçuşu (~30 dakika)\nSertifikalı pilot\nProfesyonel fotoğraf ve HD video çekimi\nSigorta\nUçuş sertifikası",
                'excluded'       => "Kişisel harcamalar\nOtel konaklaması\n90 kg üzeri katılımcılar ek ücrete tabidir",
                'tour_url'       => 'https://odeontours.com',
                'dates'          => [
                    ['departure_date' => '2026-04-20', 'return_date' => '2026-04-20', 'price' => 3200.00, 'label' => 'Nisan'],
                    ['departure_date' => '2026-05-05', 'return_date' => '2026-05-05', 'price' => 3200.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-15', 'return_date' => '2026-06-15', 'price' => 3500.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-07-10', 'return_date' => '2026-07-10', 'price' => 3500.00, 'label' => 'Temmuz'],
                ],
            ],

            // -------------------------------------------------------
            // 16 - ATV & Off-Road
            // -------------------------------------------------------
            [
                'agency_id'      => 9,
                'category_id'    => 16,
                'title'          => 'Kapadokya ATV Safari – Peri Bacaları Arası Off-Road Macera',
                'destination'    => 'Kapadokya, Nevşehir',
                'description'    => '<p>Kapadokya\'nın eşsiz peri bacaları, kaya oluşumları ve üzüm bağları arasında ATV motorlarla off-road bir safari deneyimi yaşayacaksınız. Dünya\'da başka hiçbir yerde rastlanmayan bu jeolojik oluşumların arasından geçen özel güzergahımız, hem manzara hem de adrenalin açısından unutulmaz bir deneyim sunuyor.</p><p>Kılıçlar Vadisi, Üçhisar Kalesi çevresi ve şarap üzümlerinin yetiştirildiği antik bağlar rotanın öne çıkan durakları arasındadır. Bölgeyi çok iyi tanıyan rehberlerimiz gruba yön gösterirken Kapadokya\'nın tarihi ve jeolojisi hakkında bilgi de vermektedir.</p>',
                'price'          => 2400.00,
                'currency'       => 'TRY',
                'duration_days'  => 1,
                'is_active'      => true,
                'is_international' => false,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1558618666-fcd25c85cd64?w=800',
                'included'       => "ATV aracı (tek ya da çift kişilik seçeneği)\nGüvenlik ekipmanları (kask, eldiven, gözlük)\nDeneyimli off-road rehberi\n2,5 saatlik tur (yaklaşık 35-40 km)\nBaşlangıç noktasına transfer\nSigorta",
                'excluded'       => "Kapadokya'ya geliş-dönüş ulaşım\nOtel konaklaması\nYemekler\nKişisel harcamalar",
                'tour_url'       => 'https://biblotur.com.tr',
                'dates'          => [
                    ['departure_date' => '2026-05-02', 'return_date' => '2026-05-02', 'price' => 2400.00, 'label' => 'Mayıs Sabahı'],
                    ['departure_date' => '2026-05-02', 'return_date' => '2026-05-02', 'price' => 2400.00, 'label' => 'Mayıs Öğleden Sonra'],
                    ['departure_date' => '2026-06-06', 'return_date' => '2026-06-06', 'price' => 2600.00, 'label' => 'Haziran'],
                ],
            ],

            // -------------------------------------------------------
            // 18 - Akdeniz Cruise
            // -------------------------------------------------------
            [
                'agency_id'      => 10,
                'category_id'    => 18,
                'title'          => 'Akdeniz Cruise – İtalya, Yunanistan ve Hırvatistan 10 Gece',
                'destination'    => 'Akdeniz',
                'description'    => '<p>MSC Grandiosa ile çıkacağınız bu Akdeniz cruise yolculuğunda İtalya, Yunanistan ve Hırvatistan\'ın en güzel limanlarını ziyaret ediyorsunuz. Cenova\'dan başlayıp çeşitli Akdeniz şehirlerine yanaşan gemimiz, her limanda size farklı bir kültür ve mutfak deneyimi sunuyor.</p><p>Napoli\'de Vezüv Yanardağı ve Pompei harabeleri, Palermo\'da Sicilya mutfağının zenginlikleri, Pire\'de Atina Akropolü, Santorini\'de mavi kubbeli kiliseler ve Dubrovnik\'te ortaçağdan kalma surlı şehir rotanın öne çıkan duraklarıdır. Gemide 5 farklı restoran, 3 yüzme havuzu, spa ve gece eğlencesi sizleri beklemektedir.</p>',
                'price'          => 68000.00,
                'currency'       => 'TRY',
                'duration_days'  => 11,
                'is_active'      => true,
                'is_international' => true,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1548574505-5e239809ee19?w=800',
                'included'       => "İstanbul – Cenova – İstanbul uçak bileti\nCenova'da 1 gece 4 yıldızlı otel\n10 gece MSC Grandiosa cruise (iç kabin)\nAçık büfe kahvaltı ve akşam yemekleri\nGemideki tüm eğlence ve aktiviteler\nLiman vergileri ve yakıt sürşarjı",
                'excluded'       => "Öğle yemekleri\nAlkollü içecekler ve özel restoranlar\nOpsiyonel şehir turları\nKişisel harcamalar ve alışveriş\nBahşiş (isteğe bağlı)",
                'tour_url'       => 'https://tropiktur.com',
                'dates'          => [
                    ['departure_date' => '2026-06-13', 'return_date' => '2026-06-24', 'price' => 65000.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-07-11', 'return_date' => '2026-07-22', 'price' => 72000.00, 'label' => 'Temmuz Yüksek Sezon'],
                    ['departure_date' => '2026-09-05', 'return_date' => '2026-09-16', 'price' => 62000.00, 'label' => 'Eylül'],
                ],
            ],

            // -------------------------------------------------------
            // 19 - Yunan Adaları
            // -------------------------------------------------------
            [
                'agency_id'      => 1,
                'category_id'    => 19,
                'title'          => 'Yunan Adaları Cruise – Santorini, Mykonos ve Rodos 7 Gece',
                'destination'    => 'Yunan Adaları, Yunanistan',
                'description'    => '<p>Ege\'nin masalsı adalarını bir cruise gemisiyle keşfetmek için en iyi fırsatı sunuyoruz. Kaldera manzarasıyla ünlü Santorini\'nin beyaz badanalı evleri, Mykonos\'un rüzgârlı yel değirmenleri ve ünlü Little Venice\'i, Rodos\'un ortaçağ mirasıyla şaşırtıcı şatolar ve surlu şehri – hepsi tek bir rotada.</p><p>Her adada özel rehber eşliğinde guided tur seçeneğinin yanı sıra serbest keşif vakti de tanınmaktadır. Santorini\'de İa\'dan gün batımı izlemek, Mykonos\'ta yerel tavernalarda taze deniz ürünleri tatmak ve Rodos\'ta tarihi şövalye sokağında yürümek bu turda sizi bekleyen unutulmaz anların sadece birkaçıdır.</p>',
                'price'          => 52000.00,
                'currency'       => 'TRY',
                'duration_days'  => 8,
                'is_active'      => true,
                'is_international' => true,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1530841377377-3ff06c0ca713?w=800',
                'included'       => "İstanbul – Pire – İstanbul uçak bileti\nPire'de 1 gece otel\n7 gece cruise (dış kabin – deniz manzaralı)\nTam pansiyon (gemide)\nSantorini, Mykonos, Rodos rehberli turlar\nTüm liman vergileri",
                'excluded'       => "Alkollü içecekler\nOpsiyonel aktiviteler (quad, tekne turu vb.)\nKişisel harcamalar ve alışveriş\nBahşiş",
                'tour_url'       => 'https://jollytur.com',
                'dates'          => [
                    ['departure_date' => '2026-05-30', 'return_date' => '2026-06-06', 'price' => 48000.00, 'label' => 'Mayıs Sonu'],
                    ['departure_date' => '2026-07-04', 'return_date' => '2026-07-11', 'price' => 55000.00, 'label' => 'Temmuz'],
                    ['departure_date' => '2026-08-22', 'return_date' => '2026-08-29', 'price' => 58000.00, 'label' => 'Ağustos'],
                ],
            ],

            // -------------------------------------------------------
            // 20 - Kuzey Avrupa & Fiyort
            // -------------------------------------------------------
            [
                'agency_id'      => 2,
                'category_id'    => 20,
                'title'          => 'Norveç Fiyortları ve Kuzey Işıkları Turu – 8 Gün',
                'destination'    => 'Norveç',
                'description'    => '<p>Dünyanın en heyecan verici doğa harikalarından Norveç fiyortlarını ve kuzey ışıklarını görmek için düzenlenmiş bu özel turda, Avrupa\'nın vahşi kuzeyinin büyüsünü yaşayacaksınız. Oslo\'dan başlayan yolculukta Flåm demiryoluyla efsanevi Flåmsbana rotasında seyahat ediyor, Geirangerfjord ve Nærøyfjord\'un dikey kayalıkları arasından tekne ile geçiyor, Bergen\'in tarihi Bryggen rıhtımını keşfediyoruz.</p><p>Tromsø\'da ise kuzey ışıklarını (Aurora Borealis) izlemek için özel jeep safariyle tundra bölgelerine çıkıyoruz. Kuzey ışıkları garantisi sunamayız ancak Ekim-Mart arası aktivite olasılığı yüksektir. Konaklama olarak Oslo\'da 4 yıldızlı otel, Flåm\'da ise fiyort kıyısındaki butik otel tercih edilmektedir.</p>',
                'price'          => 88000.00,
                'currency'       => 'TRY',
                'duration_days'  => 8,
                'is_active'      => true,
                'is_international' => true,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1531366936337-7c912a4589a7?w=800',
                'included'       => "İstanbul – Oslo – İstanbul uçak bileti\n4 gece Oslo 4 yıldızlı otel (kahvaltı dahil)\n2 gece Flåm butik fiyort oteli (kahvaltı dahil)\n2 gece Tromsø 4 yıldızlı otel (kahvaltı dahil)\nFlåmsbana demiryolu bileti\nGeirangerfjord tekne turu\nTromsø kuzey ışıkları jeep safari\nDeneyimli Türkçe rehber\nŞehir içi transferler",
                'excluded'       => "Öğle ve akşam yemekleri\nKişisel harcamalar\nFjordpass kart (isteğe bağlı, yaklaşık 40 EUR)\nMüze girişleri",
                'tour_url'       => 'https://etstur.com',
                'dates'          => [
                    ['departure_date' => '2026-10-03', 'return_date' => '2026-10-10', 'price' => 88000.00, 'label' => 'Ekim – Kuzey Işıkları'],
                    ['departure_date' => '2026-11-07', 'return_date' => '2026-11-14', 'price' => 85000.00, 'label' => 'Kasım'],
                    ['departure_date' => '2027-01-09', 'return_date' => '2027-01-16', 'price' => 90000.00, 'label' => 'Ocak 2027'],
                ],
            ],

            // -------------------------------------------------------
            // 21 - Şehir Turları (parent)
            // -------------------------------------------------------
            [
                'agency_id'      => 3,
                'category_id'    => 21,
                'title'          => 'Avrupa Başkentleri Turu – Paris, Amsterdam ve Brüksel 8 Gün',
                'destination'    => 'Paris, Amsterdam, Brüksel',
                'description'    => '<p>Avrupa\'nın üç ikonik başkentini tek bir turla keşfedin. Işıklar Şehri Paris\'te Eiffel Kulesi, Louvre Müzesi ve Montmartre tepesi; Amsterdam\'da tarihi kanallar, Anne Frank Evi ve dünyaca ünlü müzeler; Brüksel\'de büyüleyici Grand Place, Atomium ve Belçika çikolatası sizi bekliyor.</p><p>Her şehirde iki gecelik konaklamayla şehir merkezine yürüme mesafesindeki otellerde kalıyoruz. Sabah kahvaltılar otellerimizde hazırlanmakta, öğle ve akşam yemeklerinde yerel restoranlar ve pazarlar ziyaret edilmektedir. Her şehirde rehberli şehir turu ile birlikte serbest keşif vakti de tanınmaktadır.</p>',
                'price'          => 38500.00,
                'currency'       => 'TRY',
                'duration_days'  => 8,
                'is_active'      => true,
                'is_international' => true,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1499856871958-5b9357976b82?w=800',
                'included'       => "İstanbul – Paris gidiş, Brüksel – İstanbul dönüş uçak biletleri\n2 gece Paris 4 yıldızlı otel (kahvaltı dahil)\n3 gece Amsterdam 4 yıldızlı otel (kahvaltı dahil)\n2 gece Brüksel 4 yıldızlı otel (kahvaltı dahil)\nŞehirler arası tren biletleri (Thalys/Eurostar)\nHer şehirde rehberli şehir turu\nEiffel Kulesi girişi (2. kat)\nRijksmuseum girişi\nAtomium girişi",
                'excluded'       => "Öğle ve akşam yemekleri\nLouvre girişi (isteğe bağlı, 22 EUR)\nKişisel harcamalar",
                'tour_url'       => 'https://tatilsepeti.com',
                'dates'          => [
                    ['departure_date' => '2026-05-16', 'return_date' => '2026-05-23', 'price' => 38500.00, 'label' => 'Mayıs'],
                    ['departure_date' => '2026-06-06', 'return_date' => '2026-06-13', 'price' => 40000.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-09-19', 'return_date' => '2026-09-26', 'price' => 37000.00, 'label' => 'Eylül'],
                ],
            ],

            // -------------------------------------------------------
            // 25 - Kayak & Kış (parent)
            // -------------------------------------------------------
            [
                'agency_id'      => 4,
                'category_id'    => 25,
                'title'          => 'Alp\'lerde Kış Tatili – İsviçre Zermatt\'ta 5 Gece Kayak',
                'destination'    => 'Zermatt, İsviçre',
                'description'    => '<p>İsviçre Alpleri\'nin simgesi Matterhorn\'un gölgesinde, dünyanın en prestijli kayak merkezlerinden Zermatt\'ta unutulmaz bir kış tatili yaşayacaksınız. Araç trafiğine kapalı bu büyüleyici dağ kasabasında 360 km\'lik pistler, modern teleferikler ve muhteşem kar manzaraları sizi bekliyor.</p><p>Her seviyeye uygun pistlerin bulunduğu Zermatt\'ta profesyonel kayak dersleri de alabilirsiniz. Kasabada ahşap chalet mimarisindeki butik otelimiz, sıcak ve konforlu bir konaklama sunmaktadır. Günlük kayak dışında kızak safari, buz pateni ve köy pazarı gezmesi de programa dahildir.</p>',
                'price'          => 82000.00,
                'currency'       => 'TRY',
                'duration_days'  => 6,
                'is_active'      => true,
                'is_international' => true,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1452723312111-3a7d0db0e4a5?w=800',
                'included'       => "İstanbul – Cenevre – İstanbul uçak bileti\nCenevre – Zermatt tren bileti (panoramik)\n5 gece Zermatt butik otel (kahvaltı + akşam yemeği)\n5 günlük kayak pist pass (Zermatt + İtalya tarafı)\nKayak ekipmanı kiralama (kayak, bot, baton)\nBaşlangıç düzeyinde kayak dersi (4 saat)\nKızak safari turu\nTürkçe rehber",
                'excluded'       => "İsviçre seyahat sigortası\nÖğle yemekleri (pist restoranları)\nKişisel harcamalar ve alışveriş\nİleri düzey kayak dersleri",
                'tour_url'       => 'https://setur.com.tr',
                'dates'          => [
                    ['departure_date' => '2026-12-26', 'return_date' => '2026-12-31', 'price' => 89000.00, 'label' => 'Yılbaşı'],
                    ['departure_date' => '2027-01-10', 'return_date' => '2027-01-15', 'price' => 82000.00, 'label' => 'Ocak 2027'],
                    ['departure_date' => '2027-02-14', 'return_date' => '2027-02-19', 'price' => 85000.00, 'label' => 'Sevgililer Günü Özel'],
                ],
            ],

            // -------------------------------------------------------
            // 28 - Balayı & Romantik (parent)
            // -------------------------------------------------------
            [
                'agency_id'      => 5,
                'category_id'    => 28,
                'title'          => 'Maldivler Balayı Paketi – Su Üstü Bungalov 7 Gece',
                'destination'    => 'Maldivler',
                'description'    => '<p>Dünyanın en romantik destinasyonlarından Maldivler\'de su üstü bungalovinizden doğrudan mercan resiflerine inerek şnorkel yapabileceğiniz, kristal berraklığındaki okyanus sularında yüzebileceğiniz ve gün batımını özel seyir terasınızdan izleyebileceğiniz bir balayı deneyimi yaşayacaksınız.</p><p>5 yıldızlı resort otelimizdeki su üstü bungalovlar, özel güverte, cam tabanlı salon ve doğrudan denize iniş merdiveniyle tasarlanmıştır. Özel oluşturulan romantik programınızda güneş doğumunda tekne turu, kandil ışığında plaj yemeği, çiftlere özel spa masajı ve ışıl ışıl yıldızların altında flambe yemeği bulunmaktadır.</p>',
                'price'          => 165000.00,
                'currency'       => 'TRY',
                'duration_days'  => 8,
                'is_active'      => true,
                'is_international' => true,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1573843981267-be1999ff37cd?w=800',
                'included'       => "İstanbul – Malé – İstanbul uçak bileti (business class)\n7 gece su üstü bungalov (5 yıldızlı resort)\nTam pansiyon (kahvaltı + öğle + akşam)\nMalé – resort hidroplan transferi\nGüneş doğumu tekne turu\nPlaj yemeği (1 gece)\nÇift spa masajı (1 seans)\nŞnorkel ekipmanı\nBalayı odası süslemesi",
                'excluded'       => "Vize ücreti (gerekli değil Türk pasaportu ile)\nEkstra aktiviteler (dalış, derin deniz balıkçılığı vb.)\nAlkollü içecekler\nKişisel harcamalar",
                'tour_url'       => 'https://touristica.com.tr',
                'dates'          => [
                    ['departure_date' => '2026-06-06', 'return_date' => '2026-06-13', 'price' => 160000.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-09-12', 'return_date' => '2026-09-19', 'price' => 155000.00, 'label' => 'Eylül'],
                    ['departure_date' => '2026-12-26', 'return_date' => '2027-01-02', 'price' => 185000.00, 'label' => 'Yılbaşı Özel'],
                ],
            ],

            // -------------------------------------------------------
            // 30 - Yurt Dışı Balayı
            // -------------------------------------------------------
            [
                'agency_id'      => 6,
                'category_id'    => 30,
                'title'          => 'Paris Balayı Turu – Eiffel\'in Gölgesinde Romantik 5 Gece',
                'destination'    => 'Paris, Fransa',
                'description'    => '<p>Aşkın başkentinde, dünyanın en romantik şehri Paris\'te çiftlere özel hazırlanmış bu balayı programıyla hayalinizdeki balayı deneyimini yaşayacaksınız. Eiffel Kulesi\'nin ışıltısıyla renklenen Seine kıyılarında el ele yürüyüş, Montmartre\'da sanatçı portreleri, Versailles Sarayı\'nın ihtişamlı bahçeleri ve Paris\'in en seçkin restoranlarında şarap eşliğinde romantik yemekler sizi bekliyor.</p><p>Şehir merkezinde, Champs-Élysées\'e yürüme mesafesinde 5 yıldızlı boutique otelinizde balayı süitiniz çiçekler ve şampanya ile hazırlanmış olarak sizi karşılamaktadır. Özel Türkçe rehberimiz her gün eşliğinizde, kendi isteklerinize göre şekillenebilen programınız esnekliği sayesinde istediğiniz zaman serbest keşfe çıkabilirsiniz.</p>',
                'price'          => 54000.00,
                'currency'       => 'TRY',
                'duration_days'  => 6,
                'is_active'      => true,
                'is_international' => true,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1502602898657-3e91760cbb34?w=800',
                'included'       => "İstanbul – Paris – İstanbul uçak biletleri\n5 gece 5 yıldızlı boutique otel (balayı süiti)\nBalayı odası hazırlığı (çiçek, şampanya, çikolata)\nSabah kahvaltıları\nEiffel Kulesi girişi (3. kat)\nVersailles Sarayı girişi\nSeine nehri akşam cruise\nÖzel Türkçe rehber\nLouvre girişi",
                'excluded'       => "Öğle ve akşam yemekleri\nKişisel harcamalar\nParis müze kart (isteğe bağlı)",
                'tour_url'       => 'https://anitur.com.tr',
                'dates'          => [
                    ['departure_date' => '2026-05-09', 'return_date' => '2026-05-14', 'price' => 54000.00, 'label' => 'Mayıs Baharı'],
                    ['departure_date' => '2026-06-20', 'return_date' => '2026-06-25', 'price' => 57000.00, 'label' => 'Haziran'],
                    ['departure_date' => '2026-09-12', 'return_date' => '2026-09-17', 'price' => 52000.00, 'label' => 'Eylül Romantik'],
                ],
            ],

            // -------------------------------------------------------
            // 33 - Günübirlik Turlar
            // -------------------------------------------------------
            [
                'agency_id'      => 7,
                'category_id'    => 33,
                'title'          => 'Truva ve Çanakkale Günübirlik Turu – İstanbul Kalkışlı',
                'destination'    => 'Çanakkale, Truva',
                'description'    => '<p>MÖ 12. yüzyıldan kalma efsanevi Truva harabeleri ve Çanakkale Savaşları\'nın derin izlerini taşıyan Gelibolu\'yu tek bir günde İstanbul\'dan hareketle ziyaret ediyoruz. Homeros\'un İlyada Destanı\'na konu olan Truva Antik Kenti\'nde Truva Atı\'nın replikasına tırmanmak, şehrin savunma duvarlarını ve kazı alanlarını görmek tarihe tutku duyanlar için eşsiz bir deneyimdir.</p><p>Öğleden önce Truva kazı alanını deneyimli arkeolog rehberimiz eşliğinde gezdikten sonra Çanakkale şehir merkezine geçiyoruz. Öğle yemeği Çanakkale\'nin meşhur restoranlarında deniz ürünleriyle veriliyor. Ardından Şehitler Abidesi ve Anzak Koyu\'na geçerek Çanakkale Savaşları\'nın anlamlı topraklarını ziyaret ediyoruz. Akşam İstanbul\'a dönüş sağlanmaktadır.</p>',
                'price'          => 1850.00,
                'currency'       => 'TRY',
                'duration_days'  => 1,
                'is_active'      => true,
                'is_international' => false,
                'requires_visa'  => false,
                'image'          => 'https://images.unsplash.com/photo-1469474968028-56623f02e42e?w=800',
                'included'       => "İstanbul – Çanakkale – İstanbul özel araç transferleri\nTruva Antik Kenti girişi\nŞehitler Abidesi ve Anzak Koyu ziyareti\nArkeolog rehber (Truva)\nÇanakkale Destanı Tanıtım Merkezi girişi\nÖğle yemeği (Çanakkale'de deniz mahsulleri)\nSigorta",
                'excluded'       => "Kişisel harcamalar\nMüze içi fotoğraf izinleri\nÇanakkale'de isteğe bağlı alışveriş",
                'tour_url'       => 'https://coraltravel.com.tr',
                'dates'          => [
                    ['departure_date' => '2026-04-24', 'return_date' => '2026-04-24', 'price' => 1850.00, 'label' => 'Cuma'],
                    ['departure_date' => '2026-05-02', 'return_date' => '2026-05-02', 'price' => 1850.00, 'label' => 'Cumartesi'],
                    ['departure_date' => '2026-05-09', 'return_date' => '2026-05-09', 'price' => 1850.00, 'label' => 'Cumartesi'],
                    ['departure_date' => '2026-05-23', 'return_date' => '2026-05-23', 'price' => 1850.00, 'label' => 'Cumartesi'],
                    ['departure_date' => '2026-06-06', 'return_date' => '2026-06-06', 'price' => 2000.00, 'label' => 'Haziran'],
                ],
            ],

        ];
    }
}
