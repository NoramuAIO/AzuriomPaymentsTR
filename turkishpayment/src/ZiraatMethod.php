<?php

namespace Azuriom\Plugin\TurkishPayment;

class ZiraatMethod extends NestpayMethod
{
    protected $id = 'ziraat';
    protected $name = 'Ziraat Bankası';

    protected function getEndpointUrl(bool $testMode): string
    {
        // Ziraat uses the standard Nestpay/EST infrastructure
        return $testMode 
            ? 'https://sanalpos2.ziraatbank.com.tr/fim/est3Dgate'
            : 'https://sanalpos2.ziraatbank.com.tr/fim/est3Dgate';
    }

    public function view(): string
    {
        return 'turkishpayment::admin.ziraat';
    }

    public function image(): string
    {
        return asset('plugins/turkishpayment/img/ziraat.svg');
    }
}
