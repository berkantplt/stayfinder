<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sosyal giriş bağlantıları (Google / Apple).
 *
 * Sağlayıcı kimliği users tablosuna kolon olarak gömülmedi: bir kullanıcı hem
 * Google hem Apple hesabını bağlayabilsin ve ileride yeni sağlayıcı eklemek
 * users'ı değiştirmesin diye ayrı tablo.
 *
 * provider_user_id sağlayıcının kalıcı kimliği (Google "sub", Apple "sub").
 * E-POSTA DEĞİL: kullanıcı Gmail adresini değiştirse de aynı kimlikle gelir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20);
            // 191: provider+provider_user_id birleşik unique indeksi utf8mb4'te
            // InnoDB'nin bayt sınırını aşmasın.
            $table->string('provider_user_id', 191);
            $table->string('email')->nullable();
            $table->string('avatar', 1024)->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();

            // Aynı sağlayıcı hesabı iki kullanıcıya bağlanamaz — hesap devralmaya
            // karşı son savunma hattı (uygulama katmanı atlansa bile DB reddeder).
            $table->unique(['provider', 'provider_user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
