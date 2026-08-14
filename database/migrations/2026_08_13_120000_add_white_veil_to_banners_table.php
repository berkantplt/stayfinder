<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Hero'daki beyaz perde (soldan gelen beyaz degrade) banner başına ayarlanır.
     *
     * Neden var: yeni hero AÇIK zeminli — başlık koyu lacivert. Okunurluğu bu
     * perde sağlıyor. Her fotoğrafın açıklığı farklı olduğu için tek sabit değer
     * yetmiyordu: açık gökyüzünde perde fazla geliyor, koyu fotoğrafta az.
     *
     * 100 = tasarımdaki tam perde (mevcut görünüm), 0 = perde yok.
     * Varsayılan 100 ki eski banner'lar bugünkü görünümünü korusun.
     */
    public function up(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->integer('white_veil')->default(100)->after('darkness');
        });
    }

    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            $table->dropColumn('white_veil');
        });
    }
};
