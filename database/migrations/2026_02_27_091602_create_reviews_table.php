<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->tinyInteger('rating')->default(5); // 1-5
            $table->text('comment');
            $table->timestamps();

            $table->unique(['user_id', 'tour_id']); // one review per user per tour
            $table->index('tour_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
