<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * B5 — agency_category_subscriptions.category_id: cascadeOnDelete → restrictOnDelete.
 * Kategori silinince acentanın ödediği abonelik satırı (denetim izi) DB tarafından
 * sessizce siliniyordu; controller kontrolü yarış penceresinde yetmez. Artık DB
 * reddeder. Yalnız MySQL: SQLite (test) FK'yi tablo yeniden yaratmadan değiştiremez,
 * orada uygulama kontrolü yeterli.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('agency_category_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::table('agency_category_subscriptions', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
        });
    }
};
