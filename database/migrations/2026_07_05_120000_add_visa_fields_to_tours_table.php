<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vizeli turlar için detay alanları. requires_visa (boolean) kolonu zaten var
 * (add_ai_filters migration'ı) — form "Vizeli" seçildiğinde bu alanlar dolar:
 * genel bilgiler, gerekli evraklar, ücretler ve önemli notlar. Vizesiz turlarda
 * hepsi null tutulur (kayıtta sunucu tarafında temizlenir).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->text('visa_general')->nullable()->after('frequency');      // pasaport gereksinimleri, başvuru süreci, vize türü
            $table->text('visa_documents')->nullable()->after('visa_general'); // gerekli evraklar (standart + meslek grubuna göre)
            $table->text('visa_fees')->nullable()->after('visa_documents');    // vize ücretleri (merkez / yaş grubu / tutar)
            $table->text('visa_notes')->nullable()->after('visa_fees');        // önemli notlar + konsolosluk bilgilendirmesi
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['visa_general', 'visa_documents', 'visa_fees', 'visa_notes']);
        });
    }
};
