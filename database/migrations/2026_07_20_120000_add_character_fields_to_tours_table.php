<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tur karakteri: sohbet asistanının "bu tur dinlenmek için / eğlence turu"
     * mantığını kurabilmesi için tur başına tek seferlik üretilen özet + tempo.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->text('character_summary')->nullable()->after('search_text');
            $table->decimal('pace_score', 3, 2)->nullable()->after('character_summary');
            $table->unsignedTinyInteger('character_version')->default(0)->index()->after('pace_score');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['character_summary', 'pace_score', 'character_version']);
        });
    }
};
