<?php

namespace Azuriom\Plugin\TurkishPayment;

use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Azuriom\Plugin\TurkishPayment\Concerns\HasSecurityFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GarantiMethod extends PaymentMethod
{
    use HasSecurityFeatures;

    protected $id = 'garanti';
    protected $name = 'Garanti BBVA';

    private const SUPPORTED_CURRENCIES = ['TRY', 'TL', 'USD', 'EUR', 'GBP'];

    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        $currency = strtoupper($currency);
        if ($currency === 'TL') {
            $currency = 'TRY';
        }

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            logger()->error('[Shop] Garanti - Desteklenmeyen para birimi: '.$currency);
            return $this->errorResponse();
        }

        $payment = $this->createPayment($cart, $amount, $currency);

        $merchantId = $this->gateway->data['merchant-id'];
        $terminalId = $this->gateway->data['terminal-id'];
        $storeKey = $this->gateway->data['store-key'];
        
        $oid = (string) $payment->id;
        $amountKurus = (int) round($amount * 100);
        $successUrl = route('shop.payments.notification', $this->id);
        $errorUrl = route('shop.payments.notification', $this->id);

        $hashStr = $terminalId . $oid . $amountKurus . $successUrl . $errorUrl . 'sales' . '0' . $storeKey;
        $securityData = strtoupper(hash('sha512', $hashStr));
        
        $hashData = strtoupper(hash('sha512', $merchantId . $merchantId . $terminalId . $securityData));

        $inputs = [
            'secure3dsecuritylevel' => '3D',
            'mode' => 'PROD',
            'apiversion' => 'v0.01',
            'terminalprovuserid' => 'PROVAUT',
            'terminaluserid' => 'PROVAUT',
            'terminalmerchantid' => $merchantId,
            'txntype' => 'sales',
            'txnamount' => $amountKurus,
            'txncurrencycode' => $currency === 'TRY' ? '949' : ($currency === 'USD' ? '840' : '978'),
            'txninstallmentcount' => '',
            'orderid' => $oid,
            'terminalid' => $terminalId,
            'successurl' => $successUrl,
            'errorurl' => $errorUrl,
            'customeremailaddress' => $payment->user->email,
            'customeripaddress' => $this->getSafeServerIp(),
            'secure3dhash' => $hashData,
        ];

        $cardFields = [
            'pan' => 'cardnumber',
            'month' => 'cardexpiredatemonth',
            'year' => 'cardexpiredateyear',
            'cvv' => 'cardcvv2'
        ];

        return view('turkishpayment::card-form', [
            'url' => 'https://sanalposprov.garanti.com.tr/servlet/gt3dengine',
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
        $mdStatus = $request->input('mdstatus');
        $oid = $request->input('orderid');
        $hash = $request->input('secure3dhash');
        $txnAmount = $request->input('txnamount');

        if (empty($oid)) {
            logger()->warning('[Shop] Garanti - Callback missing required fields');
            return to_route('shop.cart.index')->with('error', 'Geçersiz ödeme dönüşü.');
        }

        $oid = $this->sanitizeLogInput($oid);

        // Hash doğrulaması
        if (! empty($hash)) {
            $storeKey = $this->gateway->data['store-key'];
            $terminalId = $this->gateway->data['terminal-id'];
            $merchantId = $this->gateway->data['merchant-id'];
            $successUrl = route('shop.payments.notification', $this->id);
            $errorUrl = route('shop.payments.notification', $this->id);

            $hashStr = $terminalId . $oid . $txnAmount . $successUrl . $errorUrl . 'sales' . '0' . $storeKey;
            $securityData = strtoupper(hash('sha512', $hashStr));
            $expectedHash = strtoupper(hash('sha512', $merchantId . $merchantId . $terminalId . $securityData));

            if (! hash_equals($expectedHash, $hash)) {
                logger()->warning('[Shop] Garanti - Hash mismatch for order: '.$oid);
                return to_route('shop.cart.index')->with('error', 'İmza doğrulaması başarısız.');
            }
        }

        if ($mdStatus != 1 && $mdStatus != 2 && $mdStatus != 3 && $mdStatus != 4) {
            $errMsg = $this->sanitizeLogInput($request->input('mderrorinfo', '3D Secure doğrulama başarısız'));
            logger()->warning('[Shop] Garanti - 3D Secure failed for order: '.$oid.' ('.$errMsg.')');
            return to_route('shop.cart.index')->with('error', '3D Secure hatası: ' . $errMsg);
        }

        return DB::transaction(function () use ($oid, $txnAmount) {
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
            if ($txnAmount !== null) {
                $expectedAmount = (int) round($payment->price * 100);
                $callbackAmount = (int) $txnAmount;
                if ($expectedAmount !== $callbackAmount) {
                    logger()->warning('[Shop] Garanti - Amount mismatch for #'.$oid.': expected '.$expectedAmount.', received '.$callbackAmount);
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
        return 'turkishpayment::admin.garanti';
    }

    public function rules(): array
    {
        return [
            'merchant-id' => ['required', 'string'],
            'terminal-id' => ['required', 'string'],
            'store-key' => ['required', 'string'],
        ];
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/garanti.svg');
    }
}
