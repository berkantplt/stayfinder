<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('source_type'); // tour, post, destination, agency, faq, policy
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title')->nullable();
            $table->text('content');
            $table->string('content_hash')->nullable(); // İçerik değişti mi kontrolü için
            $table->json('embedding')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index('content_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
