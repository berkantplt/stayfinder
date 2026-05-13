<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_category_orders', function (Blueprint $table) {
            $table->string('payment_provider')->default('manual')->after('status');
            $table->string('provider_token')->nullable()->after('payment_provider');
            $table->string('provider_payment_id')->nullable()->after('provider_token');
            $table->string('buyer_type')->nullable()->after('provider_payment_id');
            $table->json('buyer_snapshot')->nullable()->after('buyer_type');
            $table->timestamp('paid_at')->nullable()->after('purchased_at');
            $table->text('failure_reason')->nullable()->after('paid_at');

            $table->unique('provider_token');
            $table->index(['payment_provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('agency_category_orders', function (Blueprint $table) {
            $table->dropUnique(['provider_token']);
            $table->dropIndex(['payment_provider', 'status']);
            $table->dropColumn([
                'payment_provider',
                'provider_token',
                'provider_payment_id',
                'buyer_type',
                'buyer_snapshot',
                'paid_at',
                'failure_reason',
            ]);
        });
    }
};
