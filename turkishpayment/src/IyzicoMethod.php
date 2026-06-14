<?php

namespace Azuriom\Plugin\TurkishPayment;

use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Azuriom\Plugin\TurkishPayment\Concerns\HasSecurityFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class IyzicoMethod extends PaymentMethod
{
    use HasSecurityFeatures;

    protected $id = 'iyzico';
    protected $name = 'Iyzico';

    private const SUPPORTED_CURRENCIES = ['TRY', 'EUR', 'USD', 'GBP', 'IRR', 'NOK', 'RUB', 'CHF'];

    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        $currency = strtoupper($currency);
        // Iyzico expects TRY instead of TL
        if ($currency === 'TL') {
            $currency = 'TRY';
        }

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            logger()->error('[Shop] Iyzico - Desteklenmeyen para birimi: '.$currency);
            return $this->errorResponse();
        }

        $payment = $this->createPayment($cart, $amount, $currency);

        $apiKey = $this->gateway->data['api-key'];
        $secretKey = $this->gateway->data['secret-key'];
        $testMode = (bool) ($this->gateway->data['test-mode'] ?? false);
        
        $baseUrl = $testMode ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com';
        $uriPath = '/payment/checkoutform/initialize/auth/ecom';
        
        $userIp = $this->getSafeServerIp();

        $basketItems = [];
        $totalPrice = 0;
        foreach ($cart->content() as $item) {
            $itemPrice = $item->price() * $item->quantity;
            $totalPrice += $itemPrice;
            $basketItems[] = [
                'id' => (string) $item->buyable()->id,
                'name' => $this->sanitizeBasketItemName($item->name()),
                'category1' => 'Digital',
                'itemType' => 'VIRTUAL',
                'price' => number_format($itemPrice, 2, '.', '')
            ];
        }

        // Handle exact amount for price and paidPrice
        $formattedAmount = number_format($amount, 2, '.', '');

        $payload = [
            'locale' => app()->getLocale() === 'tr' ? 'tr' : 'en',
            'conversationId' => (string) $payment->id,
            'price' => $formattedAmount,
            'paidPrice' => $formattedAmount,
            'currency' => $currency,
            'basketId' => (string) $payment->id,
            'paymentGroup' => 'PRODUCT',
            'callbackUrl' => route('shop.payments.notification', $this->id),
            'buyer' => [
                'id' => (string) $payment->user->id,
                'name' => $payment->user->name,
                'surname' => 'Müşterisi',
                'gsmNumber' => $this->getValidPhone(),
                'email' => $payment->user->email,
                'identityNumber' => '74300864791',
                'registrationAddress' => $this->getValidAddress(),
                'ip' => $userIp,
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'zipCode' => '34000'
            ],
            'shippingAddress' => [
                'contactName' => $payment->user->name,
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'address' => $this->getValidAddress(),
                'zipCode' => '34000'
            ],
            'billingAddress' => [
                'contactName' => $payment->user->name,
                'city' => 'Istanbul',
                'country' => 'Turkey',
                'address' => $this->getValidAddress(),
                'zipCode' => '34000'
            ],
            'basketItems' => $basketItems
        ];

        $requestString = json_encode($payload);
        $randomString = Str::random(16);
        $hashData = $randomString . $uriPath . $requestString;
        $signature = base64_encode(hash_hmac('sha256', $hashData, $secretKey, true));
        $authorization = 'IYZWSv2 ' . $apiKey . ':' . $signature;

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => $authorization,
                'x-iyzi-rnd' => $randomString,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . $uriPath, $payload);

            if ($response->failed()) {
                logger()->error('[Shop] Iyzico - API request failed with status: '.$response->status());
                return $this->errorResponse();
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                logger()->error('[Shop] Iyzico - Token request rejected: '.($data['errorMessage'] ?? 'Unknown'));
                return $this->errorResponse();
            }

            return view('turkishpayment::iframe', [
                'token' => $data['token'],
                'url' => $data['paymentPageUrl'] . '&iframe=true'
            ]);

        } catch (\Exception $e) {
            logger()->error('[Shop] Iyzico - Connection error: '.$e->getMessage());
            return $this->errorResponse();
        }
    }

    public function notification(Request $request, ?string $paymentId)
    {
        $token = $request->input('token');

        if (empty($token)) {
            return to_route('shop.cart.index')->with('error', 'Iyzico token eksik.');
        }

        $apiKey = $this->gateway->data['api-key'];
        $secretKey = $this->gateway->data['secret-key'];
        $testMode = (bool) ($this->gateway->data['test-mode'] ?? false);
        
        $baseUrl = $testMode ? 'https://sandbox-api.iyzipay.com' : 'https://api.iyzipay.com';
        $uriPath = '/payment/checkoutform/auth/ecom/detail';

        $payload = [
            'locale' => 'tr',
            'token' => $token
        ];

        $requestString = json_encode($payload);
        $randomString = Str::random(16);
        $hashData = $randomString . $uriPath . $requestString;
        $signature = base64_encode(hash_hmac('sha256', $hashData, $secretKey, true));
        $authorization = 'IYZWSv2 ' . $apiKey . ':' . $signature;

        try {
            $response = Http::timeout(30)->withHeaders([
                'Authorization' => $authorization,
                'x-iyzi-rnd' => $randomString,
                'Content-Type' => 'application/json',
            ])->post($baseUrl . $uriPath, $payload);

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success' || ($data['paymentStatus'] ?? '') !== 'SUCCESS') {
                logger()->warning('[Shop] Iyzico - Payment failed for token: '.$token);
                return to_route('shop.cart.index')->with('error', 'Ödeme başarısız.');
            }

            $orderId = $data['basketId'];
            $paymentAmount = (float) $data['paidPrice'];

            return DB::transaction(function () use ($orderId, $paymentAmount, $token) {
                $payment = Payment::lockForUpdate()->find($orderId);

                if ($payment === null || $payment->gateway_type !== $this->id) {
                    return to_route('shop.cart.index');
                }

                if ($payment->isCompleted()) {
                    return $this->success(request());
                }

                if (! $payment->isPending()) {
                    return to_route('shop.cart.index');
                }

                if (! $this->verifyAmount($payment, $orderId, $paymentAmount)) {
                    $this->invalidPayment($payment, $orderId, 'Amount mismatch');
                    return to_route('shop.cart.index')->with('error', 'Tutar eşleşmiyor.');
                }

                $this->processPayment($payment, $token);

                return $this->success(request());
            });

        } catch (\Exception $e) {
            logger()->error('[Shop] Iyzico - Callback validation error: '.$e->getMessage());
            return to_route('shop.cart.index')->with('error', 'Ödeme doğrulanamadı.');
        }
    }

    public function view(): string
    {
        return 'turkishpayment::admin.iyzico';
    }

    public function rules(): array
    {
        return [
            'api-key' => ['required', 'string'],
            'secret-key' => ['required', 'string'],
            'test-mode' => ['nullable', 'boolean'],
        ];
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/iyzico.svg');
    }
}
