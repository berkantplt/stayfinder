<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * D5 — E-posta değişikliği doğrulanmadan uygulanmıyordu: yeni adres burada
 * bekler, yeni adrese giden imzalı bağlantı onaylanınca users.email'e taşınır.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->timestamp('pending_email_requested_at')->nullable()->after('pending_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'pending_email_requested_at']);
        });
    }
};
