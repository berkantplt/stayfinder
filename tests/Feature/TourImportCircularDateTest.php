<?php

namespace Tests\Feature;

use App\Services\TourImport\TourUrlImporter;
use Illuminate\Support\Facades\Http;
use OpenAI\Laravel\Facades\OpenAI;
use OpenAI\Responses\Chat\CreateResponse;
use Tests\TestCase;

/**
 * Jolly vakası: sayfa dipnotunda "Tur sirküsü yayımlandığı 15.08.2026 tarihinde
 * geçerlidir" yazıyor ve site bu tarihi HER GÜN bugünle basıyor. Tek gerçek kalkış
 * 04 Eylül 2026 iken forma sahte bir "bugün kalkışlı" tur giriyordu — üstelik
 * listenin başına oturduğu ve fiyat bloğu olmadığı için ilk bloğun fiyatı
 * kopyalanıyordu (yanlış tarihe yanlış fiyat).
 *
 * İki katman: (1) kalkış takvimi başlığının kapsadığı bölge dışındaki tarihler
 * sayılmaz, (2) sayfada başka kalkış varken "bugün" tarihi meta tarih kabul edilir.
 */
class TourImportCircularDateTest extends TestCase
{
    private function fakeLlm(array $dates = []): void
    {
        OpenAI::fake([
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => json_encode([
                        'title' => 'Uçaklı Kapadokya Turu',
                        'price' => 12500,
                        'currency' => 'TRY',
                        'departure_dates' => $dates,
                    ])],
                ]],
            ]),
            CreateResponse::fake([
                'choices' => [[
                    'index' => 0,
                    'message' => ['role' => 'assistant', 'content' => '{"pricing_blocks":[]}'],
                ]],
            ]),
        ]);
    }

    private function fakePage(string $body): void
    {
        Http::fake(['*' => Http::response(
            '<html><body>'.$body.'</body></html>',
            200,
            ['Content-Type' => 'text/html']
        )]);
    }

    /** Katman 2: takvim bölgesi dışındaki dipnot tarihi kalkış sayılmaz. */
    public function test_footnote_date_outside_departure_region_is_ignored(): void
    {
        $footnote = today()->addDays(10);   // sirküler dipnotu — kalkış değil
        $departure = today()->addDays(45);  // gerçek kalkış

        $this->fakePage(
            '<p>Not : Çocuk kategorisi 7-12 yaşları arasıdır. Tur sirküsü yayımlandığı '
            .$footnote->format('d.m.Y').' tarihinde geçerlidir.</p>'
            .'<h2>Tur Hareket Tarihi: '.$departure->format('d.m.Y').'</h2>'
        );
        $this->fakeLlm();

        $result = (new TourUrlImporter)->import('https://1.1.1.1/kapadokya-turu');

        $this->assertSame(
            [$departure->toDateString()],
            $result['departure_dates'],
            'yalnızca takvim bölgesindeki kalkış kalmalı, dipnot tarihi elenmiş olmalı'
        );
    }

    /** Katman 1: sayfada başka kalkış varken bugünün tarihi listeden düşer. */
    public function test_today_is_dropped_when_other_departures_exist(): void
    {
        $departure = today()->addDays(20);

        // Takvim başlığı YOK — pozitif kapsam devre dışı, her iki tarih de toplanır.
        $this->fakePage(
            '<p>Tur sirküsü yayımlandığı '.today()->format('d.m.Y').' tarihinde geçerlidir.</p>'
            .'<p>Hareket: '.$departure->format('d.m.Y').'</p>'
        );
        $this->fakeLlm();

        $result = (new TourUrlImporter)->import('https://1.1.1.1/kapadokya-turu-2');

        $this->assertSame([$departure->toDateString()], $result['departure_dates']);
        $this->assertStringContainsString(
            'kalkış listesinden çıkarıldı',
            implode(' ', $result['warnings']),
            'acentaya neden çıkarıldığı bildirilmeli'
        );
    }

    /** Bugün TEK tarihse dokunulmaz — gerçekten aynı gün kalkışlı olabilir. */
    public function test_today_is_kept_when_it_is_the_only_date(): void
    {
        $this->fakePage('<p>Hareket: '.today()->format('d.m.Y').'</p>');
        $this->fakeLlm();

        $result = (new TourUrlImporter)->import('https://1.1.1.1/son-dakika-turu');

        $this->assertSame([today()->toDateString()], $result['departure_dates']);
    }

    /** Bölge içindeki kalkış, yanındaki "Kampanyalar"/"Etkinliği" yüzünden elenmez. */
    public function test_weak_context_words_do_not_drop_dates_inside_region(): void
    {
        $departure = today()->addDays(30);

        $this->fakePage(
            '<h1>Uçaklı Kapadokya Turu Şarap Tadım Etkinliği İle</h1>'
            .'<nav>Tur Programı Fiyatlar &amp; Tarihler Otel Bilgileri Kampanyalar</nav>'
            .'<p>Tur Hareket Tarihi: '.$departure->format('d.m.Y').'</p>'
        );
        $this->fakeLlm();

        $result = (new TourUrlImporter)->import('https://1.1.1.1/sarap-tadim-turu');

        $this->assertSame(
            [$departure->toDateString()],
            $result['departure_dates'],
            'takvim bölgesindeki kalkış zayıf bağlam kelimeleriyle elenmemeli'
        );
    }

    /** Regresyon: takvim başlığı olmayan sayfada serbest metindeki tarih korunur. */
    public function test_plain_page_without_region_headings_still_harvests_dates(): void
    {
        $a = today()->addDays(15);
        $b = today()->addDays(40);

        $this->fakePage('<p>Turumuz '.$a->format('d.m.Y').' ve '.$b->format('d.m.Y').' günleri düzenlenir.</p>');
        $this->fakeLlm();

        $result = (new TourUrlImporter)->import('https://1.1.1.1/duz-sayfa-turu');

        $this->assertSame([$a->toDateString(), $b->toDateString()], $result['departure_dates']);
    }
}
