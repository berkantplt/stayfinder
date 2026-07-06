<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hibrit arama için anahtar kelime kanalı: turun normalize edilmiş tam metin
 * temsili (embedding temsiliyle aynı içerik). "Yüzme molalı" gibi birebir
 * ifadeler vektör benzerliğinde sıralamada kaybolabiliyor — FULLTEXT kanalı
 * bunları yakalar, iki sıralama RRF ile birleşir. Kolon embedding üretimiyle
 * birlikte doldurulur (GenerateTourEmbeddingJob + komut).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->text('search_text')->nullable()->after('embedding');
        });

        // FULLTEXT yalnızca MySQL'de (test sqlite'ı LIKE fallback kullanır)
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tours ADD FULLTEXT search_text_fulltext (search_text)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE tours DROP INDEX search_text_fulltext');
        }

        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('search_text');
        });
    }
};
