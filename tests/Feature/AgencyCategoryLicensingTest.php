<?php

namespace Tests\Feature;

use App\Models\Agency;
use App\Models\AgencyCategoryOrder;
use App\Models\AgencyCategorySubscription;
use App\Models\Category;
use App\Models\Tour;
use App\Models\User;
use App\Services\Payment\IyzicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Status;
use Mockery\MockInterface;
use Tests\TestCase;

class AgencyCategoryLicensingTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_agency_tours_remain_publicly_visible_without_subscription_records(): void
    {
        Queue::fake();
        Notification::fake();

        $agency = Agency::create([
            'name' => 'Legacy Travel',
            'slug' => 'legacy-travel',
            'email' => 'legacy@example.com',
            'is_active' => true,
            'legacy_category_access' => true,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'category_id' => null,
            'title' => 'Legacy Tur',
            'destination' => 'Antalya',
            'description' => 'Geçiş erişimi ile görünür kalmalı.',
            'price' => 4500,
            'currency' => 'TRY',
            'duration_days' => 3,
            'departure_date' => today()->addDays(7),
            'return_date' => today()->addDays(9),
            'is_active' => true,
        ]);

        $this->assertTrue($tour->fresh()->isPubliclyVisible());
        $this->assertTrue(Tour::active()->whereKey($tour->id)->exists());
    }

    public function test_agency_cannot_create_tour_in_unlicensed_category_until_iyzico_payment_succeeds(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, $agency, $category] = $this->makeAgencyAndCategory();
        $payload = $this->validTourPayload($category->id);

        $this->actingAs($user)
            ->post(route('agency.tours.store'), $payload)
            ->assertSessionHasErrors('category_id');

        $this->assertDatabaseCount('tours', 0);

        $this->actingAs($user)
            ->post(route('agency.category-licenses.cart.add'), ['category_id' => $category->id])
            ->assertRedirect();

        $this->mockIyzicoForCheckoutFormInit('iyzico-token-1');

        $response = $this->actingAs($user)
            ->post(route('agency.category-licenses.initiate-payment'), $this->validBuyerPayload());

        $response->assertOk();
        $response->assertSee('iyzipay-checkout-form', false);

        $this->assertDatabaseHas('agency_category_orders', [
            'agency_id' => $agency->id,
            'subtotal' => '2000.00',
            'status' => AgencyCategoryOrder::STATUS_PENDING,
            'provider_token' => 'iyzico-token-1',
        ]);

        $this->assertDatabaseMissing('agency_category_subscriptions', [
            'agency_id' => $agency->id,
            'category_id' => $category->id,
        ]);

        $order = AgencyCategoryOrder::where('agency_id', $agency->id)->firstOrFail();
        $this->mockIyzicoForCallbackSuccess($order, 'iyzico-payment-1');

        $this->actingAs($user)
            ->post(route('agency.category-licenses.iyzico.callback', $order), ['token' => 'iyzico-token-1'])
            ->assertRedirect(route('agency.category-licenses.payment.result', $order));

        $this->assertDatabaseHas('agency_category_orders', [
            'id' => $order->id,
            'status' => AgencyCategoryOrder::STATUS_PAID,
            'provider_payment_id' => 'iyzico-payment-1',
        ]);

        $this->assertDatabaseHas('agency_category_subscriptions', [
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'status' => AgencyCategorySubscription::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->post(route('agency.tours.store'), $payload)
            ->assertRedirect(route('agency.tours.index'));

        $this->assertDatabaseCount('tours', 1);
        $this->assertTrue($agency->fresh()->hasCategoryAccess($category));
    }

    public function test_failed_iyzico_callback_marks_order_failed_without_creating_subscription(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, $agency, $category] = $this->makeAgencyAndCategory();

        $this->actingAs($user)
            ->post(route('agency.category-licenses.cart.add'), ['category_id' => $category->id]);

        $this->mockIyzicoForCheckoutFormInit('iyzico-token-fail');

        $this->actingAs($user)
            ->post(route('agency.category-licenses.initiate-payment'), $this->validBuyerPayload())
            ->assertOk();

        $order = AgencyCategoryOrder::where('agency_id', $agency->id)->firstOrFail();
        $this->mockIyzicoForCallbackFailure('Kart limiti yetersiz.');

        $this->actingAs($user)
            ->post(route('agency.category-licenses.iyzico.callback', $order), ['token' => 'iyzico-token-fail'])
            ->assertRedirect(route('agency.category-licenses.payment.result', $order));

        $order->refresh();

        $this->assertSame(AgencyCategoryOrder::STATUS_FAILED, $order->status);
        $this->assertNotNull($order->failure_reason);
        $this->assertDatabaseMissing('agency_category_subscriptions', [
            'agency_id' => $agency->id,
            'category_id' => $category->id,
        ]);
    }

    public function test_iyzico_callback_rejects_mismatched_token(): void
    {
        Queue::fake();
        Notification::fake();

        [$user, $agency, $category] = $this->makeAgencyAndCategory();

        $this->actingAs($user)
            ->post(route('agency.category-licenses.cart.add'), ['category_id' => $category->id]);

        $this->mockIyzicoForCheckoutFormInit('correct-token');

        $this->actingAs($user)
            ->post(route('agency.category-licenses.initiate-payment'), $this->validBuyerPayload());

        $order = AgencyCategoryOrder::where('agency_id', $agency->id)->firstOrFail();

        $this->post(route('agency.category-licenses.iyzico.callback', $order), ['token' => 'sahte-token'])
            ->assertForbidden();

        $this->assertSame(AgencyCategoryOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_unlicensed_tour_is_not_publicly_accessible_for_new_agencies(): void
    {
        Queue::fake();
        Notification::fake();

        $category = Category::create([
            'name' => 'Doğa Turları',
            'slug' => 'doga-turlari',
            'monthly_price' => 2000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $agency = Agency::create([
            'name' => 'Kilitli Acenta',
            'slug' => 'kilitli-acenta',
            'email' => 'kilitli@example.com',
            'is_active' => true,
            'legacy_category_access' => false,
        ]);

        $tour = Tour::create([
            'agency_id' => $agency->id,
            'category_id' => $category->id,
            'title' => 'Kilitli Tur',
            'destination' => 'Kaş',
            'description' => 'Abonelik yoksa görünmemeli.',
            'price' => 5200,
            'currency' => 'TRY',
            'duration_days' => 4,
            'departure_date' => today()->addDays(14),
            'return_date' => today()->addDays(17),
            'is_active' => true,
        ]);

        $this->assertFalse($tour->fresh()->isPubliclyVisible());
        $this->assertFalse(Tour::active()->whereKey($tour->id)->exists());
        $this->get(route('tours.show', $tour))->assertNotFound();
    }

    private function makeAgencyAndCategory(): array
    {
        $agency = Agency::create([
            'name' => 'Yeni Acenta',
            'slug' => 'yeni-acenta',
            'email' => 'yeni@example.com',
            'is_active' => true,
            'legacy_category_access' => false,
        ]);

        $user = User::factory()->create([
            'role' => 'agency',
            'agency_id' => $agency->id,
        ]);

        $category = Category::create([
            'name' => 'Kültür Turları',
            'slug' => 'kultur-turlari',
            'monthly_price' => 2000,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        return [$user, $agency, $category];
    }

    private function mockIyzicoForCheckoutFormInit(string $token): void
    {
        $init = \Mockery::mock(CheckoutFormInitialize::class);
        $init->shouldReceive('getToken')->andReturn($token);
        $init->shouldReceive('getCheckoutFormContent')->andReturn('<script>console.log("iyzico-mock");</script>');
        $init->shouldReceive('getPaymentPageUrl')->andReturn('https://sandbox-cpp.iyzipay.com/' . $token);

        $this->mock(IyzicoService::class, function (MockInterface $mock) use ($init) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('initializeCheckoutForm')->andReturn($init);
        });
    }

    private function mockIyzicoForCallbackSuccess(AgencyCategoryOrder $order, string $paymentId): void
    {
        $form = \Mockery::mock(CheckoutForm::class);
        $form->shouldReceive('getStatus')->andReturn(Status::SUCCESS);
        $form->shouldReceive('getPaymentStatus')->andReturn('SUCCESS');
        $form->shouldReceive('getPaymentId')->andReturn($paymentId);
        $form->shouldReceive('getErrorMessage')->andReturn(null);

        $this->mock(IyzicoService::class, function (MockInterface $mock) use ($form) {
            $mock->shouldReceive('isConfigured')->andReturn(true);
            $mock->shouldReceive('retrieveCheckoutForm')->andReturn($form);
        });
    }

    private function mockIyzicoForCallbackFailure(string $errorMessage): void
    {
        $form = \Mockery::mock(CheckoutForm::class);
        $form->shouldReceive('getStatus')->andReturn(Status::FAILURE);
        $form->shouldReceive('getPaymentStatus')->andReturn('FAILURE');
        $form->shouldReceive('getPaymentId')->andReturn(null);
        $form->shouldReceive('getErrorMessage')->andReturn($errorMessage);

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
            'title' => 'Yetkili Test Turu',
            'destination' => 'Kapadokya',
            'description' => 'Kategori yetkisi test turu.',
            'duration_days' => 3,
            'currency' => 'TRY',
            'included' => 'Ulaşım',
            'excluded' => 'Kişisel harcamalar',
            'tour_url' => 'https://example.com/test-tur',
            'pricing_options' => [
                [
                    'price' => '7500',
                    'departure_dates' => [today()->addDays(10)->toDateString()],
                ],
            ],
        ];
    }
}
