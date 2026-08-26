<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategoryOrderItem;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use App\Services\Payment\IyzicoService;
use App\Support\CategoryLicensing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Status;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Tur hakkı (slot) sistemi: her kategori aboneliği BASE_TOUR_ALLOWANCE (2) tur
 * ekleme hakkı içerir; fazlası kategori bazlı ekstra hak olarak satın alınır.
 */
class AgencyCategoryTourSlotTest extends TestCase
{
    use RefreshDatabase;

    public function test_third_tour_blocked_until_extra_slot_granted(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, $agency, $category] = $this->makeAgencyAndCategory();
        $subscription = $this->makeActiveSubscription($agency, $category);

        $this->makeTour($agency, $category, 'Tur 1');
        $this->makeTour($agency, $category, 'Tur 2');

        $this->actingAs($user)
            ->post(route('agency.tours.store'), $this->validTourPayload($category->id))
            ->assertSessionHasErrors('category_id');

        $this->assertSame(2, Tour::count());

        $subscription->update(['extra_tour_slots' => 1]);

        $this->actingAs($user)
            ->post(route('agency.tours.store'), $this->validTourPayload($category->id))
            ->assertRedirect(route('agency.tours.index'));

        $this->assertSame(3, Tour::count());
    }

    public function test_passive_tours_consume_slots_too(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, $agency, $category] = $this->makeAgencyAndCategory();
        $this->makeActiveSubscription($agency, $category);

        $this->makeTour($agency, $category, 'Aktif Tur');
        $this->makeTour($agency, $category, 'Pasif Tur', isActive: false);

        // Pasife çekip yerine yenisini ekleme açığı yok: pasif tur da hak tüketir
        $this->actingAs($user)
            ->post(route('agency.tours.store'), $this->validTourPayload($category->id))
            ->assertSessionHasErrors('category_id');
    }

    public function test_legacy_agency_has_no_tour_limit(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, $agency, $category] = $this->makeAgencyAndCategory(legacy: true);

        $this->makeTour($agency, $category, 'Tur 1');
        $this->makeTour($agency, $category, 'Tur 2');

        $this->actingAs($user)
            ->post(route('agency.tours.store'), $this->validTourPayload($category->id))
            ->assertRedirect(route('agency.tours.index'));

        $this->assertSame(3, Tour::count());
    }

    public function test_moving_tour_to_full_category_blocked_but_same_category_edit_allowed(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, $agency, $categoryA] = $this->makeAgencyAndCategory();
        $this->makeActiveSubscription($agency, $categoryA);

        $categoryB = Category::create([
            'name' => 'Deniz Turları',
            'slug' => 'deniz-turlari',
            'parent_id' => $categoryA->parent_id,
            'monthly_price' => 2000,
            'sort_order' => 2,
            'is_active' => true,
        ]);
        $this->makeActiveSubscription($agency, $categoryB);

        // A kategorisi dolu (2/2); B'de limit ÜSTÜ 3 tur var (grandfathering senaryosu)
        $this->makeTour($agency, $categoryA, 'A Tur 1');
        $this->makeTour($agency, $categoryA, 'A Tur 2');
        $this->makeTour($agency, $categoryB, 'B Tur 1');
        $this->makeTour($agency, $categoryB, 'B Tur 2');
        $editedTour = $this->makeTour($agency, $categoryB, 'B Tur 3');

        // Kendi kategorisinde kalarak düzenleme: limit üstünde olsa da serbest
        $this->actingAs($user)
            ->put(route('agency.tours.update', $editedTour), $this->validTourPayload($categoryB->id) + ['is_active' => 1])
            ->assertRedirect(route('agency.tours.index'));

        // Dolu A kategorisine taşıma: yeni hak gerektirir → engellenir
        $this->actingAs($user)
            ->put(route('agency.tours.update', $editedTour), $this->validTourPayload($categoryA->id) + ['is_active' => 1])
            ->assertSessionHasErrors('category_id');

        $this->assertSame($categoryB->id, $editedTour->fresh()->category_id);
    }

    public function test_extra_slot_checkout_flow_grants_slots_on_paid_callback(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, $agency, $category] = $this->makeAgencyAndCategory();
        $category->update(['extra_tour_price' => 750]);
        $subscription = $this->makeActiveSubscription($agency, $category);

        // Aynı kategoriye iki kez ekstra hak: adet 2 olur
        $this->actingAs($user)
            ->post(route('agency.category-licenses.cart.add-slot'), ['category_id' => $category->id])
            ->assertRedirect();
        $this->actingAs($user)
            ->post(route('agency.category-licenses.cart.add-slot'), ['category_id' => $category->id])
            ->assertRedirect();

        $this->mockIyzicoForCheckoutFormInit('slot-token-1');

        $this->actingAs($user)
            ->post(route('agency.category-licenses.initiate-payment'), $this->validBuyerPayload())
            ->assertOk();

        $order = AgencyCategoryOrder::firstOrFail();
        $this->assertSame('1500.00', (string) $order->subtotal);
        $this->assertSame(2, AgencyCategoryOrderItem::where('order_id', $order->id)
            ->where('item_type', AgencyCategoryOrderItem::TYPE_EXTRA_SLOT)
            ->where('unit_price', '750.00')
            ->count());

        $this->mockIyzicoForCallbackSuccess($order, 'payment-slot-1');

        $this->post(route('agency.category-licenses.iyzico.callback', $order), ['token' => 'slot-token-1'])
            ->assertRedirect(route('agency.category-licenses.payment.result', $order));

        $this->assertSame(AgencyCategoryOrder::STATUS_PAID, $order->fresh()->status);
        $this->assertSame(2, $subscription->fresh()->extra_tour_slots);
        $this->assertSame([], session('agency_category_slot_cart', []));
    }

    public function test_extra_slot_requires_active_subscription(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, , $category] = $this->makeAgencyAndCategory();

        $this->actingAs($user)
            ->from(route('agency.category-licenses.index'))
            ->post(route('agency.category-licenses.cart.add-slot'), ['category_id' => $category->id])
            ->assertSessionHasErrors();

        $this->assertSame([], session('agency_category_slot_cart', []));
    }

    public function test_lapsed_renewal_resets_extra_slots(): void
    {
        Queue::fake();
        Notification::fake();

        [, $agency, $category] = $this->makeAgencyAndCategory();

        // Abonelik SÜRESİ GEÇMİŞ ama hâlâ active görünüyor; 3 ekstra hak birikmişti
        $subscription = AgencyCategorySubscription::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'monthly_price' => 2000,
            'extra_tour_slots' => 3,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today()->subMonths(2),
            'expires_at' => today()->subDays(5),
        ]);

        $order = $this->makePendingIyzicoOrder($agency, $category, 'renewal-token-1');

        $this->mockIyzicoForCallbackSuccess($order, 'payment-renewal-1');

        $this->post(route('agency.category-licenses.iyzico.callback', $order), ['token' => 'renewal-token-1'])
            ->assertRedirect();

        $subscription->refresh();
        // Kesintili yenileme = yeni dönem: eski ekstra haklar yanar
        $this->assertSame(0, $subscription->extra_tour_slots);
        $this->assertTrue($subscription->started_at->isToday());
        $this->assertTrue($subscription->expires_at->equalTo(today()->addMonth()));
    }

    public function test_expire_command_resets_extra_slots(): void
    {
        Notification::fake();

        [, $agency, $category] = $this->makeAgencyAndCategory();

        $subscription = AgencyCategorySubscription::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'monthly_price' => 2000,
            'extra_tour_slots' => 2,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today()->subMonth(),
            'expires_at' => today()->subDay(),
        ]);

        $this->artisan('app:expire-category-subscriptions')->assertSuccessful();

        $subscription->refresh();
        $this->assertSame(AgencyCategorySubscription::STATUS_EXPIRED, $subscription->status);
        $this->assertSame(0, $subscription->extra_tour_slots);
    }

    public function test_admin_can_update_extra_tour_price_from_pricing_page(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        [, , $category] = $this->makeAgencyAndCategory();

        $this->actingAs($admin)
            ->put(route('admin.category-licenses.pricing.update', $category), [
                'monthly_price' => 2500,
                'extra_tour_price' => 800,
            ])
            ->assertRedirect(route('admin.category-licenses.pricing'));

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'monthly_price' => '2500.00',
            'extra_tour_price' => '800.00',
        ]);
    }

    private function makeAgencyAndCategory(bool $legacy = false): array
    {
        $agency = Agency::create([
            'name' => 'Slot Acentası',
            'slug' => 'slot-acentasi',
            'email' => 'slot@example.com',
            'is_active' => true,
            'legacy_category_access' => $legacy,
        ]);

        $user = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $parent = Category::create([
            'name' => 'Tur Grupları',
            'slug' => 'tur-gruplari',
            'monthly_price' => 0,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $category = Category::create([
            'name' => 'Kültür Turları',
            'slug' => 'kultur-turlari',
            'parent_id' => $parent->id,
            'monthly_price' => 2000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return [$user, $agency, $category];
    }

    private function makeActiveSubscription(Agency $agency, Category $category): AgencyCategorySubscription
    {
        return AgencyCategorySubscription::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'monthly_price' => $category->monthly_price,
            'extra_tour_slots' => 0,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
            'started_at' => today(),
            'expires_at' => today()->addMonth(),
        ]);
    }

    private function makeTour(Agency $agency, Category $category, string $title, bool $isActive = true): Tour
    {
        return Tour::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'title' => $title,
            'destination' => 'Kapadokya',
            'description' => 'Slot testi turu.',
            'price' => 5000,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(7),
            'return_date' => today()->addDays(9),
            'is_active' => $isActive,
        ]);
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

    private function mockIyzicoForCheckoutFormInit(string $token): void
    {
        $init = \Mockery::mock(CheckoutFormInitialize::class);
        $init->shouldReceive('getToken')->andReturn($token);
        $init->shouldReceive('getCheckoutFormContent')->andReturn('<script>console.log("iyzico-mock");</script>');
        $init->shouldReceive('getPaymentPageUrl')->andReturn('https://sandbox-cpp.iyzipay.com/'.$token);

        $this->mock(IyzicoService::class, function (MockInterface $mock) use ($init) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('initializeCheckoutForm')->andReturn($init);
        });
    }

    private function mockIyzicoForCallbackSuccess(AgencyCategoryOrder $order, string $paymentId): void
    {
        $price = number_format((float) $order->subtotal, 2, '.', '');

        $form = \Mockery::mock(CheckoutForm::class);
        $form->shouldReceive('getStatus')->andReturn(Status::SUCCESS);
        $form->shouldReceive('getPaymentStatus')->andReturn('SUCCESS');
        $form->shouldReceive('getPaymentId')->andReturn($paymentId);
        $form->shouldReceive('getErrorMessage')->andReturn(null);
        $form->shouldReceive('getPrice')->andReturn($price);
        $form->shouldReceive('getPaidPrice')->andReturn($price);

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
            'title' => 'Slot Test Turu',
            'destination' => 'Kapadokya',
            'departure_city' => 'İstanbul',
            'description' => 'Tur hakkı limiti test turu.',
            'duration_days' => 3,
            'currency' => 'TRY',
            'included' => 'Ulaşım',
            'excluded' => 'Kişisel harcamalar',
            'tour_url' => 'https://example.com/slot-test-tur',
            'pricing_options' => [
                [
                    'price' => '7500',
                    'departure_dates' => [today()->addDays(10)->toDateString()],
                ],
            ],
        ];
    }
}
