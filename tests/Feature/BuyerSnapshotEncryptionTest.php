<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BuyerSnapshotEncryptionTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrderWithBuyer(array $buyer): AgencyCategoryOrder
    {
        $agency = Agency::create([
            'name' => 'Şifreleme Acenta',
            'slug' => 'sifreleme-acenta',
            'email' => 'sifre@example.com',
            'is_active' => true,
            'legacy_category_access' => false,
        ]);

        return AgencyCategoryOrder::create([
            'agency_id' => $agency->id,
            'order_number' => 'KYM-TEST-ENC001',
            'billing_cycle' => 'monthly',
            'subtotal' => 2000,
            'currency' => 'TRY',
            'status' => AgencyCategoryOrder::STATUS_PENDING,
            'payment_provider' => AgencyCategoryOrder::PROVIDER_IYZICO,
            'buyer_type' => AgencyCategoryOrder::BUYER_INDIVIDUAL,
            'buyer_snapshot' => $buyer,
            'purchased_at' => now(),
        ]);
    }

    public function test_buyer_snapshot_is_stored_encrypted_in_database(): void
    {
        $order = $this->makeOrderWithBuyer([
            'type' => 'individual',
            'name' => 'Ali',
            'surname' => 'Yılmaz',
            'identity_number' => '12345678901',
            'gsm' => '+905001112233',
            'address' => 'Gizli Mahallesi No:1',
        ]);

        $raw = (string) DB::table('agency_category_orders')
            ->where('id', $order->id)
            ->value('buyer_snapshot');

        // Ham kolonda TC kimlik, ad ve adres okunamaz olmalı
        $this->assertStringNotContainsString('12345678901', $raw);
        $this->assertStringNotContainsString('Yılmaz', $raw);
        $this->assertStringNotContainsString('Gizli Mahallesi', $raw);

        // Ham değer düz JSON da değil (şifreli payload)
        $this->assertNull(json_decode($raw, true));
    }

    public function test_buyer_snapshot_round_trips_through_model_cast(): void
    {
        $order = $this->makeOrderWithBuyer([
            'type' => 'individual',
            'name' => 'Ayşe',
            'surname' => 'Demir',
            'identity_number' => '98765432109',
        ]);

        $fresh = $order->fresh();

        $this->assertSame('98765432109', $fresh->buyer_snapshot['identity_number']);
        $this->assertSame('Ayşe', $fresh->buyer_snapshot['name']);
    }

    public function test_migration_backfill_encrypts_legacy_plaintext_rows(): void
    {
        $order = $this->makeOrderWithBuyer(['name' => 'Düz', 'identity_number' => '11111111111']);

        // Migration öncesi durumu taklit et: düz metin JSON yaz
        $plain = json_encode(['name' => 'Düz', 'identity_number' => '11111111111']);
        DB::table('agency_category_orders')->where('id', $order->id)->update(['buyer_snapshot' => $plain]);

        // Migration'daki backfill mantığının birebir aynısı
        $raw = (string) DB::table('agency_category_orders')->where('id', $order->id)->value('buyer_snapshot');
        if (is_array(json_decode($raw, true))) {
            DB::table('agency_category_orders')
                ->where('id', $order->id)
                ->update(['buyer_snapshot' => Crypt::encryptString($raw)]);
        }

        $rawAfter = (string) DB::table('agency_category_orders')->where('id', $order->id)->value('buyer_snapshot');
        $this->assertStringNotContainsString('11111111111', $rawAfter);

        // Model cast şifrelenmiş satırı sorunsuz okur
        $this->assertSame('11111111111', $order->fresh()->buyer_snapshot['identity_number']);
    }
}
