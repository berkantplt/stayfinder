<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * tour_url VARCHAR(255) idi; uzun (takip parametreli) tur linkleri taşıyordu.
     * TEXT'e genişlet — herhangi uzunlukta URL sığsın.
     */
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->text('tour_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('tour_url')->nullable()->change();
        });
    }
};
