<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Çoklu görsel galerisi: sıralı /storage yolları (ilk görsel = kapak).
     * Mevcut `image` kolonu kapak olarak korunur (kartlar, og:image vb. onu
     * kullanmaya devam eder); `images[0]` ile senkron tutulur.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->json('images')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('images');
        });
    }
};
