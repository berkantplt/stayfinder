<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Duyurular: "yeni tur eklendi" gibi herkese açık olaylar için kullanıcı
     * başına notifications satırı yazmak yerine olay başına TEK satır
     * (fan-out on read). Okunma durumu users.announcements_seen_at ile izlenir.
     */
    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('type')->default('new_tour');
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('icon', 16)->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('announcements_seen_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('announcements_seen_at');
        });

        Schema::dropIfExists('announcements');
    }
};
