<?php

namespace Azuriom\Plugin\TurkishPayment;

use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Azuriom\Plugin\TurkishPayment\Concerns\HasSecurityFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class PaparaMethod extends PaymentMethod
{
    use HasSecurityFeatures;

    protected $id = 'papara';
    protected $name = 'Papara';

    private const SUPPORTED_CURRENCIES = ['TRY', 'TL'];

    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        $currency = strtoupper($currency);

        if (! in_array($currency, self::SUPPORTED_CURRENCIES, true)) {
            logger()->error('[Shop] Papara - Desteklenmeyen para birimi: '.$currency);
            return $this->errorResponse();
        }

        $payment = $this->createPayment($cart, $amount, $currency);

        $apiKey = $this->gateway->data['api-key'];
        $testMode = (bool) ($this->gateway->data['test-mode'] ?? false);
        
        $baseUrl = $testMode ? 'https://merchant-api.test.papara.com' : 'https://merchant-api.papara.com';

        try {
            $response = Http::timeout(30)->withHeaders([
                'apikey' => $apiKey,
                'Content-Type' => 'application/json'
            ])->post($baseUrl . '/payments', [
                'amount' => round($amount, 2),
                'referenceId' => (string) $payment->id,
                'orderDescription' => $this->sanitizeBasketItemName($this->getPurchaseDescription($payment)),
                'notificationUrl' => route('shop.payments.notification', $this->id),
                'failUrl' => route('shop.payments.failure', $this->id),
                'redirectUrl' => route('shop.payments.success', $this->id),
            ]);

            if ($response->failed()) {
                logger()->error('[Shop] Papara - API request failed with status: '.$response->status());
                return $this->errorResponse();
            }

            $data = $response->json();

            if (!isset($data['succeeded']) || !$data['succeeded']) {
                logger()->error('[Shop] Papara - Token request rejected: '.($data['error']['message'] ?? 'Unknown'));
                return $this->errorResponse();
            }

            return redirect()->away($data['data']['paymentUrl']);

        } catch (\Exception $e) {
            logger()->error('[Shop] Papara - Connection error: '.$e->getMessage());
            return $this->errorResponse();
        }
    }

    public function notification(Request $request, ?string $paymentId)
    {
        $status = $request->input('status');
        $referenceId = $request->input('referenceId');
        $id = $request->input('id'); // Papara payment id
        $amount = $request->input('amount');

        if (empty($referenceId) || empty($status)) {
            logger()->warning('[Shop] Papara - Callback missing required fields');
            return response()->json(['error' => 'Invalid request'], 400);
        }

        $referenceId = $this->sanitizeLogInput($referenceId);
        
        // Güvenlik: Papara API'sine istek atıp ödemeyi sorguluyoruz (Server-side validation)
        $apiKey = $this->gateway->data['api-key'];
        $testMode = (bool) ($this->gateway->data['test-mode'] ?? false);
        $baseUrl = $testMode ? 'https://merchant-api.test.papara.com' : 'https://merchant-api.papara.com';

        try {
            $verifyResponse = Http::timeout(30)->withHeaders([
                'apikey' => $apiKey,
            ])->get($baseUrl . '/payments?id=' . urlencode($id));

            if ($verifyResponse->failed() || !$verifyResponse->json('succeeded')) {
                logger()->warning('[Shop] Papara - Payment verification failed for order: '.$referenceId);
                return response()->json(['error' => 'Verification failed'], 400);
            }

            $verifyData = $verifyResponse->json('data');

            if ($verifyData['status'] !== 1) { // 1 = Succeeded
                logger()->warning('[Shop] Papara - Payment not succeeded in API for order: '.$referenceId);
                return response('OK'); // It's not an error but it's not completed
            }

            $apiAmount = (float) $verifyData['amount'];

            return DB::transaction(function () use ($referenceId, $apiAmount, $id) {
                $payment = Payment::lockForUpdate()->find($referenceId);

                if ($payment === null) {
                    logger()->warning('[Shop] Papara - Payment not found: '.$referenceId);
                    return response('OK');
                }

                if ($payment->gateway_type !== $this->id || $payment->isCompleted()) {
                    return response('OK');
                }

                if (! $payment->isPending()) {
                    return response('OK');
                }

                if (! $this->verifyAmount($payment, $referenceId, $apiAmount)) {
                    $this->invalidPayment($payment, $referenceId, 'Amount mismatch');
                    return response('OK');
                }

                $this->processPayment($payment, $id);

                return response('OK');
            });

        } catch (\Exception $e) {
            logger()->error('[Shop] Papara - Validation error: '.$e->getMessage());
            return response()->json(['error' => 'Validation error'], 500);
        }
    }

    public function view(): string
    {
        return 'turkishpayment::admin.papara';
    }

    public function rules(): array
    {
        return [
            'api-key' => ['required', 'string'],
            'test-mode' => ['nullable', 'boolean'],
        ];
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/papara.svg');
    }
}
