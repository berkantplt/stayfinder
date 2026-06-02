<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_search_logs', function (Blueprint $table) {
            $table->json('rejected_tour_ids')->nullable()->after('selected_at');
            $table->timestamp('rejected_at')->nullable()->after('rejected_tour_ids');

            // Negatif memory sorgusu için: user/session bazlı son 24 saat filtreleri
            $table->index(['user_id', 'rejected_at']);
            $table->index(['session_id', 'rejected_at']);
        });
    }

    public function down(): void
    {
        Schema::table('ai_search_logs', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'rejected_at']);
            $table->dropIndex(['session_id', 'rejected_at']);
            $table->dropColumn(['rejected_tour_ids', 'rejected_at']);
        });
    }
};
