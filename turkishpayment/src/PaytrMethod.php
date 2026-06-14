<?php

namespace Azuriom\Plugin\TurkishPayment;

use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Azuriom\Plugin\TurkishPayment\Concerns\HasSecurityFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaytrMethod extends PaymentMethod
{
    use HasSecurityFeatures;

    protected $id = 'paytr';
    protected $name = 'PayTR';

    private const SUPPORTED_CURRENCIES = ['TL', 'TRY', 'EUR', 'USD', 'GBP', 'RUB'];

    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        // Para birimi doğrulaması
        $currency = strtoupper($currency);

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            logger()->error('[Shop] PayTR - Desteklenmeyen para birimi: '.$currency);
            return $this->errorResponse();
        }

        $payment = $this->createPayment($cart, $amount, $currency);

        $merchantId = $this->gateway->data['merchant-id'];
        $merchantKey = $this->gateway->data['merchant-key'];
        $merchantSalt = $this->gateway->data['merchant-salt'];
        $testMode = (int) ($this->gateway->data['test-mode'] ?? 0);

        $email = $payment->user->email;
        $userName = $payment->user->name;

        // PayTR requires amount in cents/kurus (kuruş)
        $paymentAmount = (int) round($amount * 100);
        $merchantOid = (string) $payment->id;

        // Sunucu IP'sini güvenli bir şekilde tespit et (IP Sahteciliği ve IP Fallback çözümü)
        $userIp = $this->getSafeServerIp();

        $merchantOkUrl = route('shop.payments.success', $this->id);
        $merchantFailUrl = route('shop.payments.failure', $this->id);

        // Sepet güvenliği (JSON bozulmasını ve XSS'i önlemek için filtreleme eklendi)
        $safeItemName = $this->sanitizeBasketItemName($this->getPurchaseDescription($payment));
        $basket = [
            [$safeItemName, number_format($amount, 2, '.', ''), 1],
        ];
        $userBasket = base64_encode(json_encode($basket, JSON_UNESCAPED_UNICODE));

        $noInstallment = 0;
        $maxInstallment = 12;
        $timeoutLimit = 30;
        $debugOn = $testMode ? 1 : 0;

        $hashStr = $merchantId.$userIp.$merchantOid.$email.$paymentAmount
            .$userBasket.$noInstallment.$maxInstallment.$currency.$testMode;
        $paytrToken = base64_encode(
            hash_hmac('sha256', $hashStr.$merchantSalt, $merchantKey, true)
        );

        try {
            $response = Http::timeout(30)->asForm()->post('https://www.paytr.com/odeme/api/get-token', [
                'merchant_id' => $merchantId,
                'user_ip' => $userIp,
                'merchant_oid' => $merchantOid,
                'email' => $email,
                'payment_amount' => $paymentAmount,
                'paytr_token' => $paytrToken,
                'user_basket' => $userBasket,
                'user_name' => $userName,
                'user_address' => $this->getValidAddress(), // Fraud koruması
                'user_phone' => $this->getValidPhone(), // Fraud koruması
                'merchant_ok_url' => $merchantOkUrl,
                'merchant_fail_url' => $merchantFailUrl,
                'timeout_limit' => $timeoutLimit,
                'no_installment' => $noInstallment,
                'max_installment' => $maxInstallment,
                'currency' => $currency,
                'test_mode' => $testMode,
                'debug_on' => $debugOn,
            ]);

            if ($response->failed()) {
                logger()->error('[Shop] PayTR - API request failed with HTTP status: '.$response->status());
                return $this->errorResponse();
            }

            $data = $response->json();

            if (($data['status'] ?? '') !== 'success') {
                logger()->error('[Shop] PayTR - Token request rejected: '.($data['reason'] ?? 'Unknown'));
                return $this->errorResponse();
            }

            $token = $data['token'];

            if (empty($token) || ! preg_match('/^[a-zA-Z0-9]+$/', $token)) {
                logger()->error('[Shop] PayTR - Invalid token format received');
                return $this->errorResponse();
            }

            return view('turkishpayment::iframe', [
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            logger()->error('[Shop] PayTR - Connection error: '.$e->getMessage());
            return $this->errorResponse();
        }
    }

    public function notification(Request $request, ?string $paymentId)
    {
        // Webhook IP doğrulaması: Eğer ayarlarda allowed-ips tanımlanmışsa kontrol et
        $allowedIps = []; // PayTR IP aralıkları buraya eklenebilir. Şimdilik boş = hepsine izin ver (HMAC yeterli)
        if (!$this->isValidIp($request->ip(), $allowedIps)) {
            logger()->warning('[Shop] PayTR - Webhook blocked from untrusted IP: ' . $request->ip());
            return response('Invalid IP', 403);
        }

        $merchantOid = $request->input('merchant_oid');
        $status = $request->input('status');
        $totalAmount = $request->input('total_amount');
        $hash = $request->input('hash');

        if (empty($merchantOid) || empty($status) || $totalAmount === null || empty($hash)) {
            logger()->warning('[Shop] PayTR - Callback missing required fields');
            return response('Invalid request', 400);
        }

        $merchantOid = $this->sanitizeLogInput($merchantOid);
        $status = $this->sanitizeLogInput($status);
        $totalAmount = $this->sanitizeLogInput((string) $totalAmount);

        $merchantKey = $this->gateway->data['merchant-key'];
        $merchantSalt = $this->gateway->data['merchant-salt'];

        $expectedHash = base64_encode(
            hash_hmac('sha256', $merchantOid.$merchantSalt.$status.$totalAmount, $merchantKey, true)
        );

        // Katı HMAC doğrulaması
        if (! hash_equals($expectedHash, $hash)) {
            logger()->warning('[Shop] PayTR - Callback signature mismatch for order: '.$merchantOid);
            return response('Invalid hash', 400);
        }

        // Replay Attack ve Double Spend önlemi: veritabanı satır kilidi (lockForUpdate)
        return DB::transaction(function () use ($request, $merchantOid, $status, $totalAmount) {
            $payment = Payment::lockForUpdate()->find($merchantOid);

            if ($payment === null) {
                logger()->warning('[Shop] PayTR - Payment not found: '.$merchantOid);
                return response('OK');
            }

            if ($payment->gateway_type !== $this->id) {
                logger()->warning('[Shop] PayTR - Gateway mismatch for #'.$merchantOid);
                return response('OK');
            }

            if ($payment->isCompleted()) {
                return response('OK');
            }

            if (! $payment->isPending()) {
                logger()->warning('[Shop] PayTR - Payment #'.$merchantOid.' is not pending, status: '.$payment->status);
                return response('OK');
            }

            // Tutar Manipülasyonu Koruması (Kuruş bazında kesin eşitlik)
            $expectedAmount = (int) round($payment->price * 100);
            $callbackAmount = (int) $totalAmount;

            if ($expectedAmount !== $callbackAmount) {
                logger()->warning('[Shop] PayTR - Amount mismatch for #'.$merchantOid
                    .': expected '.$expectedAmount.', received '.$callbackAmount);

                $this->invalidPayment($payment, $merchantOid, 'Amount mismatch');
                return response('OK');
            }

            if (! in_array(strtoupper($payment->currency), self::SUPPORTED_CURRENCIES, true)) {
                logger()->warning('[Shop] PayTR - Currency mismatch for #'.$merchantOid.': '.$payment->currency);
                $this->invalidPayment($payment, $merchantOid, 'Currency mismatch');
                return response('OK');
            }

            if ($status === 'success') {
                $this->processPayment($payment, $merchantOid);
                return response('OK');
            }

            $failedReasonCode = $this->sanitizeLogInput($request->input('failed_reason_code', 'unknown'));
            $failedReasonMsg = $this->sanitizeLogInput($request->input('failed_reason_msg', 'Payment failed'));

            logger()->info('[Shop] PayTR - Payment #'.$merchantOid.' failed: ['.$failedReasonCode.'] '.$failedReasonMsg);

            $this->invalidPayment($payment, $merchantOid, 'Payment failed: '.$failedReasonCode);

            return response('OK');
        });
    }

    public function view(): string
    {
        return 'turkishpayment::admin.paytr';
    }

    public function rules(): array
    {
        return [
            'merchant-id' => ['required', 'string', 'regex:/^[0-9]+$/'],
            'merchant-key' => ['required', 'string', 'min:8'],
            'merchant-salt' => ['required', 'string', 'min:8'],
            'test-mode' => ['nullable', 'boolean'],
            'fallback-ip' => ['nullable', 'ipv4'],
        ];
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/paytr.svg');
    }
}
