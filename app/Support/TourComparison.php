<?php

namespace App\Support;

use App\Models\CurrencyRate;
use App\Models\Tour;
use Illuminate\Support\Collection;

/**
 * Tur karşılaştırma sayfasının hesap katmanı.
 *
 * NEDEN AYRI SINIF: sayfanın değeri "üç kartı yan yana dizmek" değil, FARKI
 * bulup göstermek. Fark hesabı (en ucuz kim, gün başına maliyet ne, dahil
 * listesindeki hangi madde sadece bir turda var) view'da yapılamayacak kadar
 * iş; controller'a konursa test edilemez.
 *
 * VİZE SATIRININ KAYNAĞI: tours.requires_visa — acentanın formda işaretlediği
 * ya da acentanın KENDİ sayfasından çıkarılan beyan. DestinationProfile
 * .requires_visa_for_tr'ye BAĞLAMA: o alan LLM'in dünya bilgisi ve doğrulandı ki
 * hatalı (Yunanistan "vize gerekmiyor" dönüyor). Burada taşıdığımız iddia bizim
 * değil acentanın; metasearch için doğru duruş bu.
 *
 * Alan üç durumlu (vizeli/vizesiz/belirtilmemiş) — belirtilmemişse satır boş
 * kalır, "vizesiz" diye okunmaz.
 *
 * LLM YOK — bilinçli. "kahvaltı dahil" ile "sabah kahvaltısı" ayrı madde
 * sayılır, bunu kabul ediyoruz: her karşılaştırma isteğinde API çağrısı
 * yapmak bu doğruluk artışını hak etmiyor. Eşleştirme SearchText::normalize
 * üzerinden — arama tarafıyla aynı katlama, yani "Yüzme Molası" ↔ "yuzme
 * molasi" tek madde.
 */
class TourComparison
{
    /** Fiyat farkı bu yüzdenin altında kalırsa aynı sayılır (kur yuvarlaması gürültüsü). */
    private const FIYAT_ESIK_YUZDE = 1.0;

    /** Bu uzunluğun altındaki satır madde değil, artık karakterdir. */
    private const MADDE_MIN_UZUNLUK = 3;

    /**
     * @param  Collection<int, Tour>  $tours
     * @return array<string, mixed>
     */
    public static function build(Collection $tours): array
    {
        return [
            'fiyatlar' => self::fiyatlar($tours),
            'satirlar' => self::satirlar($tours),
            'dahil' => self::listeDiff($tours, 'included'),
            'haric' => self::listeDiff($tours, 'excluded'),
        ];
    }

    /**
     * Fiyat kutusu: geçerli fiyat, kampanya, TL normalizasyonu, gün başına maliyet.
     *
     * @param  Collection<int, Tour>  $tours
     * @return array<int, array<string, mixed>>
     */
    private static function fiyatlar(Collection $tours): array
    {
        $sonuc = [];

        foreach ($tours as $tour) {
            $kampanya = $tour->activeCampaign;
            $liste = (float) $tour->price;
            $gecerli = $kampanya ? (float) $kampanya->discount_price : $liste;

            // TL normalizasyonu ŞART: EUR turun ham sayısıyla TRY turunkini yan
            // yana koyup "bu daha ucuz" demek düpedüz yanlış cevaptır.
            $gecerliTry = CurrencyRate::toTry($gecerli, $tour->currency);
            $gun = (int) ($tour->duration_days ?? 0);

            $sonuc[$tour->id] = [
                'etiket' => self::paraEtiketi($gecerli, $tour->currency_symbol),
                'eskiEtiket' => $kampanya ? self::paraEtiketi($liste, $tour->currency_symbol) : null,
                'kampanya' => $kampanya !== null,
                // Para birimi TL değilse kıyasın hangi sayı üzerinden yapıldığını
                // göstermek dürüstlük meselesi — rozet havadan gelmiş görünmesin.
                'tryEtiket' => strtoupper((string) $tour->currency) === 'TRY'
                    ? null
                    : '≈ '.self::paraEtiketi($gecerliTry, '₺'),
                'try' => $gecerliTry,
                'gunlukTry' => $gun > 0 && $gecerliTry > 0 ? $gecerliTry / $gun : null,
                'gunlukEtiket' => null,
                'enUcuz' => false,
                'enAvantajli' => false,
                'farkYuzde' => null,
            ];
        }

        return self::rozetle($sonuc);
    }

    /**
     * "EN UCUZ" ve "GÜNÜ EN UYGUN" rozetleri.
     *
     * Gün başına maliyet ayrı bir rozet çünkü asıl karar burada veriliyor:
     * 4 günlük 20.000 TL'lik tur, 7 günlük 28.000 TL'likten ucuz görünür ama
     * günü 5.000 TL'ye gelir, diğeri 4.000 TL'ye. Tek başına toplam fiyat
     * kullanıcıyı yanlış tura itiyor.
     *
     * @param  array<int, array<string, mixed>>  $fiyatlar
     * @return array<int, array<string, mixed>>
     */
    private static function rozetle(array $fiyatlar): array
    {
        $tutarlar = array_filter(array_column($fiyatlar, 'try'), fn ($t) => $t > 0);
        $enDusuk = $tutarlar !== [] ? min($tutarlar) : null;

        $gunlukler = array_filter(array_column($fiyatlar, 'gunlukTry'), fn ($g) => $g !== null && $g > 0);
        $enDusukGunluk = $gunlukler !== [] ? min($gunlukler) : null;

        foreach ($fiyatlar as $id => $satir) {
            if ($enDusuk !== null && $satir['try'] > 0) {
                $fark = ($satir['try'] - $enDusuk) / $enDusuk * 100;
                $fiyatlar[$id]['farkYuzde'] = $fark < self::FIYAT_ESIK_YUZDE ? 0 : (int) round($fark);
                $fiyatlar[$id]['enUcuz'] = $fiyatlar[$id]['farkYuzde'] === 0;
            }

            if ($satir['gunlukTry'] !== null && $enDusukGunluk !== null) {
                $fiyatlar[$id]['gunlukEtiket'] = self::paraEtiketi($satir['gunlukTry'], '₺').' / gün';
                $fiyatlar[$id]['enAvantajli'] =
                    ($satir['gunlukTry'] - $enDusukGunluk) / $enDusukGunluk * 100 < self::FIYAT_ESIK_YUZDE;
            }
        }

        // Herkes kazanıyorsa rozet bilgi taşımaz, sadece gürültü olur.
        foreach (['enUcuz', 'enAvantajli'] as $rozet) {
            $kazanan = array_filter($fiyatlar, fn ($f) => $f[$rozet]);
            if (count($kazanan) === count($fiyatlar)) {
                foreach ($fiyatlar as $id => $_) {
                    $fiyatlar[$id][$rozet] = false;
                }
            }
        }

        return $fiyatlar;
    }

    private static function paraEtiketi(float $tutar, string $sembol): string
    {
        return number_format($tutar, 0, ',', '.').' '.$sembol;
    }

    /**
     * Özellik satırları. Sıra kasıtlı: karar verdiren alanlar üstte, teyit
     * amaçlı okunanlar (iptal, rehber) altta.
     *
     * @param  Collection<int, Tour>  $tours
     * @return array<int, array<string, mixed>>
     */
    private static function satirlar(Collection $tours): array
    {
        return array_values(array_filter([
            self::satir('Acenta', $tours, fn (Tour $t) => $t->agency?->name),
            self::satir('Kategori', $tours, fn (Tour $t) => $t->category
                ? trim(($t->category->icon ?? '').' '.$t->category->name)
                : null),
            self::satir('Destinasyon', $tours, fn (Tour $t) => $t->destination),
            self::sureSatiri($tours),
            self::satir('Ulaşım', $tours, fn (Tour $t) => $t->transport_label ?: null),
            self::satir('Kalkış yeri', $tours, fn (Tour $t) => $t->departure_label ?: null),
            self::satir('En yakın kalkış', $tours, fn (Tour $t) => self::kalkisMetni($t)),
            self::satir('Hareket sıklığı', $tours, fn (Tour $t) => $t->frequency),
            self::satir('Konaklama', $tours, fn (Tour $t) => $t->hotel_info),
            self::satir('Program', $tours, fn (Tour $t) => is_array($t->itinerary) && $t->itinerary !== []
                ? count($t->itinerary).' günlük detaylı program'
                : null),
            self::satir('Tempo', $tours, fn (Tour $t) => self::tempoEtiketi($t->pace_score)),
            self::satir('Vize', $tours, fn (Tour $t) => match (true) {
                $t->requires_visa === null => null,
                // Kapıda vize kullanıcı için ayrı bir kategori: konsolosluk
                // randevusu/evrak yok, sınırda ödeniyor. "Vize gerekiyor"a
                // katlansa turu gereksiz yere caydırıcı gösterirdi.
                $t->requires_visa && (bool) $t->visa_on_arrival => 'Kapıda vize',
                (bool) $t->requires_visa => 'Vize gerekiyor',
                default => 'Vize gerekmiyor',
            }),
            self::satir('Ekstra turlar', $tours, fn (Tour $t) => $t->extras),
            self::satir('Rehber', $tours, fn (Tour $t) => $t->guide_info),
            self::satir('İptal koşulları', $tours, fn (Tour $t) => $t->cancellation_policy),
        ]));
    }

    /**
     * @param  Collection<int, Tour>  $tours
     * @param  array<int, string>  $rozetler
     * @return array<string, mixed>|null
     */
    private static function satir(string $etiket, Collection $tours, callable $deger, array $rozetler = []): ?array
    {
        $degerler = [];
        foreach ($tours as $tour) {
            $ham = $deger($tour);
            $degerler[$tour->id] = is_string($ham) ? (trim($ham) ?: null) : $ham;
        }

        $dolu = array_filter($degerler, fn ($d) => $d !== null && $d !== '');

        // Hiçbir turda yoksa satır hiç basılmaz — üç tane "—" kıyasa katkı vermez.
        if ($dolu === []) {
            return null;
        }

        return [
            'etiket' => $etiket,
            'degerler' => $degerler,
            // "Aynı" demek için hepsi DOLU ve birebir eşit olmalı: birinde değer
            // varken diğerinde yoksa bu bir farktır, "farkları göster"de gizlenmez.
            'ayni' => count($dolu) === $tours->count() && count(array_unique($dolu)) === 1,
            'rozetler' => $rozetler,
        ];
    }

    /**
     * @param  Collection<int, Tour>  $tours
     * @return array<string, mixed>|null
     */
    private static function sureSatiri(Collection $tours): ?array
    {
        $gunler = $tours->filter(fn (Tour $t) => (int) $t->duration_days > 0)
            ->mapWithKeys(fn (Tour $t) => [$t->id => (int) $t->duration_days]);

        $rozetler = [];
        // Rozet ancak fark varsa bilgi taşır: hepsi 5 günse "EN UZUN" gürültüdür.
        if ($gunler->count() > 1 && $gunler->unique()->count() > 1) {
            $enUzun = $gunler->max();
            foreach ($gunler->filter(fn ($g) => $g === $enUzun)->keys() as $id) {
                $rozetler[$id] = 'EN UZUN';
            }
        }

        return self::satir('Süre', $tours, fn (Tour $t) => $t->duration_label ?: null, $rozetler);
    }

    /**
     * Bugünden sonraki ilk kalkış + kalan tarih sayısı.
     *
     * dates ilişkisi controller'da eager-load'lu geliyor; nextDate accessor'ı
     * kullanılsaydı tur başına bir sorgu daha açardı.
     */
    private static function kalkisMetni(Tour $tour): ?string
    {
        $bugun = now()->startOfDay();
        $gelecek = $tour->dates->filter(fn ($d) => $d->departure_date >= $bugun)->values();
        $ilk = $gelecek->first()?->departure_date ?? $tour->departure_date;

        if (! $ilk) {
            return null;
        }

        $metin = $ilk->translatedFormat('j F Y');
        $kalan = max($gelecek->count() - 1, 0);

        return $kalan > 0 ? $metin.' (+'.$kalan.' tarih)' : $metin;
    }

    /** pace_score 0-1: düşük = dinlenme ağırlıklı, yüksek = tempolu gezi. */
    private static function tempoEtiketi(?float $skor): ?string
    {
        if ($skor === null) {
            return null;
        }

        return match (true) {
            $skor < 0.35 => 'Dinlenme ağırlıklı',
            $skor < 0.65 => 'Dengeli',
            default => 'Tempolu gezi',
        };
    }

    /**
     * included/excluded metinlerini maddelere bölüp turlar arasında eşleştirir.
     *
     * @param  Collection<int, Tour>  $tours
     * @return array<int, array<string, mixed>>
     */
    private static function listeDiff(Collection $tours, string $alan): array
    {
        $maddeler = [];
        foreach ($tours as $tour) {
            $maddeler[$tour->id] = self::maddeler((string) $tour->{$alan});
        }

        // Bir madde kaç turda geçiyor: 1 → sadece o turda, hepsi → ortak.
        $sayim = [];
        foreach ($maddeler as $liste) {
            foreach (array_keys($liste) as $anahtar) {
                $sayim[$anahtar] = ($sayim[$anahtar] ?? 0) + 1;
            }
        }

        $turSayisi = $tours->count();
        $sonuc = [];

        foreach ($maddeler as $id => $liste) {
            $satirlar = [];
            foreach ($liste as $anahtar => $metin) {
                $satirlar[] = [
                    'metin' => $metin,
                    'ortak' => $sayim[$anahtar] === $turSayisi,
                    'ozel' => $sayim[$anahtar] === 1,
                ];
            }

            // Farklar üste: kullanıcı bu sayfaya ortak olanı okumaya gelmedi.
            usort($satirlar, fn ($a, $b) => $a['ortak'] <=> $b['ortak']);

            $sonuc[$id] = [
                'maddeler' => $satirlar,
                'ozelSayisi' => count(array_filter($satirlar, fn ($s) => $s['ozel'])),
                'ortakSayisi' => count(array_filter($satirlar, fn ($s) => $s['ortak'])),
            ];
        }

        return $sonuc;
    }

    /**
     * Serbest metni maddelere böler.
     *
     * @return array<string, string> normalize anahtar => görünen metin
     */
    private static function maddeler(string $metin): array
    {
        $sonuc = [];

        foreach (preg_split('/\r\n|\r|\n/', $metin) ?: [] as $satir) {
            // Acenta metinlerinde madde işareti serbest: •, -, *, ✓, "1." hepsi geçiyor.
            $temiz = trim(preg_replace('/^\s*(?:[-•*·✓✔✗×–—]+|\d+[.)])\s*/u', '', $satir) ?? $satir);
            $temiz = trim($temiz, " \t.;,");

            if (mb_strlen($temiz) < self::MADDE_MIN_UZUNLUK) {
                continue;
            }

            $anahtar = SearchText::normalize($temiz);

            if ($anahtar === '' || isset($sonuc[$anahtar])) {
                continue;
            }

            $sonuc[$anahtar] = $temiz;
        }

        return $sonuc;
    }
}
