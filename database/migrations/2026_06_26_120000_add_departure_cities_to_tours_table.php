<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kalkış şehri (otobüsün kalktığı il) + durak şehirler (yol üstünde yolcu
     * alınan iller). Müşteri "kalkış şehrim" filtresi bunlara bakar: kalkış VEYA
     * duraklardan biri müşterinin şehriyse tur listede görünür. Kolonlar DB'de
     * nullable (eski satırlar bozulmaz); zorunluluk validation katmanında.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('departure_city', 60)->nullable()->after('destination');
            $table->json('stop_cities')->nullable()->after('departure_city');
            $table->index('departure_city');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['departure_city']);
            $table->dropColumn(['departure_city', 'stop_cities']);
        });
    }
};
