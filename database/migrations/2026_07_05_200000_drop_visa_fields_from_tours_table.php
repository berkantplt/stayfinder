<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Vizeli/Vizesiz özelliği kaldırıldı (LLM maliyeti tasarrufu — vize için ekstra
 * çıkarım çağrısı yapılmıyor): aynı gün eklenen 4 vize detay kolonu düşürülür.
 * requires_visa/is_international kolonları ÖZELLİKTEN ÖNCE de vardı (AI arama
 * filtreleri) — onlara dokunulmaz.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn(['visa_general', 'visa_documents', 'visa_fees', 'visa_notes']);
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->text('visa_general')->nullable()->after('frequency');
            $table->text('visa_documents')->nullable()->after('visa_general');
            $table->text('visa_fees')->nullable()->after('visa_documents');
            $table->text('visa_notes')->nullable()->after('visa_fees');
        });
    }
};
