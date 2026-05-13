<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destination_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('city');
            $table->string('normalized_city')->unique();
            $table->decimal('crowd_score', 3, 2)->default(0.50);
            $table->decimal('liveliness_score', 3, 2)->default(0.50);
            $table->string('source', 16)->default('default');
            $table->text('reasoning')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->timestamps();

            $table->index(['source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destination_profiles');
    }
};
