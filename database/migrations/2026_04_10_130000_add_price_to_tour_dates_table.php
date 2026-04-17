<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tour_dates', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0)->after('return_date');
            $table->index('price');
        });

        DB::table('tour_dates')
            ->orderBy('id')
            ->chunkById(200, function ($dates) {
                $tourIds = collect($dates)->pluck('tour_id')->unique()->values()->all();

                $tourPrices = DB::table('tours')
                    ->whereIn('id', $tourIds)
                    ->pluck('price', 'id');

                foreach ($dates as $date) {
                    DB::table('tour_dates')
                        ->where('id', $date->id)
                        ->update([
                            'price' => $tourPrices[$date->tour_id] ?? 0,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('tour_dates', function (Blueprint $table) {
            $table->dropIndex(['price']);
            $table->dropColumn('price');
        });
    }
};
