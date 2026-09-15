<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A6 — Duyurular hedefsizdi: "Yeni tur eklendi" tüketici duyurusu admin ve
 * acenta zil rozetinde de sayılıyordu (acenta rakiplerinin turlarını bildirim
 * olarak alıyordu). audience = hangi role gösterileceği; mevcut kayıtlar
 * müşteri duyurusudur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('audience', 16)->default('visitor')->after('type')->index();
        });
    }

    public function down(): void
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropIndex(['audience']);
            $table->dropColumn('audience');
        });
    }
};
