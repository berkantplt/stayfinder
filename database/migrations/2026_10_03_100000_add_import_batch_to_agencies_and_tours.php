<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Toplu içe aktarım izi: komutla (app:bulk-import-*) yaratılan acenta ve turlar
 * hangi partiden geldiğini taşır. Gerçek acenta sonradan kendi hesabını
 * yönetmeye başlasa bile bizden gelen turlar ayrıştırılabilir; app:bulk-import-cleanup
 * tek partiyi arşivler. Elle/panelden girilen kayıtlarda NULL kalır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->string('import_batch', 60)->nullable()->after('legacy_category_access')->index();
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->string('import_batch', 60)->nullable()->after('tour_url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('agencies', function (Blueprint $table) {
            $table->dropIndex(['import_batch']);
            $table->dropColumn('import_batch');
        });

        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['import_batch']);
            $table->dropColumn('import_batch');
        });
    }
};
