<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D9 — Karşılaştırma listesi yalnız tarayıcı localStorage'ındaydı; giriş yapmış
 * kullanıcıda sunucuya da yazılır (cihaz değişince kaybolmaz, hesapta görünür).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_compare_tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'tour_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_compare_tours');
    }
};
