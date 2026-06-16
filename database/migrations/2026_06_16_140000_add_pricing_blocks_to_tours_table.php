<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fiyat blokları: her blok bir fiyat matrisi taşır — paketler (otel) ve
     * her paket için oda/yaş tipi fiyatları (eski + indirimli) + o bloğa ait
     * tarihler. Farklı fiyatlı tarihler ayrı blokta. tour_dates ve tours.price
     * bu yapıdan türetilir; bu kolon matrisin doğruluk kaynağıdır.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->json('pricing_blocks')->nullable()->after('price_try');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('pricing_blocks');
        });
    }
};
