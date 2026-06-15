<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\CurrencyRate;
use App\Models\Tour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CurrencyConversionTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Notification::fake();

        CurrencyRate::resetCache();
        CurrencyRate::where('currency', 'USD')->update(['rate_to_try' => 40]);

        $this->agency = Agency::create([
            'name' => 'Kur Acenta',
            'slug' => 'kur-acenta',
            'email' => 'kur@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);
    }

    protected function tearDown(): void
    {
        CurrencyRate::resetCache();
        parent::tearDown();
    }

    private function makeTour(float $price, string $currency, string $title = 'Kur Test Turu'): Tour
    {
        return Tour::create([
            'agency_id' => $this->agency->id,
            'category_id' => null,
            'title' => $title,
            'destination' => 'Roma',
            'description' => 'Kur dönüşüm testi.',
            'price' => $price,
            'currency' => $currency,
            'duration_days' => 5,
            'departure_date' => today()->addDays(30),
            'return_date' => today()->addDays(35),
            'is_active' => true,
        ]);
    }

    public function test_price_try_is_computed_on_create(): void
    {
        $usdTour = $this->makeTour(500, 'USD');
        $tryTour = $this->makeTour(8000, 'TRY');

        $this->assertSame('20000.00', (string) $usdTour->fresh()->price_try);
        $this->assertSame('8000.00', (string) $tryTour->fresh()->price_try);
    }

    public function test_price_try_recomputes_when_price_or_currency_changes(): void
    {
        $tour = $this->makeTour(500, 'USD');

        $tour->update(['price' => 600]);
        $this->assertSame('24000.00', (string) $tour->fresh()->price_try);

        $tour->update(['currency' => 'TRY']);
        $this->assertSame('600.00', (string) $tour->fresh()->price_try);
    }

    public function test_price_filter_compares_in_try_across_currencies(): void
    {
        $this->makeTour(500, 'USD', 'Pahalı USD Turu');   // 20.000 TL
        $this->makeTour(8000, 'TRY', 'Uygun TRY Turu');

        // "10.000 TL altı" filtresi: 500 USD turu artık yanlışlıkla geçemez
        $cheap = Tour::active()->priceBetween(null, 10000)->pluck('title');
        $this->assertContains('Uygun TRY Turu', $cheap->all());
        $this->assertNotContains('Pahalı USD Turu', $cheap->all());

        // Liste sayfası entegrasyonu
        $this->get(route('tours.index', ['max_price' => 10000]))
            ->assertOk()
            ->assertSee('Uygun TRY Turu')
            ->assertDontSee('Pahalı USD Turu');
    }

    public function test_update_currency_rates_command_fetches_tcmb_and_recomputes(): void
    {
        $tour = $this->makeTour(500, 'USD'); // rate 40 → 20000

        Http::fake([
            'www.tcmb.gov.tr/*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Tarih_Date Tarih="12.06.2026" Date="06/12/2026" Bulten_No="2026/111">
<Currency CrossOrder="0" Kod="USD" CurrencyCode="USD"><Unit>1</Unit><Isim>ABD DOLARI</Isim><CurrencyName>US DOLLAR</CurrencyName><ForexBuying>44.9000</ForexBuying><ForexSelling>45.0000</ForexSelling></Currency>
<Currency CrossOrder="9" Kod="EUR" CurrencyCode="EUR"><Unit>1</Unit><Isim>EURO</Isim><CurrencyName>EURO</CurrencyName><ForexBuying>51.9000</ForexBuying><ForexSelling>52.0000</ForexSelling></Currency>
</Tarih_Date>
XML, 200),
        ]);

        $this->artisan('app:update-currency-rates')->assertExitCode(0);

        $this->assertDatabaseHas('currency_rates', [
            'currency' => 'USD',
            'rate_to_try' => '45.0000',
            'source' => 'tcmb',
        ]);
        $this->assertDatabaseHas('currency_rates', ['currency' => 'EUR', 'rate_to_try' => '52.0000']);

        // Mevcut USD turunun TL karşılığı yeni kurla tazelendi
        $this->assertSame('22500.00', (string) $tour->fresh()->price_try);
    }

    public function test_command_keeps_old_rates_when_tcmb_is_unreachable(): void
    {
        Http::fake(['www.tcmb.gov.tr/*' => Http::response('', 500)]);

        $this->artisan('app:update-currency-rates')->assertExitCode(1);

        // Eski kur korunur
        $this->assertDatabaseHas('currency_rates', ['currency' => 'USD', 'rate_to_try' => '40.0000']);
    }

    public function test_unknown_currency_falls_back_to_raw_price(): void
    {
        $this->assertSame(1234.0, CurrencyRate::toTry(1234.0, 'XXX'));
        $this->assertSame(1234.0, CurrencyRate::toTry(1234.0, null));
    }
}
