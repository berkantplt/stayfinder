<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kayıtlı arama: /turlar filtre kombinasyonu üyeye bağlı saklanır; günlük
 * komut (app:check-saved-searches) son kontrolden sonra eklenen ve filtreye
 * uyan turları bulup site içi bildirim atar. Kayıt formundaki "fiyat alarmı"
 * vaadinin arama tarafı.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->json('params');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
