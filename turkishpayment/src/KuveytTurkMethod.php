<?php

namespace Azuriom\Plugin\TurkishPayment;

use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Azuriom\Plugin\TurkishPayment\Concerns\HasSecurityFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class KuveytTurkMethod extends PaymentMethod
{
    use HasSecurityFeatures;

    protected $id = 'kuveytturk';
    protected $name = 'KuveytTürk';

    private const SUPPORTED_CURRENCIES = ['TRY', 'TL', 'USD', 'EUR'];

    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        $currency = strtoupper($currency);
        if ($currency === 'TL') {
            $currency = 'TRY';
        }

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            logger()->error('[Shop] KuveytTürk - Desteklenmeyen para birimi: '.$currency);
            return $this->errorResponse();
        }

        $payment = $this->createPayment($cart, $amount, $currency);

        $merchantId = $this->gateway->data['merchant-id'];
        $customerId = $this->gateway->data['customer-id'];
        $username = $this->gateway->data['username'];
        $password = $this->gateway->data['password'];
        $testMode = (bool) ($this->gateway->data['test-mode'] ?? false);

        $oid = (string) $payment->id;
        // KuveytTurk expects amount in cents/kurus without dot, e.g. 10.50 -> 1050
        // Wait, for 3DModelPayGate, it usually expects 1050 for amount in Hash, but format can vary.
        // According to API, Amount should be in minor units (kurus)
        $amountKurus = (int) round($amount * 100);
        
        $okUrl = route('shop.payments.notification', $this->id);
        $failUrl = route('shop.payments.notification', $this->id);

        $hashedPassword = base64_encode(sha1($password, true));
        $hashStr = $merchantId . $oid . $amountKurus . $okUrl . $failUrl . $username . $hashedPassword;
        $hashData = base64_encode(sha1($hashStr, true));

        $currencyCode = match($currency) {
            'TRY' => '0949',
            'USD' => '0840',
            'EUR' => '0978',
            default => '0949'
        };

        $inputs = [
            'MerchantOrderId' => $oid,
            'Amount' => $amountKurus,
            'CustomerId' => $customerId,
            'MerchantId' => $merchantId,
            'UserName' => $username,
            'HashedPassword' => $hashedPassword,
            'HashData' => $hashData,
            'OkUrl' => $okUrl,
            'FailUrl' => $failUrl,
            'TransactionType' => 'Sale',
            'InstallmentCount' => '0',
            'CurrencyCode' => $currencyCode,
        ];

        $cardFields = [
            'pan' => 'CardNumber',
            'month' => 'CardExpireDateMonth',
            'year' => 'CardExpireDateYear',
            'cvv' => 'CardCVV2'
        ];

        return view('turkishpayment::card-form', [
            'url' => 'https://boa.kuveytturk.com.tr/sanalposservice/Home/ThreeDModelPayGate',
            'inputs' => $inputs,
            'payment' => $payment,
            'gatewayName' => $this->name,
            'amount' => number_format($amount, 2, '.', ''),
            'currency' => $currency,
            'cardFields' => $cardFields
        ]);
    }

    public function notification(Request $request, ?string $paymentId)
    {
        $responseCode = $request->input('ResponseCode');
        $oid = $request->input('MerchantOrderId');
        $hashData = $request->input('HashData');
        $amount = $request->input('Amount');
        
        if (empty($oid)) {
            logger()->warning('[Shop] KuveytTürk - Callback missing required fields');
            return to_route('shop.cart.index')->with('error', 'Geçersiz ödeme dönüşü.');
        }

        $oid = $this->sanitizeLogInput($oid);

        // Hash doğrulaması
        if (! empty($hashData)) {
            $merchantId = $this->gateway->data['merchant-id'];
            $password = $this->gateway->data['password'];
            $username = $this->gateway->data['username'];
            $okUrl = route('shop.payments.notification', $this->id);
            $failUrl = route('shop.payments.notification', $this->id);

            $hashedPassword = base64_encode(sha1($password, true));
            $expectedHashStr = $merchantId . $oid . $amount . $okUrl . $failUrl . $username . $hashedPassword;
            $expectedHash = base64_encode(sha1($expectedHashStr, true));

            if (! hash_equals($expectedHash, $hashData)) {
                logger()->warning('[Shop] KuveytTürk - Hash mismatch for order: '.$oid);
                return to_route('shop.cart.index')->with('error', 'İmza doğrulaması başarısız.');
            }
        }

        if ($responseCode !== '00') {
            $errMsg = $this->sanitizeLogInput($request->input('ResponseMessage', 'Ödeme onaylanmadı'));
            logger()->warning('[Shop] KuveytTürk - Payment declined for order: '.$oid.' ('.$errMsg.')');
            return to_route('shop.cart.index')->with('error', 'Ödeme başarısız: ' . $errMsg);
        }

        return DB::transaction(function () use ($oid, $amount) {
            $payment = Payment::lockForUpdate()->find($oid);

            if ($payment === null || $payment->gateway_type !== $this->id) {
                return to_route('shop.cart.index');
            }

            if ($payment->isCompleted()) {
                return $this->success(request());
            }

            if (! $payment->isPending()) {
                return to_route('shop.cart.index');
            }

            // Tutar Manipülasyonu Koruması (kuruş bazlı)
            if ($amount !== null) {
                $expectedAmount = (int) round($payment->price * 100);
                $callbackAmount = (int) $amount;
                if ($expectedAmount !== $callbackAmount) {
                    logger()->warning('[Shop] KuveytTürk - Amount mismatch for #'.$oid.': expected '.$expectedAmount.', received '.$callbackAmount);
                    $this->invalidPayment($payment, $oid, 'Amount mismatch');
                    return to_route('shop.cart.index')->with('error', 'Tutar eşleşmiyor.');
                }
            }

            $this->processPayment($payment, $oid);

            return $this->success(request());
        });
    }

    public function view(): string
    {
        return 'turkishpayment::admin.kuveytturk';
    }

    public function rules(): array
    {
        return [
            'merchant-id' => ['required', 'string'],
            'customer-id' => ['required', 'string'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'test-mode' => ['nullable', 'boolean'],
        ];
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/kuveytturk.svg');
    }
}
