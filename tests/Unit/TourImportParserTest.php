<?php

namespace Tests\Unit;

use App\Services\TourImport\TourUrlImporter;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Deterministik fiyat/tarih parser'ının GERÇEK malitur.com sayfalarına karşı
 * doğruluk testleri. Fixture'lar tests/Fixtures/import/page_N.txt (cleanHtml
 * çıktısıyla aynı format); beklenen değerler sayfaların elle doğrulanmış
 * ground-truth'undan alınmıştır (10-URL doğrulama denetimi, 2026-07).
 */
class TourImportParserTest extends TestCase
{
    private TourUrlImporter $importer;

    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();
        // Fixture'lardaki tarihler 2026 yazı/sonbaharı — "geçmiş tarih" filtresi
        // deterministik çalışsın diye sabit bugün.
        Carbon::setTestNow(Carbon::parse('2026-07-03'));
        $this->importer = new TourUrlImporter;
        $this->ref = new ReflectionClass($this->importer);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function fixture(int $n): string
    {
        return (string) file_get_contents(__DIR__."/../Fixtures/import/page_{$n}.txt");
    }

    private function invoke(string $method, array $args = []): mixed
    {
        $m = $this->ref->getMethod($method);
        $m->setAccessible(true);

        return $m->invokeArgs($this->importer, $args);
    }

    /** @return array{blocks: array, currency: ?string} */
    private function parseBlocks(int $n): array
    {
        return $this->invoke('deterministicPricingBlocks', [$this->fixture($n)]);
    }

    /** Bloklardaki tüm paketleri düz listeye açar. */
    private function allPackages(array $blocks): array
    {
        $out = [];
        foreach ($blocks as $b) {
            foreach ($b['packages'] as $p) {
                $out[] = $p;
            }
        }

        return $out;
    }

    // ─── B2: "Kabul Edilemez" hayalet paketleri ────────────────────────────────

    public function test_no_ghost_packages_on_any_fixture(): void
    {
        foreach ([1, 2, 3, 6, 7, 8, 9, 10] as $n) {
            $blocks = $this->parseBlocks($n)['blocks'];
            foreach ($this->allPackages($blocks) as $pkg) {
                $this->assertStringNotContainsStringIgnoringCase(
                    'kabul edilemez',
                    $pkg['hotel'],
                    "page_{$n}: 'Kabul Edilemez' paket adı olarak sızmış"
                );
            }
        }
    }

    // ─── B1: İndirimli sayfalarda old/new eşlemesi ─────────────────────────────

    public function test_sivas_discounted_prices_old_new_mapping(): void
    {
        // page_2: %50 indirim — çift kişi 49.998 → 24.999, tek kişi 58.998 → 29.499
        $result = $this->parseBlocks(2);
        $blocks = $result['blocks'];
        $this->assertNotEmpty($blocks, 'Sivas blokları boş dönmemeli');
        $this->assertSame('TRY', $result['currency']);

        $pkg = $this->allPackages($blocks)[0];
        $this->assertSame(49998.0, $pkg['prices']['double_pp']['old'], 'double eski fiyat');
        $this->assertSame(24999.0, $pkg['prices']['double_pp']['new'], 'double indirimli fiyat');
        $this->assertSame(58998.0, $pkg['prices']['single']['old'], 'single eski fiyat');
        $this->assertSame(29499.0, $pkg['prices']['single']['new'], 'single indirimli fiyat');

        // Çocuk fiyatları DOĞRU pakette ve indirimli değerleriyle olmalı
        $this->assertSame(20499.0, $pkg['prices']['child_3_5']['new'], '3-5,99 yaş indirimli');
        $this->assertSame(22749.0, $pkg['prices']['child_7_11']['new'], '7-11,99 yaş indirimli');

        // Başlangıç fiyatı = en düşük İNDİRİMLİ yetişkin fiyat
        $this->assertSame(24999.0, $this->invoke('minAdultPriceFromBlocks', [$blocks]));

        // 18 kalkış tarihi tek blokta
        $allDates = array_merge(...array_map(fn ($b) => $b['dates'], $blocks));
        $this->assertCount(18, array_unique($allDates), 'Sivas: 18 kalkış tarihi');
    }

    public function test_salda_discounted_prices(): void
    {
        // page_9: %50 indirim — en düşük çift kişi 9.500 → 4.750
        $blocks = $this->parseBlocks(9)['blocks'];
        $this->assertNotEmpty($blocks);

        $found = false;
        foreach ($this->allPackages($blocks) as $pkg) {
            if (($pkg['prices']['double_pp']['old'] ?? null) === 9500.0) {
                $this->assertSame(4750.0, $pkg['prices']['double_pp']['new'], 'Salda 9500→4750');
                $found = true;
            }
        }
        $this->assertTrue($found, 'Salda: 9500 eski fiyatlı paket bulunmalı');
        $this->assertSame(4750.0, $this->invoke('minAdultPriceFromBlocks', [$blocks]));

        // Otel adları gerçek adlar olmalı
        $hotels = implode(' | ', array_column($this->allPackages($blocks), 'hotel'));
        $this->assertStringContainsString('Marlen', $hotels);
        $this->assertStringContainsString('Anemon', $hotels);

        // 19 tarih
        $allDates = array_unique(array_merge(...array_map(fn ($b) => $b['dates'], $blocks)));
        $this->assertCount(19, $allDates, 'Salda: 19 kalkış tarihi');
    }

    public function test_ege_koyleri_discounted_prices(): void
    {
        // page_10: blok1 16.498→8.249, blok2 14.998→7.499
        $blocks = $this->parseBlocks(10)['blocks'];
        $this->assertNotEmpty($blocks);

        $pairs = [];
        foreach ($this->allPackages($blocks) as $pkg) {
            $d = $pkg['prices']['double_pp'];
            $pairs[($d['old'] ?? 0).'→'.($d['new'] ?? 0)] = true;
        }
        $this->assertArrayHasKey('16498→8249', $pairs, 'Ege: yaz dönemi indirim çifti');
        $this->assertArrayHasKey('14998→7499', $pairs, 'Ege: ekim dönemi indirim çifti');
        $this->assertSame(7499.0, $this->invoke('minAdultPriceFromBlocks', [$blocks]));
    }

    // ─── İndirimsiz sayfalar: regresyon (old=null, new=tek fiyat) ─────────────

    public function test_italya_blocks_regression(): void
    {
        // page_1: indirim yok — 6 blok, LIGHT PAKET, double 899/849/799/749/699/599
        $result = $this->parseBlocks(1);
        $blocks = $result['blocks'];
        $this->assertCount(6, $blocks, 'İtalya: 6 fiyat bloğu');
        $this->assertSame('EUR', $result['currency']);

        $doubleNews = [];
        foreach ($this->allPackages($blocks) as $pkg) {
            $this->assertSame('LIGHT PAKET', $pkg['hotel']);
            $this->assertNull($pkg['prices']['double_pp']['old'], 'İndirimsiz sayfada old=null kalmalı');
            $doubleNews[] = $pkg['prices']['double_pp']['new'];
        }
        sort($doubleNews);
        $this->assertSame([599.0, 699.0, 749.0, 799.0, 849.0, 899.0], $doubleNews);

        // B4: "2 - 10,99 Yaş" bandı child_3_5 VE child_7_11'i doldurmalı
        $pkg = $this->allPackages($blocks)[0];
        $this->assertNotNull($pkg['prices']['child_3_5']['new'], 'geniş bant child_3_5');
        $this->assertNotNull($pkg['prices']['child_7_11']['new'], 'geniş bant child_7_11');
        $this->assertSame(
            $pkg['prices']['child_3_5']['new'],
            $pkg['prices']['child_7_11']['new'],
            'aynı bandın fiyatı iki kovada aynı olmalı'
        );

        $this->assertSame(599.0, $this->invoke('minAdultPriceFromBlocks', [$blocks]));
    }

    public function test_japonya_blocks_regression(): void
    {
        // page_6: 9 blok, 50 fiyat hücresi — en karmaşık indirimsiz sayfa
        $blocks = $this->parseBlocks(6)['blocks'];
        $this->assertCount(9, $blocks, 'Japonya: 9 fiyat bloğu');
        $this->assertSame(2599.0, $this->invoke('minAdultPriceFromBlocks', [$blocks]));

        // İlk blokta (13-07) iki paket olmalı
        $first = null;
        foreach ($blocks as $b) {
            if (in_array('2026-07-13', $b['dates'], true)) {
                $first = $b;
                break;
            }
        }
        $this->assertNotNull($first, '13-07 bloğu bulunmalı');
        $this->assertCount(2, $first['packages'], '13-07: 2 paket (3* + 3-4* merkezi)');
    }

    public function test_cruise_cabin_names(): void
    {
        // page_3: kabin adları gerçek adlar olmalı (önceki bug: 7/10 "Kabul Edilemez")
        $blocks = $this->parseBlocks(3)['blocks'];
        $this->assertNotEmpty($blocks);

        $hotels = array_unique(array_column($this->allPackages($blocks), 'hotel'));
        $joined = implode(' | ', $hotels);
        $this->assertStringContainsString('İç Kabin', $joined);
        $this->assertStringContainsString('Dış Kabin', $joined);
        $this->assertStringContainsString('Okyanus', $joined);

        // B5: başlangıç fiyatı ilave yatak (493.28) DEĞİL, en düşük double (767.43)
        $this->assertSame(767.43, $this->invoke('minAdultPriceFromBlocks', [$blocks]));
    }

    // ─── B3: Fantom tarihler (kampanya + etkinlik) ────────────────────────────

    public function test_campaign_date_not_harvested(): void
    {
        // Kupon metnindeki "31-12-2026" kalkış tarihi DEĞİL — 1, 6, 7, 8. sayfalarda var
        foreach ([1, 6, 7, 8] as $n) {
            $dates = $this->invoke('harvestDates', [$this->fixture($n)]);
            $this->assertNotContains('2026-12-31', $dates, "page_{$n}: kampanya bitiş tarihi sızmamalı");
        }
    }

    public function test_real_dates_still_harvested(): void
    {
        // Filtre gerçek kalkış tarihlerini YEMEMELİ
        $dates1 = $this->invoke('harvestDates', [$this->fixture(1)]);
        foreach (['2026-07-06', '2026-08-17', '2026-10-26'] as $d) {
            $this->assertContains($d, $dates1, "İtalya gerçek tarihi {$d} korunmalı");
        }

        $dates7 = $this->invoke('harvestDates', [$this->fixture(7)]);
        $this->assertContains('2026-12-26', $dates7, 'İskandinavya 26-12 gerçek tarihi korunmalı');
    }

    public function test_stray_lt_in_text_does_not_nuke_tail(): void
    {
        // yunanistan.com vakası: kaçan bir script'in metni ("i < options.length")
        // yalın "<" bırakıyordu; eski kuyruk-silme regex'i ('<[^<>]*$') SON yalın
        // "<"ten string sonuna dek HER ŞEYİ siliyordu — 196KB kabin fiyat kartı
        // sessizce yok oldu. Yeni kural: yalnız tag-adıyla başlayan, satırsonu
        // içermeyen KISA kırıntı silinir; yalın "<" sonrası içerik korunur.
        $html = "<div>Kruvaziyer Turu</div>\n"
              ."for (var i = 0; i < options.length; i++) {}\n"
              ."<p>IA İç Kabin (27.10.2026)</p>\n"
              ."<span>Çift Kişilik Oda</span>\n"
              ."<b>1.398,00 €</b>\n"
              ."Seç ve Satın Al";
        $text = $this->invoke('cleanHtml', [$html]);
        $this->assertStringContainsString('İç Kabin', $text, 'yalın < sonrası içerik korunmalı');
        $this->assertStringContainsString('1.398,00 €', $text, 'fiyat korunmalı');
        $this->assertStringContainsString('Çift Kişilik Oda', $text);

        // Dosya sonundaki GERÇEK kapanmamış tag kırıntısı yine temizlenir
        $text2 = $this->invoke('cleanHtml', ['<div>İçerik burada</div><div class="yarim']);
        $this->assertStringContainsString('İçerik burada', $text2);
        $this->assertStringNotContainsString('yarim', $text2, 'kapanmamış tag kırıntısı silinmeli');
    }

    public function test_date_row_table_parsed_deterministically(): void
    {
        // page_11: tatilciniz İtalya-Yunanistan (gerçek sayfa) — satır-bazlı tablo:
        // "25.07.2026 3* & 4* Oteller Vb. 769 ,00 €". LLM bu 27 satırlık tabloda
        // yalnız son 2 satırı döndürüp TÜM tarihlere 599 şablonlatmıştı (canlı vaka:
        // 25.07 gerçekte 769). Satır parser'ı kodla okur — her tarih KENDİ fiyatı.
        $r = $this->invoke('deterministicPricingBlocks', [$this->fixture(11)]);
        $this->assertSame('EUR', $r['currency']);

        $got = [];
        foreach ($r['blocks'] as $b) {
            foreach ($b['dates'] as $d) {
                $got[$d] = $b['packages'][0]['prices']['double_pp']['new'];
                $this->assertSame('3* & 4* Oteller Vb.', $b['packages'][0]['hotel']);
            }
        }
        $this->assertCount(27, $got, 'tablodaki 27 tarihin HEPSİ fiyatlanmalı');
        // Sayfadaki gerçek değerler (ground truth):
        foreach ([
            '2026-07-17' => 649.0, '2026-07-25' => 769.0, '2026-07-31' => 749.0,
            '2026-08-01' => 669.0, '2026-08-07' => 699.0, '2026-09-04' => 599.0,
            '2026-11-14' => 599.0,
        ] as $date => $price) {
            $this->assertSame($price, $got[$date], "{$date} kendi fiyatını almalı");
        }

        // Etstur tipi TÜRKÇE kalkış-dönüş ARALIĞI satırları tablo kaydı SAYILMAZ —
        // ikinci tarih (dönüş) otel adına düşer, satır elenir. Aksi halde satır
        // parser'ı etstur render metninde sahte blok üretip per-tarih akışını bozar.
        $ranges = "7 Ocak 2027 - 10 Ocak 2027 3.619,00 EUR\n"
                 ."13 Şubat 2027 - 16 Şubat 2027 3.890,00 EUR\n"
                 ."27 Şubat 2027 - 2 Mart 2027 3.690,00 EUR";
        $r2 = $this->invoke('deterministicPricingBlocks', [$ranges]);
        $this->assertSame([], $r2['blocks'], 'Türkçe tarih-aralığı satırları blok üretmemeli');
    }

    public function test_modal_matrix_marker_parsed(): void
    {
        // etstur Bali vakası: modal tablosu data-price attribute'larından TAM matris.
        // hdr[0]=dönem, hdr[1..]=kolonlar ↔ p[0..]; "2 - 11 Yaş" HEM child_3_5 HEM
        // child_7_11 kovasına yazılır (yaş bandı kapsaması).
        $json = json_encode([[
            'r' => '25 Ağustos 2026 - 1 Eylül 2026',
            't' => [
                'hdr' => ['25 Ağustos 2026 - 01 Eylül 2026', 'Double Odada Kişi Başı Fiyat', 'Tek Kişilik Oda', 'Üçüncü Kişi Fiyat', '0 - 1 Yaş Çocuk', '2 - 11 Yaş Çocuk'],
                'rows' => [[
                    'h' => '4 ve 5 Yıldızlı Oteller - Standard Room',
                    'p' => [
                        ['v' => '2769.00', 'c' => 'USD'],
                        ['v' => '4339.00', 'c' => 'USD'],
                        ['v' => '2099.00', 'c' => 'USD'],
                        ['v' => '199.00', 'c' => 'USD'],
                        ['v' => '1599.00', 'c' => 'USD'],
                    ],
                ]],
            ],
        ]], JSON_UNESCAPED_UNICODE);
        $m = $this->invoke('harvestModalMatrix', ['önce ETSMATRIXJSON<<<'.$json.'>>> sonra']);
        $this->assertCount(1, $m);
        $pkg = $m['2026-08-25']['packages'][0] ?? null;
        $this->assertNotNull($pkg, 'dönemin İLK tarihi (25 Ağustos) kalkış olmalı');
        $this->assertSame('4 ve 5 Yıldızlı Oteller - Standard Room', $pkg['hotel']);
        $this->assertSame(2769.0, $pkg['prices']['double_pp']['new']);
        $this->assertSame(4339.0, $pkg['prices']['single']['new']);
        $this->assertSame(2099.0, $pkg['prices']['extra_bed']['new']);
        $this->assertSame(199.0, $pkg['prices']['child_0_2']['new']);
        $this->assertSame(1599.0, $pkg['prices']['child_3_5']['new']);
        $this->assertSame(1599.0, $pkg['prices']['child_7_11']['new']);
        $this->assertSame('USD', $m['2026-08-25']['currency']);

        // Entity'li işaretçi de (ham HTML innerText) çözülür
        $ent = str_replace(['<<<', '>>>', '"'], ['&lt;&lt;&lt;', '&gt;&gt;&gt;', '&quot;'], 'ETSMATRIXJSON<<<'.$json.'>>>');
        $this->assertCount(1, $this->invoke('harvestModalMatrix', [$ent]));

        $this->assertSame([], $this->invoke('harvestModalMatrix', ['işaretçi yok']));
    }

    public function test_sold_out_dates_harvested(): void
    {
        // etstur tourPeriods JSON'u: "sold":true kalkışlar Tükendi'dir, içe aktarılmaz.
        $html = '{"departCode":"1054017","departureDate":{"year":2026,"month":8,"day":25},"x":1,"sold":false,"remaining":4},'
              . '{"departCode":"1054012","departureDate":{"year":2026,"month":7,"day":21},"x":1,"sold":true,"remaining":0},'
              . '{"departCode":"1054013","departureDate":{"year":2026,"month":7,"day":28},"x":1,"sold":true,"remaining":0}';
        $sold = $this->invoke('harvestSoldOutDates', [$html]);
        $this->assertSame(['2026-07-21', '2026-07-28'], $sold);
        $this->assertNotContains('2026-08-25', $sold, 'satıştaki tarih Tükendi sayılmamalı');

        // "sold" alanı olmayan sayfalarda (etstur-dışı) boş döner
        $this->assertSame([], $this->invoke('harvestSoldOutDates', ['"departureDate":{"year":2026,"month":8,"day":25}']));
    }

    public function test_per_date_prices_parsed_from_marker(): void
    {
        // etstur render iterator'ının bıraktığı işaretçi; Firecrawl markdown'ında
        // pipe'lar kaçışlı gelir ("\|") — harvestPerDatePrices bunu temizleyip
        // her tarihe KENDİ başlangıç fiyatını çıkarmalı (kullanıcı vakası: 3 tarih ayrı).
        $marker = 'ETSDATEPRICES<<<7 Ocak 2027\|3619\|EURO :: 13 Şubat 2027\|3890\|EURO :: 27 Şubat 2027\|3690\|EURO>>>';
        $pd = $this->invoke('harvestPerDatePrices', [$marker]);
        $this->assertCount(3, $pd);
        $this->assertSame(3619.0, $pd['2027-01-07']['price'] ?? null);
        $this->assertSame('EUR', $pd['2027-01-07']['currency'] ?? null);
        $this->assertSame(3890.0, $pd['2027-02-13']['price'] ?? null);
        $this->assertSame(3690.0, $pd['2027-02-27']['price'] ?? null);

        // İşaretçi yoksa boş döner (etstur-dışı sayfalar)
        $this->assertSame([], $this->invoke('harvestPerDatePrices', ['<html>fiyat yok</html>']));

        // Entity'li işaretçi (ham HTML'de innerText &lt;&lt;&lt; olur) da tanınır,
        // ve BİRDEN ÇOK işaretçi (birleşik render + telafi çağrısı) birleştirilir.
        $multi = 'ETSDATEPRICES&lt;&lt;&lt;7 Ocak 2027|3619|EURO&gt;&gt;&gt; ara '
               . 'ETSDATEPRICES<<<27 Şubat 2027\|3690\|EURO>>>';
        $pd2 = $this->invoke('harvestPerDatePrices', [$multi]);
        $this->assertCount(2, $pd2);
        $this->assertSame(3619.0, $pd2['2027-01-07']['price'] ?? null);
        $this->assertSame(3690.0, $pd2['2027-02-27']['price'] ?? null);
    }

    public function test_departure_return_range_not_double_counted(): void
    {
        // Ayder deseni: "kalkış / dönüş" — İKİNCİ tarih dönüştür, sayılmamalı.
        $ayder = 'Kalkış Tarihleri 17.07.2026 / 22.07.2026 Kesin Kalkış '
               . '24.07.2026 / 29.07.2026 07.08.2026 / 12.08.2026';
        $d = $this->invoke('harvestDates', [$ayder]);
        $this->assertContains('2026-07-17', $d, 'kalkış korunmalı');
        $this->assertContains('2026-07-24', $d, 'kalkış korunmalı');
        $this->assertContains('2026-08-07', $d, 'kalkış korunmalı');
        $this->assertNotContains('2026-07-22', $d, 'dönüş tarihi sayılmamalı');
        $this->assertNotContains('2026-07-29', $d, 'dönüş tarihi sayılmamalı');
        $this->assertNotContains('2026-08-12', $d, 'dönüş tarihi sayılmamalı');
        $this->assertCount(3, $d, 'sadece 3 kalkış — dönüşler ayıklanmış (çift sayım yok)');

        // Keyftur deseni: "kalkış - dönüş" (sayısal, boşluklu tire)
        $keyftur = 'Tarihleri Seçin 17-07-2026 - 19-07-2026 24-07-2026 - 26-07-2026';
        $k = $this->invoke('harvestDates', [$keyftur]);
        $this->assertContains('2026-07-17', $k);
        $this->assertContains('2026-07-24', $k);
        $this->assertNotContains('2026-07-19', $k, 'dönüş sayılmamalı');
        $this->assertNotContains('2026-07-26', $k, 'dönüş sayılmamalı');
        $this->assertCount(2, $k, 'sadece 2 kalkış');
    }

    public function test_hotel_page_detected_and_concert_dates_suppressed(): void
    {
        // page_5: otel sayfası — tespit edilmeli
        $this->assertTrue(
            $this->invoke('looksLikeHotelPage', [$this->fixture(5)]),
            'Acapulco otel sayfası tespit edilmeli'
        );

        // Tur sayfaları otel sanılmamalı
        foreach ([1, 2, 6, 9] as $n) {
            $this->assertFalse(
                $this->invoke('looksLikeHotelPage', [$this->fixture($n)]),
                "page_{$n} tur sayfası otel sanılmamalı"
            );
        }

        // Konser tarihleri harvest'ten de elenmiş olmalı (etkinlik bağlamı)
        $dates = $this->invoke('harvestDates', [$this->fixture(5)]);
        $this->assertNotContains('2026-12-31', $dates, 'Derya Uluğ konser tarihi elenmiş olmalı');
    }

    // ─── B4: Geniş yaş bandı eşleme ───────────────────────────────────────────

    public function test_age_band_bucket_mapping(): void
    {
        $cases = [
            ['0 - 1,99 yas', ['child_0_2']],
            ['3 - 5,99 yas', ['child_3_5']],
            ['7 - 11,99 yas', ['child_7_11']],
            ['3 - 11,99 yas', ['child_3_5', 'child_7_11']],
            ['0 - 11,99 yas', ['child_0_2', 'child_3_5', 'child_7_11']],
            ['2 - 11,99 yas', ['child_3_5', 'child_7_11']],
            ['2 - 10,99 yas', ['child_3_5', 'child_7_11']],
            ['iki kisilik oda kisi basi', ['double_pp']],
            ['tek kisilik oda', ['single']],
            ['ilave yatak', ['extra_bed']],
        ];
        foreach ($cases as [$label, $expected]) {
            $this->assertSame(
                $expected,
                $this->invoke('roomTypesFromLabel', [$label]),
                "etiket: {$label}"
            );
        }
    }

    // ─── B8: LLM fiyatı × matris çapraz doğrulama (sahte akışla) ──────────────

    public function test_unavailable_cell_detection(): void
    {
        foreach (['Kabul Edilemez', 'kabul edilemez', 'DOLU', '-', 'Sold Out'] as $cell) {
            $this->assertTrue($this->invoke('isUnavailableCell', [$cell]), "'{$cell}' hücre değeri sayılmalı");
        }
        foreach (['LIGHT PAKET', '4* Anemon Otelleri', 'İç Kabin', '899,00 €'] as $name) {
            $this->assertFalse($this->invoke('isUnavailableCell', [$name]), "'{$name}' hücre değeri sayılmamalı");
        }
    }

    // ─── B9: YATAY tablo (Jolly tipi) — başlıklar art arda, fiyatlar toplu ─────
    // Gerçek jollytur.com sayfasının Firecrawl render çıktısı (2026-07-09):
    // kapak fiyatı hatası vakası — doğru kapak 32.599,50 (iki kişilik kişi başı),
    // ilave yatak 29.099,50 kapak fiyatı OLMAMALI.

    public function test_jolly_horizontal_table_is_parsed_deterministically(): void
    {
        $text = (string) file_get_contents(__DIR__.'/../Fixtures/import/page_jolly_likya_horizontal.txt');
        $result = $this->invoke('deterministicPricingBlocks', [$text]);
        $blocks = $result['blocks'];

        $this->assertNotEmpty($blocks, 'Yatay Jolly tablosu deterministik çözülmeli (LLM fallback tetiklenmemeli)');
        $this->assertSame('TRY', $result['currency']);

        // 9 kalkış tarihi, hepsi aynı fiyat imzasında → tek blokta gruplanır
        $allDates = [];
        foreach ($blocks as $block) {
            $allDates = array_merge($allDates, $block['dates']);
        }
        $this->assertCount(9, $allDates);
        $this->assertContains('2026-07-25', $allDates);
        $this->assertContains('2026-09-19', $allDates);

        $pkg = $blocks[0]['packages'][0];
        $this->assertSame('Fethiye Otelleri. Konaklama Opsiyonu', $pkg['hotel']);

        // Kapak sütunu: eski 65.199 / güncel 32.599,50
        $this->assertEquals(65199.0, $pkg['prices']['double_pp']['old']);
        $this->assertEquals(32599.5, $pkg['prices']['double_pp']['new']);
        $this->assertEquals(38399.5, $pkg['prices']['single']['new']);
        // İlave yatak matriste doğru kovada (kapak fiyatı DEĞİL)
        $this->assertEquals(29099.5, $pkg['prices']['extra_bed']['new']);
        // Bebek: indirimsiz TEK fiyat (999) — çift sanılmamalı
        $this->assertEquals(999.0, $pkg['prices']['child_0_2']['new']);
        $this->assertNull($pkg['prices']['child_0_2']['old']);
        $this->assertEquals(26799.5, $pkg['prices']['child_7_11']['new']);
        // 2.Çocuk (2-6,99 yaş): indirimsiz tek fiyat — kapağa eşit ama farklı kova
        $this->assertEquals(32599.0, $pkg['prices']['child_3_5']['new']);

        // Başlangıç fiyatı = double_pp min — İLAVE YATAK DEĞİL
        $this->assertEquals(32599.5, $this->invoke('minAdultPriceFromBlocks', [$blocks]));
    }

    public function test_horizontal_price_assignment_handles_pairs_and_singles(): void
    {
        // 6 sütun, 10 fiyat: 3 çift + tek + çift + tek (Jolly imzası)
        $assigned = $this->invoke('assignHorizontalPrices', [6, [
            65199.0, 32599.5, 76799.0, 38399.5, 58199.0, 29099.5, 999.0, 53599.0, 26799.5, 32599.0,
        ]]);

        $this->assertNotNull($assigned);
        $this->assertSame(['old' => 65199.0, 'new' => 32599.5], $assigned[0]);
        $this->assertSame(['old' => null, 'new' => 999.0], $assigned[3]);
        $this->assertSame(['old' => 53599.0, 'new' => 26799.5], $assigned[4]);
        $this->assertSame(['old' => null, 'new' => 32599.0], $assigned[5]);

        // Sayı tutarsızlığı → null (güvenme): 2 sütuna 5 fiyat sığmaz
        $this->assertNull($this->invoke('assignHorizontalPrices', [2, [1.0, 2.0, 3.0, 4.0, 5.0]]));
    }

    public function test_title_tail_junk_is_stripped(): void
    {
        // tatilciniz: süre + kalkış kuyruğu atılır; otel-adı segmenti KORUNUR
        // (b977401 regresyonu: "Resort" içeren meşru segmentler siliniyordu)
        $this->assertSame(
            'Fethiye Ölüdeniz Pamukkale Turu / Sunshine Holiday Resort Fethiye',
            $this->invoke('cleanTitle', ['Fethiye Ölüdeniz Pamukkale Turu / Sunshine Holiday Resort Fethiye / 3 Gece 4 Gün / İstanbul, İzmit ve Sakarya Çıkışlı'])
        );
        // turperisi: " | " süre kuyruğu ("3 Gece Otel Konaklamalı" → "N gece" ile yakalanır)
        $this->assertSame(
            'Fethiye Ölüdeniz Dalyan Gökova Turu',
            $this->invoke('cleanTitle', ['Fethiye Ölüdeniz Dalyan Gökova Turu | 3 Gece Otel Konaklamalı'])
        );
        // "Resort/Otel" içeren tek segmentli meşru tur adı DOKUNULMAZ
        $this->assertSame(
            'Rixos Premium Belek Resort Turu',
            $this->invoke('cleanTitle', ['Rixos Premium Belek Resort Turu'])
        );
        // Meşru "/" içeren başlık dokunulmaz kalır
        $this->assertSame(
            'Kapadokya / Ihlara Vadisi Turu',
            $this->invoke('cleanTitle', ['Kapadokya / Ihlara Vadisi Turu'])
        );
        // Tüm segmentler kalıba uyarsa orijinal korunur (boş başlık üretme)
        $this->assertSame(
            '3 Gece 4 Gün / İzmir Çıkışlı',
            $this->invoke('cleanTitle', ['3 Gece 4 Gün / İzmir Çıkışlı'])
        );
        // gezentiturizm: süre BİLGİSİ tur-adı segmentinde ("Kapadokya Turu 2 Gece
        // Konaklamalı") → segment tur adı olduğundan ATILMAZ; sadece pazarlama
        // kuyruğu kalır ama tur adı korunur (eskiden yalnız "Yemek Dahil..." kalıyordu)
        $this->assertSame(
            'Kapadokya Turu 2 Gece Konaklamalı / Yemek Dahil Özel Program',
            $this->invoke('cleanTitle', ['Kapadokya Turu 2 Gece Konaklamalı | Yemek Dahil Özel Program'])
        );
        // etstur Lapland: gerçek ad süre İÇERİYOR ama "tur" kelimesi içermiyor —
        // süre+dolgu soyulunca anlamlı ad kaldığından KORUNUR (eskiden atılıp yalnız
        // "Ekstra Turlar Dahil" etiketi kalıyordu). Sondaki yetim "|" da kırpılır.
        $this->assertSame(
            'Türk Hava Yolları ile Büyüleyici Kuzey Işıkları & Lapland 3 Gece 4 Gün / Ekstra Turlar Dahil',
            $this->invoke('cleanTitle', ['Türk Hava Yolları ile Büyüleyici Kuzey Işıkları & Lapland 3 Gece 4 Gün | Ekstra Turlar Dahil |'])
        );
        // Ayraçsız ama sonu yetim pipe'lı başlık da temizlenir
        $this->assertSame(
            'Ekstra Turlar Dahil',
            $this->invoke('cleanTitle', ['Ekstra Turlar Dahil |'])
        );
    }

    public function test_json_departure_dates_harvested_from_spa_raw_html(): void
    {
        // Etstur/SPA vakası: fiyat/tarih client-side yüklenir, metin şablonu {{ }}
        // ama ham HTML'de kalkış takvimi JSON objesi nettir → deterministik gelir.
        $rawHtml = '<script>window.__DATA={"periods":['
            .'{"departCode":"1","departureDate":{"year":2026,"month":9,"day":25},"price":{}},'
            .'{"departCode":"2","departureDate":{"year":2026,"month":10,"day":17},"price":{}},'
            .'{"departCode":"3","departureDate":{"year":2026,"month":11,"day":27},"price":{}}'
            .']}</script>';

        $dates = $this->invoke('harvestJsonDates', [$rawHtml]);

        $this->assertSame(['2026-09-25', '2026-10-17', '2026-11-27'], $dates);

        // Geçmiş yıl elenir; alakasız JSON false-positive üretmez
        $this->assertSame([], $this->invoke('harvestJsonDates', ['{"foo":{"year":2020,"bar":1}}']));
        $this->assertSame([], $this->invoke('harvestJsonDates', ['']));
    }
}
