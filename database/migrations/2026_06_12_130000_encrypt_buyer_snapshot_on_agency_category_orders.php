<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * buyer_snapshot TC kimlik dahil kişisel veri içeriyor — at-rest şifreleme
     * (model cast: encrypted:array). Şifreli base64 değer geçerli JSON olmadığı
     * için kolon json → text'e çevrilir, mevcut düz metin satırlar şifrelenir.
     *
     * DİKKAT: Şifreleme APP_KEY'e bağlıdır — APP_KEY kaybolur/değişirse bu
     * veriler okunamaz hale gelir. Key'i yedekleyin, rotasyonda re-encrypt gerekir.
     */
    public function up(): void
    {
        Schema::table('agency_category_orders', function (Blueprint $table) {
            $table->text('buyer_snapshot')->nullable()->change();
        });

        DB::table('agency_category_orders')
            ->whereNotNull('buyer_snapshot')
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $raw = (string) $order->buyer_snapshot;

                    // json_decode array dönüyorsa düz metin demektir; şifreli
                    // base64 string'de null döner — onlara dokunma (idempotent)
                    if (! is_array(json_decode($raw, true))) {
                        continue;
                    }

                    DB::table('agency_category_orders')
                        ->where('id', $order->id)
                        ->update(['buyer_snapshot' => Crypt::encryptString($raw)]);
                }
            });
    }

    public function down(): void
    {
        DB::table('agency_category_orders')
            ->whereNotNull('buyer_snapshot')
            ->orderBy('id')
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    try {
                        $plain = Crypt::decryptString((string) $order->buyer_snapshot);
                    } catch (DecryptException) {
                        continue; // zaten düz metin
                    }

                    DB::table('agency_category_orders')
                        ->where('id', $order->id)
                        ->update(['buyer_snapshot' => $plain]);
                }
            });

        Schema::table('agency_category_orders', function (Blueprint $table) {
            $table->json('buyer_snapshot')->nullable()->change();
        });
    }
};
