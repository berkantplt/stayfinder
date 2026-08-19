<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * requires_visa üç durumlu oluyor: true (vizeli) / false (vizesiz) / null (belirtilmemiş).
 *
 * NEDEN: kolon `default(false)` ile açılmıştı ve hiçbir form onu doldurmuyordu,
 * yani "vizesiz" ile "girilmemiş" ayırt edilemiyordu. HomeController'daki vize
 * filtresi tam bu yüzden bu kolonu KULLANMIYOR ("yanlış veri satılmış olurdu"
 * notu orada yazılı). Tur ekleme formuna vizeli/vizesiz kutucukları geldiği için
 * artık üçüncü duruma ihtiyaç var.
 *
 * VERİ: mevcut `false` değerleri null'a çevrilir — hiçbiri kullanıcı beyanı
 * değil, kolon varsayılanı. `true` olanlar korunur (demo seeder bilerek yazmış,
 * canlıda zaten yok). Geri alma yönünde null'lar false'a döner.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->boolean('requires_visa')->nullable()->default(null)->change();
        });

        DB::table('tours')->where('requires_visa', false)->update(['requires_visa' => null]);
    }

    public function down(): void
    {
        DB::table('tours')->whereNull('requires_visa')->update(['requires_visa' => false]);

        Schema::table('tours', function (Blueprint $table) {
            $table->boolean('requires_visa')->default(false)->nullable(false)->change();
        });
    }
};
