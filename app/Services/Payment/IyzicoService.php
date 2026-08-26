<?php

namespace App\Services\Payment;

use App\Models\AgencyCategoryOrder;
use Iyzipay\Model\Address;
use Iyzipay\Model\BasketItem;
use Iyzipay\Model\BasketItemType;
use Iyzipay\Model\Buyer;
use Iyzipay\Model\Card;
use Iyzipay\Model\CheckoutForm;
use Iyzipay\Model\CheckoutFormInitialize;
use Iyzipay\Model\Locale;
use Iyzipay\Model\Payment;
use Iyzipay\Model\PaymentCard;
use Iyzipay\Model\PaymentChannel;
use Iyzipay\Model\Status;
use Iyzipay\Options;
use Iyzipay\Request\CreateCheckoutFormInitializeRequest;
use Iyzipay\Request\CreatePaymentRequest;
use Iyzipay\Request\DeleteCardRequest;
use Iyzipay\Request\RetrieveCheckoutFormRequest;
use Iyzipay\Request\RetrievePaymentRequest;
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
        string $clientIp,
        ?string $cardUserKey = null
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

        // Kayıtlı kartı olan acentada iyzico formu saklı kartları da listeler;
        // yeni kart saklama onayını formun kendi "kartımı sakla" kutusu alır.
        if ($cardUserKey !== null && $cardUserKey !== '') {
            $request->setCardUserKey($cardUserKey);
        }

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

    /**
     * Saklı karttan 3DS'siz tahsilat (otomatik aylık yenileme). iyzico
     * hesabında kart saklama + non-3DS çekim izni AÇIK olmalı; yoksa iyzico
     * hata döner ve çağıran taraf siparişi failed işaretler.
     *
     * @param array<int, array{id:int|string, name:string, category:string, price:float|string}> $basketItems
     * @param array<string, mixed> $buyer Son ödemeli siparişin buyer_snapshot'ı
     */
    public function chargeStoredCard(
        AgencyCategoryOrder $order,
        array $basketItems,
        array $buyer,
        string $cardUserKey,
        string $cardToken,
        string $clientIp = '127.0.0.1'
    ): Payment {
        $this->ensureConfigured();

        $request = new CreatePaymentRequest();
        $request->setLocale($this->resolveLocale());
        $request->setConversationId((string) $order->id);
        $request->setPrice($this->formatAmount($order->subtotal));
        $request->setPaidPrice($this->formatAmount($order->subtotal));
        $request->setCurrency($order->currency ?: ($this->config['currency'] ?? 'TRY'));
        $request->setInstallment(1);
        $request->setBasketId('KYM-' . $order->id);
        $request->setPaymentChannel(PaymentChannel::WEB);
        $request->setPaymentGroup('SUBSCRIPTION');

        $paymentCard = new PaymentCard();
        $paymentCard->setCardUserKey($cardUserKey);
        $paymentCard->setCardToken($cardToken);
        $request->setPaymentCard($paymentCard);

        $request->setBuyer($this->buildBuyer($order, $buyer, $clientIp));

        $address = $this->buildAddress($buyer);
        $request->setShippingAddress($address);
        $request->setBillingAddress($address);

        $request->setBasketItems($this->buildBasketItems($basketItems));

        return Payment::create($request, $this->buildOptions());
    }

    /**
     * Daha önce başlatılmış bir çekimi iyzico'dan geri sorgular (MUTABAKAT):
     * conversationId = bizim sipariş id'miz. Saklı-kart çekiminde checkout
     * token'ı olmadığından belirsiz sonuçlar (zaman aşımı, crash) ancak bu
     * sorguyla çözülür. Çekim iyzico'ya hiç ulaşmadıysa hata statüsü döner.
     */
    public function retrievePayment(string $conversationId): Payment
    {
        $this->ensureConfigured();

        $request = new RetrievePaymentRequest();
        $request->setLocale($this->resolveLocale());
        $request->setConversationId($conversationId);
        $request->setPaymentConversationId($conversationId);

        return Payment::retrieve($request, $this->buildOptions());
    }

    /**
     * Saklı kartı iyzico tarafından siler. Uzak silme başarısız olsa bile
     * çağıran taraf yerel kaydı silebilir (token'sız kayıt işe yaramaz).
     */
    public function deleteStoredCard(string $cardUserKey, string $cardToken): void
    {
        $this->ensureConfigured();

        $request = new DeleteCardRequest();
        $request->setLocale($this->resolveLocale());
        $request->setCardUserKey($cardUserKey);
        $request->setCardToken($cardToken);

        $response = Card::delete($request, $this->buildOptions());

        if ($response->getStatus() !== Status::SUCCESS) {
            throw new RuntimeException(
                $response->getErrorMessage() ?: 'iyzico kayıtlı kart silinemedi.'
            );
        }
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
