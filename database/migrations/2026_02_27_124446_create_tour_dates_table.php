<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->date('departure_date');
            $table->date('return_date');
            $table->string('label')->nullable(); // e.g. "Erken Rezervasyon", "Bayram Özel"
            $table->timestamps();

            $table->index('tour_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_dates');
    }
};
