<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trafik sayfası "son 7/30/90 gün" kırılımını viewed_at üzerinden sorguluyor.
     * tour_clicks'te clicked_at indeksi vardı, tour_views'te karşılığı yoktu.
     */
    public function up(): void
    {
        Schema::table('tour_views', function (Blueprint $table) {
            $table->index('viewed_at');
        });
    }

    public function down(): void
    {
        Schema::table('tour_views', function (Blueprint $table) {
            $table->dropIndex(['viewed_at']);
        });
    }
};
