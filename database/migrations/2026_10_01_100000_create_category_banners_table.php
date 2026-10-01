<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kategori landing sayfalarının (/balkan-turlari, /kultur-turlari) hero banner'ı.
 *
 * Ana sayfadaki `banners` tablosundan BİLEREK AYRI: o tablo ana sayfa
 * karuseli (çoklu görsel, sıra, blur, beyaz perde); bu tablo kategori başına
 * TEK hero (görsel + üst başlık + başlık + alt başlık + konum notu + koyuluk).
 *
 * category_id NULL = genel varsayılan: kendi banner'ı (ya da üst kategorisinin
 * banner'ı) olmayan her kategori sayfasında bu kullanılır. Çözümleme sırası
 * App\Support\CategoryHero'da.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable()->unique()
                ->constrained('categories')->cascadeOnDelete();
            $table->string('image');                       // public disk yolu (category-banners/...)
            $table->string('eyebrow', 80)->nullable();     // "BALKANLARI KEŞFET"
            $table->string('title', 120)->nullable();      // "Bir yolculuk, birçok hikâye."
            $table->string('subtitle', 220)->nullable();   // "Balkan turlarını keşfet, sana uygun rotayı bul."
            $table->string('caption', 120)->nullable();    // "Mostar, Bosna-Hersek" (sağ alt konum notu)
            $table->unsignedTinyInteger('darkness')->default(38); // 0-100 karartma
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_banners');
    }
};
