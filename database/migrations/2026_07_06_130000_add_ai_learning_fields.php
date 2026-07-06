<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Öğrenen arama alanları:
 * - tours.ai_ctr_bonus: "gösterildi ama tıklanmadı" sinyalinden gece hesaplanan
 *   sınırlı (±0.03) skor düzeltmesi — zengin-daha-zengin döngüsüne izin yok.
 * - users.ai_preference: girişli kullanıcının geçmiş tıklama/favorilerinden
 *   türetilen tercih profili (bütçe bandı, yön oranı, aylar, ortalama embedding).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->decimal('ai_ctr_bonus', 5, 4)->default(0)->after('embedding');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('ai_preference')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('tours', fn (Blueprint $table) => $table->dropColumn('ai_ctr_bonus'));
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('ai_preference'));
    }
};
