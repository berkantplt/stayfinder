<?php

namespace App\Services\Chat\Tools;

use App\Services\AiSearch\DestinationKnowledgeService;

/**
 * "Nelerimiz var, neyi hiç satmıyoruz" (şartname madde 3).
 *
 * Kritik: SATMADIĞIMIZ ürün tiplerini de döndürür. Model "villa turumuz yok"
 * cümlesini tahminle kurarsa negatif yönde uydurma yapmış olur; bu araç o
 * cümlenin veriye dayanmasını sağlar.
 */
class EnvanterOzeti implements ChatTool
{
    /**
     * Katalogda satılan/satılmayan ürün tipleri. Katalog alanı eklemek yerine
     * sabit liste: turXtur paket/grup turu pazaryeri, konaklama kiralama değil.
     */
    private const SATILAN = ['paket tur', 'grup turu', 'günübirlik tur', 'turlu konaklama'];

    private const SATILMAYAN = [
        'villa / özel mülk kiralama',
        'müstakil bungalov kiralama',
        'sadece otel/oda rezervasyonu',
        'kruvaziyer / gemi turu',
        'uçak bileti veya araç kiralama',
    ];

    public function __construct(private readonly DestinationKnowledgeService $knowledge) {}

    public static function name(): string
    {
        return 'envanter_ozeti';
    }

    public static function schema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => self::name(),
                'description' => 'Katalogda hangi destinasyonlarda kaç tur olduğunu ve hangi ÜRÜN '
                    .'TİPLERİNİ sattığımızı/satmadığımızı döndürür. Kullanıcı satmadığımız bir şey '
                    .'istediğinde (villa, kruvaziyer, sadece otel) bunu SÖYLEMEDEN ÖNCE bu aracı çağır — '
                    .'yokluğu tahminle söyleme.',
                'parameters' => ['type' => 'object', 'properties' => (object) []],
            ],
        ];
    }

    public function run(array $args): array
    {
        // inventory(): {normalized => {city, count}} — satılabilir turlardan sayım
        $sehirler = collect($this->knowledge->inventory())
            ->map(fn ($row) => ['sehir' => $row['city'], 'tur_sayisi' => $row['count']])
            ->sortByDesc('tur_sayisi')
            ->values()
            ->all();

        return [
            'destinasyonlar' => $sehirler,
            'farkli_destinasyon_sayisi' => count($sehirler),
            'sattigimiz_urun_tipleri' => self::SATILAN,
            'satmadigimiz_urun_tipleri' => self::SATILMAYAN,
        ];
    }
}
