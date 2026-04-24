<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->decimal('monthly_price', 10, 2)->default(2000)->after('description');
        });

        Schema::table('agencies', function (Blueprint $table) {
            $table->boolean('legacy_category_access')->default(false)->after('is_active');
        });

        DB::table('agencies')->update(['legacy_category_access' => true]);

        Schema::create('agency_category_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('order_number')->unique();
            $table->string('billing_cycle')->default('monthly');
            $table->decimal('subtotal', 10, 2);
            $table->string('currency', 3)->default('TRY');
            $table->string('status')->default('paid');
            $table->timestamp('purchased_at')->nullable();
            $table->timestamps();
        });

        Schema::create('agency_category_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->foreignId('last_order_id')->nullable()->constrained('agency_category_orders')->nullOnDelete();
            $table->decimal('monthly_price', 10, 2);
            $table->string('status')->default('active');
            $table->date('started_at');
            $table->date('expires_at');
            $table->timestamps();

            $table->unique(['agency_id', 'category_id']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('agency_category_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('agency_category_orders')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->string('category_name');
            $table->decimal('unit_price', 10, 2);
            $table->string('billing_cycle')->default('monthly');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agency_category_order_items');
        Schema::dropIfExists('agency_category_subscriptions');
        Schema::dropIfExists('agency_category_orders');

        Schema::table('agencies', function (Blueprint $table) {
            $table->dropColumn('legacy_category_access');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('monthly_price');
        });
    }
};
