<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Favoriye eklendiği andaki fiyat: "eklediğinden beri %12 düştü" için. Kur
 * oynaması düşüş sanılmasın diye para birimiyle birlikte saklanır ve kıyas
 * yalnız aynı birimde yapılır. Eski favorilerde null kalır (fark basılmaz).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->decimal('price_at_save', 10, 2)->nullable()->after('tour_id');
            $table->string('currency_at_save', 3)->nullable()->after('price_at_save');
        });
    }

    public function down(): void
    {
        Schema::table('favorites', function (Blueprint $table) {
            $table->dropColumn(['price_at_save', 'currency_at_save']);
        });
    }
};
