<?php

namespace App\Services\Chat;

use App\Support\TurkishText;

/**
 * Kullanıcının o anki mesajındaki yurt içi / yurt dışı beyanını yakalar.
 *
 * Canlı şikayet: ilk mesajda yön yoktu, model yurt dışı turlar önerdi; "Yurt
 * içi olsun" düzeltmesinde metin yurt içi turları anlattı ama kartlar aynı
 * yurt dışı turlarda kaldı. yurt_disi filtresini yazmak tamamen modele
 * bırakılmıştı. Kıyas yerinde (ReferenceDestinationDetector) olduğu gibi
 * burada da sunucu tarafında deterministik bir emniyet var: kullanıcı açıkça
 * söylediyse filtre, modelin ne gönderdiğinden bağımsız yazılır.
 *
 * Yalnız SON mesaja bakılır: yön konuşma boyunca değişebilir ("yurt dışı
 * olsun" → "yok, yurt içi kalalım"); önceki beyan zaten durumda taşınıyor.
 *
 * Dönüş yurt_disi bayrağıyla aynı anlamda: true = yurt dışı, false = yurt içi,
 * null = beyan yok ya da belirsiz (filtreye dokunulmaz). Belirsizde dokunmamak
 * bilinçli: yanlış yön, kullanıcının istediği turları ondan gizler.
 */
class OriginIntentDetector
{
    /**
     * Yurt dışı beyanları — anahtar ifade, değer izin verilen Türkçe ek
     * uzunluğu: "yurt dışına" (2), "yurtdışında" (3). 4 yapılmaz: "yurt
     * dışından gelen misafirlerim" bir hedef beyanı değil.
     */
    private const YURT_DISI = ['yurt disi' => 3, 'yurtdisi' => 3, 'ulke disi' => 3, 'turkiye disi' => 3];

    /** Yurt içi beyanları. "turkiyede" ek almaz: "Türkiye'den çıkalım" yurt içi sayılmasın. */
    private const YURT_ICI = [
        'yurt ici' => 3, 'yurtici' => 3, 'ulke ici' => 3, 'turkiye ici' => 3,
        'turkiyede' => 0, "turkiye'de" => 0,
    ];

    /** Beyanın hemen ardında geçerse anlam tersine döner: "yurt dışı olmasın", "yurt dışına çıkmak istemiyorum". */
    private const OLUMSUZ = ['olmasin', 'olmaz', 'istemiyor', 'istemem', 'istemeyiz', 'cikmayalim', 'kalmayalim', 'degil', 'yok'];

    /** Beyanın hemen ardında geçerse beyan sayılmaz: "yurt içi yurt dışı fark etmez". */
    private const KAYITSIZ = ['fark etmez', 'farketmez', 'fark etmiyor', 'farketmiyor', 'onemli degil'];

    /** Beyandan sonra bakılan pencere (byte); ~6-8 kelime. */
    private const PENCERE = 48;

    public function detect(string $mesaj): ?bool
    {
        // Tipografik kesme işareti ("Türkiye’de") düz kesmeye indirilir
        $metin = str_replace(['’', '‘', '`'], "'", TurkishText::normalize($mesaj));
        if ($metin === '') {
            return null;
        }

        $oylar = array_unique(array_merge(
            $this->oylar($metin, self::YURT_DISI, true),
            $this->oylar($metin, self::YURT_ICI, false),
        ));

        // Tek yön → beyan. İki yön birden ("yurt içi mi yurt dışı mı?") ya da
        // hiç beyan yok → null, filtreye dokunulmaz.
        return count($oylar) === 1 ? reset($oylar) : null;
    }

    /**
     * İfadelerin her eşleşmesi için çözümlenmiş yön. Hemen ardında olumsuzlama
     * varsa yön tersine döner, kayıtsızlık varsa eşleşme sayılmaz.
     *
     * @param  array<string, int>  $ifadeler  ifade → izin verilen ek uzunluğu
     * @return bool[]
     */
    private function oylar(string $metin, array $ifadeler, bool $yon): array
    {
        $oylar = [];
        foreach ($ifadeler as $ifade => $ek) {
            $desen = '/(?<![\p{L}\d])'.preg_quote($ifade, '/').'\p{L}{0,'.$ek.'}(?![\p{L}\d])/u';
            if (! preg_match_all($desen, $metin, $eslesmeler, PREG_OFFSET_CAPTURE)) {
                continue;
            }
            foreach ($eslesmeler[0] as [$eslesme, $offset]) {
                $pencere = mb_strcut($metin, $offset + strlen($eslesme), self::PENCERE, 'UTF-8');
                if ($this->icerir($pencere, self::KAYITSIZ)) {
                    continue;
                }
                $oylar[] = $this->icerir($pencere, self::OLUMSUZ) ? ! $yon : $yon;
            }
        }

        return $oylar;
    }

    /** @param  string[]  $kelimeler */
    private function icerir(string $pencere, array $kelimeler): bool
    {
        foreach ($kelimeler as $kelime) {
            if (TurkishText::hasWord($pencere, $kelime)) {
                return true;
            }
        }

        return false;
    }
}
