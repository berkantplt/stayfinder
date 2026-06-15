<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StalePendingOrderCleanupTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    protected function setUp(): void
    {
        parent::setUp();

        $this->agency = Agency::create([
            'name' => 'Temizlik Acenta',
            'slug' => 'temizlik-acenta',
            'email' => 'temizlik@example.com',
            'is_active' => true,
            'legacy_category_access' => false,
        ]);
    }

    private function makeOrder(string $status, int $ageHours, string $orderNumber): AgencyCategoryOrder
    {
        $order = AgencyCategoryOrder::create([
            'agency_id' => $this->agency->id,
            'order_number' => $orderNumber,
            'billing_cycle' => 'monthly',
            'subtotal' => 2000,
            'currency' => 'TRY',
            'status' => $status,
            'payment_provider' => AgencyCategoryOrder::PROVIDER_IYZICO,
            'buyer_snapshot' => ['name' => 'Ali', 'identity_number' => '12345678901'],
            'purchased_at' => now()->subHours($ageHours),
        ]);

        DB::table('agency_category_orders')
            ->where('id', $order->id)
            ->update(['created_at' => now()->subHours($ageHours)]);

        return $order;
    }

    public function test_stale_pending_order_is_cancelled_and_buyer_data_is_cleared(): void
    {
        $stale = $this->makeOrder(AgencyCategoryOrder::STATUS_PENDING, 25, 'KYM-STALE-1');

        $this->artisan('app:cancel-stale-pending-orders')->assertExitCode(0);

        $stale->refresh();

        $this->assertSame(AgencyCategoryOrder::STATUS_CANCELLED, $stale->status);
        $this->assertStringContainsString('zaman aşımı', $stale->failure_reason);
        $this->assertNull($stale->buyer_snapshot);
    }

    public function test_fresh_pending_order_is_untouched(): void
    {
        $fresh = $this->makeOrder(AgencyCategoryOrder::STATUS_PENDING, 2, 'KYM-FRESH-1');

        $this->artisan('app:cancel-stale-pending-orders')->assertExitCode(0);

        $fresh->refresh();

        $this->assertSame(AgencyCategoryOrder::STATUS_PENDING, $fresh->status);
        $this->assertSame('12345678901', $fresh->buyer_snapshot['identity_number']);
    }

    public function test_paid_and_failed_orders_are_untouched_regardless_of_age(): void
    {
        $paid = $this->makeOrder(AgencyCategoryOrder::STATUS_PAID, 100, 'KYM-PAID-1');
        $failed = $this->makeOrder(AgencyCategoryOrder::STATUS_FAILED, 100, 'KYM-FAIL-1');

        $this->artisan('app:cancel-stale-pending-orders')->assertExitCode(0);

        $this->assertSame(AgencyCategoryOrder::STATUS_PAID, $paid->fresh()->status);
        $this->assertSame(AgencyCategoryOrder::STATUS_FAILED, $failed->fresh()->status);
        $this->assertNotNull($paid->fresh()->buyer_snapshot);
    }

    public function test_custom_hours_option_changes_threshold(): void
    {
        $order = $this->makeOrder(AgencyCategoryOrder::STATUS_PENDING, 3, 'KYM-OPT-1');

        $this->artisan('app:cancel-stale-pending-orders', ['--hours' => 2])->assertExitCode(0);

        $this->assertSame(AgencyCategoryOrder::STATUS_CANCELLED, $order->fresh()->status);
    }
}
