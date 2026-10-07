<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keşif Rehberi şehir tabanı: parametresiz, ETİKETLİ şehir içerik havuzu
 * (öne çıkanlar, tarihi yerler, müzeler, yemekler, ipuçları). Taban varken
 * rehber üretimi yalnız günlük planı AI'dan ister (çıktı ~%40 → 6-8 sn).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discovery_city_bases', function (Blueprint $table) {
            $table->id();
            // DestinationFilter::normalize çıktısı — rehber girdisiyle aynı normalize
            $table->string('normalized_city', 120)->unique();
            $table->string('display_name', 120);
            $table->string('country', 100)->nullable();
            $table->json('base_payload');
            $table->string('source', 16)->default('generated');
            $table->string('model', 60)->nullable();
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discovery_city_bases');
    }
};
