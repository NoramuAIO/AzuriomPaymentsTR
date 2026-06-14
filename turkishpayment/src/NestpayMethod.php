<?php

namespace Azuriom\Plugin\TurkishPayment;

use Azuriom\Plugin\Shop\Cart\Cart;
use Azuriom\Plugin\Shop\Models\Payment;
use Azuriom\Plugin\Shop\Payment\PaymentMethod;
use Azuriom\Plugin\TurkishPayment\Concerns\HasSecurityFeatures;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

abstract class NestpayMethod extends PaymentMethod
{
    use HasSecurityFeatures;

    protected const SUPPORTED_CURRENCIES = ['TRY', 'TL', 'USD', 'EUR', 'GBP'];

    abstract protected function getEndpointUrl(bool $testMode): string;

    public function startPayment(Cart $cart, float $amount, string $currency)
    {
        $currency = strtoupper($currency);
        if ($currency === 'TL') {
            $currency = 'TRY';
        }

        if (! in_array($currency, static::SUPPORTED_CURRENCIES, true)) {
            logger()->error('[Shop] '.$this->name.' - Desteklenmeyen para birimi: '.$currency);
            return $this->errorResponse();
        }

        $payment = $this->createPayment($cart, $amount, $currency);

        $clientId = $this->gateway->data['client-id'];
        $storeKey = $this->gateway->data['store-key'];
        $testMode = (bool) ($this->gateway->data['test-mode'] ?? false);

        $oid = (string) $payment->id;
        $amountFormatted = number_format($amount, 2, '.', '');
        
        $okUrl = route('shop.payments.notification', $this->id);
        $failUrl = route('shop.payments.notification', $this->id);

        // Tahmin edilmesi zor rastgele değer (microtime yerine cryptographic random)
        $rnd = bin2hex(random_bytes(16));
        $storeType = "3d";
        $hashStr = $clientId . $oid . $amountFormatted . $okUrl . $failUrl . $rnd . $storeKey;
        $hash = base64_encode(pack('H*', hash('sha512', $hashStr)));

        $inputs = [
            'clientid' => $clientId,
            'storetype' => $storeType,
            'hash' => $hash,
            'islemtipi' => 'Auth',
            'amount' => $amountFormatted,
            'currency' => $currency === 'TRY' ? '949' : ($currency === 'USD' ? '840' : '978'),
            'oid' => $oid,
            'okUrl' => $okUrl,
            'failUrl' => $failUrl,
            'rnd' => $rnd,
            'lang' => app()->getLocale() === 'tr' ? 'tr' : 'en',
            'Email' => $payment->user->email,
            'FirmaAdi' => site_name(),
        ];

        return view('turkishpayment::card-form', [
            'url' => $this->getEndpointUrl($testMode),
            'inputs' => $inputs,
            'payment' => $payment,
            'gatewayName' => $this->name,
            'amount' => $amountFormatted,
            'currency' => $currency
        ]);
    }

    public function notification(Request $request, ?string $paymentId)
    {
        $mdStatus = $request->input('mdStatus');
        $oid = $request->input('oid');
        $hashParams = $request->input('HASHPARAMS');
        $hash = $request->input('HASH');
        $response = $request->input('Response');

        // Zorunlu alanlar kontrolü
        if (empty($oid) || empty($hash) || empty($hashParams)) {
            logger()->warning('[Shop] '.$this->name.' - Callback missing required fields');
            return to_route('shop.cart.index')->with('error', 'Geçersiz ödeme dönüşü.');
        }

        // Log sanitizasyonu
        $safeOid = $this->sanitizeLogInput($oid);
        $safeMdStatus = $this->sanitizeLogInput((string) $mdStatus);

        // ---- HASHPARAMS Doğrulaması ----
        // Nestpay/EST, callback'de hangi parametrelerin hash'e dahil olduğunu
        // HASHPARAMS alanında "param1:param2:param3:" formatında gönderir.
        // Bu parametrelerin değerlerini sırasıyla birleştirip storeKey ekleyerek
        // beklenen hash'i hesaplıyoruz.

        $storeKey = $this->gateway->data['store-key'];

        // HASHPARAMS'ı parçala ve sadece alfanümerik parametre isimlerine izin ver
        $paramNames = array_filter(
            explode(':', $hashParams),
            fn($p) => $p !== '' && preg_match('/^[a-zA-Z0-9_]+$/', $p)
        );

        // HASHPARAMS boş gelirse veya hiç geçerli parametre yoksa reddet
        if (empty($paramNames)) {
            logger()->warning('[Shop] '.$this->name.' - Empty or invalid HASHPARAMS for order: '.$safeOid);
            return to_route('shop.cart.index')->with('error', 'Banka imza doğrulaması başarısız.');
        }

        $paramsval = '';
        foreach ($paramNames as $paramName) {
            $paramsval .= $request->input($paramName, '');
        }

        $hashval = $paramsval . $storeKey;
        $expectedHash = base64_encode(pack('H*', hash('sha512', $hashval)));

        // Timing-safe hash karşılaştırma
        if (! hash_equals($expectedHash, $hash)) {
            logger()->warning('[Shop] '.$this->name.' - Hash mismatch for order: '.$safeOid);
            return to_route('shop.cart.index')->with('error', 'Banka imza doğrulaması başarısız.');
        }

        // 3D Secure doğrulama (mdStatus 1-4 arası kabul)
        if (! in_array((int) $mdStatus, [1, 2, 3, 4], true)) {
            logger()->warning('[Shop] '.$this->name.' - 3D Secure failed for order: '.$safeOid.' (mdStatus: '.$safeMdStatus.')');
            return to_route('shop.cart.index')->with('error', '3D Secure doğrulama başarısız.');
        }

        // Banka onay kontrolü
        if ($response !== 'Approved') {
            $errMsg = $this->sanitizeLogInput($request->input('ErrMsg', 'Bilinmeyen hata'));
            logger()->warning('[Shop] '.$this->name.' - Payment declined for order: '.$safeOid.' ('.$errMsg.')');
            // Kullanıcıya gösterilecek mesajdan potansiyel XSS temizlendi
            return to_route('shop.cart.index')->with('error', 'Ödeme onaylanmadı: '.e($errMsg));
        }

        return DB::transaction(function () use ($safeOid, $oid, $request) {
            $payment = Payment::lockForUpdate()->find($oid);

            if ($payment === null || $payment->gateway_type !== $this->id) {
                return to_route('shop.cart.index');
            }

            // Double-spend koruması
            if ($payment->isCompleted()) {
                return $this->success(request());
            }

            if (! $payment->isPending()) {
                return to_route('shop.cart.index');
            }

            // Tutar Manipülasyonu Koruması
            $callbackAmount = $request->input('amount');
            if ($callbackAmount !== null) {
                if (! $this->verifyAmount($payment, $safeOid, (float) $callbackAmount)) {
                    $this->invalidPayment($payment, $safeOid, 'Amount mismatch');
                    return to_route('shop.cart.index')->with('error', 'Tutar eşleşmiyor.');
                }
            }

            $this->processPayment($payment, $oid);

            return $this->success(request());
        });
    }

    public function rules(): array
    {
        return [
            'client-id' => ['required', 'string'],
            'store-key' => ['required', 'string'],
            'test-mode' => ['nullable', 'boolean'],
        ];
    }
}
