<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Models\AgencyStoredCard;
use App\Models\Category;
use App\Models\User;
use App\Notifications\CategorySubscriptionExpiringNotification;
use App\Notifications\CategorySubscriptionRenewalFailedNotification;
use App\Notifications\CategorySubscriptionRenewedNotification;
use App\Services\Payment\IyzicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\Payment;
use Iyzipay\Model\Status;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Otomatik aylık yenileme + abonelik iptali:
 * - İptal: dönem sonuna kadar kullanım sürer, dönem sonunda çekim YAPILMAZ.
 * - Yenileme: kayıtlı karttan güncel tarife (kategori + hak × ekstra fiyat).
 * - Ekstra haklar aylık; azaltma planı yeni dönemde uygulanır.
 */
class CategorySubscriptionRenewalTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_keeps_access_until_period_end_and_resume_reopens(): void
    {
        Queue::fake();
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [$user, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category);

        $this->actingAs($user)
            ->post(route('agency.category-licenses.subscription.cancel', $subscription))
            ->assertRedirect()
            ->assertSessionHas('success');

        $subscription->refresh();
        $this->assertFalse($subscription->auto_renew);
        $this->assertNotNull($subscription->cancelled_at);
        // Statü/bitiş DEĞİŞMEZ: dönem sonuna kadar kullanım sürer
        $this->assertSame(AgencyCategorySubscription::STATUS_ACTIVE, $subscription->status);
        $this->assertTrue($agency->fresh()->hasCategoryAccess($category->id));

        // İptalden sonra bile tur eklenebilir (dönem içinde hak sürer)
        $this->actingAs($user)
            ->post(route('agency.tours.store'), $this->validTourPayload($category->id))
            ->assertRedirect(route('agency.tours.index'));

        $this->actingAs($user)
            ->post(route('agency.category-licenses.subscription.resume', $subscription))
            ->assertRedirect()
            ->assertSessionHas('success');

        $subscription->refresh();
        $this->assertTrue($subscription->auto_renew);
        $this->assertNull($subscription->cancelled_at);
    }

    public function test_cancel_requires_ownership(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category);

        $otherAgency = Agency::create([
            'name' => 'Başka Acenta', 'slug' => 'baska-acenta', 'email' => 'baska@example.com',
            'is_active' => true, 'legacy_category_access' => false,
        ]);
        $otherUser = User::factory()->create(['role' => 'agency', 'agency_id' => $otherAgency->id]);

        $this->actingAs($otherUser)
            ->post(route('agency.category-licenses.subscription.cancel', $subscription))
            ->assertForbidden();

        $this->assertTrue($subscription->fresh()->auto_renew);
    }

    public function test_slot_reduction_plan_validates_and_stores(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [$user, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, extraSlots: 3);

        // Artırma satın almayla yapılır — plan yalnız azaltabilir
        $this->actingAs($user)
            ->post(route('agency.category-licenses.subscription.slot-plan', $subscription), ['keep' => 5])
            ->assertSessionHasErrors('keep');

        $this->actingAs($user)
            ->post(route('agency.category-licenses.subscription.slot-plan', $subscription), ['keep' => 1])
            ->assertSessionHas('success');

        $subscription->refresh();
        $this->assertSame(1, $subscription->next_extra_tour_slots);
        $this->assertSame(3, $subscription->extra_tour_slots); // mevcut dönem korunur

        // keep = mevcut → plan iptali
        $this->actingAs($user)
            ->post(route('agency.category-licenses.subscription.slot-plan', $subscription), ['keep' => 3])
            ->assertSessionHas('success');

        $this->assertNull($subscription->fresh()->next_extra_tour_slots);
    }

    public function test_renewal_command_charges_stored_card_with_current_tariff_and_extends(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [$user, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, extraSlots: 2, expiresAt: today());
        $this->makeStoredCard($agency);
        $this->makePaidOrderWithBuyer($agency, $category);

        // Admin zam yaptı: yenileme GÜNCEL tarifeden çekilmeli
        $category->update(['monthly_price' => 2500, 'extra_tour_price' => 800]);

        $this->mockIyzicoForStoredCardCharge('renewal-payment-1');

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        $order = AgencyCategoryOrder::where('auto_renewal', true)->firstOrFail();
        $this->assertSame(AgencyCategoryOrder::STATUS_PAID, $order->status);
        $this->assertSame('4100.00', (string) $order->subtotal); // 2500 + 2×800
        $this->assertSame('renewal-payment-1', $order->provider_payment_id);
        $this->assertStringStartsWith('KYM-YEN-', $order->order_number);
        $this->assertSame(1, $order->items()->where('item_type', AgencyCategoryOrderItem::TYPE_LICENSE)->count());
        $this->assertSame(2, $order->items()->where('item_type', AgencyCategoryOrderItem::TYPE_EXTRA_SLOT)->count());

        $subscription->refresh();
        $this->assertTrue($subscription->expires_at->equalTo(today()->addMonth())); // uzatma
        $this->assertSame(2, $subscription->extra_tour_slots); // SET semantiği: katlanmaz
        $this->assertSame('2500.00', (string) $subscription->monthly_price);

        Notification::assertSentTo($user, CategorySubscriptionRenewedNotification::class);
    }

    public function test_renewal_applies_slot_reduction_plan(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, extraSlots: 3, expiresAt: today());
        $subscription->update(['next_extra_tour_slots' => 1]);
        $this->makeStoredCard($agency);
        $this->makePaidOrderWithBuyer($agency, $category);

        $this->mockIyzicoForStoredCardCharge('renewal-payment-2');

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        $order = AgencyCategoryOrder::where('auto_renewal', true)->firstOrFail();
        $this->assertSame('3000.00', (string) $order->subtotal); // 2000 + 1×1000

        $subscription->refresh();
        $this->assertSame(1, $subscription->extra_tour_slots);
        $this->assertNull($subscription->next_extra_tour_slots);
    }

    public function test_renewal_applies_zero_slot_reduction_plan(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, extraSlots: 3, expiresAt: today());
        $subscription->update(['next_extra_tour_slots' => 0]);
        $this->makeStoredCard($agency);
        $this->makePaidOrderWithBuyer($agency, $category);

        $this->mockIyzicoForStoredCardCharge('renewal-payment-zero');

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        $order = AgencyCategoryOrder::where('auto_renewal', true)->firstOrFail();
        $this->assertSame('2000.00', (string) $order->subtotal); // sadece kategori ücreti

        $subscription->refresh();
        // keep=0: hiç slot kalemi olmasa da haklar SIFIRLANMALI, plan temizlenmeli
        $this->assertSame(0, $subscription->extra_tour_slots);
        $this->assertNull($subscription->next_extra_tour_slots);
    }

    public function test_expire_command_closes_cancelled_subscription_without_charge(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, extraSlots: 2, expiresAt: today()->subDay());
        $subscription->update([
            'auto_renew' => false,
            'cancelled_at' => now()->subDays(10),
            'next_extra_tour_slots' => 1,
        ]);
        $this->makeStoredCard($agency);

        $this->mock(IyzicoService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('chargeStoredCard');
        });

        // İptal akışının son adımı: dönem bitti → çekim YOK, abonelik kapanır
        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();
        $this->artisan('app:expire-category-subscriptions')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame(AgencyCategorySubscription::STATUS_EXPIRED, $subscription->status);
        $this->assertSame(0, $subscription->extra_tour_slots);
        $this->assertNull($subscription->next_extra_tour_slots);
        $this->assertSame(0, AgencyCategoryOrder::where('auto_renewal', true)->count());
    }

    public function test_renewal_reconciles_unresolved_pending_order_instead_of_recharging(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [$user, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, expiresAt: today());
        $this->makeStoredCard($agency);
        $this->makePaidOrderWithBuyer($agency, $category);

        // Dünkü koşuda çekim yapılmış ama finalize düşmemiş senaryosu:
        // pending + auto_renewal sipariş askıda
        $unresolved = $this->makeUnresolvedRenewalOrder($agency, $category);

        $payment = $this->makePaymentMock('recon-payment-1', success: true);
        $this->mock(IyzicoService::class, function (MockInterface $mock) use ($payment) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('retrievePayment')->once()->andReturn($payment);
            // Para zaten çekilmiş — İKİNCİ çekim ASLA yapılmamalı
            $mock->shouldNotReceive('chargeStoredCard');
        });

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        $unresolved->refresh();
        $this->assertSame(AgencyCategoryOrder::STATUS_PAID, $unresolved->status);
        $this->assertSame(1, AgencyCategoryOrder::where('auto_renewal', true)->count()); // yeni sipariş açılmadı
        $this->assertTrue($subscription->fresh()->expires_at->equalTo(today()->addMonth()));
        Notification::assertSentTo($user, CategorySubscriptionRenewedNotification::class);
    }

    public function test_renewal_recharges_after_reconciliation_confirms_no_payment(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, expiresAt: today());
        $this->makeStoredCard($agency);
        $this->makePaidOrderWithBuyer($agency, $category);

        $unresolved = $this->makeUnresolvedRenewalOrder($agency, $category);

        $notFound = $this->makePaymentMock(null, success: false, errorMessage: 'Ödeme bulunamadı');
        $charged = $this->makePaymentMock('recharge-payment-1', success: true);
        $this->mock(IyzicoService::class, function (MockInterface $mock) use ($notFound, $charged) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('retrievePayment')->once()->andReturn($notFound);
            $mock->shouldReceive('chargeStoredCard')->once()->andReturn($charged);
        });

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        // Eski belirsiz sipariş failed'a çekildi, yeni çekim yapıldı
        $this->assertSame(AgencyCategoryOrder::STATUS_FAILED, $unresolved->fresh()->status);
        $this->assertSame(1, AgencyCategoryOrder::where('auto_renewal', true)
            ->where('status', AgencyCategoryOrder::STATUS_PAID)->count());
        $this->assertTrue($subscription->fresh()->expires_at->equalTo(today()->addMonth()));
    }

    public function test_admin_revoke_clears_slot_reduction_plan(): void
    {
        Notification::fake();

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, extraSlots: 3);
        $subscription->update(['next_extra_tour_slots' => 2]);

        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)
            ->post(route('admin.agencies.categories.revoke', [$agency, $subscription]))
            ->assertRedirect();

        $subscription->refresh();
        $this->assertSame(AgencyCategorySubscription::STATUS_CANCELLED, $subscription->status);
        $this->assertSame(0, $subscription->extra_tour_slots);
        // Bayat plan kalsaydı re-grant sonrası ilk yenilemede acentanın sahip
        // olmadığı 2 hak için çekim yapılırdı
        $this->assertNull($subscription->next_extra_tour_slots);
    }

    public function test_renewal_skips_cancelled_subscription(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, expiresAt: today());
        $subscription->update(['auto_renew' => false, 'cancelled_at' => now()->subDays(5)]);
        $this->makeStoredCard($agency);
        $this->makePaidOrderWithBuyer($agency, $category);

        // Çekim çağrısı hiç yapılmamalı
        $this->mock(IyzicoService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('chargeStoredCard');
        });

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        $this->assertSame(0, AgencyCategoryOrder::where('auto_renewal', true)->count());
        // Dönem sonuna kadar hâlâ aktif
        $this->assertTrue($subscription->fresh()->is_active);
    }

    public function test_renewal_failure_marks_order_failed_and_notifies(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [$user, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category, expiresAt: today());
        $originalExpires = $subscription->expires_at->copy();
        $this->makeStoredCard($agency);
        $this->makePaidOrderWithBuyer($agency, $category);

        $this->mockIyzicoForStoredCardCharge(null, success: false, errorMessage: 'Yetersiz bakiye');

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        $order = AgencyCategoryOrder::where('auto_renewal', true)->firstOrFail();
        $this->assertSame(AgencyCategoryOrder::STATUS_FAILED, $order->status);
        $this->assertSame('Yetersiz bakiye', $order->failure_reason);

        $subscription->refresh();
        $this->assertTrue($subscription->expires_at->equalTo($originalExpires)); // uzatılmadı
        Notification::assertSentTo($user, CategorySubscriptionRenewalFailedNotification::class);
    }

    public function test_renewal_attempts_only_once_per_day(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $this->makeActiveSubscription($agency, $category, expiresAt: today()->addDay());
        $this->makeStoredCard($agency);
        $this->makePaidOrderWithBuyer($agency, $category);

        // Başarısız çekim: abonelik pencerede kalır ama aynı gün İKİNCİ deneme
        // yapılmamalı (renewal_attempted_at claim'i) — çift sipariş oluşmaz
        $this->mockIyzicoForStoredCardCharge(null, success: false, errorMessage: 'Banka reddi');

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();
        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        $this->assertSame(1, AgencyCategoryOrder::where('auto_renewal', true)->count());
    }

    public function test_renewal_noop_when_flag_disabled(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => false]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $this->makeActiveSubscription($agency, $category, expiresAt: today());
        $this->makeStoredCard($agency);
        $this->makePaidOrderWithBuyer($agency, $category);

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        $this->assertSame(0, AgencyCategoryOrder::where('auto_renewal', true)->count());
    }

    public function test_renewal_skips_when_no_stored_card(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $this->makeActiveSubscription($agency, $category, expiresAt: today());
        $this->makePaidOrderWithBuyer($agency, $category);

        $this->mock(IyzicoService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldNotReceive('chargeStoredCard');
        });

        $this->artisan('app:renew-category-subscriptions')->assertSuccessful();

        $this->assertSame(0, AgencyCategoryOrder::where('auto_renewal', true)->count());
        Notification::assertNothingSent();
    }

    public function test_checkout_success_captures_stored_card(): void
    {
        Queue::fake();
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [, $agency, $category] = $this->makeAgencyAndCategory();
        $order = $this->makePendingIyzicoOrder($agency, $category, 'card-capture-token');

        $this->mockIyzicoForCallbackSuccess($order, 'payment-card-1', card: [
            'user_key' => 'cuk-123',
            'token' => 'ct-456',
            'last_four' => '4242',
            'association' => 'VISA',
            'family' => 'Bonus',
        ]);

        $this->post(route('agency.category-licenses.iyzico.callback', $order), ['token' => 'card-capture-token'])
            ->assertRedirect();

        $storedCard = AgencyStoredCard::where('agency_id', $agency->id)->firstOrFail();
        $this->assertSame('cuk-123', $storedCard->card_user_key);
        $this->assertSame('ct-456', $storedCard->card_token);
        $this->assertSame('4242', $storedCard->last_four);
        $this->assertSame('VISA', $storedCard->card_association);
    }

    public function test_slot_can_be_bought_together_with_category_in_same_checkout(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, $agency, $category] = $this->makeAgencyAndCategory();

        // Abonelik YOK ama kategori sepette → hak da eklenebilmeli
        $this->actingAs($user)
            ->post(route('agency.category-licenses.cart.add'), ['category_id' => $category->id])
            ->assertRedirect();
        $this->actingAs($user)
            ->post(route('agency.category-licenses.cart.add-slot'), ['category_id' => $category->id])
            ->assertSessionHas('success');

        $this->mockIyzicoForCheckoutFormInit('combo-token-1');

        $this->actingAs($user)
            ->post(route('agency.category-licenses.initiate-payment'), $this->validBuyerPayload())
            ->assertOk();

        $order = AgencyCategoryOrder::firstOrFail();
        $this->assertSame('3000.00', (string) $order->subtotal); // 2000 + 1×1000

        $this->mockIyzicoForCallbackSuccess($order, 'payment-combo-1');

        $this->post(route('agency.category-licenses.iyzico.callback', $order), ['token' => 'combo-token-1'])
            ->assertRedirect();

        $subscription = AgencyCategorySubscription::where('agency_id', $agency->id)
            ->where('category_id', $category->id)
            ->firstOrFail();
        $this->assertSame(1, $subscription->extra_tour_slots);
    }

    public function test_reminders_skip_auto_renewable_and_cancelled_subscriptions(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        // (a) kartlı + yenileme açık → hatırlatma YOK (otomatik çekilecek)
        [$autoUser, $autoAgency, $categoryA] = $this->makeAgencyAndCategory();
        $this->makeActiveSubscription($autoAgency, $categoryA, expiresAt: today()->addDays(3));
        $this->makeStoredCard($autoAgency);

        // (b) iptal edilmiş → hatırlatma YOK (bilinçli bitiriyor)
        $cancelAgency = Agency::create([
            'name' => 'İptalci', 'slug' => 'iptalci', 'email' => 'iptal@example.com',
            'is_active' => true, 'legacy_category_access' => false,
        ]);
        $cancelUser = User::factory()->create(['role' => 'agency', 'agency_id' => $cancelAgency->id]);
        $cancelSub = $this->makeActiveSubscription($cancelAgency, $categoryA, expiresAt: today()->addDays(3));
        $cancelSub->update(['auto_renew' => false, 'cancelled_at' => now()]);

        // (c) kartsız normal abonelik → hatırlatma VAR
        $plainAgency = Agency::create([
            'name' => 'Normal', 'slug' => 'normal', 'email' => 'normal@example.com',
            'is_active' => true, 'legacy_category_access' => false,
        ]);
        $plainUser = User::factory()->create(['role' => 'agency', 'agency_id' => $plainAgency->id]);
        $this->makeActiveSubscription($plainAgency, $categoryA, expiresAt: today()->addDays(3));

        $this->artisan('app:expire-category-subscriptions')->assertSuccessful();

        Notification::assertNotSentTo($autoUser, CategorySubscriptionExpiringNotification::class);
        Notification::assertNotSentTo($cancelUser, CategorySubscriptionExpiringNotification::class);
        Notification::assertSentTo($plainUser, CategorySubscriptionExpiringNotification::class);
    }

    public function test_delete_stored_card_removes_local_record(): void
    {
        Notification::fake();
        config(['iyzico.auto_renew_enabled' => true]);

        [$user, $agency] = $this->makeAgencyAndCategory();
        $this->makeStoredCard($agency);

        $this->mock(IyzicoService::class, function (MockInterface $mock) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('deleteStoredCard')->once();
        });

        $this->actingAs($user)
            ->delete(route('agency.category-licenses.stored-card.delete'))
            ->assertSessionHas('success');

        $this->assertSame(0, AgencyStoredCard::count());
    }

    public function test_lapsed_manual_repurchase_resets_cancellation_state(): void
    {
        Queue::fake();
        Notification::fake();

        [, $agency, $category] = $this->makeAgencyAndCategory();

        $subscription = AgencyCategorySubscription::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'monthly_price' => 2000,
            'extra_tour_slots' => 2,
            'auto_renew' => false,
            'cancelled_at' => now()->subMonth(),
            'next_extra_tour_slots' => 1,
            'status' => AgencyCategorySubscription::STATUS_EXPIRED,
            'started_at' => today()->subMonths(2),
            'expires_at' => today()->subDays(10),
        ]);

        $order = $this->makePendingIyzicoOrder($agency, $category, 'repurchase-token-1');

        $this->mockIyzicoForCallbackSuccess($order, 'payment-repurchase-1');

        $this->post(route('agency.category-licenses.iyzico.callback', $order), ['token' => 'repurchase-token-1'])
            ->assertRedirect();

        $subscription->refresh();
        // Yeniden satın alma = yeni abonelik iradesi: iptal durumu temizlenir
        $this->assertTrue($subscription->auto_renew);
        $this->assertNull($subscription->cancelled_at);
        $this->assertNull($subscription->next_extra_tour_slots);
        $this->assertSame(0, $subscription->extra_tour_slots); // kesintide haklar yandı
        $this->assertSame(AgencyCategorySubscription::STATUS_ACTIVE, $subscription->status);
    }

    // ------------------------------------------------------------------
    // Yardımcılar
    // ------------------------------------------------------------------

    private function makeAgencyAndCategory(): array
    {
        $agency = Agency::create([
            'name' => 'Yenileme Acentası',
            'slug' => 'yenileme-acentasi',
            'email' => 'yenileme@example.com',
            'is_active' => true,
            'legacy_category_access' => false,
        ]);

        $user = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $parent = Category::create([
            'name' => 'Tur Grupları', 'slug' => 'tur-gruplari',
            'monthly_price' => 0, 'sort_order' => 1, 'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Kültür Turları', 'slug' => 'kultur-turlari',
            'parent_id' => $parent->id, 'monthly_price' => 2000,
            'extra_tour_price' => 1000, 'sort_order' => 1, 'is_active' => true,
        ]);

        return [$user, $agency, $category];
    }

    private function makeActiveSubscription(
        Agency $agency,
        Category $category,
        int $extraSlots = 0,
        $expiresAt = null
    ): AgencyCategorySubscription {
        return AgencyCategorySubscription::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'monthly_price' => $category->monthly_price,
            'extra_tour_slots' => $extraSlots,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today()->subWeeks(3),
            'expires_at' => $expiresAt ?? today()->addWeek(),
        ]);
    }

    private function makeStoredCard(Agency $agency): AgencyStoredCard
    {
        return AgencyStoredCard::create([
            'agency_id' => $agency->id,
            'card_user_key' => 'test-card-user-key',
            'card_token' => 'test-card-token',
            'last_four' => '4242',
            'card_association' => 'VISA',
        ]);
    }

    private function makePaidOrderWithBuyer(Agency $agency, Category $category): AgencyCategoryOrder
    {
        $order = AgencyCategoryOrder::create([
            'agency_id' => $agency->id,
            'order_number' => 'KYM-PREV-'.$agency->id,
            'billing_cycle' => 'monthly',
            'subtotal' => $category->monthly_price,
            'currency' => 'TRY',
            'status' => AgencyCategoryOrder::STATUS_PAID,
            'payment_provider' => AgencyCategoryOrder::PROVIDER_IYZICO,
            'buyer_type' => AgencyCategoryOrder::BUYER_INDIVIDUAL,
            'buyer_snapshot' => $this->validBuyerPayload() + ['type' => 'individual'],
            'purchased_at' => now()->subMonth(),
            'paid_at' => now()->subMonth(),
        ]);

        AgencyCategoryOrderItem::create([
            'order_id' => $order->id,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'item_type' => AgencyCategoryOrderItem::TYPE_LICENSE,
            'unit_price' => $category->monthly_price,
            'billing_cycle' => 'monthly',
        ]);

        return $order;
    }

    /** Dünkü koşudan askıda kalmış (pending, token'sız) otomatik yenileme siparişi. */
    private function makeUnresolvedRenewalOrder(Agency $agency, Category $category): AgencyCategoryOrder
    {
        $order = AgencyCategoryOrder::create([
            'agency_id' => $agency->id,
            'order_number' => 'KYM-YEN-UNRESOLVED-'.$agency->id,
            'billing_cycle' => 'monthly',
            'subtotal' => $category->monthly_price,
            'currency' => 'TRY',
            'status' => AgencyCategoryOrder::STATUS_PENDING,
            'payment_provider' => AgencyCategoryOrder::PROVIDER_IYZICO,
            'auto_renewal' => true,
            'purchased_at' => now()->subDay(),
        ]);

        AgencyCategoryOrderItem::create([
            'order_id' => $order->id,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'item_type' => AgencyCategoryOrderItem::TYPE_LICENSE,
            'unit_price' => $category->monthly_price,
            'billing_cycle' => 'monthly',
        ]);

        return $order;
    }

    private function makePendingIyzicoOrder(Agency $agency, Category $category, string $token): AgencyCategoryOrder
    {
        $order = AgencyCategoryOrder::create([
            'agency_id' => $agency->id,
            'order_number' => 'KYM-TEST-'.strtoupper(substr(md5($token), 0, 6)),
            'billing_cycle' => 'monthly',
            'subtotal' => $category->monthly_price,
            'currency' => 'TRY',
            'status' => AgencyCategoryOrder::STATUS_PENDING,
            'payment_provider' => AgencyCategoryOrder::PROVIDER_IYZICO,
            'provider_token' => $token,
            'purchased_at' => now(),
        ]);

        AgencyCategoryOrderItem::create([
            'order_id' => $order->id,
            'category_id' => $category->id,
            'category_name' => $category->name,
            'item_type' => AgencyCategoryOrderItem::TYPE_LICENSE,
            'unit_price' => $category->monthly_price,
            'billing_cycle' => 'monthly',
        ]);

        return $order;
    }

    private function mockIyzicoForStoredCardCharge(
        ?string $paymentId,
        bool $success = true,
        ?string $errorMessage = null
    ): void {
        $payment = $this->makePaymentMock($paymentId, $success, $errorMessage);

        $this->mock(IyzicoService::class, function (MockInterface $mock) use ($payment) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('chargeStoredCard')->andReturn($payment);
        });
    }

    /**
     * Non-3DS /payment/auth cevabının GERÇEK şekli: başarıda paymentStatus
     * alanı GELMEZ (null) — iyzico dokümanı böyle söylüyor. Mock bunu birebir
     * taklit eder ki başarı kontrolü yanlış alana bakarsa test kırılsın.
     */
    private function makePaymentMock(?string $paymentId, bool $success, ?string $errorMessage = null): Payment
    {
        $payment = \Mockery::mock(Payment::class);
        $payment->shouldReceive('getStatus')->andReturn($success ? Status::SUCCESS : Status::FAILURE);
        $payment->shouldReceive('getPaymentStatus')->andReturn(null);
        $payment->shouldReceive('getPaymentId')->andReturn($paymentId);
        $payment->shouldReceive('getErrorMessage')->andReturn($errorMessage);

        return $payment;
    }

    private function mockIyzicoForCheckoutFormInit(string $token): void
    {
        $init = \Mockery::mock(\Iyzipay\Model\CheckoutFormInitialize::class);
        $init->shouldReceive('getToken')->andReturn($token);
        $init->shouldReceive('getCheckoutFormContent')->andReturn('<script>console.log("iyzico-mock");</script>');
        $init->shouldReceive('getPaymentPageUrl')->andReturn('https://sandbox-cpp.iyzipay.com/'.$token);

        $this->mock(IyzicoService::class, function (MockInterface $mock) use ($init) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('initializeCheckoutForm')->andReturn($init);
        });
    }

    /** @param array{user_key?:string,token?:string,last_four?:string,association?:string,family?:string}|null $card */
    private function mockIyzicoForCallbackSuccess(AgencyCategoryOrder $order, string $paymentId, ?array $card = null): void
    {
        $price = number_format((float) $order->subtotal, 2, '.', '');

        $form = \Mockery::mock(CheckoutForm::class);
        $form->shouldReceive('getStatus')->andReturn(Status::SUCCESS);
        $form->shouldReceive('getPaymentStatus')->andReturn('SUCCESS');
        $form->shouldReceive('getPaymentId')->andReturn($paymentId);
        $form->shouldReceive('getErrorMessage')->andReturn(null);
        $form->shouldReceive('getPrice')->andReturn($price);
        $form->shouldReceive('getPaidPrice')->andReturn($price);
        $form->shouldReceive('getCardUserKey')->andReturn($card['user_key'] ?? null);
        $form->shouldReceive('getCardToken')->andReturn($card['token'] ?? null);
        $form->shouldReceive('getLastFourDigits')->andReturn($card['last_four'] ?? null);
        $form->shouldReceive('getCardAssociation')->andReturn($card['association'] ?? null);
        $form->shouldReceive('getCardFamily')->andReturn($card['family'] ?? null);

        $this->mock(IyzicoService::class, function (MockInterface $mock) use ($form) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('retrieveCheckoutForm')->andReturn($form);
        });
    }

    private function validBuyerPayload(): array
    {
        return [
            'buyer_type' => AgencyCategoryOrder::BUYER_INDIVIDUAL,
            'name' => 'Ali',
            'surname' => 'Yılmaz',
            'identity_number' => '12345678901',
            'email' => 'ali@example.com',
            'gsm' => '+905001112233',
            'address' => 'Test Mahallesi No:1',
            'city' => 'İstanbul',
            'country' => 'Turkey',
            'zip_code' => '34000',
        ];
    }

    private function validTourPayload(int $categoryId): array
    {
        return [
            'category_id' => $categoryId,
            'title' => 'Yenileme Test Turu',
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'description' => 'Abonelik iptali test turu.',
            'duration_days' => 3,
            'currency' => 'TRY',
            'included' => 'Ulaşım',
            'excluded' => 'Kişisel harcamalar',
            'tour_url' => 'https://example.com/yenileme-test-tur',
            'requires_visa' => '0',
            'pricing_options' => [
                [
                    'price' => '7500',
                    'departure_dates' => [today()->addDays(10)->toDateString()],
                ],
            ],
        ];
    }
}
