<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori görseli: ana sayfadaki dört büyük kategori kartı için. Yalnız emoji
 * ikon vardı; görsel yönetim panelinden (Üst Kategoriler) yüklenir, public
 * diskte categories/ altında durur. Boşsa kart turkuaz zemin + ikonla çizilir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('image')->nullable()->after('icon');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('image');
        });
    }
};
