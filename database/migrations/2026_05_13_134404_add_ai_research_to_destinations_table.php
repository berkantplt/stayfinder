<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->json('ai_research')->nullable();
            $table->timestamp('ai_research_updated_at')->nullable();
            $table->string('ai_research_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn(['ai_research', 'ai_research_updated_at', 'ai_research_status']);
        });
    }
};
