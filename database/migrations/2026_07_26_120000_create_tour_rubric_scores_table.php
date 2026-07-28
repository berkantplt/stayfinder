<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rubrik puanları tur kaydına GÖMÜLMEZ (brif §7): ayrı tablo →
        // yeniden puanlama + rubric_version takibi temiz kalır.
        Schema::create('tour_rubric_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('rubric_version', 10);
            $table->string('input_hash', 64); // girdi metni değişince otomatik yeniden puanlama
            $table->json('scores');           // {boyut: {value:1-5|null, confidence, evidence}}
            $table->string('review_status', 20)->default('auto'); // auto | needs_review | approved
            $table->timestamp('scored_at')->nullable();
            $table->timestamps();

            $table->unique(['tour_id', 'rubric_version']);
        });

        // Brif kapsam kısıtı: test sonucu KALICI profile yazılmaz (oturum bazlı).
        // Dünkü kolon kaldırılıyor — KVKK yükünü de düşürür.
        if (Schema::hasColumn('users', 'recreation_profile')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('recreation_profile'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_rubric_scores');
        Schema::table('users', fn (Blueprint $table) => $table->json('recreation_profile')->nullable());
    }
};
