<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tours.views_count / clicks_count: model $fillable'da ve sorgularda
     * (popüler sıralama, admin raporu) kullanılıyordu ama kolonlar HİÇ
     * yaratılmamıştı (phantom kolon — o sorgular SQL hatası veriyordu).
     * Bu migration kolonları yaratır ve mevcut ham tablolardan yaşam-boyu
     * toplamları backfill eder. Sonrasında tour_views/tour_clicks ham
     * satırları retention ile budansa bile toplamlar sayaçlarda yaşar.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            if (! Schema::hasColumn('tours', 'views_count')) {
                $table->unsignedBigInteger('views_count')->default(0)->after('is_active');
            }
            if (! Schema::hasColumn('tours', 'clicks_count')) {
                $table->unsignedBigInteger('clicks_count')->default(0)->after('views_count');
            }
        });

        DB::statement('UPDATE tours SET views_count = (SELECT COUNT(*) FROM tour_views WHERE tour_views.tour_id = tours.id)');
        DB::statement('UPDATE tours SET clicks_count = (SELECT COUNT(*) FROM tour_clicks WHERE tour_clicks.tour_id = tours.id)');
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['views_count', 'clicks_count']);
        });
    }
};
