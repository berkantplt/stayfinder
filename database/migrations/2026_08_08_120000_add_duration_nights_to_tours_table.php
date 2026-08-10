<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tur süresi bugüne kadar yalnız gün olarak tutuluyordu, oysa Türk tur
 * sayfalarının standardı "7 gece 8 gün". İçe aktarma gece sayısını ZATEN
 * çıkarıyordu (TourUrlImporter LLM şemasında duration_nights var) ama
 * gün = gece + 1 hesabından sonra atıyordu.
 *
 * NULL bırakılabilir: gece bilgisi bilinmeyen turlarda gösterim gün-1'e düşer
 * (bkz. Tour::getDurationLabelAttribute). Mevcut kayıtlar backfill EDİLMEZ —
 * "9 gün / 7 gece otel + uçak" gibi turlarda gece = gün-1 yanlış olur, uydurma
 * veri yazmak yerine bilinmiyor bırakılıyor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->unsignedTinyInteger('duration_nights')->nullable()->after('duration_days');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('duration_nights');
        });
    }
};
