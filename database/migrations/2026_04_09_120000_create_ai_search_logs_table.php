<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_search_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 100)->nullable()->index();
            $table->text('raw_query');
            $table->text('normalized_query')->nullable();
            $table->json('intent')->nullable();
            $table->json('applied_filters')->nullable();
            $table->unsignedInteger('candidate_count')->default(0);
            $table->json('result_tour_ids')->nullable();
            $table->json('result_scores')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->foreignId('selected_tour_id')->nullable()->constrained('tours')->nullOnDelete();
            $table->unsignedSmallInteger('selected_rank')->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_search_logs');
    }
};

