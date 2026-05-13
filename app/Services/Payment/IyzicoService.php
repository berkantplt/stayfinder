<?php

namespace App\Services\Payment;

use App\Models\AgencyCategoryOrder;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Locale;
use Iyzipay\Model\Status;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\RetrieveCheckoutFormRequest;
use RuntimeException;

class IyzicoService
{
    public function __construct(private readonly array $config) {}

    public function isConfigured(): bool
    {
        return !empty($this->config['api_key']) && !empty($this->config['secret_key']);
    }

    /**
     * @param array<int, array{id:int|string, name:string, category:string, price:float|string}> $basketItems
     * @param array<string, mixed> $buyer Validated, normalized buyer payload
     */
    public function initializeCheckoutForm(
        AgencyCategoryOrder $order,
        array $basketItems,
        array $buyer,
        string $callbackUrl,
        string $clientIp
    ): CheckoutFormInitialize {
        $this->ensureConfigured();

        $request = new CreateCheckoutFormInitializeRequest();
        $request->setLocale($this->resolveLocale());
        $request->setConversationId((string) $order->id);
        $request->setPrice($this->formatAmount($order->subtotal));
        $request->setPaidPrice($this->formatAmount($order->subtotal));
        $request->setCurrency($order->currency ?: ($this->config['currency'] ?? 'TRY'));
        $request->setBasketId('KYM-' . $order->id);
        $request->setPaymentGroup('SUBSCRIPTION');
        $request->setCallbackUrl($callbackUrl);

        $request->setBuyer($this->buildBuyer($order, $buyer, $clientIp));

        $address = $this->buildAddress($buyer);
        $request->setShippingAddress($address);
        $request->setBillingAddress($address);

        $request->setBasketItems($this->buildBasketItems($basketItems));

        $response = CheckoutFormInitialize::create($request, $this->buildOptions());

        if ($response->getStatus() !== Status::SUCCESS) {
            throw new RuntimeException(
                $response->getErrorMessage() ?: 'iyzico ödeme formu başlatılamadı.'
            );
        }

        return $response;
    }

    public function retrieveCheckoutForm(string $token, string $conversationId): CheckoutForm
    {
        $this->ensureConfigured();

        $request = new RetrieveCheckoutFormRequest();
        $request->setLocale($this->resolveLocale());
        $request->setConversationId($conversationId);
        $request->setToken($token);

        return CheckoutForm::retrieve($request, $this->buildOptions());
    }

    private function buildBuyer(AgencyCategoryOrder $order, array $buyer, string $clientIp): Buyer
    {
        $model = new Buyer();
        $model->setId((string) ($order->agency_id ?: $order->id));
        $model->setName($buyer['name']);
        $model->setSurname($buyer['surname']);
        $model->setIdentityNumber($buyer['identity_number']);
        $model->setEmail($buyer['email']);
        $model->setGsmNumber($buyer['gsm']);
        $model->setRegistrationAddress($buyer['address']);
        $model->setIp($clientIp);
        $model->setCity($buyer['city']);
        $model->setCountry($buyer['country']);

        if (!empty($buyer['zip_code'])) {
            $model->setZipCode($buyer['zip_code']);
        }

        return $model;
    }

    private function buildAddress(array $buyer): Address
    {
        $address = new Address();
        $address->setContactName(trim($buyer['name'] . ' ' . $buyer['surname']));
        $address->setCity($buyer['city']);
        $address->setCountry($buyer['country']);
        $address->setAddress($buyer['address']);

        if (!empty($buyer['zip_code'])) {
            $address->setZipCode($buyer['zip_code']);
        }

        return $address;
    }

    /**
     * @param array<int, array{id:int|string, name:string, category:string, price:float|string}> $items
     * @return array<int, BasketItem>
     */
    private function buildBasketItems(array $items): array
    {
        return array_map(function (array $item) {
            $basketItem = new BasketItem();
            $basketItem->setId((string) $item['id']);
            $basketItem->setName($item['name']);
            $basketItem->setCategory1($item['category'] ?? 'Kategori Yetkisi');
            $basketItem->setItemType(BasketItemType::VIRTUAL);
            $basketItem->setPrice($this->formatAmount($item['price']));

            return $basketItem;
        }, $items);
    }

    private function buildOptions(): Options
    {
        $options = new Options();
        $options->setApiKey($this->config['api_key']);
        $options->setSecretKey($this->config['secret_key']);
        $options->setBaseUrl($this->config['base_uri']);

        return $options;
    }

    private function resolveLocale(): string
    {
        return ($this->config['locale'] ?? 'tr') === 'en' ? Locale::EN : Locale::TR;
    }

    private function formatAmount(float|string $amount): string
    {
        return number_format((float) $amount, 2, '.', '');
    }

    private function ensureConfigured(): void
    {
        if (!$this->isConfigured()) {
            throw new RuntimeException('iyzico API anahtarları yapılandırılmamış. Lütfen .env içinde IYZICO_API_KEY ve IYZICO_SECRET_KEY tanımlayın.');
        }
    }
}
