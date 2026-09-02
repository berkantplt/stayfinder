<?php

namespace App\Services\Chat\Tools;

use App\Services\Chat\ChatPrompts;
use App\Services\Chat\ConversationState;
use App\Services\Chat\LlmProfileBuilder;
use App\Services\Matching\Rubric;
use App\Services\Matching\TourMatcher;
use Illuminate\Support\Facades\Log;

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
                'description' => ChatPrompts::TUR_ARA_ACIKLAMA,
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
                                'destinasyon' => [
                                    'type' => 'string',
                                    'description' => 'Kullanıcının GİTMEK istediği yer. Daha önce gittiği ya da '
                                        .'"... gibi / tarzında / benzeri" diye andığı yeri BURAYA YAZMA — '
                                        .'burası sert filtredir, arama o şehre kilitlenir.',
                                ],
                                'referans_yer' => [
                                    'type' => 'string',
                                    'description' => 'Kullanıcının KIYAS için andığı yer: daha önce gidip beğendiği '
                                        .'ya da "orası gibi olsun" dediği yer. Burası ARANMAZ, tersine sonuçlardan '
                                        .'çıkarılır; kullanıcı zaten bildiği yeri değil benzerini istiyor.',
                                ],
                                'yurt_disi' => ['type' => 'boolean'],
                            ],
                        ],
                        'kaldirilan_kisitlar' => [
                            'type' => 'array',
                            'items' => ['type' => 'string', 'enum' => ConversationState::FILTRE_ANAHTARLARI],
                            'description' => 'Kullanıcının bu mesajda VAZGEÇTİĞİ filtre alanları '
                                .'("bütçe fark etmez", "Fethiye şart değil"). Yazılan alanlar hem bu aramadan '
                                .'hem konuşma hafızasından silinir. Vazgeçilen yoksa boş bırak.',
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

        $baglam = is_array($args['filtre'] ?? null) ? $args['filtre'] : [];

        if ($profil['agirliklar'] === [] || array_sum($profil['agirliklar']) <= 0) {
            return $this->profilsizDal($profil, $baglam, (string) ($args['transkript'] ?? ''));
        }

        $sonuc = $this->matcher->match($profil, $baglam);

        // Katalogda hiç yayınlanabilir puan yoksa bu bir SİSTEM durumu, kullanıcının
        // isteğiyle ilgisi yok. Ayırt edilmezse bot "sana uyan tur yok" diyerek
        // yanlış bilgi verir (aslında hiçbir tur aranabilir durumda değildir).
        if (($sonuc['katalog_puanli_tur'] ?? 0) === 0) {
            return $this->katalogHazirDegilDali();
        }

        return $this->normalDal($profil, $sonuc);
    }

    /**
     * "Diğerleri" görünümü: chat'te gösterilen turlardan sonrakiler.
     *
     * LLM'e hiç gidilmez — oturumdaki profil (değerler + ağırlıklar + kısıtlar)
     * yeniden kullanılıp aynı eşleştirici daha uzun listeyle çalıştırılır.
     * Böylece hem ücretsiz hem sıralama chat'tekiyle birebir tutarlı.
     *
     * @param  int  $limit  toplamda kaç tur gösterilir (chat'teki kartlar dahil)
     * @return array{items: array, toplam: int}
     */
    public function genisletilmisListe(ConversationState $durum, int $limit): array
    {
        $gosterilen = $durum->gosterilenIdler();
        $sonuc = $this->matcher->match(
            ['degerler' => $durum->degerler, 'agirliklar' => $durum->agirliklar],
            $durum->varsayilanFiltre() + [
                'top_n' => max(1, $limit - count($gosterilen)),
                'haric' => $gosterilen,   // konum değil KİMLİK dışlama
                'cesitlilik' => false,    // genişletilmiş liste saf sıralama
            ],
        );

        return [
            'items' => $sonuc['tours'],
            'toplam' => $sonuc['toplam_eslesme'],
        ];
    }

    /**
     * Profil çıkmadı (hiçbir boyut kanıtla doldurulamadı) dalı.
     *
     * Kullanıcı SOMUT kısıt verdiyse (Fethiye / 4-5 gün / bütçe...) elimizde
     * arama yapacak kadar bilgi VAR — tatil tarzı boyutu çıkmadı diye soru
     * sormak, cevabı bilerek geri çevirmek olur. Canlı şikayet: "fethiye
     * turu düşünüyorum 2 kişi 4 veya 5 gün" mesajına tur yerine soru döndü.
     *
     * Soru sormak yalnızca ELDE HİÇBİR ŞEY YOKKA makul; orada da ikinci kez
     * boş dönersek kullanıcıyı döngüye sokmamak için listeye düşülür.
     */
    private function profilsizDal(array $profil, array $baglam, string $transkript): array
    {
        // Düşen boyutlar her iki dalda da raporlanır: eval "kanıtsız boyut
        // doldurma denemesi"ni buradan okuyor, sessizce yutulmamalı
        $dusurulen = array_map(fn ($d) => Rubric::label($d), $profil['dusurulen']);

        $somutKisit = $this->somutKisitVarMi($baglam);

        if (! $somutKisit && $this->kullaniciTuru($transkript) < 2) {
            return [
                'turlar' => [],
                'olculemeyen_boyutlar' => $dusurulen,
                'hata' => ChatPrompts::TUR_ARA_PROFILSIZ_HATA,
            ];
        }

        $liste = $this->matcher->listele($baglam, 5);

        return [
            'turlar' => $liste['tours'],
            // Bilerek 'toplam_eslesme' DEĞİL: o anahtar "diğerleri" butonunu
            // açıyor, buton ise oturumdaki profille çalışıyor — profil
            // yokken boş dönerdi. Sayı yalnız modelin bilgisi olarak durur.
            'toplam_uygun_tur' => $liste['toplam_eslesme'],
            'olculemeyen_boyutlar' => $dusurulen,
            'profilsiz_liste' => true,
            'not' => $somutKisit
                ? ChatPrompts::TUR_ARA_PROFILSIZ_NOT_KISITLI
                : ChatPrompts::TUR_ARA_PROFILSIZ_NOT_KISITSIZ,
        ];
    }

    /** Katalogda hiç yayınlanabilir rubrik puanı yok — SİSTEM durumu dalı. */
    private function katalogHazirDegilDali(): array
    {
        Log::warning('[TurAra] Katalogda yayınlanabilir rubrik puanı yok — arama yapılamıyor');

        return [
            'turlar' => [],
            'katalog_hazir_degil' => true,
            'hata' => ChatPrompts::TUR_ARA_KATALOG_HAZIR_DEGIL,
        ];
    }

    /** Normal dal: profil kuruldu, eşleştirici koştu, sonuç zenginleştirilerek döner. */
    private function normalDal(array $profil, array $sonuc): array
    {
        return [
            'turlar' => $sonuc['tours'],
            // Eşiği geçen tur azken doldurulan "tam uymuyor ama en yakını"
            // listesi. Modelin bunları uyumlu turmuş gibi anlatması YASAK —
            // sistem promptunda ayrı kural var.
            'yakin_turlar' => $sonuc['yakin_turlar'] ?? [],
            // Hafıza bunu okur: modelin İDDİASI değil, kanıtı doğrulanıp KABUL
            // EDİLEN profil. Reddedilen boyut bir sonraki tura sızmasın.
            'kabul_edilen_degerler' => $profil['degerler'],
            // Ağırlıklar da durumda saklanır: "diğerleri" görünümü aynı profili
            // yeniden kurup listeyi genişletebilsin (LLM'e tekrar sormadan)
            'kabul_edilen_agirliklar' => $profil['agirliklar'],
            'toplam_eslesme' => $sonuc['toplam_eslesme'],
            'kapsam' => $sonuc['kapsam'],
            'taban_alti' => $sonuc['below_floor'],
            'karsilanmayan' => array_map(fn ($d) => Rubric::label($d), $sonuc['karsilanmayan']),
            'gevsetme_notlari' => $sonuc['relaxation_notes'],
            'sor' => $sonuc['sor'],
            'olculemeyen_boyutlar' => array_map(fn ($d) => Rubric::label($d), $profil['dusurulen']),
        ];
    }

    /**
     * Kullanıcı arama yapmaya yetecek somut bir kısıt verdi mi?
     *
     * "Fethiye", "4-5 gün", "20 bin TL'ye kadar" gibi şeyler tatil TARZI
     * (sakinlik/tempo) söylemez ama katalogda arama yapmaya fazlasıyla yeter.
     * Bu varken soru sormak, cevabı bilerek geri çevirmek olur.
     *
     * yurt_disi tek başına sayılmaz: "yurt dışı olsun" hâlâ yüzlerce turu
     * kapsıyor, daraltıcı değil.
     */
    private function somutKisitVarMi(array $baglam): bool
    {
        foreach (['destinasyon', 'kalkis_sehri'] as $anahtar) {
            if (trim((string) ($baglam[$anahtar] ?? '')) !== '') {
                return true;
            }
        }

        foreach (['gun_min', 'gun_max', 'butce_max_try'] as $anahtar) {
            if (($baglam[$anahtar] ?? 0) > 0) {
                return true;
            }
        }

        return is_array($baglam['aylar'] ?? null) && $baglam['aylar'] !== [];
    }

    /**
     * Transkript kullanıcı satırlarından oluşur (ChatAgent birleştirir); satır
     * sayısı kaçıncı kez denendiğini verir. Ayrı sayaç taşımaya gerek yok.
     */
    private function kullaniciTuru(string $transkript): int
    {
        return count(array_filter(
            preg_split('/\R/u', trim($transkript)) ?: [],
            fn ($s) => trim($s) !== '',
        ));
    }
}
