<?php

use App\Models\Tour;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tur grubu anahtarı: aynı turun farklı acenta teklifleri /turlar'da tek
 * kartta toplanır ("3 acentada · 4.499 ₺'den"). Anahtar başlığın
 * normalize slug'ı (Tour::groupKeyFor); Tour::saving her kayıtta yeniler.
 * Mevcut satırlar burada doldurulur (küçük tablo, model olaysız güncelleme).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('group_key', 160)->nullable()->after('slug')->index();
        });

        DB::table('tours')->select(['id', 'title'])->orderBy('id')->chunk(500, function ($rows) {
            foreach ($rows as $row) {
                DB::table('tours')->where('id', $row->id)->update(['group_key' => Tour::groupKeyFor((string) $row->title)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropIndex(['group_key']);
            $table->dropColumn('group_key');
        });
    }
};
