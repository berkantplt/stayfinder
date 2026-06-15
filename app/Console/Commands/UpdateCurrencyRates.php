<?php

namespace App\Console\Commands;

use App\Models\CurrencyRate;
use App\Models\Tour;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UpdateCurrencyRates extends Command
{
    protected $signature = 'app:update-currency-rates';

    protected $description = 'TCMB today.xml üzerinden döviz kurlarını günceller ve tours.price_try kolonlarını yeniden hesaplar.';

    private const TCMB_URL = 'https://www.tcmb.gov.tr/kurlar/today.xml';

    public function handle(): int
    {
        $tracked = array_diff(array_keys(Tour::SUPPORTED_CURRENCIES), ['TRY']);

        try {
            $response = Http::timeout(15)->get(self::TCMB_URL);
            $response->throw();
            $xml = simplexml_load_string($response->body());

            if ($xml === false) {
                throw new \RuntimeException('TCMB XML parse edilemedi.');
            }
        } catch (\Throwable $e) {
            Log::warning('[UpdateCurrencyRates] TCMB erişilemedi, son bilinen kurlar korunuyor.', [
                'message' => $e->getMessage(),
            ]);
            $this->error('TCMB erişilemedi: '.$e->getMessage().' — mevcut kurlar korunuyor.');

            return self::FAILURE;
        }

        $updated = 0;

        foreach ($xml->Currency as $node) {
            $code = strtoupper((string) $node['CurrencyCode']);

            if (! in_array($code, $tracked, true)) {
                continue;
            }

            $unit = max(1, (int) $node->Unit);
            $selling = (float) str_replace(',', '.', (string) $node->ForexSelling);

            if ($selling <= 0) {
                continue; // TCMB bazı günler boş döndürebilir — eski kuru koru
            }

            $rate = round($selling / $unit, 4);

            CurrencyRate::updateOrCreate(
                ['currency' => $code],
                ['rate_to_try' => $rate, 'source' => 'tcmb', 'fetched_at' => now()]
            );

            // O para birimindeki tüm turların TL karşılığını tek sorguda tazele
            DB::table('tours')
                ->where('currency', $code)
                ->update(['price_try' => DB::raw('ROUND(price * '.$rate.', 2)')]);

            $this->line($code.' → '.number_format($rate, 4).' TL');
            $updated++;
        }

        CurrencyRate::resetCache();

        if ($updated === 0) {
            $this->warn('TCMB cevabında takip edilen para birimi bulunamadı.');

            return self::FAILURE;
        }

        $this->info($updated.' kur güncellendi, price_try kolonları tazelendi.');

        return self::SUCCESS;
    }
}
