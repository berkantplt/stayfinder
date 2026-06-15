<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Yenileme hatırlatması idempotency bayrağı: aynı abonelik dönemi için
     * hatırlatma bir kez gönderilir; yenilemede (expires_at uzayınca) sıfırlanır.
     */
    public function up(): void
    {
        Schema::table('agency_category_subscriptions', function (Blueprint $table) {
            $table->timestamp('renewal_reminder_sent_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('agency_category_subscriptions', function (Blueprint $table) {
            $table->dropColumn('renewal_reminder_sent_at');
        });
    }
};
