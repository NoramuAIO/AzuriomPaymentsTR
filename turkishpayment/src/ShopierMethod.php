<?php

namespace Azuriom\Plugin\TurkishPayment;

use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Azuriom\Plugin\TurkishPayment\Concerns\HasSecurityFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopierMethod extends PaymentMethod
{
    use HasSecurityFeatures;

    protected $id = 'shopier';
    protected $name = 'Shopier';

    private const SUPPORTED_CURRENCIES = ['TL', 'TRY', 'EUR', 'USD'];

    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        $currency = strtoupper($currency);

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            logger()->error('[Shop] Shopier - Desteklenmeyen para birimi: '.$currency);
            return $this->errorResponse();
        }

        $payment = $this->createPayment($cart, $amount, $currency);

        $apiKey = $this->gateway->data['api-key'];
        $apiSecret = $this->gateway->data['api-secret'];
        
        // Shopier API expects specific currency codes
        $currencyCode = match ($currency) {
            'TRY', 'TL' => 0,
            'USD' => 1,
            'EUR' => 2,
            default => 0,
        };

        $orderNo = (string) $payment->id;
        $orderTotal = number_format($amount, 2, '.', '');
        
        $callbackUrl = route('shop.payments.notification', $this->id);
        
        $randomNr = rand(100000, 999999);
        $signatureString = $randomNr . $orderNo . $orderTotal . $currencyCode;
        $signature = base64_encode(hash_hmac('sha256', $signatureString, $apiSecret, true));

        $inputs = [
            'API_key' => $apiKey,
            'website_index' => $this->gateway->data['website-index'] ?? 1,
            'platform_order_id' => $orderNo,
            'product_name' => $this->sanitizeBasketItemName($this->getPurchaseDescription($payment)),
            'product_type' => 0, // 0 = digital
            'buyer_name' => $payment->user->name,
            'buyer_surname' => 'Müşterisi',
            'buyer_email' => $payment->user->email,
            'buyer_account_age' => (int) $payment->user->created_at->diffInDays(),
            'buyer_id_nr' => $payment->user->id,
            'buyer_phone' => $this->getValidPhone(),
            'billing_address' => $this->getValidAddress(),
            'billing_city' => 'Istanbul',
            'billing_country' => 'Turkey',
            'billing_postcode' => '34000',
            'shipping_address' => $this->getValidAddress(),
            'shipping_city' => 'Istanbul',
            'shipping_country' => 'Turkey',
            'shipping_postcode' => '34000',
            'total_order_value' => $orderTotal,
            'currency' => $currencyCode,
            'platform' => 0,
            'is_in_frame' => 0,
            'current_language' => app()->getLocale() === 'tr' ? 0 : 1,
            'modul_version' => '1.0.4',
            'random_nr' => $randomNr,
            'signature' => $signature,
            'callback' => $callbackUrl,
        ];

        return view('turkishpayment::redirect', [
            'url' => 'https://www.shopier.com/ShowProduct/api_pay4.php',
            'inputs' => $inputs,
        ]);
    }

    public function notification(Request $request, ?string $paymentId)
    {
        $status = $request->input('status');
        $invoiceId = $request->input('invoice_id');
        $orderId = $request->input('platform_order_id');
        $randomNr = $request->input('random_nr');
        $signature = $request->input('signature');

        if (empty($status) || empty($orderId) || empty($signature)) {
            return to_route('shop.cart.index')->with('error', 'Geçersiz Shopier dönüşü.');
        }

        $apiSecret = $this->gateway->data['api-secret'];
        
        $expectedSignature = base64_encode(hash_hmac('sha256', $randomNr . $orderId, $apiSecret, true));

        if (! hash_equals($expectedSignature, $signature)) {
            logger()->warning('[Shop] Shopier - Callback signature mismatch for order: '.$orderId);
            return to_route('shop.cart.index')->with('error', 'Shopier imza hatası.');
        }

        if (strtolower($status) !== 'success') {
            logger()->warning('[Shop] Shopier - Payment failed for order: '.$orderId);
            return to_route('shop.cart.index')->with('error', 'Ödeme başarısız.');
        }

        return DB::transaction(function () use ($orderId, $invoiceId) {
            $payment = Payment::lockForUpdate()->find($orderId);

            if ($payment === null) {
                return to_route('shop.cart.index')->with('error', 'Ödeme kaydı bulunamadı.');
            }

            if ($payment->gateway_type !== $this->id) {
                return to_route('shop.cart.index');
            }

            if ($payment->isCompleted()) {
                return $this->success(request());
            }

            if (! $payment->isPending()) {
                return to_route('shop.cart.index');
            }

            $this->processPayment($payment, $invoiceId);

            return $this->success(request());
        });
    }

    public function view(): string
    {
        return 'turkishpayment::admin.shopier';
    }

    public function rules(): array
    {
        return [
            'api-key' => ['required', 'string'],
            'api-secret' => ['required', 'string'],
            'website-index' => ['nullable', 'numeric'],
        ];
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/shopier.svg');
    }
}
