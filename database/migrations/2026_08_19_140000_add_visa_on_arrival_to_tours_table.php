<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Kapıda vize" ayrı bir kolon — requires_visa'ya sıkıştırılmadı.
 *
 * NEDEN AYRI: kapıda vize yolcu için vizeli turdan tamamen farklı bir iş
 * (konsolosluk randevusu, evrak, haftalar süren başvuru YOK; sınırda ödenip
 * alınıyor). Tek boolean'a katlansaydı "vize gerekiyor" deyip kullanıcıyı
 * gereksiz yere caydırırdık. Bileşim: requires_visa=true + visa_on_arrival=true.
 *
 * Nullable, çünkü requires_visa gibi üç durumlu: bilinmiyorsa null.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->boolean('visa_on_arrival')->nullable()->default(null)->after('requires_visa');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('visa_on_arrival');
        });
    }
};
