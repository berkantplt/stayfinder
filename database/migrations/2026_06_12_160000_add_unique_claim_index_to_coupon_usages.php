<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bir kullanıcı bir kuponu yalnızca bir kez alabilir (claim). Uygulama
     * katmanındaki kontrole ek DB-seviyesi son savunma. user_id NULL satırlar
     * (anonim/eski kayıt) unique kısıtına takılmaz.
     */
    public function up(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->unique(['coupon_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('coupon_usages', function (Blueprint $table) {
            $table->dropUnique(['coupon_id', 'user_id']);
        });
    }
};
