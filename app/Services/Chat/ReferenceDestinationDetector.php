<?php

namespace App\Services\Chat;

use App\Support\TurkishText;

/**
 * "Gitmek istediği yer" ile "kıyas için andığı yer"i ayırır.
 *
 * Canlı şikayet: "geçen sene eşimle Fethiye'ye gittik, o tarz benzer turlar
 * öner" mesajında model Fethiye'yi filtre.destinasyon'a yazıyordu. destinasyon
 * SERT filtre olduğu için katalog Fethiye turlarına kilitleniyor, rubrik de
 * yalnız onları kendi arasında sıralayabiliyordu — kullanıcıya benzer yerler
 * yerine zaten bildiği yer geri gösteriliyordu.
 *
 * Model "benzer" kelimesini anlıyor (cevabında da benzetme yaptığını sanıyor);
 * sorun onu yazacak ALANIN olmamasıydı. Alan açıldı, ama prompt tek başına
 * güvence değil: aynı hata bir kez daha yapıldığında konuşmanın tamamı yanlış
 * şehre kilitlendiği için sunucu tarafında deterministik bir emniyet var.
 *
 * KURAL: şehrin geçtiği HER yerde kıyas/geçmiş işareti varsa referanstır.
 * Bir kez bile düz istek olarak geçiyorsa ("yine Fethiye istiyoruz") hedeftir.
 * Yanlış dışlamaktansa filtreyi olduğu gibi bırakmak daha az zararlı: kaçırılan
 * benzetme kullanıcıya fazladan bir tur gösterir, yanlış dışlama ise istediği
 * yeri ondan gizler.
 */
class ReferenceDestinationDetector
{
    /** Kıyas işaretleri — "Fethiye gibi", "o tarzda", "havasında". */
    private const KIYAS = [
        'gibi', 'gibisi', 'benzer', 'benzeri', 'benzerini', 'benzerleri', 'benzerlerini',
        'benzerinden', 'tarz', 'tarzi', 'tarzinda', 'havasi', 'havasini', 'havasinda',
        'misali', 'muadili', 'kivaminda', 'ayarinda', 'tadinda', 'alternatifi', 'esdegeri',
    ];

    /** Geçmiş ziyaret işaretleri — "geçen sene gittik", "gezmiştik". */
    private const GECMIS = [
        'gittik', 'gittim', 'gitmistik', 'gitmistim', 'gitmisiz', 'gittigimiz', 'gittigim',
        'gezdik', 'gezdim', 'gezmistik', 'gezmistim', 'gorduk', 'gordum', 'gormustuk',
        'kaldik', 'kaldim', 'tatildeydik', 'yapmistik', 'olmustuk',
        'gecen sene', 'gecen yil', 'gecen yaz', 'gecen kis',
    ];

    /**
     * Şehrin hemen ÖNÜNDE geçerse kıyas değil tekrar isteğidir: "yine Fethiye",
     * "tekrar Fethiye'ye". Geçmişten söz etmesi burada hedefi değiştirmez.
     */
    private const TEKRAR = ['yine', 'tekrar', 'gene', 'bir daha'];

    /** Şehir çevresinde bakılan pencere (byte); ~12-14 kelime. */
    private const PENCERE = 90;

    /** "yine/tekrar" için dar pencere: yalnız şehrin hemen önü. */
    private const TEKRAR_PENCERESI = 24;

    /**
     * Model filtresini düzeltir: kıyas için anılan yer destinasyon'dan
     * referans_yer'e taşınır.
     *
     * @param  array<string, mixed>  $filtre
     * @param  string  $transkript  kullanıcının KENDİ satırları (ChatAgent üretir)
     * @return array<string, mixed>
     */
    public function apply(array $filtre, string $transkript): array
    {
        $hedef = trim((string) ($filtre['destinasyon'] ?? ''));
        if ($hedef === '') {
            return $filtre;
        }

        $referans = trim((string) ($filtre['referans_yer'] ?? ''));

        // Model aynı yeri iki alana da yazdıysa kıyas kazanır: bir yer hem
        // "gitmek istediğim" hem "benzerini istediğim" olamaz, ikisi birden
        // durursa sert filtre yine devreye girip aramayı kilitlerdi.
        if ($referans !== '' && $this->ayniYer($referans, $hedef)) {
            unset($filtre['destinasyon']);

            return $filtre;
        }

        if ($referans === '' && $this->isReference($hedef, $transkript)) {
            $filtre['referans_yer'] = $hedef;
            unset($filtre['destinasyon']);
        }

        return $filtre;
    }

    /**
     * Şehir konuşmada yalnızca KIYAS olarak mı geçiyor?
     *
     * Cümle değil PENCERE bakılır: kullanıcılar noktalama kullanmadan yazıyor
     * ("...fethiyeye gittik çok güzeldi bize o tarz benzer turlar önerir misin")
     * ve cümleye bölmek bu mesajda tek parça verirdi.
     */
    public function isReference(string $sehir, string $transkript): bool
    {
        $metin = TurkishText::normalize($transkript);
        $ad = TurkishText::normalize($sehir);

        // 3 harften kısa ad kelime sınırıyla bile güvenilir eşleşmiyor
        if ($metin === '' || mb_strlen($ad, 'UTF-8') < 3) {
            return false;
        }

        // Türkçe ek toleransı: "fethiyeye", "kapadokyada"
        $desen = '/(?<![\p{L}\d])'.preg_quote($ad, '/').'\p{L}{0,4}(?![\p{L}\d])/u';
        if (! preg_match_all($desen, $metin, $eslesmeler, PREG_OFFSET_CAPTURE)) {
            // Şehir konuşmada hiç geçmiyor (ör. önceki turdan yapışan filtre) —
            // karar verecek kanıt yok, filtreye dokunma
            return false;
        }

        foreach ($eslesmeler[0] as [$eslesme, $offset]) {
            $uzunluk = strlen($eslesme);

            // "yine Fethiye" → kullanıcı oraya TEKRAR gitmek istiyor
            $on = mb_strcut($metin, max(0, $offset - self::TEKRAR_PENCERESI), min($offset, self::TEKRAR_PENCERESI), 'UTF-8');
            foreach (self::TEKRAR as $kelime) {
                if (TurkishText::hasWord($on, $kelime, 0)) {
                    return false;
                }
            }

            $pencere = mb_strcut(
                $metin,
                max(0, $offset - self::PENCERE),
                min($offset, self::PENCERE) + $uzunluk + self::PENCERE,
                'UTF-8'
            );

            if (! $this->isaretVar($pencere)) {
                return false; // en az bir kez DÜZ istek olarak geçmiş → hedef
            }
        }

        return true;
    }

    private function isaretVar(string $normalizePencere): bool
    {
        foreach ([...self::KIYAS, ...self::GECMIS] as $kelime) {
            if (TurkishText::hasWord($normalizePencere, $kelime, 0)) {
                return true;
            }
        }

        return false;
    }

    private function ayniYer(string $a, string $b): bool
    {
        $na = TurkishText::normalize($a);
        $nb = TurkishText::normalize($b);

        return $na !== '' && $nb !== '' && (str_contains($na, $nb) || str_contains($nb, $na));
    }
}
