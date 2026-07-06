<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Faz 4 alanları:
 * - ai_search_conversations.handoff_*: sohbetten acentaya sıcak devir kaydı
 *   (acentaya "AI danışmandan gelen lead" raporlaması için).
 * - ai_search_logs.near_misses: eşiğin ALTINDA kalan adayların kısa dökümü —
 *   acenta talep radarının "turun neden kaçırdı" içgörüsünün ham verisi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_search_conversations', function (Blueprint $table) {
            $table->timestamp('handoff_at')->nullable()->after('last_result_tour_ids');
            $table->foreignId('handoff_tour_id')->nullable()->after('handoff_at')->constrained('tours')->nullOnDelete();
        });

        Schema::table('ai_search_logs', function (Blueprint $table) {
            $table->json('near_misses')->nullable()->after('result_scores');
        });
    }

    public function down(): void
    {
        Schema::table('ai_search_conversations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('handoff_tour_id');
            $table->dropColumn('handoff_at');
        });

        Schema::table('ai_search_logs', fn (Blueprint $table) => $table->dropColumn('near_misses'));
    }
};
