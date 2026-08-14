<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Genel arayüz ayarları için anahtar/değer tablosu.
     *
     * İlk sakini hero'nun beyaz perdesi: bir önceki adımda banner başına
     * ayarlanıyordu, kullanıcı kararıyla (2026-08-13) TEK ayara çevrildi —
     * tüm görsellere aynı perde uygulanıyor. banners.white_veil sütunu bu
     * yüzden kaldırılıyor; mevcut değer kaybolmasın diye ilk banner'ınki
     * genel ayara taşınıyor.
     */
    public function up(): void
    {
        Schema::create('site_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        $mevcut = 100;
        if (Schema::hasColumn('banners', 'white_veil')) {
            $mevcut = (int) (DB::table('banners')->orderBy('sort_order')->value('white_veil') ?? 100);
        }

        DB::table('site_settings')->insert([
            'key' => 'hero_white_veil',
            'value' => (string) $mevcut,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if (Schema::hasColumn('banners', 'white_veil')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->dropColumn('white_veil');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('banners', 'white_veil')) {
            Schema::table('banners', function (Blueprint $table) {
                $table->integer('white_veil')->default(100)->after('darkness');
            });
        }

        Schema::dropIfExists('site_settings');
    }
};
