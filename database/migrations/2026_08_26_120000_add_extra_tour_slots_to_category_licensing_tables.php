<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kategori aboneliği artık 2 tur ekleme hakkı içerir; fazlası kategori
     * bazlı "ekstra tur hakkı" olarak satılır (tek seferlik ödeme). Ekstra hak,
     * abonelik kesintisiz aktif kaldığı sürece geçerlidir — süre dolunca veya
     * iptalde sıfırlanır (bkz. ExpireCategorySubscriptions / revokeCategory).
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('extra_tour_price', 10, 2)->default(1000)->after('monthly_price');
        });

        Schema::table('agency_category_subscriptions', function (Blueprint $table) {
            $table->unsignedInteger('extra_tour_slots')->default(0)->after('monthly_price');
        });

        Schema::table('agency_category_order_items', function (Blueprint $table) {
            $table->string('item_type', 20)->default('license')->after('category_name');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('extra_tour_price');
        });

        Schema::table('agency_category_subscriptions', function (Blueprint $table) {
            $table->dropColumn('extra_tour_slots');
        });

        Schema::table('agency_category_order_items', function (Blueprint $table) {
            $table->dropColumn('item_type');
        });
    }
};
