<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D4 — KVKK "unutulma hakkı": hesap silme talebi 30 gün bekler (bu sürede giriş
 * talebi iptal eder), sonra users:purge-deleted komutu kaydı anonimleştirir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deletion_requested_at')->nullable()->after('remember_token')->index();
            $table->timestamp('anonymized_at')->nullable()->after('deletion_requested_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['deletion_requested_at']);
            $table->dropColumn(['deletion_requested_at', 'anonymized_at']);
        });
    }
};
