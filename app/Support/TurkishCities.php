<?php

namespace App\Support;

class TurkishCities
{
    /**
     * Türkiye'nin 81 ili (alfabetik). Kalkış/durak şehir seçimi ve müşteri
     * şehir filtresi bu sabit listeyi kullanır — serbest metin yerine tutarlı
     * değerler (filtrelemede "İstanbul" = "İstanbul").
     *
     * @var array<int, string>
     */
    public const PROVINCES = [
        'Adana', 'Adıyaman', 'Afyonkarahisar', 'Ağrı', 'Aksaray', 'Amasya', 'Ankara',
        'Antalya', 'Ardahan', 'Artvin', 'Aydın', 'Balıkesir', 'Bartın', 'Batman',
        'Bayburt', 'Bilecik', 'Bingöl', 'Bitlis', 'Bolu', 'Burdur', 'Bursa',
        'Çanakkale', 'Çankırı', 'Çorum', 'Denizli', 'Diyarbakır', 'Düzce', 'Edirne',
        'Elazığ', 'Erzincan', 'Erzurum', 'Eskişehir', 'Gaziantep', 'Giresun',
        'Gümüşhane', 'Hakkâri', 'Hatay', 'Iğdır', 'Isparta', 'İstanbul', 'İzmir',
        'Kahramanmaraş', 'Karabük', 'Karaman', 'Kars', 'Kastamonu', 'Kayseri',
        'Kırıkkale', 'Kırklareli', 'Kırşehir', 'Kilis', 'Kocaeli', 'Konya', 'Kütahya',
        'Malatya', 'Manisa', 'Mardin', 'Mersin', 'Muğla', 'Muş', 'Nevşehir', 'Niğde',
        'Ordu', 'Osmaniye', 'Rize', 'Sakarya', 'Samsun', 'Siirt', 'Sinop', 'Sivas',
        'Şanlıurfa', 'Şırnak', 'Tekirdağ', 'Tokat', 'Trabzon', 'Tunceli', 'Uşak',
        'Van', 'Yalova', 'Yozgat', 'Zonguldak',
    ];

    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return self::PROVINCES;
    }

    /**
     * Verilen değer geçerli bir il mi (normalize karşılaştırma).
     */
    public static function isValid(?string $value): bool
    {
        return self::canonical($value) !== null;
    }

    /**
     * Serbest metni listedeki resmi il adına eşler (büyük/küçük harf ve
     * Türkçe karakter toleranslı); eşleşme yoksa null. İçe aktarmada metinden
     * yakalanan şehir adını standart il adına çevirmek için kullanılır.
     */
    public static function canonical(?string $value): ?string
    {
        $needle = self::fold((string) $value);
        if ($needle === '') {
            return null;
        }

        foreach (self::PROVINCES as $province) {
            if (self::fold($province) === $needle) {
                return $province;
            }
        }

        return null;
    }

    private static function fold(string $value): string
    {
        $value = trim($value);
        $map = ['İ' => 'i', 'I' => 'i', 'ı' => 'i', 'Ş' => 's', 'ş' => 's',
            'Ğ' => 'g', 'ğ' => 'g', 'Ü' => 'u', 'ü' => 'u', 'Ö' => 'o', 'ö' => 'o',
            'Ç' => 'c', 'ç' => 'c', 'Â' => 'a', 'â' => 'a'];
        $value = strtr($value, $map);

        return mb_strtolower($value, 'UTF-8');
    }
}
