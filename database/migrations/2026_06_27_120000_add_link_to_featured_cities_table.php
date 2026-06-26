<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Story "Turları Gör" butonunun yönlendireceği site içi bağlantı (ör.
     * "/turlar?q=Paris" veya "/turlar?destination=Paris"). Boşsa /turlar?q=ad
     * davranışına düşülür.
     */
    public function up(): void
    {
        Schema::table('featured_cities', function (Blueprint $table) {
            $table->string('link', 500)->nullable()->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('featured_cities', function (Blueprint $table) {
            $table->dropColumn('link');
        });
    }
};
