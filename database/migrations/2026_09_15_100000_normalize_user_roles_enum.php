<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A9 — users.role serbest metindi (string, default 'visitor'); tanımsız değer
 * ("user" gibi) sessizce kabul ediliyor, isAdmin/isAgency false döndüğü için
 * tesadüfen müşteri gibi çalışıyordu. Önce veri düzeltilir, sonra MySQL'de
 * kolon enum'a çevrilir. SQLite (test) ve diğer sürücülerde yalnız veri düzeltme
 * yapılır — tip değişikliği orada tablo yeniden yaratma gerektirir, gereksiz.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNotIn('role', User::ROLES)
            ->update(['role' => User::ROLE_VISITOR]);

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY `role` ENUM('admin','agency','visitor') NOT NULL DEFAULT 'visitor'");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY `role` VARCHAR(255) NOT NULL DEFAULT 'visitor'");
        }
    }
};
