<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tur detay alanları — acentanın kendi sitesinden URL ile çekilebilen,
     * tur sayfasını zenginleştiren ek bilgiler.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->text('departure_points')->nullable()->after('excluded');   // kalkış/biniş noktaları + saatler
            $table->text('itinerary')->nullable()->after('departure_points');  // gün gün program
            $table->text('hotel_info')->nullable()->after('itinerary');        // konaklama / otel
            $table->text('extras')->nullable()->after('hotel_info');           // ekstra tur ve aktiviteler
            $table->text('cancellation_policy')->nullable()->after('extras');  // iptal / iade koşulları
            $table->text('guide_info')->nullable()->after('cancellation_policy'); // rehber bilgisi / notları
            $table->string('frequency')->nullable()->after('guide_info');      // hareket sıklığı (örn. "Her Cuma")
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn([
                'departure_points', 'itinerary', 'hotel_info', 'extras',
                'cancellation_policy', 'guide_info', 'frequency',
            ]);
        });
    }
};
