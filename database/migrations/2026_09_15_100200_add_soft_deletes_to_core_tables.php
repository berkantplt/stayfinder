<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A10 — Hiçbir modelde soft delete yoktu: acenta "Sil" deyince tur, tarihleri,
 * tıklama geçmişi, yorumları ve favorileriyle cascade ile kalıcı gidiyordu;
 * geri alma yoktu. deleted_at ile silme "arşiv"e döner: kayıt ve bağlı satırlar
 * yerinde kalır, acenta paneli geri alabilir, 30 gün sonra model:prune kalıcı siler.
 */
return new class extends Migration
{
    private const TABLES = ['tours', 'agencies', 'coupons', 'reviews'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->softDeletes();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropSoftDeletes();
            });
        }
    }
};
