<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ulaşım tipi: kartta "Gidiş Dönüş Otobüs" gibi gösterilir.
 *
 * Rakip taramasında 10 Türk tur sitesinin 7'sinde ulaşım tipi filtresi vardı,
 * turXtur'da hiç yoktu — ne kolon ne gösterim. Kart bilgisi olarak eklendi;
 * filtreye sokmak veri dolmadan yanıltıcı olur (çoğu tur NULL kalacak).
 *
 * Tek kolon, gidiş-dönüş ortak: ayrı gidiş/dönüş tipi tutan siteler var ama
 * pratikte turların ezici çoğunluğu aynı araçla gidip dönüyor. Gerçekten
 * farklılaşan tur çıkarsa kolon enum'una değil, ayrı bir alana ihtiyaç olur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('transport_type', 20)->nullable()->after('duration_nights');
            $table->index('transport_type');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['transport_type']);
            $table->dropColumn('transport_type');
        });
    }
};
