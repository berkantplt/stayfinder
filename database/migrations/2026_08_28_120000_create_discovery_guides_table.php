<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_guides', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Misafir sahipliği: AiSearchConversation ile aynı kalıp — rehber
            // oturum kimliğine bağlanır, giriş yapılmışsa user_id'ye.
            $table->string('session_id', 64)->nullable()->index();
            $table->string('destination_input', 100);
            $table->foreignId('destination_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('duration_days');
            $table->string('traveler_type', 20)->nullable();
            $table->json('interests')->nullable();
            $table->string('pace', 20)->default('normal');
            $table->string('budget', 20)->default('standard');
            $table->string('status', 20)->default('pending')->index();
            $table->json('guide_payload')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_guides');
    }
};
