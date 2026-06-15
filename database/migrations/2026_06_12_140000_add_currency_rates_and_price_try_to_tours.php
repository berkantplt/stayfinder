<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Çoklu para birimi karşılaştırma düzeltmesi: tüm fiyat filtreleri/sıralama/
     * agregasyonlar TL'ye normalize edilmiş tours.price_try üzerinden çalışır.
     * Kurlar currency_rates tablosunda; app:update-currency-rates komutu TCMB'den
     * günlük günceller. Seed değerleri yaklaşıktır — ilk TCMB çekiminde ezilir.
     */
    private const SEED_RATES = [
        'TRY' => 1.0,
        'USD' => 43.0,
        'EUR' => 50.0,
        'GBP' => 57.0,
        'AED' => 11.7,
        'SAR' => 11.5,
    ];

    public function up(): void
    {
        Schema::create('currency_rates', function (Blueprint $table) {
            $table->id();
            $table->string('currency', 3)->unique();
            $table->decimal('rate_to_try', 12, 4);
            $table->string('source')->default('seed');
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();
        });

        foreach (self::SEED_RATES as $currency => $rate) {
            DB::table('currency_rates')->insert([
                'currency' => $currency,
                'rate_to_try' => $rate,
                'source' => 'seed',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        Schema::table('tours', function (Blueprint $table) {
            $table->decimal('price_try', 12, 2)->nullable()->after('currency');
            $table->index('price_try');
        });

        // Backfill: mevcut turların TL karşılığı
        foreach (self::SEED_RATES as $currency => $rate) {
            DB::table('tours')
                ->where('currency', $currency)
                ->update(['price_try' => DB::raw('ROUND(price * '.$rate.', 2)')]);
        }

        // Bilinmeyen/boş para birimi → TRY varsay
        DB::table('tours')
            ->whereNull('price_try')
            ->update(['price_try' => DB::raw('price')]);
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['price_try']);
            $table->dropColumn('price_try');
        });

        Schema::dropIfExists('currency_rates');
    }
};
