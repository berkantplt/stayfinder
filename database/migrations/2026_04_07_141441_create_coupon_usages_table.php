<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('coupon_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null'); // if nullable
            $table->unsignedBigInteger('tour_id')->nullable(); 
            // tour_id constraint won't automatically cascade if the tour table isn't fully robust, or maybe manual
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            
            $table->foreign('tour_id')->references('id')->on('tours')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_usages');
    }
};
