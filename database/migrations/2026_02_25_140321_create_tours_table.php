<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->string('destination');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->string('currency', 5)->default('TRY');
            $table->unsignedSmallInteger('duration_days')->default(1);
            $table->date('departure_date')->nullable();
            $table->date('return_date')->nullable();
            $table->text('included')->nullable();
            $table->text('excluded')->nullable();
            $table->string('image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('destination');
            $table->index('departure_date');
            $table->index(['is_active', 'departure_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
