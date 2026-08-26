<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Otomatik aylık yenileme + abonelik iptali:
     * - Abonelik iptal edilirse dönem sonuna kadar kullanım sürer, dönem
     *   sonunda otomatik çekim YAPILMAZ (auto_renew=false + cancelled_at).
     * - Ekstra tur hakları artık aylık: yenileme tutarı = güncel kategori
     *   ücreti + hak sayısı × güncel ekstra tur fiyatı. next_extra_tour_slots
     *   doluysa yeni dönemde hak sayısı ona düşürülür.
     * - agency_stored_cards: iyzico kart saklama token'ları (PAN değil).
     *   Fiili çekim IYZICO_AUTO_RENEW bayrağıyla açılır.
     */
    public function up(): void
    {
        Schema::table('agency_category_subscriptions', function (Blueprint $table) {
            $table->boolean('auto_renew')->default(true)->after('extra_tour_slots');
            $table->timestamp('cancelled_at')->nullable()->after('auto_renew');
            $table->unsignedInteger('next_extra_tour_slots')->nullable()->after('cancelled_at');
            $table->date('renewal_attempted_at')->nullable()->after('next_extra_tour_slots');
        });

        Schema::table('agency_category_orders', function (Blueprint $table) {
            $table->boolean('auto_renewal')->default(false)->after('payment_provider');
        });

        Schema::create('agency_stored_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agency_id')->unique()->constrained()->cascadeOnDelete();
            // iyzico token'ları (kart numarası DEĞİL) — yine de at-rest şifreli (model cast)
            $table->text('card_user_key');
            $table->text('card_token');
            $table->string('last_four', 4)->nullable();
            $table->string('card_association', 30)->nullable();
            $table->string('card_family', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('agency_category_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['auto_renew', 'cancelled_at', 'next_extra_tour_slots', 'renewal_attempted_at']);
        });

        Schema::table('agency_category_orders', function (Blueprint $table) {
            $table->dropColumn('auto_renewal');
        });

        Schema::dropIfExists('agency_stored_cards');
    }
};
