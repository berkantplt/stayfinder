<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tatil Karakteri Testi çıktısı: {vector, tempo, tags, persona,
            // answers, quiz_version, confirmed_at}. Anonim kullanıcıda aynı veri
            // konuşma meta'sında (_recreation_profile) yaşar, login'de buraya taşınır.
            $table->json('recreation_profile')->nullable()->after('ai_preference');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('recreation_profile');
        });
    }
};
