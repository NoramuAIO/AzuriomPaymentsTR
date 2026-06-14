<?php

namespace Azuriom\Plugin\TurkishPayment;

use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Azuriom\Plugin\TurkishPayment\Concerns\HasSecurityFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaywantMethod extends PaymentMethod
{
    use HasSecurityFeatures;

    protected $id = 'paywant';
    protected $name = 'Paywant';

    private const SUPPORTED_CURRENCIES = ['TL', 'TRY', 'EUR', 'USD'];

    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        $currency = strtoupper($currency);

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            logger()->error('[Shop] Paywant - Desteklenmeyen para birimi: '.$currency);
            return $this->errorResponse();
        }

        $payment = $this->createPayment($cart, $amount, $currency);

        $apiKey = $this->gateway->data['api-key'];
        $apiSecret = $this->gateway->data['api-secret'];
        $returnData = (string) $payment->id;
        
        $userEmail = $payment->user->email;
        $userName = $payment->user->name;
        $paymentAmount = (int) round($amount * 100);

        $userIp = $this->getSafeServerIp();

        $hashStr = $returnData . $userEmail . $paymentAmount . $userIp . $apiKey;
        $hash = base64_encode(hash_hmac('sha256', $hashStr, $apiSecret, true));

        try {
            $response = Http::timeout(30)->asForm()->post('https://api.paywant.com/gateway/', [
                'apiKey' => $apiKey,
                'hash' => $hash,
                'returnData' => $returnData,
                'userEmail' => $userEmail,
                'userIPAddress' => $userIp,
                'userID' => $payment->user->id,
                'proApi' => 1,
                'productData' => [
                    'name' => $this->sanitizeBasketItemName($this->getPurchaseDescription($payment)),
                    'amount' => $paymentAmount,
                    'extraData' => '',
                    'paymentChannel' => '1,2,3',
                    'commissionType' => 1
                ],
                'buyerName' => $userName,
                'buyerSurname' => 'Müşterisi',
                'buyerPhone' => $this->getValidPhone(),
                'buyerCity' => 'Istanbul',
                'buyerBillingCountry' => 'Turkey',
                'buyerBillingAddress' => $this->getValidAddress(),
                'buyerZip' => '34000'
            ]);

            if ($response->failed()) {
                logger()->error('[Shop] Paywant - API request failed with status: '.$response->status());
                return $this->errorResponse();
            }

            $data = $response->json();

            if (($data['status'] ?? '') === 'error' || empty($data['Message'])) {
                logger()->error('[Shop] Paywant - Token request rejected: '.($data['Message'] ?? 'Unknown'));
                return $this->errorResponse();
            }

            return redirect()->away($data['Message']);
            
        } catch (\Exception $e) {
            logger()->error('[Shop] Paywant - Connection error: '.$e->getMessage());
            return $this->errorResponse();
        }
    }

    public function notification(Request $request, ?string $paymentId)
    {
        $status = $request->input('Status');
        $orderId = $request->input('ReturnData');
        $hash = $request->input('Hash');
        $paymentAmount = $request->input('PaymentAmount');

        if (empty($status) || empty($orderId) || empty($hash)) {
            logger()->warning('[Shop] Paywant - Callback missing required fields');
            return response('Invalid request', 400);
        }

        $orderId = $this->sanitizeLogInput($orderId);
        $status = $this->sanitizeLogInput($status);
        $paymentAmount = $this->sanitizeLogInput((string) $paymentAmount);

        $apiKey = $this->gateway->data['api-key'];
        $apiSecret = $this->gateway->data['api-secret'];

        $expectedHash = base64_encode(hash_hmac('sha256', $orderId . $status . $paymentAmount . $apiKey, $apiSecret, true));

        if (! hash_equals($expectedHash, $hash)) {
            logger()->warning('[Shop] Paywant - Callback signature mismatch for order: '.$orderId);
            return response('Invalid hash', 400);
        }

        return DB::transaction(function () use ($orderId, $status) {
            $payment = Payment::lockForUpdate()->find($orderId);

            if ($payment === null) {
                logger()->warning('[Shop] Paywant - Payment not found: '.$orderId);
                return response('OK');
            }

            if ($payment->gateway_type !== $this->id) {
                logger()->warning('[Shop] Paywant - Gateway mismatch for #'.$orderId);
                return response('OK');
            }

            if ($payment->isCompleted()) {
                return response('OK');
            }

            if (! $payment->isPending()) {
                logger()->warning('[Shop] Paywant - Payment #'.$orderId.' is not pending, status: '.$payment->status);
                return response('OK');
            }

            if ($status == 100) {
                // Tutar Manipülasyonu Koruması
                $expectedAmount = (int) round($payment->price * 100);
                if ($expectedAmount !== (int) $paymentAmount) {
                    logger()->warning('[Shop] Paywant - Amount mismatch for #'.$orderId.': expected '.$expectedAmount.', received '.$paymentAmount);
                    $this->invalidPayment($payment, $orderId, 'Amount mismatch');
                    return response('OK');
                }
                
                $this->processPayment($payment, $orderId);
                return response('OK');
            }

            logger()->info('[Shop] Paywant - Payment #'.$orderId.' failed with status: '.$status);
            $this->invalidPayment($payment, $orderId, 'Payment failed');

            return response('OK');
        });
    }

    public function view(): string
    {
        return 'turkishpayment::admin.paywant';
    }

    public function rules(): array
    {
        return [
            'api-key' => ['required', 'string'],
            'api-secret' => ['required', 'string'],
        ];
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/paywant.svg');
    }
}
