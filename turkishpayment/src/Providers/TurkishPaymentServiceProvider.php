<?php

namespace Azuriom\Plugin\TurkishPayment\Providers;

use Azuriom\Extensions\Plugin\BasePluginServiceProvider;
use Azuriom\Plugin\TurkishPayment\AkbankMethod;
use Azuriom\Plugin\TurkishPayment\GarantiMethod;
use Azuriom\Plugin\TurkishPayment\IsbankMethod;
use Azuriom\Plugin\TurkishPayment\IyzicoMethod;
use Azuriom\Plugin\TurkishPayment\KuveytTurkMethod;
use Azuriom\Plugin\TurkishPayment\PaparaMethod;
use Azuriom\Plugin\TurkishPayment\PaytrMethod;
use Azuriom\Plugin\TurkishPayment\PaywantMethod;
use Azuriom\Plugin\TurkishPayment\ShopierMethod;
use Azuriom\Plugin\TurkishPayment\ZiraatMethod;

class TurkishPaymentServiceProvider extends BasePluginServiceProvider
{
    /**
     * Register any plugin services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any plugin services.
     */
    public function boot(): void
    {
        $this->loadViews();

        $this->loadTranslations();

        payment_manager()->registerPaymentMethod('paytr', PaytrMethod::class);
        payment_manager()->registerPaymentMethod('shopier', ShopierMethod::class);
        payment_manager()->registerPaymentMethod('paywant', PaywantMethod::class);
        payment_manager()->registerPaymentMethod('iyzico', IyzicoMethod::class);
        payment_manager()->registerPaymentMethod('papara', PaparaMethod::class);
        payment_manager()->registerPaymentMethod('kuveytturk', KuveytTurkMethod::class);
        payment_manager()->registerPaymentMethod('garanti', GarantiMethod::class);
        payment_manager()->registerPaymentMethod('isbank', IsbankMethod::class);
        payment_manager()->registerPaymentMethod('akbank', AkbankMethod::class);
        payment_manager()->registerPaymentMethod('ziraat', ZiraatMethod::class);
    }
}
