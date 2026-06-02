<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('destination_profiles', function (Blueprint $table) {
            $table->string('country')->nullable()->after('city');
            $table->text('summary')->nullable()->after('liveliness_score');
            $table->json('climate_by_month')->nullable()->after('summary');
            $table->json('vibe_tags')->nullable()->after('climate_by_month');
            $table->json('best_months')->nullable()->after('vibe_tags');
            $table->json('crowded_months')->nullable()->after('best_months');
            $table->boolean('requires_visa_for_tr')->nullable()->after('crowded_months');
            $table->unsignedTinyInteger('enrichment_version')->default(1)->after('source');

            $table->index('enrichment_version');
        });
    }

    public function down(): void
    {
        Schema::table('destination_profiles', function (Blueprint $table) {
            $table->dropIndex(['enrichment_version']);
            $table->dropColumn([
                'country',
                'summary',
                'climate_by_month',
                'vibe_tags',
                'best_months',
                'crowded_months',
                'requires_visa_for_tr',
                'enrichment_version',
            ]);
        });
    }
};
