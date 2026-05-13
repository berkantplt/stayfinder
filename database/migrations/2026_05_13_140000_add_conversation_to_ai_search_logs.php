<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_search_logs', function (Blueprint $table) {
            $table->string('conversation_id', 64)->nullable()->index();
            $table->unsignedBigInteger('parent_log_id')->nullable()->index();
            $table->string('turn_type', 24)->nullable(); // 'search' | 'followup'
            $table->text('ai_comment')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_search_logs', function (Blueprint $table) {
            $table->dropColumn(['conversation_id', 'parent_log_id', 'turn_type', 'ai_comment']);
        });
    }
};
