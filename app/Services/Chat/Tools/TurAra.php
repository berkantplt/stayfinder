<?php

namespace App\Services\Chat\Tools;

use App\Services\Chat\LlmProfileBuilder;
use App\Services\Matching\Rubric;
use App\Services\Matching\TourMatcher;

/**
 * Kullanıcının tarif ettiği tatili katalogda arar.
 *
 * Model serbest metinden 10 boyutlu vektörü doldurur, deterministik rubrik
 * eşleştirici (asimetrik cezalarla) turu bulur. "Sakin isteyene yoğun tur"
 * verilmesi burada MATEMATİKSEL olarak engellenir — prompt ricasıyla değil.
 */
class TurAra implements ChatTool
{
    public function __construct(
        private readonly TourMatcher $matcher,
        private readonly LlmProfileBuilder $profileBuilder,
    ) {}

    public static function name(): string
    {
        return 'tur_ara';
    }

    public static function schema(): array
    {
        $boyutlar = [];
        foreach (Rubric::dimensions() as $d) {
            $tanim = Rubric::load()['dimensions'][$d];
            $boyutlar[$d] = [
                'type' => 'object',
                'description' => sprintf(
                    '%s. 0 = %s, 100 = %s. SADECE kullanıcı bunu ima ettiyse doldur.',
                    $tanim['ad'], $tanim['uc0'], $tanim['uc100']
                ),
                'properties' => [
                    'deger' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100],
                    'kanit' => [
                        'type' => 'string',
                        'description' => 'Kullanıcının KENDİ cümlesinden birebir alıntı. Uydurma; alıntı konuşmada geçmiyorsa bu boyut yok sayılır.',
                    ],
                ],
                'required' => ['deger', 'kanit'],
            ];
        }

        return [
            'type' => 'function',
            'function' => [
                'name' => self::name(),
                'description' => 'Kullanıcının tarif ettiği tatile uyan turları katalogda arar. '
                    .'Boyutları yalnız kullanıcının söylediklerinden doldur; emin olmadığını BOŞ BIRAK '
                    .'(boş bırakılan boyut eşleştirmeye hiç girmez, yanlış doldurmaktan iyidir). '
                    .'Ölçek çıpaları: "sakin kasaba" ≈ kalabaliklik 20, "her gün yeni şehir" ≈ tempo 85, '
                    .'"5 yıldız" ≈ konfor 80, "kamp" ≈ konfor 15, "kimse rahatsız etmesin" ≈ sosyallik 10.',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'boyutlar' => ['type' => 'object', 'properties' => $boyutlar],
                        'onemli' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'enum' => Rubric::dimensions()],
                            'description' => 'Kullanıcının özellikle vurguladığı en fazla 2 boyut.',
                        ],
                        'filtre' => [
                            'type' => 'object',
                            'description' => 'Sert filtreler — yalnız kullanıcı açıkça söylediyse doldur.',
                            'properties' => [
                                'aylar' => ['type' => 'array', 'items' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 12]],
                                'gun_min' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 60],
                                'gun_max' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 60],
                                'butce_max_try' => ['type' => 'number', 'minimum' => 0],
                                'kalkis_sehri' => ['type' => 'string'],
                                'destinasyon' => ['type' => 'string'],
                                'yurt_disi' => ['type' => 'boolean'],
                            ],
                        ],
                    ],
                    'required' => ['boyutlar'],
                ],
            ],
        ];
    }

    public function run(array $args): array
    {
        $profil = $this->profileBuilder->build(
            is_array($args['boyutlar'] ?? null) ? $args['boyutlar'] : [],
            array_slice((array) ($args['onemli'] ?? []), 0, 2),
            (string) ($args['transkript'] ?? ''), // ChatAgent enjekte eder, model vermez
        );

        if ($profil['agirliklar'] === [] || array_sum($profil['agirliklar']) <= 0) {
            return [
                'turlar' => [],
                'hata' => 'Hiçbir boyut doldurulamadı — kullanıcının ne istediğini anlatan '
                    .'en az bir alıntı gerekiyor. Ona ne aradığını sor.',
            ];
        }

        $baglam = is_array($args['filtre'] ?? null) ? $args['filtre'] : [];
        $sonuc = $this->matcher->match($profil, $baglam);

        return [
            'turlar' => $sonuc['tours'],
            // Hafıza bunu okur: modelin İDDİASI değil, kanıtı doğrulanıp KABUL
            // EDİLEN profil. Reddedilen boyut bir sonraki tura sızmasın.
            'kabul_edilen_degerler' => $profil['degerler'],
            'kapsam' => $sonuc['kapsam'],
            'taban_alti' => $sonuc['below_floor'],
            'karsilanmayan' => array_map(fn ($d) => Rubric::label($d), $sonuc['karsilanmayan']),
            'gevsetme_notlari' => $sonuc['relaxation_notes'],
            'sor' => $sonuc['sor'],
            'olculemeyen_boyutlar' => array_map(fn ($d) => Rubric::label($d), $profil['dusurulen']),
        ];
    }
}
