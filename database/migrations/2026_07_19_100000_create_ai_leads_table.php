<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * AI sohbetinden çıkan sıcak lead'ler: geri arama / opsiyon / fiyat alarmı.
     * Eski akışta devir yalnız WhatsApp linki göstermekti — kullanıcı tıklamazsa
     * lead kayboluyordu. Artık ad+telefon alınıp acentaya kalıcı kayıt düşülür.
     */
    public function up(): void
    {
        Schema::create('ai_leads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->nullable()
                ->constrained('ai_search_conversations')->nullOnDelete();
            $table->foreignId('tour_id')->nullable()->constrained('tours')->nullOnDelete();
            $table->foreignId('agency_id')->nullable()->constrained('agencies')->nullOnDelete();
            $table->string('name', 120);
            $table->string('phone', 32);
            $table->string('intent', 32); // geri_arama | opsiyon | fiyat_alarmi
            $table->text('note')->nullable(); // sohbet özeti: ay, bütçe, profil, şikayet vb.
            $table->string('status', 24)->default('new'); // new | contacted | closed
            $table->timestamps();

            $table->index(['agency_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_leads');
    }
};
