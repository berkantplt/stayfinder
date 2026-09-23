<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Bu kullanıcı kendi şifresini belirledi mi?" bilgisi.
 *
 * Sosyal giriş ile açılan hesaba password kolonunda RASTGELE bir hash yazılır;
 * null BIRAKILMAZ. Sebebi somut: D6'da eklenen AuthenticateSession middleware'i
 * her istekte oturumdaki şifre özetini getAuthPassword() ile karşılaştırır ve
 * null dönerse hash_equals() tip hatası verir; Auth::logoutOtherDevices() de
 * aynı şekilde bozulur. Rastgele hash ile mevcut oturum güvenliği hiç değişmeden
 * çalışır, kullanıcı o şifreyi bilmez.
 *
 * Bu kolon da o yüzden gerekli: password dolu olduğu için "şifresi var mı"
 * sorusunun cevabı artık password'e bakarak verilemez. Profilde "Şifre belirle"
 * mi "Şifre değiştir" mi gösterileceğini ve sosyal bağlantının kaldırılıp
 * kaldırılamayacağını bu kolon belirler.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('password_set_at')->nullable()->after('password');
        });

        // Mevcut kullanıcıların hepsi şifresini kendisi belirledi (sosyal giriş
        // bu migration'dan önce yoktu) — kayıt tarihleriyle işaretlenir.
        DB::statement('UPDATE users SET password_set_at = created_at');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password_set_at');
        });
    }
};
