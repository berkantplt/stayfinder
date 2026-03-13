<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('country')->default('Türkiye')->after('description');
            $table->string('highlights')->nullable()->after('country'); // comma-separated
        });
    }

    public function down(): void
    {
        Schema::table('destinations', function (Blueprint $table) {
            $table->dropColumn(['description', 'country', 'highlights']);
        });
    }
};
