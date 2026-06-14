<?php

namespace Azuriom\Plugin\TurkishPayment\Concerns;

use Azuriom\Plugin\Shop\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

trait HasSecurityFeatures
{
    /**
     * Log enjeksiyonu koruması: Kullanıcı kontrollü string'lerdeki
     * tehlikeli karakterleri temizler.
     */
    protected function sanitizeLogInput(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Satır sonu ve kontrol karakterlerini kaldır
        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        return Str::limit($value, 200, '...');
    }

    /**
     * Sepet ürün ismi filtresi: XSS ve JSON bozulmasını engellemek için
     * ürün adındaki özel karakterleri siler ve sadece alfanümerik
     * değerler bırakır.
     */
    protected function sanitizeBasketItemName(?string $name): string
    {
        if (empty($name)) {
            return 'Urun';
        }

        // Yalnızca harf, rakam ve boşluklara izin ver, tırnakları ve özel karakterleri sil
        $name = preg_replace('/[^a-zA-Z0-9\sğüşıöçĞÜŞİÖÇ]/u', '', $name);
        $name = trim($name);

        return empty($name) ? 'Urun' : Str::limit($name, 50, '');
    }

    /**
     * Fraud (Sahtecilik) Filtrelerini Aşmak İçin Geçerli Formatlı Dummy Telefon:
     * Bankalar 0000000 veya 123456 gibi verileri reddeder.
     */
    protected function getValidPhone(): string
    {
        return '05390000000'; // Geçerli formatta ancak sahte bir numara
    }

    /**
     * Fraud Filtrelerini Aşmak İçin Geçerli Formatlı Dummy Adres:
     * Bankalar 'N/A' veya '-' adreslerini şüpheli işlem sayar.
     */
    protected function getValidAddress(): string
    {
        return 'Dijital Urun Teslimati, Istanbul, Turkiye';
    }

    /**
     * Webhook / Callback IP Doğrulaması:
     * İstek yapan IP adresinin güvenilir bloklardan gelip gelmediğini kontrol eder.
     * $allowedIps dizi halinde verilebilir veya subnet (CIDR) kabul edebilir.
     * Not: Eğer boş liste verilirse doğrulama başarılı sayılır (opsiyonel kullanım).
     */
    protected function isValidIp(string $requestIp, array $allowedIps): bool
    {
        if (empty($allowedIps)) {
            return true;
        }

        foreach ($allowedIps as $allowed) {
            if (str_contains($allowed, '/')) {
                // CIDR Subnet kontrolü (örneğin 213.238.8.0/24)
                if (\Symfony\Component\HttpFoundation\IpUtils::checkIp($requestIp, $allowed)) {
                    return true;
                }
            } else {
                // Tam eşleşme (örneğin 213.238.8.45)
                if ($requestIp === $allowed) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Sunucunun dış (public) IP adresini tespit eder.
     * Yalnızca sunucu IP'sinin kesinlikle gerektiği durumlarda kullanılmalıdır.
     * Güvenlik nedeniyle Fallback olarak X-Forwarded-For kullanılmamalıdır.
     */
    protected function resolveExternalIp(): ?string
    {
        return cache()->remember('turkishpayment_external_ip', 3600, function () {
            try {
                $response = Http::timeout(5)->get('https://api.ipify.org');

                if ($response->successful()) {
                    $ip = trim($response->body());

                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return $ip;
                    }
                }
            } catch (\Exception $e) {}

            return null;
        });
    }

    /**
     * Güvenli sunucu IP'si alma:
     * Eğer request()->ip() private bir ağdaysa (örneğin Cloudflare arkasında veya localhost)
     * gerçek public IP'yi tespit etmeye çalışır.
     */
    protected function getSafeServerIp(): string
    {
        $ip = request()->ip();
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $ip;
        }
        
        return $this->resolveExternalIp() ?? '85.110.124.50'; // En son çare varsayılan bir public IP
    }

    /**
     * Tutar doğrulaması: İşlem tutarı ile beklenen tutar eşleşmeli.
     */
    protected function verifyAmount(Payment $payment, string $transactionId, float $callbackAmount): bool
    {
        // Kuruş/cent bazlı kontroller veya format hatalarını engellemek için float toleransı
        if (abs($payment->price - $callbackAmount) > 0.01) {
            logger()->warning('[Shop] Turkish Payment - Amount mismatch for #'.$transactionId
                .': expected '.$payment->price.', received '.$callbackAmount);

            return false;
        }

        return true;
    }
}
